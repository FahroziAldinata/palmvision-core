<?php

namespace App\Services;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\PoligonBlok;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Shapefile\Shapefile;
use Shapefile\ShapefileReader;
use ZipArchive;

class GisImportService
{
    /**
     * Import a GeoJSON polygon string/array into a block.
     *
     * @param  string|array<string, mixed>  $geoJsonInput
     *
     * @throws ValidationException
     */
    public function importGeoJson(Blok $blok, string|array $geoJsonInput): PoligonBlok
    {
        $geometryJson = $this->extractGeometryJson($geoJsonInput);

        // 1. Validate that the geometry is valid PostGIS geometry
        $validityCheck = DB::selectOne(
            'SELECT ST_IsValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) AS is_valid,
                    ST_GeometryType(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) AS geom_type',
            [$geometryJson, $geometryJson]
        );

        if (! $validityCheck || ! $validityCheck->is_valid) {
            throw ValidationException::withMessages([
                'file' => 'Format poligon tidak valid atau geometri rusak menurut PostGIS.',
            ]);
        }

        // 2. Overlap detection (ST_Overlaps) against latest version of other blocks
        $overlappingBlocks = DB::select(
            'SELECT b.id, b.kode_blok
             FROM blok b
             JOIN (
                 SELECT DISTINCT ON (blok_id) blok_id, poligon
                 FROM poligon_blok
                 ORDER BY blok_id, versi DESC
             ) p ON b.id = p.blok_id
             WHERE b.id != ?
               AND b.deleted_at IS NULL
               AND ST_Overlaps(p.poligon, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))',
            [$blok->id, $geometryJson]
        );

        if (! empty($overlappingBlocks)) {
            $conflictingCodes = array_map(fn ($row) => (string) $row->kode_blok, $overlappingBlocks);
            $codesList = implode(', ', array_unique($conflictingCodes));

            throw ValidationException::withMessages([
                'file' => "Poligon tumpang tindih (overlap) terdeteksi dengan blok lain: {$codesList}.",
            ]);
        }

        // 3. Calculate area using ST_Area(poligon::geography) / 10000 in hectares
        $areaResult = DB::selectOne(
            'SELECT (ST_Area(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography) / 10000.0) AS luas_ha',
            [$geometryJson]
        );

        $calculatedLuasHa = $areaResult ? round((float) $areaResult->luas_ha, 2) : $blok->luas_ha;

        // 4. Versioning: increment version without overwriting past versions
        $currentMaxVersion = (int) PoligonBlok::where('blok_id', $blok->id)->max('versi');
        $newVersion = $currentMaxVersion + 1;
        $newId = (string) Str::uuid();
        $now = now();
        $today = Carbon::now()->toDateString();

        DB::statement(
            'INSERT INTO poligon_blok (id, blok_id, versi, diperbarui_pada, poligon, created_at, updated_at)
             VALUES (?, ?, ?, ?, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), ?, ?)',
            [$newId, $blok->id, $newVersion, $today, $geometryJson, $now, $now]
        );

        $poligonBlok = PoligonBlok::findOrFail($newId);

        // 5. Update blok.luas_ha
        $blok->update([
            'luas_ha' => $calculatedLuasHa,
        ]);

        return $poligonBlok;
    }

    /**
     * Import polygon from an uploaded Shapefile (.zip or .shp).
     *
     *
     * @throws ValidationException
     */
    public function importShapefile(Blok $blok, UploadedFile $file): PoligonBlok
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $tempDir = storage_path('app/temp/shp_'.Str::uuid());
        File::ensureDirectoryExists($tempDir);

        try {
            $shpPath = null;

            if ($extension === 'zip') {
                $zip = new ZipArchive;
                if ($zip->open($file->getRealPath()) !== true) {
                    throw ValidationException::withMessages([
                        'file' => 'Gagal membuka berkas ZIP Shapefile.',
                    ]);
                }

                $zip->extractTo($tempDir);
                $zip->close();

                // Find .shp file inside extracted directory
                $shpFiles = File::glob($tempDir.'/*.shp');
                if (empty($shpFiles)) {
                    // Search recursively in subfolders if zip had a nested folder
                    $allFiles = File::allFiles($tempDir);
                    foreach ($allFiles as $f) {
                        if (strtolower($f->getExtension()) === 'shp') {
                            $shpFiles[] = $f->getPathname();
                        }
                    }
                }

                if (empty($shpFiles)) {
                    throw ValidationException::withMessages([
                        'file' => 'Tidak ditemukan berkas .shp di dalam arsip ZIP yang diunggah.',
                    ]);
                }

                $shpPath = $shpFiles[0];
            } elseif ($extension === 'shp') {
                $targetFile = $tempDir.'/'.$file->getClientOriginalName();
                $file->move($tempDir, $file->getClientOriginalName());
                $shpPath = $targetFile;
            } else {
                throw ValidationException::withMessages([
                    'file' => 'Format berkas tidak didukung. Harap unggah berkas GeoJSON (.json, .geojson) atau Shapefile (.zip, .shp).',
                ]);
            }

            // Suppress PHP 8.4 deprecation warnings from third-party library
            $geoJson = @$this->readGeoJsonFromShapefile($shpPath);

            return $this->importGeoJson($blok, $geoJson);
        } finally {
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Read GeoJSON from a Shapefile path.
     *
     *
     * @throws ValidationException
     */
    protected function readGeoJsonFromShapefile(string $shpPath): string
    {
        try {
            $reader = new ShapefileReader($shpPath);

            foreach ($reader as $record) {
                if ($record->isDeleted()) {
                    continue;
                }

                $geoJson = $record->getGeoJSON();
                if (! empty($geoJson)) {
                    return $geoJson;
                }
            }

            throw ValidationException::withMessages([
                'file' => 'Berkas Shapefile tidak memuat data poligon yang valid.',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' => 'Gagal membaca berkas Shapefile: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Extract geometry GeoJSON string from input.
     *
     * @param  string|array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    protected function extractGeometryJson(string|array $input): string
    {
        $data = is_string($input) ? json_decode($input, true) : $input;

        if (! is_array($data)) {
            throw ValidationException::withMessages([
                'file' => 'Data GeoJSON tidak valid (gagal diuraikan).',
            ]);
        }

        // Check if it's FeatureCollection
        if (isset($data['type']) && $data['type'] === 'FeatureCollection' && ! empty($data['features'])) {
            $data = $data['features'][0];
        }

        // Check if it's Feature
        if (isset($data['type']) && $data['type'] === 'Feature' && isset($data['geometry'])) {
            $data = $data['geometry'];
        }

        // Must be Polygon or MultiPolygon
        if (! isset($data['type']) || ! in_array($data['type'], ['Polygon', 'MultiPolygon'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Geometri GeoJSON harus bertipe Polygon atau MultiPolygon.',
            ]);
        }

        $encoded = json_encode($data);
        if (! $encoded) {
            throw ValidationException::withMessages([
                'file' => 'Gagal mengonversi geometri GeoJSON ke format JSON.',
            ]);
        }

        return $encoded;
    }
}
