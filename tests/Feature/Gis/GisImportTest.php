<?php

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\PoligonBlok;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Shapefile\Geometry\Linestring;
use Shapefile\Geometry\Point;
use Shapefile\Geometry\Polygon;
use Shapefile\Shapefile;
use Shapefile\ShapefileWriter;
use ZipArchive;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('US-06 AC1 & AC2: tim_gis can import valid GeoJSON, updates luas_ha automatically', function () {
    $gisUser = User::where('email', 'gis@palmvision.test')->firstOrFail();
    $blok = Blok::where('kode_blok', 'A01')->firstOrFail();

    $initialVersionCount = PoligonBlok::where('blok_id', $blok->id)->count();
    $initialLuas = (float) $blok->luas_ha;

    // Poligon baru di koordinat non-overlap (misal sedikit digeser/disesuaikan)
    $validGeoJson = [
        'type' => 'Polygon',
        'coordinates' => [
            [
                [101.4401, 0.5301],
                [101.4449, 0.5301],
                [101.4449, 0.5349],
                [101.4401, 0.5349],
                [101.4401, 0.5301],
            ],
        ],
    ];

    $response = $this->actingAs($gisUser)
        ->postJson(route('gis.blok.import', $blok), [
            'geojson' => json_encode($validGeoJson),
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'versi' => 2,
        ]);

    $blok->refresh();
    $newVersionCount = PoligonBlok::where('blok_id', $blok->id)->count();

    // Verifikasi versi baru bertambah dan tidak overwrite
    expect($newVersionCount)->toBe($initialVersionCount + 1)
        ->and((float) $blok->luas_ha)->not->toBe($initialLuas)
        ->and((float) $blok->luas_ha)->toBeGreaterThan(0.0);
});

test('US-06 AC3: import creates new version and preserves previous version history', function () {
    $gisUser = User::where('email', 'gis@palmvision.test')->firstOrFail();
    $blok = Blok::where('kode_blok', 'A02')->firstOrFail();

    // Pastikan versi awal = 1
    expect(PoligonBlok::where('blok_id', $blok->id)->count())->toBe(1);

    // Update ke-1
    $geoJsonV2 = [
        'type' => 'Polygon',
        'coordinates' => [
            [
                [101.4451, 0.5301],
                [101.4499, 0.5301],
                [101.4499, 0.5349],
                [101.4451, 0.5349],
                [101.4451, 0.5301],
            ],
        ],
    ];
    $this->actingAs($gisUser)
        ->postJson(route('gis.blok.import', $blok), ['geojson' => json_encode($geoJsonV2)])
        ->assertOk();

    // Update ke-2
    $geoJsonV3 = [
        'type' => 'Polygon',
        'coordinates' => [
            [
                [101.4452, 0.5302],
                [101.4498, 0.5302],
                [101.4498, 0.5348],
                [101.4452, 0.5348],
                [101.4452, 0.5302],
            ],
        ],
    ];
    $this->actingAs($gisUser)
        ->postJson(route('gis.blok.import', $blok), ['geojson' => json_encode($geoJsonV3)])
        ->assertOk();

    // Verifikasi bahwa untuk satu blok yang sudah 2x di-update, kini terdapat 3 baris di poligon_blok
    $versions = PoligonBlok::where('blok_id', $blok->id)->orderBy('versi')->get();
    expect($versions)->toHaveCount(3)
        ->and($versions[0]->versi)->toBe(1)
        ->and($versions[1]->versi)->toBe(2)
        ->and($versions[2]->versi)->toBe(3);
});

