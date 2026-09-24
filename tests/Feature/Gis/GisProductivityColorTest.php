<?php

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Pemanen\Models\Pemanen;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Produksi\Models\ProduksiHarianDetail;
use App\Domain\Taksasi\Models\Taksasi;
use App\Models\User;
use App\Services\GisMapService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('US-05 AC1: productivity status color thresholds (hijau <= 5%, kuning 5-15%, merah > 15%, netral)', function () {
    $direksi = User::where('email', 'direksi@palmvision.test')->firstOrFail();
    $pemanen = Pemanen::firstOrFail();
    $currentMonth = now()->month;
    $currentYear = now()->year;

    // Ambil 4 blok terpisah untuk 4 skenario
    $bloks = Blok::limit(4)->get();
    $blokHijau = $bloks[0];
    $blokKuning = $bloks[1];
    $blokMerah = $bloks[2];
    $blokNetral = $bloks[3];

    // Skenario 1: Deviasi 3.0% (Hijau <= 5%)
    // Taksasi = 10,000 Kg, Realisasi = 10,300 Kg -> Deviasi = +3.0%
    Taksasi::create([
        'blok_id' => $blokHijau->id,
        'dicatat_oleh' => $direksi->id,
        'tanggal_taksasi' => now()->toDateString(),
        'pokok_disampel' => 50,
        'estimasi_janjang' => 100,
        'estimasi_bjr' => 20,
        'estimasi_total_kg' => 10000,
    ]);
    $prodHijau = ProduksiHarian::create([
        'blok_id' => $blokHijau->id,
        'dicatat_oleh' => $direksi->id,
        'tanggal' => now()->toDateString(),
        'status_validasi' => 'disetujui',
    ]);
    ProduksiHarianDetail::create([
        'produksi_harian_id' => $prodHijau->id,
        'pemanen_id' => $pemanen->id,
        'jumlah_janjang' => 500,
        'berat_kg' => 10300,
    ]);

    // Skenario 2: Deviasi 10.0% (Kuning 5-15%)
    // Taksasi = 10,000 Kg, Realisasi = 11,000 Kg -> Deviasi = +10.0%
    Taksasi::create([
        'blok_id' => $blokKuning->id,
        'dicatat_oleh' => $direksi->id,
        'tanggal_taksasi' => now()->toDateString(),
        'pokok_disampel' => 50,
        'estimasi_janjang' => 100,
        'estimasi_bjr' => 20,
        'estimasi_total_kg' => 10000,
    ]);
    $prodKuning = ProduksiHarian::create([
        'blok_id' => $blokKuning->id,
        'dicatat_oleh' => $direksi->id,
        'tanggal' => now()->toDateString(),
        'status_validasi' => 'disetujui',
    ]);
    ProduksiHarianDetail::create([
        'produksi_harian_id' => $prodKuning->id,
        'pemanen_id' => $pemanen->id,
        'jumlah_janjang' => 550,
        'berat_kg' => 11000,
    ]);

    // Skenario 3: Deviasi 20.0% (Merah > 15%)
    // Taksasi = 10,000 Kg, Realisasi = 12,000 Kg -> Deviasi = +20.0%
    Taksasi::create([
        'blok_id' => $blokMerah->id,
        'dicatat_oleh' => $direksi->id,
        'tanggal_taksasi' => now()->toDateString(),
        'pokok_disampel' => 50,
        'estimasi_janjang' => 100,
        'estimasi_bjr' => 20,
        'estimasi_total_kg' => 10000,
    ]);
    $prodMerah = ProduksiHarian::create([
        'blok_id' => $blokMerah->id,
        'dicatat_oleh' => $direksi->id,
        'tanggal' => now()->toDateString(),
        'status_validasi' => 'disetujui',
    ]);
    ProduksiHarianDetail::create([
        'produksi_harian_id' => $prodMerah->id,
        'pemanen_id' => $pemanen->id,
        'jumlah_janjang' => 600,
        'berat_kg' => 12000,
    ]);

    // Skenario 4: Blok Netral (tanpa taksasi/realisasi) -> blokNetral

    /** @var GisMapService $gisMapService */
    $gisMapService = app(GisMapService::class);
    $features = $gisMapService->getGeoJsonFeatures();

    $featureMap = [];
    foreach ($features['features'] as $f) {
        $featureMap[$f['properties']['blok_id']] = $f['properties'];
    }

    // Assert Skenario 1 (Hijau)
    expect($featureMap[$blokHijau->id]['status_warna'])->toBe('hijau')
        ->and($featureMap[$blokHijau->id]['persentase_deviasi'])->toBe(3.0);

    // Assert Skenario 2 (Kuning)
    expect($featureMap[$blokKuning->id]['status_warna'])->toBe('kuning')
        ->and($featureMap[$blokKuning->id]['persentase_deviasi'])->toBe(10.0);

    // Assert Skenario 3 (Merah)
    expect($featureMap[$blokMerah->id]['status_warna'])->toBe('merah')
        ->and($featureMap[$blokMerah->id]['persentase_deviasi'])->toBe(20.0);

    // Assert Skenario 4 (Netral / Abu-abu)
    expect($featureMap[$blokNetral->id]['status_warna'])->toBe('netral')
        ->and($featureMap[$blokNetral->id]['persentase_deviasi'])->toBeNull();
});

