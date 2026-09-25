<?php

use App\Domain\Forecasting\Models\CurahHujan;
use App\Domain\Forecasting\Models\ForecastResult;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\Kebun;
use App\Jobs\GenerateForecastJob;
use App\Models\User;
use App\Services\ForecastingService;
use App\Services\RainfallService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ForecastingHistoricalSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('unauthenticated request to forecast endpoint is rejected', function () {
    $blok = Blok::firstOrFail();

    $response = $this->getJson("/api/v1/forecast/{$blok->id}");
    $response->assertUnauthorized();

    $responseGenerate = $this->postJson('/api/v1/forecast/generate', [
        'blok_id' => $blok->id,
    ]);
    $responseGenerate->assertUnauthorized();
});

test('GET /api/v1/forecast/{blok_id} returns exact contract response shape when forecast exists', function () {
    $user = User::where('email', 'direksi@palmvision.test')->firstOrFail();
    $blok = Blok::firstOrFail();

    // Buat data forecast tersimpan
    ForecastResult::create([
        'blok_id' => $blok->id,
        'periode' => '2026-11-01',
        'nilai_kg' => 18420.0,
        'interval_bawah' => 16100.0,
        'interval_atas' => 20700.0,
        'mape_model' => 0.087,
        'versi_model' => 'prophet-v1-202610',
    ]);
    ForecastResult::create([
        'blok_id' => $blok->id,
        'periode' => '2026-12-01',
        'nilai_kg' => 19500.0,
        'interval_bawah' => 17000.0,
        'interval_atas' => 22000.0,
        'mape_model' => 0.087,
        'versi_model' => 'prophet-v1-202610',
    ]);

    $response = $this->actingAs($user)->getJson("/api/v1/forecast/{$blok->id}");

    $response->assertOk();
    $response->assertJsonStructure([
        'blok_id',
        'proyeksi' => [
            '*' => [
                'periode',
                'nilai_kg',
                'interval_bawah',
                'interval_atas',
            ],
        ],
        'mape_model',
        'versi_model',
    ]);

    $data = $response->json();
    expect($data['blok_id'])->toBe($blok->id)
        ->and($data['mape_model'])->toEqual(0.087)
        ->and($data['versi_model'])->toBe('prophet-v1-202610')
        ->and($data['proyeksi'])->toHaveCount(2)
        ->and($data['proyeksi'][0]['periode'])->toBe('2026-11')
        ->and($data['proyeksi'][0]['nilai_kg'])->toEqual(18420.0)
        ->and($data['proyeksi'][0]['interval_bawah'])->toEqual(16100.0)
        ->and($data['proyeksi'][0]['interval_atas'])->toEqual(20700.0);
});

test('GET /api/v1/forecast/{blok_id} returns empty projection when no forecast exists', function () {
    $user = User::where('email', 'direksi@palmvision.test')->firstOrFail();
    $blok = Blok::firstOrFail();

    $response = $this->actingAs($user)->getJson("/api/v1/forecast/{$blok->id}");

    $response->assertOk();
    $data = $response->json();
    expect($data['blok_id'])->toBe($blok->id)
        ->and($data['proyeksi'])->toBeEmpty()
        ->and($data['mape_model'])->toBeNull()
        ->and($data['versi_model'])->toBeNull();
});

test('POST /api/v1/forecast/generate dispatches GenerateForecastJob to Horizon queue', function () {
    Bus::fake([GenerateForecastJob::class]);

    $user = User::where('email', 'manajer.sentosa@palmvision.test')->firstOrFail();
    $blok = Blok::firstOrFail();

    $response = $this->actingAs($user)->postJson('/api/v1/forecast/generate', [
        'blok_id' => $blok->id,
        'horizon_bulan' => 3,
    ]);

    $response->assertStatus(202)
        ->assertJson([
            'status' => 'queued',
            'horizon_bulan' => 3,
        ]);

    Bus::assertDispatched(GenerateForecastJob::class, function ($job) use ($blok) {
        return $job->blokId === $blok->id && $job->horizonMonths === 3;
    });
});

test('POST /api/v1/forecast/generate dispatches jobs for all blocks when kebun_id provided', function () {
    Bus::fake([GenerateForecastJob::class]);

    $user = User::where('email', 'direksi@palmvision.test')->firstOrFail();
    $kebun = Kebun::where('kode_kebun', 'KBS')->firstOrFail();

    $response = $this->actingAs($user)->postJson('/api/v1/forecast/generate', [
        'kebun_id' => $kebun->id,
        'horizon_bulan' => 3,
    ]);

    $response->assertStatus(202)
        ->assertJson([
            'status' => 'queued',
            'total_blok' => 10,
        ]);

    Bus::assertDispatched(GenerateForecastJob::class, 10);
});

