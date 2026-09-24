<?php

use App\Domain\Organisasi\Models\Kebun;
use App\Models\User;
use App\Services\GisMapService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PerformanceBenchmarkSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(PerformanceBenchmarkSeeder::class);
});

test('PRD section 15: GIS rendering performance benchmark on full estate (250 blocks) comparing GeoJSON vs Vector Tile', function () {
    $direksi = User::where('email', 'direksi@palmvision.test')->firstOrFail();
    $benchKebun = Kebun::where('kode_kebun', 'KB-BENCH')->firstOrFail();

    /** @var GisMapService $gisMapService */
    $gisMapService = app(GisMapService::class);

    // 1. Verifikasi Validitas Geometri Spasial 250 Blok
    $validityCheck = DB::table('poligon_blok')
        ->join('blok', 'blok.id', '=', 'poligon_blok.blok_id')
        ->join('afdeling', 'afdeling.id', '=', 'blok.afdeling_id')
        ->where('afdeling.kebun_id', $benchKebun->id)
        ->selectRaw('COUNT(*) as total, SUM(CASE WHEN ST_IsValid(poligon) THEN 1 ELSE 0 END) as valid_count')
        ->first();

    expect((int) $validityCheck->total)->toBe(250)
        ->and((int) $validityCheck->valid_count)->toBe(250);

    // 2. STRATEGI A: GeoJSON Langsung (Skala Kebun Penuh 250 Blok)
    // Warm-up query
    $gisMapService->getGeoJsonFeatures($benchKebun->id);

    $memBefore = memory_get_usage(true);
    $geoJsonStart = hrtime(true);

    $geoJsonResult = $gisMapService->getGeoJsonFeatures($benchKebun->id);
    $geoJsonPayload = json_encode($geoJsonResult);
    $geoJsonPayloadBytes = strlen($geoJsonPayload ?: '');

    $geoJsonEnd = hrtime(true);
    $memAfter = memory_get_usage(true);

    $geoJsonTimeMs = ($geoJsonEnd - $geoJsonStart) / 1_000_000;
    $geoJsonMemoryDeltaMb = max(0, ($memAfter - $memBefore)) / (1024 * 1024);
    $geoJsonPayloadKb = $geoJsonPayloadBytes / 1024;

    // 3. STRATEGI B: Vector Tile Server via Protected Proxy (/gis/tiles/{z}/{x}/{y}.pbf)
    // Hitung koordinat tile z=12 aktual dari poligon blok kebun benchmark
    $tileCoord = DB::selectOne('
        SELECT
            12 AS z,
            floor((ST_X(ST_Centroid(p.poligon)) + 180.0) / 360.0 * power(2, 12))::int AS x,
            floor((1.0 - ln(tan(radians(ST_Y(ST_Centroid(p.poligon)))) + 1.0 / cos(radians(ST_Y(ST_Centroid(p.poligon))))) / pi()) / 2.0 * power(2, 12))::int AS y
        FROM poligon_blok p
        JOIN blok b ON b.id = p.blok_id
        JOIN afdeling a ON a.id = b.afdeling_id
        WHERE a.kebun_id = ?
        LIMIT 1
    ', [$benchKebun->id]);

    // Eksekusi fungsi PostGIS ST_AsMVT native
    $mvtDbStart = hrtime(true);
    $mvtRaw = DB::selectOne(
        'SELECT length(public.mvt_poligon_blok(?, ?, ?, ?, ?)) AS len, public.mvt_poligon_blok(?, ?, ?, ?, ?) AS data',
        [$tileCoord->z, $tileCoord->x, $tileCoord->y, (string) $benchKebun->id, null, $tileCoord->z, $tileCoord->x, $tileCoord->y, (string) $benchKebun->id, null]
    );
    $mvtDbEnd = hrtime(true);
    $mvtDbTimeMs = ($mvtDbEnd - $mvtDbStart) / 1_000_000;
    $mvtBytes = (int) ($mvtRaw->len ?? 0);

    // Mock response internal pg_tileserv dengan data MVT nyata dari PostGIS test DB
    Http::fake([
        '*/public.mvt_poligon_blok/*' => function () use ($mvtRaw) {
            return Http::response($mvtRaw->data ?? '', 200, [
                'Content-Type' => 'application/x-protobuf',
            ]);
        },
    ]);

    $tileStart = hrtime(true);
    $tileResponse = $this->actingAs($direksi)
        ->get("/gis/tiles/{$tileCoord->z}/{$tileCoord->x}/{$tileCoord->y}.pbf?kebun_id={$benchKebun->id}");
    $tileEnd = hrtime(true);

    $tileProxyTimeMs = ($tileEnd - $tileStart) / 1_000_000;
    $tileTotalTimeMs = $mvtDbTimeMs + $tileProxyTimeMs;
    $tilePayloadBytes = strlen($tileResponse->getContent());
    $tilePayloadKb = $tilePayloadBytes / 1024;

    // 4. Verifikasi status Vector Tile response
    expect($tileResponse->status())->toBe(200);
    $tileResponse->assertHeader('Content-Type', 'application/x-protobuf');
    expect($tilePayloadBytes)->toBe($mvtBytes)
        ->and($tilePayloadBytes)->toBeGreaterThan(1000); // 250 blok ter-encode dalam protobuf (> 1 KB)

    // 5. Verifikasi Strategi Dual-Mode (BaseMap.vue & GisMapService)
    // Skenario kebun kecil (10 blok) -> GeoJSON langsung
    // Skenario kebun penuh (250 blok) -> Vector Tile (pg_tileserv)
    expect($gisMapService->getRecommendedRenderStrategy(10))->toBe('geojson')
        ->and($gisMapService->getRecommendedRenderStrategy(250))->toBe('vector_tile');

    // 6. Cetak Laporan Perbandingan Kinerja Eksplisit
    fwrite(STDERR, sprintf(
        "\n==========================================================================".
        "\n[BENCHMARK PERFORMA GIS — SKENARIO 1 KEBUN PENUH (250 BLOK)]".
        "\n--------------------------------------------------------------------------".
        "\n1. Strategi GeoJSON Langsung (Bulk Download):".
        "\n   - Jumlah Fitur Poligon : %d Blok".
        "\n   - Ukuran Payload JSON  : %.2f KB (%d bytes)".
        "\n   - Waktu Eksekusi       : %.2f ms".
        "\n   - Delta Memori         : %.2f MB".
        "\n".
        "\n2. Strategi Vector Tile (pg_tileserv Protobuf via Proxy):".
        "\n   - Endpoint             : /gis/tiles/%d/%d/%d.pbf".
        "\n   - Ukuran Tile PBF      : %.2f KB (%d bytes)".
        "\n   - PostGIS ST_AsMVT     : %.2f ms".
        "\n   - Laravel Proxy Time   : %.2f ms".
        "\n   - Total Waktu Pipeline : %.2f ms".
        "\n   - Content-Type         : application/x-protobuf".
        "\n".
        "\n3. Analisis & Manfaat Vector Tiling:".
        "\n   - Rasio Efisiensi Data : Vector Tile ~%.1fx lebih ringan dibanding GeoJSON utuh.".
        "\n   - Keputusan Dual-Mode  : Skala 10 blok => GeoJSON | Skala 250 blok => Vector Tile.".
        "\n   - Di skala ratusan-ribuan blok, Vector Tile mencegah browser kehabisan memori".
        "\n     karena mengunduh tile secara bertahap pada viewport aktif pengguna.".
        "\n==========================================================================\n",
        count($geoJsonResult['features']),
        $geoJsonPayloadKb,
        $geoJsonPayloadBytes,
        $geoJsonTimeMs,
        $geoJsonMemoryDeltaMb,
        $tileCoord->z,
        $tileCoord->x,
        $tileCoord->y,
        $tilePayloadKb,
        $tilePayloadBytes,
        $mvtDbTimeMs,
        $tileProxyTimeMs,
        $tileTotalTimeMs,
        $geoJsonPayloadBytes > 0 && $tilePayloadBytes > 0 ? ($geoJsonPayloadBytes / max(1, $tilePayloadBytes)) : 1.0
    ));

    // Verifikasi fitur terbentuk lengkap
    expect(count($geoJsonResult['features']))->toBe(250)
        ->and($geoJsonPayloadBytes)->toBeGreaterThan(50000); // 250 blok GeoJSON berukuran signifikan (> 50KB)
});
