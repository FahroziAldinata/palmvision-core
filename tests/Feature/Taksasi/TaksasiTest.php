<?php

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Pemanen\Models\Pemanen;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Produksi\Models\ProduksiHarianDetail;
use App\Domain\Taksasi\Models\Taksasi;
use App\Http\Controllers\Web\TaksasiController;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('US-03 AC2: formula taksasi calculates estimasi_total_kg accurately using locked agronomic formula', function () {
    $kerani = User::where('email', 'kerani@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blokA1 = Blok::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();

    // Set known fixed jumlah_pokok for deterministic formula verification
    $blokA1->update(['jumlah_pokok' => 1300]);

    // Example calculation:
    // pokok_disampel = 20
    // estimasi_janjang = 100
    // janjang_per_pokok = 100 / 20 = 5.0
    // estimasi_bjr = 18.0
    // estimasi_total_kg = 5.0 * 1300 * 18.0 = 117,000.00
    $payload = [
        'blok_id' => $blokA1->id,
        'tanggal_taksasi' => now()->toDateString(),
        'pokok_disampel' => 20,
        'estimasi_janjang' => 100,
        'estimasi_bjr' => 18.0,
        'catatan' => 'Uji formula agronomi taksasi.',
    ];

    $response = $this->actingAs($kerani)
        ->post(route('taksasi.store'), $payload);

    $response->assertRedirect(route('taksasi.index'));

    $taksasi = Taksasi::where('blok_id', $blokA1->id)->latest()->firstOrFail();

    expect((float) $taksasi->estimasi_total_kg)->toBe(117000.0)
        ->and($taksasi->pokok_disampel)->toBe(20)
        ->and($taksasi->estimasi_janjang)->toBe(100)
        ->and((float) $taksasi->estimasi_bjr)->toBe(18.0);
});

test('US-03 AC3: new taksasi entry does not overwrite existing records (historical non-overwriting)', function () {
    $kerani = User::where('email', 'kerani@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blokA1 = Blok::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();

    $blokA1->update(['jumlah_pokok' => 1000]);

    // First taksasi
    $this->actingAs($kerani)
        ->post(route('taksasi.store'), [
            'blok_id' => $blokA1->id,
            'tanggal_taksasi' => now()->subDay()->toDateString(),
            'pokok_disampel' => 10,
            'estimasi_janjang' => 30,
            'estimasi_bjr' => 15.0,
        ])
        ->assertRedirect(route('taksasi.index'));

    expect(Taksasi::where('blok_id', $blokA1->id)->count())->toBe(1);

    // Second taksasi for same blok
    $this->actingAs($kerani)
        ->post(route('taksasi.store'), [
            'blok_id' => $blokA1->id,
            'tanggal_taksasi' => now()->toDateString(),
            'pokok_disampel' => 10,
            'estimasi_janjang' => 40,
            'estimasi_bjr' => 16.0,
        ])
        ->assertRedirect(route('taksasi.index'));

    // Both records exist (history preserved)
    expect(Taksasi::where('blok_id', $blokA1->id)->count())->toBe(2);
});

test('kerani taksasi is forbidden from entering taksasi for a blok outside their afdeling', function () {
    $kerani = User::where('email', 'kerani@palmvision.test')->firstOrFail();
    $afdelingBeta = Afdeling::where('kode', 'AFD-B')->firstOrFail();
    $blokBeta = Blok::where('afdeling_id', $afdelingBeta->id)->firstOrFail();

    $this->actingAs($kerani)
        ->post(route('taksasi.store'), [
            'blok_id' => $blokBeta->id,
            'tanggal_taksasi' => now()->toDateString(),
            'pokok_disampel' => 20,
            'estimasi_janjang' => 60,
            'estimasi_bjr' => 16.0,
        ])
        ->assertSessionHasErrors('blok_id');

    expect(Taksasi::where('blok_id', $blokBeta->id)->count())->toBe(0);
});

test('US-04: akurasi page calculates deviation percentage and identifies deviation > 15%', function () {
    $kerani = User::where('email', 'kerani@palmvision.test')->firstOrFail();
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blokA1 = Blok::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();
    $pemanen = Pemanen::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();

    $today = now();

    // 1. Create Taksasi: 10,000 Kg
    Taksasi::create([
        'blok_id' => $blokA1->id,
        'dicatat_oleh' => $kerani->id,
        'tanggal_taksasi' => $today->toDateString(),
        'pokok_disampel' => 10,
        'estimasi_janjang' => 50,
        'estimasi_bjr' => 20.0,
        'estimasi_total_kg' => 10000.0,
    ]);

    // 2. Create Approved Realisasi: 12,500 Kg (Deviasi: +25% > 15%)
    $prod = ProduksiHarian::create([
        'blok_id' => $blokA1->id,
        'dicatat_oleh' => $mandor->id,
        'tanggal' => $today->toDateString(),
        'status_validasi' => 'disetujui',
    ]);

    ProduksiHarianDetail::create([
        'produksi_harian_id' => $prod->id,
        'pemanen_id' => $pemanen->id,
        'jumlah_janjang' => 625,
        'berat_kg' => 12500.0,
    ]);

    $response = $this->actingAs($kerani)
        ->get(route('taksasi.akurasi', [
            'bulan' => $today->month,
            'tahun' => $today->year,
        ]));

    $response->assertOk();

    // Verify through controller logic directly
    $rows = app(TaksasiController::class)
        ->akurasi(request()->merge(['bulan' => $today->month, 'tahun' => $today->year]))
        ->toResponse(request())
        ->original['page']['props']['rows'];

    $blokRow = collect($rows)->firstWhere('blok_id', $blokA1->id);
    expect($blokRow)->not->toBeNull()
        ->and((float) $blokRow['taksasi_kg'])->toBe(10000.0)
        ->and((float) $blokRow['realisasi_kg'])->toBe(12500.0)
        ->and((float) $blokRow['selisih_kg'])->toBe(2500.0)
        ->and((float) $blokRow['persentase_deviasi'])->toBe(25.0)
        ->and($blokRow['is_high_deviation'])->toBeTrue();
});
