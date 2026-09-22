<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('health check returns 200 when all services are reachable', function () {
    Http::fake([
        '*/health' => Http::response(['status' => 'ok', 'service' => 'palmvision-forecasting'], 200),
    ]);

    $response = $this->get('/health');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'app',
            'environment',
            'database' => [
                'status',
                'postgis_version',
            ],
            'redis' => [
                'status',
            ],
            'forecasting' => [
                'status',
            ],
        ])
        ->assertJson([
            'status' => 'ok',
            'database' => [
                'status' => 'connected',
            ],
            'redis' => [
                'status' => 'connected',
            ],
            'forecasting' => [
                'status' => 'reachable',
            ],
        ]);
});

test('health check returns 200 even when forecasting service is unreachable', function () {
    Http::fake([
        '*/health' => function () {
            throw new ConnectionException('Connection refused');
        },
    ]);

    $response = $this->get('/health');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'database' => [
                'status' => 'connected',
            ],
            'redis' => [
                'status' => 'connected',
            ],
            'forecasting' => [
                'status' => 'unreachable',
            ],
        ]);
});
