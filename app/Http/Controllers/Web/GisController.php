<?php

namespace App\Http\Controllers\Web;

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\Kebun;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GisImportService;
use App\Services\GisMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GisController extends Controller
{
    /**
     * Dedicated GIS Map Explorer page.
     */
    public function index(Request $request, GisMapService $gisMapService): Response
    {
        /** @var User $user */
        $user = $request->user();

        $kebunId = $request->query('kebun_id');
        $afdelingId = $request->query('afdeling_id');

        // Enforce role-based hierarchy scoping
        if ($user->hasRole('manajer_kebun') && $user->kebun_id) {
            $kebunId = $user->kebun_id;
        } elseif ($user->hasRole('asisten_afdeling') && $user->afdeling_id) {
            $afdelingId = $user->afdeling_id;
            $kebunId = $user->kebun_id;
        }

        $kebunQuery = Kebun::query();
        if ($user->hasRole('manajer_kebun') && $user->kebun_id) {
            $kebunQuery->where('id', $user->kebun_id);
        }
        $kebuns = $kebunQuery->select('id', 'kode_kebun', 'nama', 'siklus_rotasi_hari')->get();

        $afdelingQuery = Afdeling::query();
        if ($kebunId) {
            $afdelingQuery->where('kebun_id', $kebunId);
        }
        if ($user->hasRole('asisten_afdeling') && $user->afdeling_id) {
            $afdelingQuery->where('id', $user->afdeling_id);
        }
        $afdelings = $afdelingQuery->select('id', 'kebun_id', 'kode', 'nama')->get();

        $geoJson = $gisMapService->getGeoJsonFeatures($kebunId, $afdelingId);

        return Inertia::render('Gis/Index', [
            'kebuns' => $kebuns,
            'afdelings' => $afdelings,
            'selectedKebunId' => $kebunId,
            'selectedAfdelingId' => $afdelingId,
            'geoJson' => $geoJson,
            'canImport' => $user->hasAnyRole(['tim_gis', 'admin_it']),
        ]);
    }

    /**
     * Authenticated Vector Tile proxy (protected reverse proxy to pg_tileserv).
     */
    public function tiles(int|string $z, int|string $x, int|string $y, Request $request): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();

        $kebunId = $request->query('kebun_id');
        $afdelingId = $request->query('afdeling_id');

        // Apply hierarchical scoping
        if ($user->hasRole('manajer_kebun') && $user->kebun_id) {
            $kebunId = $user->kebun_id;
        } elseif ($user->hasRole('asisten_afdeling') && $user->afdeling_id) {
            $afdelingId = $user->afdeling_id;
        }

        $tileservBaseUrl = config('services.tileserv.url', 'http://tileserv:7800');
        $queryParams = [];
        if ($kebunId) {
            $queryParams['kebun_id'] = $kebunId;
        }
        if ($afdelingId) {
            $queryParams['afdeling_id'] = $afdelingId;
        }

        $tileUrl = "{$tileservBaseUrl}/public.mvt_poligon_blok/{$z}/{$x}/{$y}.pbf";
        if (! empty($queryParams)) {
            $tileUrl .= '?'.http_build_query($queryParams);
        }

        try {
            $tileResponse = Http::timeout(5)->get($tileUrl);

            return response($tileResponse->body(), $tileResponse->status(), [
                'Content-Type' => 'application/x-protobuf',
                'Cache-Control' => 'private, max-age=300',
            ]);
        } catch (\Throwable $e) {
            // Return empty MVT on backend connection error
            return response('', 204, [
                'Content-Type' => 'application/x-protobuf',
            ]);
        }
    }

    /**
     * Fetch comprehensive block details for modal / side-panel.
     */
    public function blockDetail(Blok $blok, GisMapService $gisMapService): JsonResponse
    {
        Gate::authorize('view', $blok);

        return response()->json($gisMapService->getBlockDetail($blok));
    }

    /**
     * Handle polygon upload (GeoJSON or Shapefile) for a block.
     *
     *
     * @throws ValidationException
     */
    public function importPoligon(Blok $blok, Request $request, GisImportService $gisImportService): RedirectResponse|JsonResponse
    {
        Gate::authorize('updatePoligon', $blok);

        $request->validate([
            'file' => 'nullable|file|max:20480', // max 20MB
            'geojson' => 'nullable',
        ]);

        if (! $request->hasFile('file') && ! $request->input('geojson')) {
            throw ValidationException::withMessages([
                'file' => 'Harap sertakan berkas (GeoJSON/Shapefile) atau payload teks GeoJSON.',
            ]);
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());

            if (in_array($ext, ['json', 'geojson'], true)) {
                $content = file_get_contents($file->getRealPath());
                if (! $content) {
                    throw ValidationException::withMessages(['file' => 'Gagal membaca isi berkas GeoJSON.']);
                }
                $poligon = $gisImportService->importGeoJson($blok, $content);
            } elseif (in_array($ext, ['zip', 'shp'], true)) {
                $poligon = $gisImportService->importShapefile($blok, $file);
            } else {
                throw ValidationException::withMessages([
                    'file' => 'Format berkas tidak didukung (.geojson, .json, .zip, .shp).',
                ]);
            }
        } else {
            $poligon = $gisImportService->importGeoJson($blok, $request->input('geojson'));
        }

        $message = "Poligon blok {$blok->kode_blok} berhasil diperbarui ke Versi {$poligon->versi} (Luas dihitung ulang: {$blok->fresh()->luas_ha} Ha).";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'poligon_id' => $poligon->id,
                'versi' => $poligon->versi,
                'luas_ha' => $blok->fresh()->luas_ha,
            ]);
        }

        return back()->with('success', $message);
    }
}
