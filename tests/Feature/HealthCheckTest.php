<?php

test('health check returns 200 with all services verified', function () {
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
