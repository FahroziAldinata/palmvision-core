<?php

namespace Database\Seeders;

use App\Domain\Forecasting\Models\CurahHujan;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\Kebun;
use App\Domain\Pemanen\Models\Pemanen;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Produksi\Models\ProduksiHarianDetail;
use App\Domain\Taksasi\Models\Taksasi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ForecastingHistoricalSeeder extends Seeder
{
    /**
     * Seed 18 months of historical production, harvest estimation (taksasi),
     * and synchronized rainfall data for time-series forecasting training.
     */
    public function run(): void
    {
        $kebun = Kebun::where('kode_kebun', 'KBS')->first();
        if (! $kebun) {
            $this->call(DatabaseSeeder::class);
            $kebun = Kebun::where('kode_kebun', 'KBS')->firstOrFail();
        }

        $direksi = User::where('email', 'direksi@palmvision.test')->first() ?? User::first();
        $pemanens = Pemanen::whereHas('afdeling', fn ($q) => $q->where('kebun_id', $kebun->id))->get();
        if ($pemanens->isEmpty()) {
            $pemanens = Pemanen::all();
        }

        // Target sample blocks for forecasting demo (Blok A01 & A02)
        $targetBloks = Blok::whereHas('afdeling', fn ($q) => $q->where('kebun_id', $kebun->id))
            ->whereIn('kode_blok', ['A01', 'A02'])
            ->get();

        if ($targetBloks->isEmpty()) {
            $targetBloks = Blok::take(2)->get();
        }

        // Siklus musiman bulanan kelapa sawit di Sumatera/Riau (Indeks 1..12)
        $seasonalFactors = [
            1 => 0.95,  // Jan: Moderat
            2 => 0.78,  // Feb: Mulai musim trek (rendah)
            3 => 0.72,  // Mar: Puncak musim trek
            4 => 0.76,  // Apr: Musim trek akhir
            5 => 0.85,  // Mei: Pemulihan
            6 => 0.92,  // Jun: Normal
            7 => 1.05,  // Jul: Peningkatan
            8 => 1.12,  // Agu: Menjelang panen raya
            9 => 1.22,  // Sep: Panen raya
            10 => 1.36, // Okt: Puncak panen raya (peak crop)
            11 => 1.40, // Nov: Puncak panen raya
            12 => 1.24, // Des: Akhir panen raya
        ];

        // Rata-rata curah hujan bulanan (mm/bulan)
        $monthlyRainfallBase = [
            1 => 160.0,
            2 => 110.0,
            3 => 140.0,
            4 => 180.0,
            5 => 150.0,
            6 => 90.0,
            7 => 85.0,
            8 => 120.0,
            9 => 210.0,
            10 => 290.0,
            11 => 330.0,
            12 => 260.0,
        ];

        // 18 Bulan data: Jan 2025 s/d Jun 2026
        $startDate = Carbon::create(2025, 1, 1);
        $endDate = Carbon::create(2026, 6, 30);

        // 1. Generate Curah Hujan Harian Sinkron (18 Bulan)
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $m = $currentDate->month;
            $daysInMonth = $currentDate->daysInMonth;
            $avgDailyRain = $monthlyRainfallBase[$m] / $daysInMonth;

            // Variasi curah hujan: ada hari kering (0 mm) dan hari hujan lebat
            $rainChance = ($m >= 9 || $m <= 1) ? 0.65 : 0.40;
            $hasRain = (mt_rand(1, 100) / 100) < $rainChance;
            $dailyMm = 0.0;
            if ($hasRain) {
                $dailyMm = round($avgDailyRain * (0.5 + (mt_rand(1, 150) / 100)), 2);
            }

            CurahHujan::updateOrCreate(
                [
                    'kebun_id' => $kebun->id,
                    'tanggal' => $currentDate->toDateString(),
                ],
                [
                    'curah_hujan_mm' => $dailyMm,
                    'sumber' => 'sintetis-historis',
                ]
            );

            $currentDate->addDay();
        }

        // 2. Generate Produksi Harian & Taksasi per Blok (Pola Rotasi 10 Hari)
        foreach ($targetBloks as $blok) {
            $baseKgPerPanen = ($blok->luas_ha * 136) * 1.8; // Standar produksi per rotasi panen ~6.000 - 8.000 kg

            $harvestDate = $startDate->copy()->addDays(mt_rand(2, 5));
            while ($harvestDate->lte($endDate)) {
                $m = $harvestDate->month;
                $factor = $seasonalFactors[$m];

                // Random walk noise (+- 5%)
                $noise = 0.95 + (mt_rand(0, 100) / 1000);
                $panenKg = round($baseKgPerPanen * $factor * $noise, 2);
                $janjang = (int) round($panenKg / 22.5); // BJR ~22.5 kg

                // Taksasi dicatat 1 hari sebelum panen
                $taksasiDate = $harvestDate->copy()->subDay();
                $taksasiDeviasi = 0.96 + (mt_rand(0, 80) / 1000); // Taksasi mendekati realisasi (+- 4%)
                $taksasiKg = round($panenKg * $taksasiDeviasi, 2);
                $taksasiJanjang = (int) round($taksasiKg / 22.0);

                Taksasi::firstOrCreate(
                    [
                        'blok_id' => $blok->id,
                        'tanggal_taksasi' => $taksasiDate->toDateString(),
                    ],
                    [
                        'dicatat_oleh' => $direksi->id,
                        'pokok_disampel' => 50,
                        'estimasi_janjang' => $taksasiJanjang,
                        'estimasi_bjr' => 22.0,
                        'estimasi_total_kg' => $taksasiKg,
                        'catatan' => 'Taksasi historis sintetis untuk training forecasting',
                    ]
                );

                // Produksi Harian
                $prod = ProduksiHarian::firstOrCreate(
                    [
                        'blok_id' => $blok->id,
                        'tanggal' => $harvestDate->toDateString(),
                        'dicatat_oleh' => $direksi->id,
                    ],
                    [
                        'status_validasi' => 'disetujui',
                        'catatan' => 'Realisasi produksi historis sintetis untuk training forecasting',
                    ]
                );

                // Distribusikan ke pemanen
                $activePemanens = $pemanens->take(3);
                $pemanenCount = max(1, $activePemanens->count());
                $kgPerPemanen = round($panenKg / $pemanenCount, 2);
                $janjangPerPemanen = (int) round($janjang / $pemanenCount);

                foreach ($activePemanens as $p) {
                    ProduksiHarianDetail::firstOrCreate(
                        [
                            'produksi_harian_id' => $prod->id,
                            'pemanen_id' => $p->id,
                        ],
                        [
                            'jumlah_janjang' => $janjangPerPemanen,
                            'berat_kg' => $kgPerPemanen,
                        ]
                    );
                }

                // Rotasi berikutnya ~10 hari
                $harvestDate->addDays(10);
            }
        }
    }
}
