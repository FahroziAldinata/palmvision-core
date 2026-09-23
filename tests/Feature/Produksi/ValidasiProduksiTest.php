<?php

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Pemanen\Models\Pemanen;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Produksi\Models\ProduksiHarianDetail;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('asisten afdeling can approve harvest record directly', function () {
    $asisten = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blokA1 = Blok::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();
    $pemanen = Pemanen::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();

    $produksi = ProduksiHarian::create([
        'blok_id' => $blokA1->id,
        'dicatat_oleh' => $mandor->id,
        'tanggal' => now()->toDateString(),
        'status_validasi' => 'menunggu',
    ]);

    ProduksiHarianDetail::create([
        'produksi_harian_id' => $produksi->id,
        'pemanen_id' => $pemanen->id,
        'jumlah_janjang' => 40,
        'berat_kg' => 640.0,
    ]);

    $this->actingAs($asisten)
        ->post(route('produksi.validasi.approve', $produksi->id))
        ->assertRedirect();

    $produksi->refresh();
    expect($produksi->status_validasi)->toBe('disetujui');

    // Verify activity log audit trail
    $activity = Activity::where('subject_id', $produksi->id)
        ->where('log_name', 'persetujuan_produksi')
        ->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($asisten->id);
});

test('asisten afdeling can correct harvester numbers with reason and audit trail records old vs new values', function () {
    $asisten = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blokA1 = Blok::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();
    $pemanen = Pemanen::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();

    $produksi = ProduksiHarian::create([
        'blok_id' => $blokA1->id,
        'dicatat_oleh' => $mandor->id,
        'tanggal' => now()->toDateString(),
        'status_validasi' => 'menunggu',
    ]);

    $detail = ProduksiHarianDetail::create([
        'produksi_harian_id' => $produksi->id,
        'pemanen_id' => $pemanen->id,
        'jumlah_janjang' => 40,
        'berat_kg' => 640.0,
    ]);

    $alasan = 'Koreksi berat timbangan TPH sesuai slip timbang pabrik';

    $response = $this->actingAs($asisten)
        ->post(route('produksi.validasi.koreksi', $produksi->id), [
            'alasan_koreksi' => $alasan,
            'items' => [
                [
                    'id' => $detail->id,
                    'jumlah_janjang' => 42,
                    'berat_kg' => 680.0,
                ],
            ],
        ]);

    $response->assertRedirect();

    $produksi->refresh();
    $detail->refresh();

    expect($produksi->status_validasi)->toBe('disetujui')
        ->and($detail->jumlah_janjang)->toBe(42)
        ->and((float) $detail->berat_kg)->toBe(680.0);

    // Verify audit trail captured old vs new values
    $activity = Activity::where('subject_id', $produksi->id)
        ->where('log_name', 'koreksi_produksi')
        ->firstOrFail();

    expect($activity->causer_id)->toBe($asisten->id)
        ->and($activity->properties['alasan'])->toBe($alasan)
        ->and($activity->properties['old']['total_janjang'])->toBe(40)
        ->and((float) $activity->properties['old']['total_berat_kg'])->toBe(640.0)
        ->and($activity->properties['new']['total_janjang'])->toBe(42)
        ->and((float) $activity->properties['new']['total_berat_kg'])->toBe(680.0);
});

test('US-02 AC3: mandor cannot modify or delete harvest record once validated and approved', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blokA1 = Blok::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();
    $pemanen = Pemanen::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();

    $tanggal = now()->toDateString();

    $produksi = ProduksiHarian::create([
        'blok_id' => $blokA1->id,
        'dicatat_oleh' => $mandor->id,
        'tanggal' => $tanggal,
        'status_validasi' => 'disetujui', // Already approved!
    ]);

    ProduksiHarianDetail::create([
        'produksi_harian_id' => $produksi->id,
        'pemanen_id' => $pemanen->id,
        'jumlah_janjang' => 50,
        'berat_kg' => 800.0,
    ]);

    // Attempt to modify via store endpoint
    $response = $this->actingAs($mandor)
        ->post(route('produksi.store'), [
            'blok_id' => $blokA1->id,
            'tanggal' => $tanggal,
            'items' => [
                [
                    'pemanen_id' => $pemanen->id,
                    'jumlah_janjang' => 60,
                    'berat_kg' => 950.0,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('blok_id');

    // Values remain untouched
    $produksi->refresh();
    expect($produksi->total_janjang)->toBe(50);
});

test('asisten afdeling cannot validate or correct records belonging to another afdeling', function () {
    $asistenAlpha = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdelingBeta = Afdeling::where('kode', 'AFD-B')->firstOrFail();
    $blokBeta = Blok::where('afdeling_id', $afdelingBeta->id)->firstOrFail();
    $pemanenBeta = Pemanen::where('afdeling_id', $afdelingBeta->id)->firstOrFail();

    $produksi = ProduksiHarian::create([
        'blok_id' => $blokBeta->id,
        'dicatat_oleh' => $mandor->id,
        'tanggal' => now()->toDateString(),
        'status_validasi' => 'menunggu',
    ]);

    $detail = ProduksiHarianDetail::create([
        'produksi_harian_id' => $produksi->id,
        'pemanen_id' => $pemanenBeta->id,
        'jumlah_janjang' => 30,
        'berat_kg' => 450.0,
    ]);

    // Asisten Alpha attempts to approve record in Afdeling Beta -> Forbidden
    $this->actingAs($asistenAlpha)
        ->post(route('produksi.validasi.approve', $produksi->id))
        ->assertForbidden();

    // Asisten Alpha attempts to correct record in Afdeling Beta -> Forbidden
    $this->actingAs($asistenAlpha)
        ->post(route('produksi.validasi.koreksi', $produksi->id), [
            'alasan_koreksi' => 'Percobaan koreksi lintas afdeling',
            'items' => [
                [
                    'id' => $detail->id,
                    'jumlah_janjang' => 35,
                    'berat_kg' => 500.0,
                ],
            ],
        ])
        ->assertForbidden();
});
