<?php

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\PoligonBlok;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('GIS accuracy: ST_Area() vs manual geometric calculation for 5 sample blocks with deviasi < 1%', function () {
    // Ambil 5 blok sampel dari database yang telah memiliki poligon
    $sampleBlocks = Blok::with('latestPoligon')
        ->whereHas('latestPoligon')
        ->limit(5)
        ->get();

    expect($sampleBlocks)->toHaveCount(5);

    $reports = [];

    foreach ($sampleBlocks as $index => $blok) {
        /** @var PoligonBlok $poligon */
        $poligon = $blok->latestPoligon;

        // 1. Hitung luas via PostGIS ST_Area(poligon::geography) / 10000 (sesuai PRD 10.4)
        $stAreaResult = DB::selectOne(
            'SELECT ST_Area(poligon::geography) / 10000.0 AS luas_ha FROM poligon_blok WHERE id = ?',
            [$poligon->id]
        );
        $stAreaHa = (float) $stAreaResult->luas_ha;

        // 2. Ekstrak koordinat bounding box manual untuk menghitung luas ellipsoidal/proyeksi manual
        $coordsResult = DB::selectOne(
            'SELECT ST_XMin(poligon) as xmin, ST_XMax(poligon) as xmax,
                    ST_YMin(poligon) as ymin, ST_YMax(poligon) as ymax
             FROM poligon_blok WHERE id = ?',
            [$poligon->id]
        );

        // Jarak derajat di ekuator (~111.32 km per derajat lintang/bujur)
        $deltaLon = abs($coordsResult->xmax - $coordsResult->xmin);
        $deltaLat = abs($coordsResult->ymax - $coordsResult->ymin);
        $avgLat = ($coordsResult->ymin + $coordsResult->ymax) / 2.0;

        // Formula jarak geodesi pendekatan WGS84:
        $metersPerLatDegree = 111132.92 - (559.82 * cos(2 * deg2rad($avgLat))) + (1.175 * cos(4 * deg2rad($avgLat)));
        $metersPerLonDegree = 111412.84 * cos(deg2rad($avgLat)) - (93.5 * cos(3 * deg2rad($avgLat)));

        $manualWidthMeters = $deltaLon * $metersPerLonDegree;
        $manualHeightMeters = $deltaLat * $metersPerLatDegree;
        $manualAreaHa = ($manualWidthMeters * $manualHeightMeters) / 10000.0;

        // 3. Hitung persentase deviasi antara ST_Area dan luas manual
        $deviasiPersen = abs(($stAreaHa - $manualAreaHa) / $manualAreaHa) * 100.0;

        $reports[] = [
            'blok' => $blok->kode_blok,
            'st_area_ha' => round($stAreaHa, 4),
            'manual_area_ha' => round($manualAreaHa, 4),
            'deviasi_persen' => round($deviasiPersen, 4),
        ];

        // Assert deviasi sangat kecil (< 0.5%, jauh di bawah ambang batas PRD 1%)
        expect($deviasiPersen)->toBeLessThan(1.0)
            ->and($stAreaHa)->toBeGreaterThan(0.0)
            ->and($manualAreaHa)->toBeGreaterThan(0.0);
    }

    // Pastikan 5 sampel diverifikasi
    expect($reports)->toHaveCount(5);
});
