<?php

namespace App\Http\Controllers\Api;

use App\Domain\Forecasting\Models\ForecastResult;
use App\Domain\Organisasi\Models\Blok;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateForecastJob;
use App\Services\ForecastingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ForecastController extends Controller
{
    /**
     * Trigger job forecasting untuk satu blok atau satu kebun (PRD 9.2).
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blok_id' => ['nullable', 'uuid', 'exists:blok,id'],
            'kebun_id' => ['nullable', 'uuid', 'exists:kebun,id'],
            'horizon_bulan' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $blokId = $validated['blok_id'] ?? null;
        $kebunId = $validated['kebun_id'] ?? null;
        $horizonMonths = (int) ($validated['horizon_bulan'] ?? 3);

        if (! $blokId && ! $kebunId) {
            return response()->json([
                'message' => 'Parameter blok_id atau kebun_id wajib diisi.',
            ], 422);
        }

        /** @var array<int, string> $dispatchedBlokIds */
        $dispatchedBlokIds = [];

        if ($blokId) {
            GenerateForecastJob::dispatch($blokId, $horizonMonths);
            $dispatchedBlokIds[] = $blokId;
        } else {
            $bloks = Blok::whereHas('afdeling', fn ($q) => $q->where('kebun_id', $kebunId))->get();

            foreach ($bloks as $blok) {
                GenerateForecastJob::dispatch($blok->id, $horizonMonths);
                $dispatchedBlokIds[] = $blok->id;
            }
        }

        return response()->json([
            'message' => 'Job generate forecast berhasil dijadwalkan ke antrean Horizon.',
            'total_blok' => count($dispatchedBlokIds),
            'blok_ids' => $dispatchedBlokIds,
            'horizon_bulan' => $horizonMonths,
            'status' => 'queued',
        ], 202);
    }

    /**
     * Ambil hasil forecast tersimpan untuk satu blok (PRD 9.2 & 9.3).
     */
    public function show(string $blok_id, ForecastingService $forecastingService): JsonResponse
    {
        if (! Str::isUuid($blok_id)) {
            return response()->json([
                'message' => 'ID blok tidak valid (harus UUID).',
            ], 404);
        }

        $blok = Blok::find($blok_id);
        if (! $blok) {
            return response()->json([
                'message' => 'Blok tidak ditemukan.',
            ], 404);
        }

        $results = $forecastingService->getLatestForecast($blok);

        if ($results->isEmpty()) {
            return response()->json([
                'blok_id' => $blok->id,
                'proyeksi' => [],
                'mape_model' => null,
                'versi_model' => null,
                'message' => 'Belum ada hasil forecast tersimpan untuk blok ini.',
            ], 200);
        }

        $first = $results->first();

        $proyeksi = $results->map(function (ForecastResult $item) {
            return [
                'periode' => Carbon::parse($item->periode)->format('Y-m'),
                'nilai_kg' => (float) $item->nilai_kg,
                'interval_bawah' => (float) $item->interval_bawah,
                'interval_atas' => (float) $item->interval_atas,
            ];
        })->values()->all();

        return response()->json([
            'blok_id' => $blok->id,
            'proyeksi' => $proyeksi,
            'mape_model' => (float) $first->mape_model,
            'versi_model' => (string) $first->versi_model,
        ], 200);
    }
}
