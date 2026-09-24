<?php

namespace Database\Seeders;

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\GrupPerusahaan;
use App\Domain\Organisasi\Models\Kebun;
use App\Domain\Pemanen\Models\Pemanen;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Produksi\Models\ProduksiHarianDetail;
use App\Domain\Taksasi\Models\Taksasi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerformanceBenchmarkSeeder extends Seeder
{
    /**
     * Seed 250 blocks within a single estate for stress-testing and benchmarking GIS rendering.
     */
    public function run(): void
    {
        $grup = GrupPerusahaan::first() ?? GrupPerusahaan::create([
            'nama' => 'PT Palma Nusantara Jaya',
            'npwp' => '01.234.567.8-901.000',
        ]);

        $direksi = User::first() ?? User::factory()->create();
        $pemanen = Pemanen::first() ?? Pemanen::factory()->create();

        // 1. Buat Kebun Benchmark Khusus Skala Luas (250 Blok)
        $kebun = Kebun::create([
            'grup_id' => $grup->id,
            'kode_kebun' => 'KB-BENCH',
            'nama' => 'Kebun Skala Luas Benchmark (250 Blok)',
            'koordinat_pusat' => DB::raw('ST_SetSRID(ST_MakePoint(102.0000, 1.0000), 4326)'),
            'siklus_rotasi_hari' => 10,
        ]);

        $baseLng = 102.000;
        $baseLat = 1.000;

        // 2. 10 Afdeling x 25 Blok = 250 Blok
        for ($a = 1; $a <= 10; $a++) {
            $afdeling = Afdeling::create([
                'kebun_id' => $kebun->id,
                'kode' => sprintf('AFD-B%02d', $a),
                'nama' => sprintf('Afdeling Benchmark %02d', $a),
            ]);

            $afdLng = $baseLng + (($a - 1) * 0.040);

            for ($b = 1; $b <= 25; $b++) {
                $kodeBlok = sprintf('B%02d-%02d', $a, $b);
                $luasHa = 25.00 + (($b % 5) * 1.25);
                $jumlahPokok = (int) round($luasHa * 136);
                $tanggalTanam = Carbon::create(2018, 1, 1)->addMonths(($a * 2) + $b)->toDateString();
                $kategoriTanah = ($b % 3 === 0) ? 'Gambut' : 'Mineral';

                $blok = Blok::create([
                    'afdeling_id' => $afdeling->id,
                    'kode_blok' => $kodeBlok,
                    'luas_ha' => $luasHa,
                    'tanggal_tanam' => $tanggalTanam,
                    'jumlah_pokok' => $jumlahPokok,
                    'kategori_tanah' => $kategoriTanah,
                ]);

                // Koordinat polygon non-overlapping grid yang valid menurut PostGIS
                $x0 = $afdLng + (($b % 5) * 0.006);
                $x1 = $x0 + 0.005;
                $y0 = $baseLat + (floor(($b - 1) / 5) * 0.006);
                $y1 = $y0 + 0.005;

                DB::statement(
                    'INSERT INTO poligon_blok (id, blok_id, versi, diperbarui_pada, poligon, created_at, updated_at)
                     VALUES (?, ?, 1, ?, ST_SetSRID(ST_MakeEnvelope(?, ?, ?, ?, 4326), 4326), NOW(), NOW())',
                    [(string) Str::uuid(), $blok->id, '2026-09-01', $x0, $y0, $x1, $y1]
                );

                // Buat variasi data operasional pada 50% blok
                if ($b % 2 === 0) {
                    $taksasiKg = 10000.0 + ($b * 100);
                    Taksasi::create([
                        'blok_id' => $blok->id,
                        'dicatat_oleh' => $direksi->id,
                        'tanggal_taksasi' => now()->toDateString(),
                        'pokok_disampel' => 50,
                        'estimasi_janjang' => 100,
                        'estimasi_bjr' => 20,
                        'estimasi_total_kg' => $taksasiKg,
                    ]);

                    // Variasikan deviasi: sebagian hijau, kuning, merah
                    $deviasiFaktor = ($b % 3 === 0) ? 1.03 : (($b % 3 === 1) ? 1.10 : 1.25);
                    $prod = ProduksiHarian::create([
                        'blok_id' => $blok->id,
                        'dicatat_oleh' => $direksi->id,
                        'tanggal' => now()->subDays($b % 8)->toDateString(),
                        'status_validasi' => 'disetujui',
                    ]);

                    ProduksiHarianDetail::create([
                        'produksi_harian_id' => $prod->id,
                        'pemanen_id' => $pemanen->id,
                        'jumlah_janjang' => 500,
                        'berat_kg' => round($taksasiKg * $deviasiFaktor, 2),
                    ]);
                }
            }
        }
    }
}
