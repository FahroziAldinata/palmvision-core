<?php

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\Kebun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('direksi can access all kebun, afdeling, and blok across organizations', function () {
    $direksi = User::where('email', 'direksi@palmvision.test')->firstOrFail();

    $kebunList = Kebun::all();
    expect($kebunList)->toHaveCount(2);

    foreach ($kebunList as $kebun) {
        $this->actingAs($direksi)
            ->getJson(route('organisasi.kebun.show', $kebun))
            ->assertOk();
    }

    $afdelingList = Afdeling::all();
    expect($afdelingList)->toHaveCount(4);

    foreach ($afdelingList as $afdeling) {
        $this->actingAs($direksi)
            ->getJson(route('organisasi.afdeling.show', $afdeling))
            ->assertOk();
    }

    $blok = Blok::firstOrFail();
    $this->actingAs($direksi)
        ->getJson(route('organisasi.blok.show', $blok))
        ->assertOk();
});

test('US-09 AC3: asisten afdeling A cannot access afdeling B in the same kebun', function () {
    $asistenAlpha = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $afdelingBeta = Afdeling::where('kode', 'AFD-B')->firstOrFail();

    // Verify both are in the same kebun
    expect($afdelingAlpha->kebun_id)->toBe($afdelingBeta->kebun_id);

    // Asisten Alpha can access Afdeling Alpha
    $this->actingAs($asistenAlpha)
        ->getJson(route('organisasi.afdeling.show', $afdelingAlpha))
        ->assertOk();

    // Asisten Alpha is forbidden from accessing Afdeling Beta in same kebun
    $this->actingAs($asistenAlpha)
        ->getJson(route('organisasi.afdeling.show', $afdelingBeta))
        ->assertForbidden();
});

test('asisten afdeling cannot access bloks belonging to another afdeling', function () {
    $asistenAlpha = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    $afdelingBeta = Afdeling::where('kode', 'AFD-B')->firstOrFail();
    $blokBeta = Blok::where('afdeling_id', $afdelingBeta->id)->firstOrFail();

    $this->actingAs($asistenAlpha)
        ->getJson(route('organisasi.blok.show', $blokBeta))
        ->assertForbidden();
});

test('manajer kebun 1 can access kebun 1 and its afdelings but cannot access kebun 2', function () {
    $manajerSentosa = User::where('email', 'manajer.sentosa@palmvision.test')->firstOrFail();
    $kebun1 = Kebun::where('kode_kebun', 'KBS')->firstOrFail();
    $kebun2 = Kebun::where('kode_kebun', 'KRM')->firstOrFail();

    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $afdelingGamma = Afdeling::where('kode', 'AFD-C')->firstOrFail();

    // Manajer Sentosa can access Kebun 1 & Afdeling Alpha
    $this->actingAs($manajerSentosa)
        ->getJson(route('organisasi.kebun.show', $kebun1))
        ->assertOk();

    $this->actingAs($manajerSentosa)
        ->getJson(route('organisasi.afdeling.show', $afdelingAlpha))
        ->assertOk();

    // Manajer Sentosa is forbidden from Kebun 2 & Afdeling Gamma
    $this->actingAs($manajerSentosa)
        ->getJson(route('organisasi.kebun.show', $kebun2))
        ->assertForbidden();

    $this->actingAs($manajerSentosa)
        ->getJson(route('organisasi.afdeling.show', $afdelingGamma))
        ->assertForbidden();
});

test('separation of concerns: admin_it is denied from viewing kebun, afdeling, and blok', function () {
    $adminIt = User::where('email', 'admin@palmvision.test')->firstOrFail();
    $kebun = Kebun::firstOrFail();
    $afdeling = Afdeling::firstOrFail();
    $blok = Blok::firstOrFail();

    // Admin IT should NOT have automatic bypass to business/operational data
    $this->actingAs($adminIt)
        ->getJson(route('organisasi.kebun.show', $kebun))
        ->assertForbidden();

    $this->actingAs($adminIt)
        ->getJson(route('organisasi.afdeling.show', $afdeling))
        ->assertForbidden();

    $this->actingAs($adminIt)
        ->getJson(route('organisasi.blok.show', $blok))
        ->assertForbidden();

    // Verify viewAny is also denied for admin_it
    expect(Gate::forUser($adminIt)->allows('viewAny', Kebun::class))->toBeFalse()
        ->and(Gate::forUser($adminIt)->allows('viewAny', Afdeling::class))->toBeFalse()
        ->and(Gate::forUser($adminIt)->allows('viewAny', Blok::class))->toBeFalse();
});

test('all seeded postgis block polygons are valid closed geometries', function () {
    $results = DB::select('SELECT id, ST_IsValid(poligon) as is_valid, ST_IsClosed(poligon) as is_closed FROM poligon_blok');

    expect($results)->not->toBeEmpty();

    foreach ($results as $row) {
        expect($row->is_valid)->toBeTrue()
            ->and($row->is_closed)->toBeTrue();
    }
});