test('US-06: import polygon that overlaps with another block is rejected with clear message', function () {
    $gisUser = User::where('email', 'gis@palmvision.test')->firstOrFail();
    $blokA1 = Blok::where('kode_blok', 'A01')->firstOrFail();
    $blokA2 = Blok::where('kode_blok', 'A02')->firstOrFail();

    // Koordinat yang sengaja menabrak/overlap batas poligon blok A02
    // A02 berada di sekitar lng 101.445 - 101.450
    $overlappingGeoJson = [
        'type' => 'Polygon',
        'coordinates' => [
            [
                [101.4430, 0.5310], // Berada di antara A01 dan A02 (overlap)
                [101.4470, 0.5310],
                [101.4470, 0.5340],
                [101.4430, 0.5340],
                [101.4430, 0.5310],
            ],
        ],
    ];

    $response = $this->actingAs($gisUser)
        ->postJson(route('gis.blok.import', $blokA1), [
            'geojson' => json_encode($overlappingGeoJson),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    $errorMessage = $response->json('errors.file.0');
    expect($errorMessage)->toContain('overlap')
        ->and($errorMessage)->toContain('A02');
});

test('US-06: unauthorized roles cannot import polygon (RBAC scoping)', function () {
    $mandor = User::where('email', 'mandor@palmvision.test')->firstOrFail();
    $kerani = User::where('email', 'kerani@palmvision.test')->firstOrFail();
    $blok = Blok::firstOrFail();

    $validGeoJson = [
        'type' => 'Polygon',
        'coordinates' => [
            [
                [101.4401, 0.5301],
                [101.4449, 0.5301],
                [101.4449, 0.5349],
                [101.4401, 0.5349],
                [101.4401, 0.5301],
            ],
        ],
    ];

    // Mandor is forbidden
    $this->actingAs($mandor)
        ->postJson(route('gis.blok.import', $blok), ['geojson' => json_encode($validGeoJson)])
        ->assertForbidden();

    // Kerani taksasi is forbidden
    $this->actingAs($kerani)
        ->postJson(route('gis.blok.import', $blok), ['geojson' => json_encode($validGeoJson)])
        ->assertForbidden();
});

test('US-06 AC1: tim_gis can import shapefile via zip archive', function () {
    $gisUser = User::where('email', 'gis@palmvision.test')->firstOrFail();
    $blok = Blok::where('kode_blok', 'A03')->firstOrFail();

    // Buat Shapefile temporer di storage
    $tempDir = storage_path('app/temp_test_shp_'.uniqid());
    File::ensureDirectoryExists($tempDir);
    $shpBasePath = $tempDir.'/test_blok';

    // Buat poligon Shapefile
    $polygon = new Polygon;
    $ring = new Linestring;
    $ring->addPoint(new Point(101.4501, 0.5301));
    $ring->addPoint(new Point(101.4549, 0.5301));
    $ring->addPoint(new Point(101.4549, 0.5349));
    $ring->addPoint(new Point(101.4501, 0.5349));
    $ring->addPoint(new Point(101.4501, 0.5301));
    $polygon->addRing($ring);

    $shapefileWriter = new ShapefileWriter($shpBasePath.'.shp', [
        Shapefile::OPTION_EXISTING_FILES_MODE => Shapefile::MODE_OVERWRITE,
    ]);
    $shapefileWriter->setShapeType(Shapefile::SHAPE_TYPE_POLYGON);
    $shapefileWriter->writeRecord($polygon);
    unset($shapefileWriter); // Pastikan file ditutup

    // Paketkan menjadi ZIP file
    $zipPath = $tempDir.'/test_blok.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFile($shpBasePath.'.shp', 'test_blok.shp');
    $zip->addFile($shpBasePath.'.shx', 'test_blok.shx');
    $zip->addFile($shpBasePath.'.dbf', 'test_blok.dbf');
    $zip->close();

    $uploadedZip = new UploadedFile(
        $zipPath,
        'test_blok.zip',
        'application/zip',
        null,
        true
    );

    $response = $this->actingAs($gisUser)
        ->postJson(route('gis.blok.import', $blok), [
            'file' => $uploadedZip,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $blok->refresh();
    expect(PoligonBlok::where('blok_id', $blok->id)->count())->toBe(2)
        ->and((float) $blok->luas_ha)->toBeGreaterThan(0.0);

    // Cleanup temp dir
    File::deleteDirectory($tempDir);
});
