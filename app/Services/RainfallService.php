<?php

namespace App\Services;

use App\Domain\Forecasting\Models\CurahHujan;
use App\Domain\Organisasi\Models\Kebun;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RainfallService
{
    /**
     * Fetch historical rainfall from Open-Meteo Archive API and cache it into database.
     *
     * @return int Number of records inserted/updated
     */
    public function fetchAndStore(Kebun $kebun, CarbonInterface|string $startDate, CarbonInterface|string $endDate): int
    {
        $startStr = Carbon::parse($startDate)->toDateString();
        $endStr = Carbon::parse($endDate)->toDateString();

        // 0. Cek apakah data untuk rentang tanggal ini sudah ter-cache penuh di database
        $expectedDays = (int) Carbon::parse($startStr)->diffInDays(Carbon::parse($endStr)) + 1;
        $cachedCount = CurahHujan::where('kebun_id', $kebun->id)
            ->whereBetween('tanggal', [$startStr, $endStr])
            ->count();

        if ($cachedCount >= $expectedDays) {
            Log::info("Curah hujan Kebun {$kebun->kode_kebun} untuk {$startStr} s/d {$endStr} sudah ter-cache ({$cachedCount} hari). Melewati panggilan HTTP live.");

            return $cachedCount;
        }

        // 1. Dapatkan koordinat lat/lng dari PostGIS Point
        $coord = DB::selectOne(
            'SELECT ST_X(koordinat_pusat) as lng, ST_Y(koordinat_pusat) as lat FROM kebun WHERE id = ?',
            [$kebun->id]
        );

        if (! $coord || $coord->lat === null || $coord->lng === null) {
            Log::warning("Kebun {$kebun->kode_kebun} tidak memiliki koordinat_pusat valid untuk fetch curah hujan.");

            return 0;
        }

        $lat = round((float) $coord->lat, 4);
        $lng = round((float) $coord->lng, 4);

        $apiUrl = sprintf(
            'https://archive-api.open-meteo.com/v1/archive?latitude=%.4f&longitude=%.4f&start_date=%s&end_date=%s&daily=precipitation_sum&timezone=auto',
            $lat,
            $lng,
            $startStr,
            $endStr
        );

        try {
            $response = Http::timeout(15)->get($apiUrl);

            if (! $response->successful()) {
                Log::error('Gagal mengambil data Open-Meteo: '.$response->body());

                return 0;
            }

            $data = $response->json();
            $dates = $data['daily']['time'] ?? [];
            $precipitations = $data['daily']['precipitation_sum'] ?? [];

            $savedCount = 0;
            DB::transaction(function () use ($kebun, $dates, $precipitations, &$savedCount) {
                foreach ($dates as $index => $date) {
                    $mm = isset($precipitations[$index])
                        ? (float) $precipitations[$index]
                        : 0.0;

                    CurahHujan::updateOrCreate(
                        [
                            'kebun_id' => $kebun->id,
                            'tanggal' => $date,
                        ],
                        [
                            'curah_hujan_mm' => $mm,
                            'sumber' => 'open-meteo',
                        ]
                    );

                    $savedCount++;
                }
            });

            return $savedCount;
        } catch (\Throwable $e) {
            Log::error("Exception saat fetch Open-Meteo untuk Kebun {$kebun->kode_kebun}: ".$e->getMessage());

            return 0;
        }
    }

    /**
     * Get monthly aggregated rainfall (mm) for a specific kebun.
     *
     * @return Collection<string, float> Keyed by YYYY-MM
     */
    public function getMonthlyRainfall(Kebun $kebun, ?CarbonInterface $start = null, ?CarbonInterface $end = null): Collection
    {
        $query = CurahHujan::where('kebun_id', $kebun->id);

        if ($start) {
            $query->where('tanggal', '>=', $start->toDateString());
        }
        if ($end) {
            $query->where('tanggal', '<=', $end->toDateString());
        }

        $results = $query
            ->selectRaw("to_char(tanggal, 'YYYY-MM') as periode, SUM(curah_hujan_mm) as total_mm")
            ->groupBy(DB::raw("to_char(tanggal, 'YYYY-MM')"))
            ->orderBy('periode')
            ->pluck('total_mm', 'periode');

        return $results->map(fn ($val) => (float) $val);
    }
}
