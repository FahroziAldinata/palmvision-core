<?php

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Pemanen\Models\Pemanen;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('asisten afdeling can view and create pemanen in their own afdeling', function () {
    $asistenAlpha = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();

    // Can access pemanen index
    $this->actingAs($asistenAlpha)
        ->get(route('pemanen.index'))
        ->assertOk();

    // Can create pemanen in Afdeling Alpha
    $response = $this->actingAs($asistenAlpha)
        ->post(route('pemanen.store'), [
            'afdeling_id' => $afdelingAlpha->id,
            'nama' => 'Pemanen Budi',
            'kode_pemanen' => 'PMN-001',
            'status' => 'aktif',
        ]);

    $response->assertRedirect(route('pemanen.index'));
    $this->assertDatabaseHas('pemanen', [
        'afdeling_id' => $afdelingAlpha->id,
        'nama' => 'Pemanen Budi',
        'kode_pemanen' => 'PMN-001',
        'status' => 'aktif',
    ]);
});

test('asisten afdeling is forbidden from creating pemanen in another afdeling', function () {
    $asistenAlpha = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    $afdelingBeta = Afdeling::where('kode', 'AFD-B')->firstOrFail();

    $this->actingAs($asistenAlpha)
        ->post(route('pemanen.store'), [
            'afdeling_id' => $afdelingBeta->id,
            'nama' => 'Pemanen Ilegal',
            'kode_pemanen' => 'PMN-999',
            'status' => 'aktif',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('pemanen', [
        'kode_pemanen' => 'PMN-999',
    ]);
});

test('kode pemanen must be unique within the same afdeling', function () {
    $asistenAlpha = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();

    Pemanen::create([
        'afdeling_id' => $afdelingAlpha->id,
        'nama' => 'Pemanen Pertama',
        'kode_pemanen' => 'PMN-001',
        'status' => 'aktif',
    ]);

    // Attempt to create another with same code in same afdeling
    $response = $this->actingAs($asistenAlpha)
        ->post(route('pemanen.store'), [
            'afdeling_id' => $afdelingAlpha->id,
            'nama' => 'Pemanen Duplikat',
            'kode_pemanen' => 'PMN-001',
            'status' => 'aktif',
        ]);

    $response->assertSessionHasErrors('kode_pemanen');
});

test('asisten afdeling can update and delete pemanen in their afdeling', function () {
    $asistenAlpha = User::where('email', 'asisten.alpha@palmvision.test')->firstOrFail();
    $afdelingAlpha = Afdeling::where('kode', 'AFD-A')->firstOrFail();

    $pemanen = Pemanen::create([
        'afdeling_id' => $afdelingAlpha->id,
        'nama' => 'Pemanen Awal',
        'kode_pemanen' => 'PMN-010',
        'status' => 'aktif',
    ]);

    // Update
    $this->actingAs($asistenAlpha)
        ->put(route('pemanen.update', $pemanen->id), [
            'nama' => 'Pemanen Diedit',
            'kode_pemanen' => 'PMN-010',
            'status' => 'nonaktif',
        ])
        ->assertRedirect(route('pemanen.index'));

    $this->assertDatabaseHas('pemanen', [
        'id' => $pemanen->id,
        'nama' => 'Pemanen Diedit',
        'status' => 'nonaktif',
    ]);

    // Delete
    $this->actingAs($asistenAlpha)
        ->delete(route('pemanen.destroy', $pemanen->id))
        ->assertRedirect(route('pemanen.index'));

    $this->assertDatabaseMissing('pemanen', [
        'id' => $pemanen->id,
    ]);
});
