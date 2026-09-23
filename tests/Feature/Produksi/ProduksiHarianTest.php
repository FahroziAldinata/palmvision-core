<?php

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Pemanen\Models\Pemanen;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('mandor can record daily harvest with harvester breakdown and aggregate total equals sum of details', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blokA1 = Blok::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();

    $pemanens = Pemanen::where('afdeling_id', $afdelingAlpha->id)->take(2)->get();
    expect($pemanens)->toHaveCount(2);

    $tanggal = now()->toDateString();

    $payload = [
        'blok_id' => $blokA1->id,
        'tanggal' => $tanggal,
        'catatan' => 'Panen rotasi 1 berjalan lancar.',
        'items' => [
            [
                'pemanen_id' => $pemanens[0]->id,
                'jumlah_janjang' => 45,
                'berat_kg' => 720.5,
            ],
            [
                'pemanen_id' => $pemanens[1]->id,
                'jumlah_janjang' => 55,
                'berat_kg' => 880.0,
            ],
        ],
    ];

    $response = $this->actingAs($mandor)
        ->post(route('produksi.store'), $payload);

    $response->assertRedirect(route('produksi.index'));

    // Check header record
    $produksi = ProduksiHarian::with('details')->where('blok_id', $blokA1->id)
        ->where('tanggal', $tanggal)
        ->where('dicatat_oleh', $mandor->id)
        ->firstOrFail();

    expect($produksi->status_validasi)->toBe('menunggu')
        ->and($produksi->catatan)->toBe('Panen rotasi 1 berjalan lancar.')
        ->and($produksi->details)->toHaveCount(2);

    // Verify Data Integrity: total header = SUM(detail)
    expect($produksi->total_janjang)->toBe(100)
        ->and($produksi->total_berat_kg)->toBe(1600.5);
});

test('US-01 AC3: mandor cannot create duplicate header for same blok and date, submission updates existing', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blokA1 = Blok::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();
    $pemanen = Pemanen::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();

    $tanggal = now()->toDateString();

    // 1st entry
    $this->actingAs($mandor)
        ->post(route('produksi.store'), [
            'blok_id' => $blokA1->id,
            'tanggal' => $tanggal,
            'items' => [
                [
                    'pemanen_id' => $pemanen->id,
                    'jumlah_janjang' => 30,
                    'berat_kg' => 480.0,
                ],
            ],
        ])
        ->assertRedirect(route('produksi.index'));

    expect(ProduksiHarian::where('blok_id', $blokA1->id)->where('tanggal', $tanggal)->count())->toBe(1);

    // 2nd entry with same blok & date updates existing header instead of duplicate
    $this->actingAs($mandor)
        ->post(route('produksi.store'), [
            'blok_id' => $blokA1->id,
            'tanggal' => $tanggal,
            'catatan' => 'Update perbaikan data timbangan.',
            'items' => [
                [
                    'pemanen_id' => $pemanen->id,
                    'jumlah_janjang' => 35,
                    'berat_kg' => 560.0,
                ],
            ],
        ])
        ->assertRedirect(route('produksi.index'));

    // Count is still 1 (no duplicate header)
    expect(ProduksiHarian::where('blok_id', $blokA1->id)->where('tanggal', $tanggal)->count())->toBe(1);

    $updated = ProduksiHarian::where('blok_id', $blokA1->id)->where('tanggal', $tanggal)->firstOrFail();
    expect($updated->total_janjang)->toBe(35)
        ->and($updated->total_berat_kg)->toBe(560.0)
        ->and($updated->catatan)->toBe('Update perbaikan data timbangan.');
});

test('mandor is forbidden from recording harvest for a blok in another afdeling', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdelingBeta = Afdeling::where('kode', 'AFD-B')->firstOrFail();
    $blokBeta = Blok::where('afdeling_id', $afdelingBeta->id)->firstOrFail();
    $pemanen = Pemanen::where('afdeling_id', $afdelingBeta->id)->firstOrFail();

    $this->actingAs($mandor)
        ->post(route('produksi.store'), [
            'blok_id' => $blokBeta->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                [
                    'pemanen_id' => $pemanen->id,
                    'jumlah_janjang' => 40,
                    'berat_kg' => 640.0,
                ],
            ],
        ])
        ->assertSessionHasErrors('blok_id');

    expect(ProduksiHarian::where('blok_id', $blokBeta->id)->count())->toBe(0);
});

test('checkExisting endpoint returns existing data for interactive UI mode edit', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blokA1 = Blok::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();
    $pemanen = Pemanen::where('afdeling_id', $afdelingAlpha->id)->firstOrFail();

    $tanggal = now()->toDateString();

    // Check before created
    $resBefore = $this->actingAs($mandor)
        ->getJson(route('produksi.check-existing', ['blok_id' => $blokA1->id, 'tanggal' => $tanggal]))
        ->assertOk()
        ->json();
    expect($resBefore['exists'])->toBeFalse();

    // Create entry
    $this->actingAs($mandor)
        ->post(route('produksi.store'), [
            'blok_id' => $blokA1->id,
            'tanggal' => $tanggal,
            'items' => [
                [
                    'pemanen_id' => $pemanen->id,
                    'jumlah_janjang' => 50,
                    'berat_kg' => 800.0,
                ],
            ],
        ]);

    // Check after created
    $resAfter = $this->actingAs($mandor)
        ->getJson(route('produksi.check-existing', ['blok_id' => $blokA1->id, 'tanggal' => $tanggal]))
        ->assertOk()
        ->json();

    expect($resAfter['exists'])->toBeTrue()
        ->and($resAfter['status_validasi'])->toBe('menunggu')
        ->and($resAfter['items'])->toHaveCount(1)
        ->and($resAfter['items'][0]['jumlah_janjang'])->toBe(50);
});
