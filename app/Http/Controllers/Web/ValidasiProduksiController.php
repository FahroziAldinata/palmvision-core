<?php

namespace App\Http\Controllers\Web;

use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Produksi\Models\ProduksiHarianDetail;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ValidasiProduksiController extends Controller
{
    /**
     * Display a listing of production records waiting for validation.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasAnyRole(['asisten_afdeling', 'manajer_kebun'])) {
            abort(403, 'Hanya asisten afdeling dan manajer kebun yang berwenang melakukan validasi.');
        }

        $query = ProduksiHarian::query()
            ->with(['blok.afdeling.kebun', 'pencatat', 'details.pemanen'])
            ->latest('tanggal');

        if ($user->hasRole('asisten_afdeling')) {
            $query->whereHas('blok', fn ($q) => $q->where('afdeling_id', $user->afdeling_id));
        } elseif ($user->hasRole('manajer_kebun')) {
            $query->whereHas('blok.afdeling', fn ($q) => $q->where('kebun_id', $user->kebun_id));
        }

        $tab = (string) $request->query('tab', 'menunggu');

        if ($tab === 'disetujui') {
            $query->where('status_validasi', 'disetujui');
        } else {
            $query->where('status_validasi', 'menunggu');
        }

        $produksis = $query->paginate(15)->withQueryString();

        // Ambil riwayat audit trail koreksi terkini
        $auditLogs = Activity::query()
            ->with('causer')
            ->whereIn('log_name', ['persetujuan_produksi', 'koreksi_produksi', 'default'])
            ->latest()
            ->limit(15)
            ->get()
            ->map(function (Activity $act) {
                $causerName = 'Sistem';
                if ($act->causer instanceof User) {
                    $causerName = $act->causer->name;
                }

                return [
                    'id' => $act->id,
                    'description' => $act->description,
                    'causer_name' => $causerName,
                    'created_at' => $act->created_at ? $act->created_at->toDateTimeString() : null,
                    'properties' => $act->properties,
                ];
            });

        return Inertia::render('Produksi/Validasi', [
            'produksis' => $produksis,
            'currentTab' => $tab,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * Approve production record directly without correction.
     */
    public function approve(Request $request, ProduksiHarian $produksi): RedirectResponse
    {
        Gate::authorize('validateRecord', $produksi);

        $produksi->update([
            'status_validasi' => 'disetujui',
        ]);

        activity('persetujuan_produksi')
            ->performedOn($produksi)
            ->causedBy($request->user())
            ->log('Menyetujui rekaman produksi');

        $kodeBlok = $produksi->blok ? $produksi->blok->kode_blok : '';

        return back()->with('success', "Catatan produksi Blok {$kodeBlok} berhasil disetujui.");
    }

    /**
     * Correct numbers and approve with audit trail reason.
     */
    public function koreksi(Request $request, ProduksiHarian $produksi): RedirectResponse
    {
        Gate::authorize('validateRecord', $produksi);

        $validated = $request->validate([
            'alasan_koreksi' => ['required', 'string', 'min:5', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'uuid', 'exists:produksi_harian_detail,id'],
            'items.*.jumlah_janjang' => ['required', 'integer', 'min:1'],
            'items.*.berat_kg' => ['required', 'numeric', 'min:0.1'],
        ], [
            'alasan_koreksi.required' => 'Alasan koreksi angka panen wajib dicatat untuk audit trail.',
            'alasan_koreksi.min' => 'Alasan koreksi minimal 5 karakter.',
        ]);

        $oldTotalJanjang = $produksi->total_janjang;
        $oldTotalBerat = $produksi->total_berat_kg;

        DB::transaction(function () use ($request, $produksi, $validated, $oldTotalJanjang, $oldTotalBerat) {
            $changedDetails = [];

            foreach ($validated['items'] as $item) {
                /** @var ProduksiHarianDetail|null $detail */
                $detail = ProduksiHarianDetail::find($item['id']);
                if ($detail && $detail->produksi_harian_id === $produksi->id) {
                    $oldJanjang = $detail->jumlah_janjang;
                    $oldBerat = (float) $detail->berat_kg;

                    $newJanjang = (int) $item['jumlah_janjang'];
                    $newBerat = (float) $item['berat_kg'];

                    if ($oldJanjang !== $newJanjang || abs($oldBerat - $newBerat) > 0.001) {
                        $changedDetails[] = [
                            'pemanen_id' => $detail->pemanen_id,
                            'pemanen_nama' => $detail->pemanen->nama,
                            'old' => ['jumlah_janjang' => $oldJanjang, 'berat_kg' => $oldBerat],
                            'new' => ['jumlah_janjang' => $newJanjang, 'berat_kg' => $newBerat],
                        ];

                        $detail->update([
                            'jumlah_janjang' => $newJanjang,
                            'berat_kg' => $newBerat,
                        ]);
                    }
                }
            }

            $catatanTambahan = "[Koreksi Asisten]: {$validated['alasan_koreksi']}";
            $catatanBaru = $produksi->catatan
                ? $produksi->catatan."\n".$catatanTambahan
                : $catatanTambahan;

            $produksi->update([
                'status_validasi' => 'disetujui',
                'catatan' => $catatanBaru,
            ]);

            $newTotalJanjang = $produksi->fresh()->total_janjang;
            $newTotalBerat = $produksi->fresh()->total_berat_kg;

            // Catat audit trail eksplisit di activity log
            activity('koreksi_produksi')
                ->performedOn($produksi)
                ->causedBy($request->user())
                ->withProperties([
                    'alasan' => $validated['alasan_koreksi'],
                    'old' => [
                        'total_janjang' => $oldTotalJanjang,
                        'total_berat_kg' => $oldTotalBerat,
                    ],
                    'new' => [
                        'total_janjang' => $newTotalJanjang,
                        'total_berat_kg' => $newTotalBerat,
                    ],
                    'detail_changes' => $changedDetails,
                ])
                ->log("Koreksi angka panen oleh Asisten: {$validated['alasan_koreksi']}");
        });

        $kodeBlok = $produksi->blok ? $produksi->blok->kode_blok : '';

        return back()->with('success', "Catatan produksi Blok {$kodeBlok} berhasil dikoreksi dan disetujui.");
    }
}
