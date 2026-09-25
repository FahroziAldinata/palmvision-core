<?php

namespace App\Services;

use App\Domain\Forecasting\Models\ForecastResult;
use App\Domain\Organisasi\Models\Blok;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForecastingService
{
    /**
     * URL layanan eksternal FastAPI time-series forecasting.
     */
    protected string $baseUrl;

    /**
     * API Key untuk otentikasi internal inter-service.
     */
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.forecasting.url', 'http://forecasting:8000'), '/');
        $this->apiKey = (string) config('services.forecasting.api_key', 'palmvision-dev-key');
    }

    /**
     * Mengumpulkan data historis bulanan per blok (produksi aktual, curah hujan kebun, usia tanaman).
     *
     * @return array<int, array<string, mixed>>
     */
    public function prepareHistoricalDataForBlock(Blok $blok): array
    {
        $blok->loadMissing('afdeling.kebun');
        $kebunId = $blok->afdeling?->kebun_id;

        // 1. Agregasi produksi bulanan (kg) dari tabel produksi_harian_detail
        $produksiBulanan = DB::table('produksi_harian_detail')
            ->join('produksi_harian', 'produksi_harian.id', '=', 'produksi_harian_detail.produksi_harian_id')
            ->where('produksi_harian.blok_id', $blok->id)
            ->where('produksi_harian.status_validasi', 'disetujui')
            ->selectRaw("to_char(produksi_harian.tanggal, 'YYYY-MM') as periode, SUM(produksi_harian_detail.berat_kg) as total_kg")
            ->groupBy(DB::raw("to_char(produksi_harian.tanggal, 'YYYY-MM')"))
            ->orderBy('periode')
            ->pluck('total_kg', 'periode');

        // 2. Agregasi curah hujan bulanan (mm) kebun
        $curahHujanBulanan = collect();
        if ($kebunId) {
            $curahHujanBulanan = DB::table('curah_hujan')
                ->where('kebun_id', $kebunId)
                ->selectRaw("to_char(tanggal, 'YYYY-MM') as periode, SUM(curah_hujan_mm) as total_mm")
                ->groupBy(DB::raw("to_char(tanggal, 'YYYY-MM')"))
                ->orderBy('periode')
                ->pluck('total_mm', 'periode');
        }

        // 3. Gabungkan ke dalam format HistoricalRecord Prophet
        $records = [];
        $tglTanam = $blok->tanggal_tanam ? Carbon::parse($blok->tanggal_tanam) : Carbon::create(2018, 1, 1);

        foreach ($produksiBulanan as $periode => $kg) {
            $periodeCarbon = Carbon::parse($periode.'-01');
            $usiaBulan = (int) round(max(0, (float) $tglTanam->diffInMonths($periodeCarbon)));
            $rainMm = (float) ($curahHujanBulanan->get($periode) ?? 150.0);

            $records[] = [
                'ds' => $periode.'-01',
                'y' => (float) $kg,
                'curah_hujan' => $rainMm,
                'usia_tanaman_bulan' => $usiaBulan,
            ];
        }

        return $records;
    }

    /**
     * Melatih model Prophet di layanan Python untuk blok ini.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    public function trainBlockModel(Blok $blok, array $records, ?string $customVersion = null): array
    {
        $response = Http::timeout(60)
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->post("{$this->baseUrl}/api/v1/train", [
                'blok_id' => $blok->id,
                'records' => $records,
                'versi_model' => $customVersion,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Gagal melatih model Prophet di layanan Python: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Meminta proyeksi peramalan ke layanan Python dan menyimpannya ke tabel forecast_result.
     * Aturan D5 PRD 7.2: Riwayat lama TIDAK dihapus (non-overwriting).
     *
     * @return Collection<int, ForecastResult>
     */
    public function generateAndStoreForecast(Blok $blok, int $horizonMonths = 3): Collection
    {
        $records = $this->prepareHistoricalDataForBlock($blok);

        // Jika data historis memadai (minimal 6 bulan), jalankan training model terbaru
        if (count($records) >= 6) {
            try {
                $this->trainBlockModel($blok, $records);
            } catch (\Throwable $e) {
                Log::warning("Gagal auto-train model untuk blok {$blok->kode_blok}: ".$e->getMessage());
            }
        }

        // Siapkan fitur tambahan
        $blok->loadMissing('afdeling.kebun');
        $tglTanam = $blok->tanggal_tanam ? Carbon::parse($blok->tanggal_tanam) : Carbon::create(2018, 1, 1);
        $usiaSekarang = (int) round(max(0, (float) $tglTanam->diffInMonths(now())));

        // Curah hujan rata-rata historis
        $rainHistory = collect($records)->pluck('curah_hujan')->take(-3)->values()->all();

        $payload = [
            'blok_id' => $blok->id,
            'horizon_bulan' => $horizonMonths,
            'fitur_tambahan' => [
                'curah_hujan_historis' => ! empty($rainHistory) ? $rainHistory : [150.0, 160.0, 140.0],
                'usia_tanaman_bulan' => $usiaSekarang,
            ],
        ];

        $response = Http::timeout(30)
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->post("{$this->baseUrl}/api/v1/forecast", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Gagal memanggil endpoint /api/v1/forecast: '.$response->body());
        }

        $data = $response->json();
        $proyeksiList = $data['proyeksi'] ?? [];
        $mape = (float) ($data['mape_model'] ?? 0.087);
        $versi = (string) ($data['versi_model'] ?? 'prophet-v1');

        $createdResults = collect();

        DB::transaction(function () use ($blok, $proyeksiList, $mape, $versi, &$createdResults) {
            foreach ($proyeksiList as $item) {
                $periodeDate = Carbon::parse($item['periode'].'-01')->toDateString();

                $forecast = ForecastResult::create([
                    'blok_id' => $blok->id,
                    'periode' => $periodeDate,
                    'nilai_kg' => (float) $item['nilai_kg'],
                    'interval_bawah' => (float) $item['interval_bawah'],
                    'interval_atas' => (float) $item['interval_atas'],
                    'mape_model' => $mape,
                    'versi_model' => $versi,
                ]);

                $createdResults->push($forecast);
            }
        });

        return $createdResults;
    }

    /**
     * Mengambil hasil forecast tersimpan terbaru per blok dari database.
     *
     * @return Collection<int, ForecastResult>
     */
    public function getLatestForecast(Blok $blok, int $limit = 3): Collection
    {
        // Ambil versi_model terbaru dari baris terakhir
        $latestRecord = ForecastResult::where('blok_id', $blok->id)
            ->latest('created_at')
            ->first();

        if (! $latestRecord) {
            return collect();
        }

        return ForecastResult::where('blok_id', $blok->id)
            ->where('versi_model', $latestRecord->versi_model)
            ->orderBy('periode')
            ->take($limit)
            ->get();
    }
}