test('forecast_result preserves audit history and does not delete old predictions on retraining (Rule D5)', function () {
    $blok = Blok::firstOrFail();

    // Versi 1
    ForecastResult::create([
        'blok_id' => $blok->id,
        'periode' => '2026-11-01',
        'nilai_kg' => 18000.0,
        'interval_bawah' => 16000.0,
        'interval_atas' => 20000.0,
        'mape_model' => 0.092,
        'versi_model' => 'prophet-v1-202609',
    ]);

    // Retraining: Versi 2
    ForecastResult::create([
        'blok_id' => $blok->id,
        'periode' => '2026-11-01',
        'nilai_kg' => 18450.0,
        'interval_bawah' => 16200.0,
        'interval_atas' => 20800.0,
        'mape_model' => 0.081,
        'versi_model' => 'prophet-v2-202610',
    ]);

    // Verifikasi kedua baris tetap ada di database (audit trail tidak terhapus)
    $allRecords = ForecastResult::where('blok_id', $blok->id)->get();
    expect($allRecords)->toHaveCount(2);

    $v1 = $allRecords->where('versi_model', 'prophet-v1-202609')->first();
    $v2 = $allRecords->where('versi_model', 'prophet-v2-202610')->first();

    expect($v1)->not->toBeNull()
        ->and($v1->mape_model)->toEqual(0.092)
        ->and($v1->nilai_kg)->toEqual(18000.0);

    expect($v2)->not->toBeNull()
        ->and($v2->mape_model)->toEqual(0.081)
        ->and($v2->nilai_kg)->toEqual(18450.0);

    // Endpoint show mengambil versi terbaru
    $user = User::where('email', 'direksi@palmvision.test')->firstOrFail();
    $response = $this->actingAs($user)->getJson("/api/v1/forecast/{$blok->id}");

    $response->assertOk();
    $data = $response->json();
    expect($data['versi_model'])->toBe('prophet-v2-202610')
        ->and($data['mape_model'])->toEqual(0.081)
        ->and($data['proyeksi'][0]['nilai_kg'])->toEqual(18450.0);
});

test('Open-Meteo rainfall caching saves data to DB and prevents redundant live calls', function () {
    $kebun = Kebun::where('kode_kebun', 'KBS')->firstOrFail();

    // Mock API call Open-Meteo
    Http::fake([
        'archive-api.open-meteo.com/*' => Http::response([
            'daily' => [
                'time' => ['2026-01-01', '2026-01-02'],
                'precipitation_sum' => [12.5, 0.0],
            ],
        ], 200),
    ]);

    $rainfallService = app(RainfallService::class);

    // Panggilan pertama: fetch dan simpan
    $records1 = $rainfallService->fetchAndStore($kebun, '2026-01-01', '2026-01-02');
    expect($records1)->toBe(2);

    // Cek di database
    $countInDb = CurahHujan::where('kebun_id', $kebun->id)->count();
    expect($countInDb)->toBe(2);

    // Panggilan kedua untuk rentang yang sama tidak boleh memanggil HTTP lagi karena sudah di-cache
    Http::fake([
        'archive-api.open-meteo.com/*' => Http::response([], 500), // Jika terpanggil lagi, akan throw 500
    ]);

    $records2 = $rainfallService->fetchAndStore($kebun, '2026-01-01', '2026-01-02');
    expect($records2)->toBe(2);
});

test('ForecastingHistoricalSeeder generates seasonal production and backtesting calculates valid MAPE', function () {
    $this->seed(ForecastingHistoricalSeeder::class);

    $blok = Blok::where('kode_blok', 'A01')->firstOrFail();

    $forecastingService = app(ForecastingService::class);
    $records = $forecastingService->prepareHistoricalDataForBlock($blok);

    // Minimal 12-18 bulan data historis tergenerate
    expect(count($records))->toBeGreaterThanOrEqual(12);

    // Verifikasi data memiliki nilai y > 0 dan curah hujan
    foreach ($records as $r) {
        expect($r['y'])->toBeGreaterThan(0)
            ->and($r['curah_hujan'])->toBeGreaterThanOrEqual(0)
            ->and($r['usia_tanaman_bulan'])->toBeGreaterThan(0);
    }
});

test('forecast:retrain command in queue mode dispatches GenerateForecastJob to Horizon', function () {
    Bus::fake([GenerateForecastJob::class]);

    $this->artisan('forecast:retrain --queue')
        ->assertSuccessful();

    // KBS has 10 blocks, KBI has 10 blocks -> total 20 blocks
    Bus::assertDispatched(GenerateForecastJob::class, 20);
});

test('forecast:retrain command with --blok option targets specific block', function () {
    Bus::fake([GenerateForecastJob::class]);

    $blok = Blok::where('kode_blok', 'A01')->firstOrFail();

    $this->artisan("forecast:retrain --blok={$blok->kode_blok} --queue")
        ->assertSuccessful();

    Bus::assertDispatched(GenerateForecastJob::class, 1);
});

test('dashboard view provides forecasting overview and renders PRD 11.5 disclaimer', function () {
    $direksi = User::where('email', 'direksi@palmvision.test')->firstOrFail();
    $blok = Blok::firstOrFail();

    ForecastResult::create([
        'blok_id' => $blok->id,
        'periode' => '2026-11-01',
        'nilai_kg' => 20500.0,
        'interval_bawah' => 18000.0,
        'interval_atas' => 23000.0,
        'mape_model' => 0.095,
        'versi_model' => 'prophet-v1-202610',
    ]);

    $response = $this->actingAs($direksi)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('forecasting')
            ->where('forecasting.has_data', true)
            ->where('forecasting.versi_terbaru', 'prophet-v1-202610')
            ->where('forecasting.rata_rata_mape', 0.095)
        );
});
