<?php

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Pemupukan\Models\Pemupukan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('user can log fertilizer application and total kg is calculated accurately', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdeling = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blok = Blok::where('afdeling_id', $afdeling->id)->firstOrFail();

    $payload = [
        'blok_id' => $blok->id,
        'tanggal_aplikasi' => now()->toDateString(),
        'jenis_pupuk' => 'NPK 13-6-27',
        'dosis_kg_per_pokok' => 2.25,
        'jumlah_pokok_dipupuk' => 135,
        'cara_aplikasi' => 'piringan',
        'catatan' => 'Aplikasi semester II sesuai rekomendasi agronomis',
    ];

    $response = $this->actingAs($mandor)
        ->post(route('pemupukan.store'), $payload);

    $response->assertSessionHas('success');

    $pemupukan = Pemupukan::where('blok_id', $blok->id)->latest()->firstOrFail();

    // 2.25 * 135 = 303.75 kg
    expect((float) $pemupukan->total_kg_terpakai)->toBe(303.75)
        ->and((float) $pemupukan->dosis_kg_per_pokok)->toBe(2.25)
        ->and($pemupukan->jumlah_pokok_dipupuk)->toBe(135)
        ->and($pemupukan->dicatat_oleh)->toBe($mandor->id);
});

test('pemupukan requires valid block and positive dosage', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();

    $response = $this->actingAs($mandor)
        ->post(route('pemupukan.store'), [
            'blok_id' => '00000000-0000-0000-0000-000000000000',
            'tanggal_aplikasi' => 'invalid-date',
            'jenis_pupuk' => '',
            'dosis_kg_per_pokok' => -1.0,
            'jumlah_pokok_dipupuk' => 0,
            'cara_aplikasi' => '',
        ]);

    $response->assertSessionHasErrors([
        'blok_id',
        'tanggal_aplikasi',
        'jenis_pupuk',
        'dosis_kg_per_pokok',
        'jumlah_pokok_dipupuk',
        'cara_aplikasi',
    ]);
});

test('pemupukan index renders list and options', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();

    $response = $this->actingAs($mandor)
        ->get(route('pemupukan.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Pemupukan/Index')
            ->has('pemupukans')
            ->has('bloks')
            ->has('jenisPupukOptions')
            ->has('caraAplikasiOptions')
        );
});
