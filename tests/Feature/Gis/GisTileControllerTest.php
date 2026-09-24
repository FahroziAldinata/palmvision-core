<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('vector tile endpoint requires authentication (US-05 & Deliverable 3.2)', function () {
    $response = $this->get('/gis/tiles/12/3202/2041.pbf');
    $response->assertRedirect(route('login'));
});

test('authenticated user can fetch vector tiles from protected proxy', function () {
    $direksi = User::where('email', 'direksi@palmvision.test')->firstOrFail();

    $response = $this->actingAs($direksi)
        ->get('/gis/tiles/12/3202/2041.pbf');

    // Endpoint tile returns either 200 OK with application/x-protobuf, or 204 if tile empty
    expect($response->status())->toBeIn([200, 204]);
    if ($response->status() === 200) {
        $response->assertHeader('Content-Type', 'application/x-protobuf');
    }
});

test('authenticated user can view dedicated GIS explorer page', function () {
    $gisUser = User::where('email', 'gis@palmvision.test')->firstOrFail();

    $response = $this->actingAs($gisUser)
        ->get(route('gis.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Gis/Index')
            ->has('kebuns')
            ->has('afdelings')
            ->has('geoJson')
            ->where('canImport', true)
        );
});
