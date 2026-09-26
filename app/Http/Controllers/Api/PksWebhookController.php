<?php

namespace App\Http\Controllers\Api;

use App\Domain\Integrasi\Pks\Models\PksRendemen;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PksWebhookController extends Controller
{
    /**
     * Handle incoming weighbridge & CPO/PK rendemen data webhook from PKS.
     */
    public function handle(Request $request): JsonResponse
    {
        $expectedApiKey = config('services.pks.api_key');
        $webhookSecret = config('services.pks.webhook_secret');

        $providedApiKey = $request->header('X-API-Key');
        $providedSignature = $request->header('X-Signature-SHA256');

        // 1. Verify API Key
        if (! $providedApiKey || ! hash_equals((string) $expectedApiKey, (string) $providedApiKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: API Key tidak valid atau tidak disertakan.',
            ], 401);
        }

        // 2. Verify HMAC SHA-256 signature
        if (! $providedSignature) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: Header X-Signature-SHA256 tidak disertakan.',
            ], 403);
        }

        $rawContent = $request->getContent();
        $computedSignature = hash_hmac('sha256', $rawContent, (string) $webhookSecret);

        if (! hash_equals($computedSignature, (string) $providedSignature)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: Signature webhook HMAC SHA-256 tidak valid.',
            ], 403);
        }

        // 3. Validate Payload
        $validator = Validator::make($request->all(), [
            'no_tiket_timbangan' => 'required|string|max:100',
            'tanggal_terima' => 'required|date',
            'berat_tbs_terima_kg' => 'required|numeric|min:0',
            'rendemen_cpo_persen' => 'required|numeric|min:0|max:100',
            'rendemen_pk_persen' => 'required|numeric|min:0|max:100',
            'ffa_persen' => 'required|numeric|min:0|max:100',
            'kebun_id' => 'nullable|uuid|exists:kebun,id',
            'catatan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi payload webhook gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // 4. Save/Update record
        $record = PksRendemen::updateOrCreate(
            ['no_tiket_timbangan' => $validated['no_tiket_timbangan']],
            [
                'kebun_id' => $validated['kebun_id'] ?? null,
                'tanggal_terima' => $validated['tanggal_terima'],
                'berat_tbs_terima_kg' => $validated['berat_tbs_terima_kg'],
                'rendemen_cpo_persen' => $validated['rendemen_cpo_persen'],
                'rendemen_pk_persen' => $validated['rendemen_pk_persen'],
                'ffa_persen' => $validated['ffa_persen'],
                'catatan' => $validated['catatan'] ?? null,
                'payload_raw' => $request->all(),
            ]
        );

        Log::info('PKS Rendemen Webhook processed', [
            'no_tiket_timbangan' => $record->no_tiket_timbangan,
            'berat_kg' => $record->berat_tbs_terima_kg,
            'cpo_persen' => $record->rendemen_cpo_persen,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data rendemen PKS berhasil diproses.',
            'data' => [
                'id' => $record->id,
                'no_tiket_timbangan' => $record->no_tiket_timbangan,
                'berat_tbs_terima_kg' => (float) $record->berat_tbs_terima_kg,
                'rendemen_cpo_persen' => (float) $record->rendemen_cpo_persen,
                'rendemen_pk_persen' => (float) $record->rendemen_pk_persen,
                'ffa_persen' => (float) $record->ffa_persen,
            ],
        ], 200);
    }
}
