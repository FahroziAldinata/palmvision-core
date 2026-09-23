<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('unauthenticated users cannot access dashboard', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

test('direksi dashboard renders cross-plantation overview with all kebuns and polygons', function () {
    $direksi = User::where('email', 'direksi@palmvision.test')->firstOrFail();

    $response = $this->actingAs($direksi)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('role', 'direksi')
            ->has('kebuns', 2)
            ->where('summary.total_kebun', 2)
            ->where('summary.total_afdeling', 4)
            ->where('summary.total_blok', 20)
            ->has('geoJson.features', 20)
        );
});

test('manajer kebun dashboard renders kebun-scoped afdelings and polygons', function () {
    $manajer = User::where('email', 'manajer.sentosa@palmvision.test')->firstOrFail();

    $response = $this->actingAs($manajer)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('role', 'manajer_kebun')
            ->where('kebun.kode_kebun', 'KBS')
            ->has('afdelings', 2)
            ->where('summary.total_afdeling', 2)
            ->where('summary.total_blok', 10)
            ->has('geoJson.features', 10)
        );
});

test('asisten afdeling dashboard renders afdeling-scoped bloks and polygons', function () {
    $asisten = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();

    $response = $this->actingAs($asisten)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('role', 'asisten_afdeling')
            ->where('afdeling.kode', 'AFD-A')
            ->has('bloks', 5)
            ->where('summary.total_blok', 5)
            ->has('geoJson.features', 5)
        );
});

test('admin it dashboard renders system management notice without operational business data', function () {
    $admin = User::where('email', 'admin@palmvision.test')->firstOrFail();

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('role', 'admin_it')
            ->has('message')
            ->where('geoJson', null)
            ->missing('kebuns')
            ->missing('afdelings')
            ->missing('bloks')
        );
});
