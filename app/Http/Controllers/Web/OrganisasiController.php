<?php

namespace App\Http\Controllers\Web;

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\Kebun;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrganisasiController extends Controller
{
    /**
     * Display the specified kebun.
     */
    public function showKebun(Kebun $kebun): JsonResponse
    {
        Gate::authorize('view', $kebun);

        return response()->json([
            'status' => 'ok',
            'data' => $kebun->load('afdelings'),
        ]);
    }

    /**
     * Display the specified afdeling.
     */
    public function showAfdeling(Afdeling $afdeling): JsonResponse
    {
        Gate::authorize('view', $afdeling);

        return response()->json([
            'status' => 'ok',
            'data' => $afdeling->load('bloks'),
        ]);
    }

    /**
     * Display the specified blok.
     */
    public function showBlok(Blok $blok): JsonResponse
    {
        Gate::authorize('view', $blok);

        return response()->json([
            'status' => 'ok',
            'data' => $blok->load(['afdeling', 'latestPoligon']),
        ]);
    }
}
