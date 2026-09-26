<?php

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Pemanen\Models\Pemanen;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Taksasi\Models\Taksasi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('mobile worker can login with valid credentials and receive sanctum token', function () {
    $response = $this->postJson(route('api.auth.login'), [
        'email' => 'mandor@palmvision.test',
        'password' => 'password',
        'device_name' => 'Redmi Note 12 - Mandor 1',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
            'user' => ['id', 'name', 'email', 'roles', 'kebun_id', 'afdeling_id'],
        ]);

    expect($response->json('user.email'))->toBe('mandor@palmvision.test');
});

test('mobile worker cannot login with wrong password', function () {
    $response = $this->postJson(route('api.auth.login'), [
        'email' => 'mandor@palmvision.test',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Email atau password tidak sesuai.',
        ]);
});

test('mobile bootstrap returns master data scoped to user afdeling and current server time', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();

    $response = $this->actingAs($mandor, 'sanctum')
        ->getJson(route('api.mobile.bootstrap'));

    $response->assertOk()
        ->assertJsonStructure([
            'server_time',
            'user',
            'bloks',
            'pemanens',
            'pupuk_options',
        ]);

    expect($response->json('bloks'))->not->toBeEmpty()
        ->and($response->json('pemanens'))->not->toBeEmpty();
});

test('mobile produksi sync inserts records with details and handles idempotency', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdeling = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blok = Blok::where('afdeling_id', $afdeling->id)->firstOrFail();
    $pemanen = Pemanen::where('afdeling_id', $afdeling->id)->firstOrFail();

    $clientUuid = (string) Str::uuid();
    $tanggal = now()->toDateString();

    $payload = [
        'items' => [
            [
                'client_uuid' => $clientUuid,
                'blok_id' => $blok->id,
                'tanggal' => $tanggal,
                'catatan' => 'Sync dari flutter app',
                'device_time' => now()->toIso8601String(),
                'details' => [
                    [
                        'pemanen_id' => $pemanen->id,
                        'jumlah_janjang' => 50,
                        'berat_kg' => 800.0,
                    ],
                ],
            ],
        ],
    ];

    // First sync
    $response1 = $this->actingAs($mandor, 'sanctum')
        ->postJson(route('api.produksi.sync'), $payload);

    $response1->assertOk()
        ->assertJsonPath('results.0.status', 'synced')
        ->assertJsonPath('results.0.client_uuid', $clientUuid)
        ->assertJsonPath('results.0.perlu_tinjauan_waktu', false);

    $serverId = $response1->json('results.0.server_id');

    // Verify row in database
    $record = ProduksiHarian::with('details')->find($serverId);
    expect($record)->not->toBeNull()
        ->and($record->client_uuid)->toBe($clientUuid)
        ->and($record->sumber)->toBe('mobile')
        ->and($record->total_janjang)->toBe(50)
        ->and($record->total_berat_kg)->toBe(800.0)
        ->and($record->perlu_tinjauan_waktu)->toBeFalse();

    // Second sync with identical client_uuid (Idempotency test)
    $response2 = $this->actingAs($mandor, 'sanctum')
        ->postJson(route('api.produksi.sync'), $payload);

    $response2->assertOk()
        ->assertJsonPath('results.0.status', 'already_synced')
        ->assertJsonPath('results.0.server_id', $serverId);

    // Assert no duplicate record created
    expect(ProduksiHarian::where('client_uuid', $clientUuid)->count())->toBe(1);
});

test('mobile produksi sync detects device clock deviation over 24 hours and flags perlu_tinjauan_waktu without rejecting', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $afdeling = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blok = Blok::where('afdeling_id', $afdeling->id)->firstOrFail();
    $pemanen = Pemanen::where('afdeling_id', $afdeling->id)->firstOrFail();

    $clientUuid = (string) Str::uuid();
    $tanggal = now()->subDays(3)->toDateString();
    // Device time is 3 days in the past (clock drift / fake device time)
    $driftedDeviceTime = now()->subDays(3)->toIso8601String();

    $payload = [
        'items' => [
            [
                'client_uuid' => $clientUuid,
                'blok_id' => $blok->id,
                'tanggal' => $tanggal,
                'catatan' => 'Sync dengan selisih waktu perangkat',
                'device_time' => $driftedDeviceTime,
                'details' => [
                    [
                        'pemanen_id' => $pemanen->id,
                        'jumlah_janjang' => 30,
                        'berat_kg' => 450.0,
                    ],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($mandor, 'sanctum')
        ->postJson(route('api.produksi.sync'), $payload);

    $response->assertOk()
        ->assertJsonPath('results.0.status', 'synced')
        ->assertJsonPath('results.0.perlu_tinjauan_waktu', true);

    $record = ProduksiHarian::where('client_uuid', $clientUuid)->firstOrFail();
    expect($record->perlu_tinjauan_waktu)->toBeTrue();
});

test('mobile taksasi sync inserts records and detects clock deviation over 24 hours', function () {
    $kerani = User::where('email', 'kerani@palmvision.test')->firstOrFail();
    $afdeling = Afdeling::where('kode', 'AFD-A')->firstOrFail();
    $blok = Blok::where('afdeling_id', $afdeling->id)->firstOrFail();

    $clientUuidNormal = (string) Str::uuid();
    $clientUuidDrift = (string) Str::uuid();

    $payload = [
        'items' => [
            [
                'client_uuid' => $clientUuidNormal,
                'blok_id' => $blok->id,
                'tanggal_taksasi' => now()->toDateString(),
                'pokok_disampel' => 10,
                'estimasi_janjang' => 40,
                'estimasi_bjr' => 16.5,
                'estimasi_total_kg' => 660.0,
                'device_time' => now()->toIso8601String(),
            ],
            [
                'client_uuid' => $clientUuidDrift,
                'blok_id' => $blok->id,
                'tanggal_taksasi' => now()->subDays(2)->toDateString(),
                'pokok_disampel' => 10,
                'estimasi_janjang' => 35,
                'estimasi_bjr' => 15.0,
                'estimasi_total_kg' => 525.0,
                'device_time' => now()->subDays(2)->toIso8601String(), // >24h
            ],
        ],
    ];

    $response = $this->actingAs($kerani, 'sanctum')
        ->postJson(route('api.taksasi.sync'), $payload);

    $response->assertOk()
        ->assertJsonPath('results.0.status', 'synced')
        ->assertJsonPath('results.0.perlu_tinjauan_waktu', false)
        ->assertJsonPath('results.1.status', 'synced')
        ->assertJsonPath('results.1.perlu_tinjauan_waktu', true);

    // Verify idempotency on second sync
    $responseRetry = $this->actingAs($kerani, 'sanctum')
        ->postJson(route('api.taksasi.sync'), $payload);

    $responseRetry->assertOk()
        ->assertJsonPath('results.0.status', 'already_synced')
        ->assertJsonPath('results.1.status', 'already_synced');

    expect(Taksasi::where('client_uuid', $clientUuidNormal)->count())->toBe(1)
        ->and(Taksasi::where('client_uuid', $clientUuidDrift)->count())->toBe(1);
});
