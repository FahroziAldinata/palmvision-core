<?php

use App\Domain\Integrasi\Pks\Models\PksRendemen;
use App\Domain\Organisasi\Models\Kebun;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config([
        'services.pks.api_key' => 'test-pks-api-key-123',
        'services.pks.webhook_secret' => 'test-pks-hmac-secret-xyz',
    ]);
});

test('pks webhook accepts valid payload with correct api key and hmac signature', function () {
    $kebun = Kebun::firstOrFail();
    $payload = [
        'no_tiket_timbangan' => 'WB-2026-09-001',
        'kebun_id' => $kebun->id,
        'tanggal_terima' => '2026-09-26',
        'berat_tbs_terima_kg' => 24850.50,
        'rendemen_cpo_persen' => 22.45,
        'rendemen_pk_persen' => 5.12,
        'ffa_persen' => 2.85,
        'catatan' => 'TBS Matang Sempurna, PKS Sei Mangkei',
    ];

    $rawJson = json_encode($payload);
    $signature = hash_hmac('sha256', $rawJson, 'test-pks-hmac-secret-xyz');

    $response = $this->withHeaders([
        'X-API-Key' => 'test-pks-api-key-123',
        'X-Signature-SHA256' => $signature,
        'Content-Type' => 'application/json',
    ])->postJson(route('api.webhooks.pks.rendemen'), $payload);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'data' => [
                'no_tiket_timbangan' => 'WB-2026-09-001',
                'berat_tbs_terima_kg' => 24850.50,
                'rendemen_cpo_persen' => 22.45,
                'rendemen_pk_persen' => 5.12,
                'ffa_persen' => 2.85,
            ],
        ]);

    $record = PksRendemen::where('no_tiket_timbangan', 'WB-2026-09-001')->firstOrFail();
    expect((float) $record->berat_tbs_terima_kg)->toBe(24850.50)
        ->and((float) $record->rendemen_cpo_persen)->toBe(22.45)
        ->and($record->kebun_id)->toBe($kebun->id);
});

test('pks webhook rejects requests with missing or invalid api key with 401', function () {
    $payload = [
        'no_tiket_timbangan' => 'WB-INVALID-001',
        'tanggal_terima' => '2026-09-26',
        'berat_tbs_terima_kg' => 10000,
        'rendemen_cpo_persen' => 20,
        'rendemen_pk_persen' => 5,
        'ffa_persen' => 3,
    ];

    $rawJson = json_encode($payload);
    $signature = hash_hmac('sha256', $rawJson, 'test-pks-hmac-secret-xyz');

    // Missing API Key
    $responseNoKey = $this->withHeaders([
        'X-Signature-SHA256' => $signature,
    ])->postJson(route('api.webhooks.pks.rendemen'), $payload);

    $responseNoKey->assertStatus(401);

    // Wrong API Key
    $responseWrongKey = $this->withHeaders([
        'X-API-Key' => 'wrong-key',
        'X-Signature-SHA256' => $signature,
    ])->postJson(route('api.webhooks.pks.rendemen'), $payload);

    $responseWrongKey->assertStatus(401);
});

test('pks webhook rejects requests with invalid hmac signature with 403', function () {
    $payload = [
        'no_tiket_timbangan' => 'WB-FORGED-001',
        'tanggal_terima' => '2026-09-26',
        'berat_tbs_terima_kg' => 15000,
        'rendemen_cpo_persen' => 21,
        'rendemen_pk_persen' => 4.5,
        'ffa_persen' => 2.5,
    ];

    // Wrong signature
    $responseWrongSig = $this->withHeaders([
        'X-API-Key' => 'test-pks-api-key-123',
        'X-Signature-SHA256' => 'invalid-hmac-signature-abc123',
    ])->postJson(route('api.webhooks.pks.rendemen'), $payload);

    $responseWrongSig->assertStatus(403);
});

test('pks webhook validates payload fields and limits', function () {
    $payload = [
        'no_tiket_timbangan' => 'WB-INVALID-DATA',
        'tanggal_terima' => 'invalid-date',
        'berat_tbs_terima_kg' => -50, // invalid negative weight
        'rendemen_cpo_persen' => 150, // invalid > 100%
        'rendemen_pk_persen' => 5,
        'ffa_persen' => 3,
    ];

    $rawJson = json_encode($payload);
    $signature = hash_hmac('sha256', $rawJson, 'test-pks-hmac-secret-xyz');

    $response = $this->withHeaders([
        'X-API-Key' => 'test-pks-api-key-123',
        'X-Signature-SHA256' => $signature,
    ])->postJson(route('api.webhooks.pks.rendemen'), $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['tanggal_terima', 'berat_tbs_terima_kg', 'rendemen_cpo_persen']);
});
