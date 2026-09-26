<?php

namespace App\Http\Controllers\Api;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Pemanen\Models\Pemanen;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Produksi\Models\ProduksiHarianDetail;
use App\Domain\Taksasi\Models\Taksasi;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    /**
     * Bootstrap master data for mobile offline storage.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $bloksQuery = Blok::query()->orderBy('kode_blok');
        $pemanensQuery = Pemanen::query()->where('status', 'aktif')->orderBy('nama');

        if ($user->hasRole('mandor') || $user->hasRole('asisten_afdeling')) {
            if ($user->afdeling_id) {
                $bloksQuery->where('afdeling_id', $user->afdeling_id);
                $pemanensQuery->where('afdeling_id', $user->afdeling_id);
            }
        } elseif ($user->hasRole('manajer_kebun')) {
            if ($user->kebun_id) {
                $bloksQuery->whereHas('afdeling', fn ($q) => $q->where('kebun_id', $user->kebun_id));
                $pemanensQuery->whereHas('afdeling', fn ($q) => $q->where('kebun_id', $user->kebun_id));
            }
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'kebun_id' => $user->kebun_id,
                'afdeling_id' => $user->afdeling_id,
            ],
            'bloks' => $bloksQuery->get(['id', 'kode_blok', 'afdeling_id', 'luas_ha', 'jumlah_pokok']),
            'pemanens' => $pemanensQuery->get(['id', 'kode_pemanen', 'nama', 'afdeling_id', 'status']),
            'pupuk_options' => [
                'jenis' => ['Urea', 'NPK', 'MOP-KCl', 'Kieserit', 'Dolomit', 'Borate', 'Lainnya'],
                'cara_aplikasi' => ['piringan', 'benam', 'tabur', 'daun'],
            ],
        ]);
    }

    /**
     * Sync offline batch of daily harvest production records.
     */
    public function syncProduksi(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.client_uuid' => 'required|uuid',
            'items.*.blok_id' => 'required|uuid|exists:blok,id',
            'items.*.tanggal' => 'required|date',
            'items.*.catatan' => 'nullable|string',
            'items.*.device_time' => 'nullable|date',
            'items.*.details' => 'required|array|min:1',
            'items.*.details.*.pemanen_id' => 'required|uuid|exists:pemanen,id',
            'items.*.details.*.jumlah_janjang' => 'required|integer|min:0',
            'items.*.details.*.berat_kg' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi data sync produksi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $items = $request->input('items');
        $results = [];

        foreach ($items as $item) {
            $clientUuid = $item['client_uuid'];

            // 1. Idempotency check: already synced with this client_uuid
            $existingByUuid = ProduksiHarian::where('client_uuid', $clientUuid)->first();
            if ($existingByUuid) {
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'server_id' => $existingByUuid->id,
                    'status' => 'already_synced',
                    'perlu_tinjauan_waktu' => (bool) $existingByUuid->perlu_tinjauan_waktu,
                ];

                continue;
            }

            // 2. Check time deviation (> 24 jam)
            $perluTinjauanWaktu = false;
            $deviceTime = null;
            if (! empty($item['device_time'])) {
                try {
                    $deviceTime = Carbon::parse($item['device_time']);
                    if (abs(now()->diffInSeconds($deviceTime)) > 86400) {
                        $perluTinjauanWaktu = true;
                    }
                } catch (\Throwable) {
                    $perluTinjauanWaktu = true;
                }
            }

            // 3. Database transaction for insert or update
            DB::beginTransaction();
            try {
                // Check if existing record by unique constraint (blok_id, tanggal, dicatat_oleh)
                /** @var ProduksiHarian|null $produksi */
                $produksi = ProduksiHarian::where('blok_id', $item['blok_id'])
                    ->where('tanggal', $item['tanggal'])
                    ->where('dicatat_oleh', $user->id)
                    ->first();

                if ($produksi) {
                    if ($produksi->status_validasi === 'disetujui') {
                        DB::rollBack();
                        $results[] = [
                            'client_uuid' => $clientUuid,
                            'status' => 'rejected',
                            'message' => 'Entri produksi untuk tanggal dan blok ini sudah disetujui.',
                        ];

                        continue;
                    }

                    $produksi->update([
                        'catatan' => $item['catatan'] ?? $produksi->catatan,
                        'client_uuid' => $clientUuid,
                        'device_time' => $deviceTime,
                        'perlu_tinjauan_waktu' => $perluTinjauanWaktu,
                        'sumber' => 'mobile',
                    ]);

                    $produksi->details()->delete();
                } else {
                    $produksi = ProduksiHarian::create([
                        'blok_id' => $item['blok_id'],
                        'dicatat_oleh' => $user->id,
                        'tanggal' => $item['tanggal'],
                        'status_validasi' => 'menunggu',
                        'catatan' => $item['catatan'] ?? null,
                        'sumber' => 'mobile',
                        'client_uuid' => $clientUuid,
                        'device_time' => $deviceTime,
                        'perlu_tinjauan_waktu' => $perluTinjauanWaktu,
                    ]);
                }

                foreach ($item['details'] as $detail) {
                    ProduksiHarianDetail::create([
                        'produksi_harian_id' => $produksi->id,
                        'pemanen_id' => $detail['pemanen_id'],
                        'jumlah_janjang' => (int) $detail['jumlah_janjang'],
                        'berat_kg' => (float) $detail['berat_kg'],
                    ]);
                }

                DB::commit();

                $results[] = [
                    'client_uuid' => $clientUuid,
                    'server_id' => $produksi->id,
                    'status' => 'synced',
                    'perlu_tinjauan_waktu' => $perluTinjauanWaktu,
                ];
            } catch (\Throwable $e) {
                DB::rollBack();
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Sync produksi selesai.',
            'results' => $results,
        ]);
    }

    /**
     * Sync offline batch of crop estimation (taksasi) records.
     */
    public function syncTaksasi(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.client_uuid' => 'required|uuid',
            'items.*.blok_id' => 'required|uuid|exists:blok,id',
            'items.*.tanggal_taksasi' => 'required|date',
            'items.*.pokok_disampel' => 'required|integer|min:1',
            'items.*.estimasi_janjang' => 'required|integer|min:0',
            'items.*.estimasi_bjr' => 'required|numeric|min:0',
            'items.*.estimasi_total_kg' => 'nullable|numeric|min:0',
            'items.*.catatan' => 'nullable|string',
            'items.*.device_time' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi data sync taksasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $items = $request->input('items');
        $results = [];

        foreach ($items as $item) {
            $clientUuid = $item['client_uuid'];

            // 1. Idempotency check: already synced with this client_uuid
            $existing = Taksasi::where('client_uuid', $clientUuid)->first();
            if ($existing) {
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'server_id' => $existing->id,
                    'status' => 'already_synced',
                    'perlu_tinjauan_waktu' => (bool) $existing->perlu_tinjauan_waktu,
                ];

                continue;
            }

            // 2. Check time deviation (> 24 jam)
            $perluTinjauanWaktu = false;
            $deviceTime = null;
            if (! empty($item['device_time'])) {
                try {
                    $deviceTime = Carbon::parse($item['device_time']);
                    if (abs(now()->diffInSeconds($deviceTime)) > 86400) {
                        $perluTinjauanWaktu = true;
                    }
                } catch (\Throwable) {
                    $perluTinjauanWaktu = true;
                }
            }

            // Calculate total kg if missing
            $estimasiTotalKg = $item['estimasi_total_kg'] ?? ((int) $item['estimasi_janjang'] * (float) $item['estimasi_bjr']);

            try {
                $taksasi = Taksasi::create([
                    'blok_id' => $item['blok_id'],
                    'dicatat_oleh' => $user->id,
                    'tanggal_taksasi' => $item['tanggal_taksasi'],
                    'pokok_disampel' => (int) $item['pokok_disampel'],
                    'estimasi_janjang' => (int) $item['estimasi_janjang'],
                    'estimasi_bjr' => (float) $item['estimasi_bjr'],
                    'estimasi_total_kg' => (float) $estimasiTotalKg,
                    'catatan' => $item['catatan'] ?? null,
                    'sumber' => 'mobile',
                    'client_uuid' => $clientUuid,
                    'device_time' => $deviceTime,
                    'perlu_tinjauan_waktu' => $perluTinjauanWaktu,
                ]);

                $results[] = [
                    'client_uuid' => $clientUuid,
                    'server_id' => $taksasi->id,
                    'status' => 'synced',
                    'perlu_tinjauan_waktu' => $perluTinjauanWaktu,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Sync taksasi selesai.',
            'results' => $results,
        ]);
    }
}