test('US-05 AC2: block detail returns production history, latest taksasi, crop age, and next rotation date', function () {
    $direksi = User::where('email', 'direksi@palmvision.test')->firstOrFail();
    $blok = Blok::firstOrFail();
    $pemanen = Pemanen::firstOrFail();

    // Set tanggal tanam 5 tahun lalu
    $blok->update(['tanggal_tanam' => Carbon::now()->subYears(5)->subMonths(3)->toDateString()]);

    // Buat input produksi terakhir pada tanggal 2026-09-10
    $prod = ProduksiHarian::create([
        'blok_id' => $blok->id,
        'dicatat_oleh' => $direksi->id,
        'tanggal' => '2026-09-10',
        'status_validasi' => 'disetujui',
    ]);
    ProduksiHarianDetail::create([
        'produksi_harian_id' => $prod->id,
        'pemanen_id' => $pemanen->id,
        'jumlah_janjang' => 150,
        'berat_kg' => 3000,
    ]);

    // Buat taksasi
    Taksasi::create([
        'blok_id' => $blok->id,
        'dicatat_oleh' => $direksi->id,
        'tanggal_taksasi' => '2026-09-09',
        'pokok_disampel' => 30,
        'estimasi_janjang' => 90,
        'estimasi_bjr' => 20,
        'estimasi_total_kg' => 2800,
    ]);

    $response = $this->actingAs($direksi)
        ->getJson(route('gis.blok.detail', $blok));

    $response->assertOk()
        ->assertJsonStructure([
            'blok' => [
                'id',
                'kode_blok',
                'luas_ha',
                'jumlah_pokok',
                'usia_tanaman',
                'tanggal_rotasi_berikutnya',
                'status_warna',
            ],
            'taksasi_terakhir' => [
                'id',
                'tanggal_taksasi',
                'estimasi_total_kg',
            ],
            'riwayat_produksi',
            'riwayat_versi',
        ]);

    $data = $response->json();

    // 1. Usia tanaman format tahun + bulan
    expect($data['blok']['usia_tanaman'])->toContain('5 Tahun 3 Bulan');

    // 2. Tanggal rotasi berikutnya = 2026-09-10 + 10 hari = 2026-09-20
    expect($data['blok']['tanggal_rotasi_berikutnya'])->toBe('2026-09-20');

    // 3. Riwayat produksi memuat entri terakhir
    expect($data['riwayat_produksi'])->not->toBeEmpty()
        ->and((float) $data['riwayat_produksi'][0]['total_berat_kg'])->toEqual(3000.0);

    // 4. Taksasi terakhir
    expect((float) $data['taksasi_terakhir']['estimasi_total_kg'])->toEqual(2800.0);
});
