<?php

namespace App\Http\Controllers\Web;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Pemanen\Models\Pemanen;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Produksi\Models\ProduksiHarianDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProduksiHarianRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProduksiHarianController extends Controller
{
    /**
     * Display a listing of daily production records.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('viewAny', ProduksiHarian::class);

        $query = ProduksiHarian::query()
            ->with(['blok.afdeling.kebun', 'pencatat', 'details.pemanen'])
            ->latest('tanggal');

        if ($user->hasRole('mandor')) {
            $query->where('dicatat_oleh', $user->id);
        } elseif ($user->hasRole('asisten_afdeling')) {
            $query->whereHas('blok', function ($q) use ($user) {
                $q->where('afdeling_id', $user->afdeling_id);
            });
        } elseif ($user->hasRole('manajer_kebun')) {
            $query->whereHas('blok.afdeling', function ($q) use ($user) {
                $q->where('kebun_id', $user->kebun_id);
            });
        }

        $produksiList = $query->paginate(15)->withQueryString();

        return Inertia::render('Produksi/Index', [
            'produksiList' => $produksiList,
            'canCreate' => $user->can('create', ProduksiHarian::class),
        ]);
    }

    /**
     * Show the form for recording daily production.
     */
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('create', ProduksiHarian::class);

        $bloks = Blok::query()
            ->when($user->hasRole('mandor'), function ($q) use ($user) {
                $q->where('afdeling_id', $user->afdeling_id);
            })
            ->orderBy('kode_blok')
            ->get(['id', 'kode_blok', 'luas_ha', 'jumlah_pokok', 'afdeling_id']);

        $pemanens = Pemanen::query()
            ->when($user->hasRole('mandor'), function ($q) use ($user) {
                $q->where('afdeling_id', $user->afdeling_id);
            })
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode_pemanen']);

        return Inertia::render('Produksi/Create', [
            'bloks' => $bloks,
            'pemanens' => $pemanens,
        ]);
    }

    /**
     * Check if a daily production record already exists for the given block and date.
     */
    public function checkExisting(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $blokId = (string) $request->query('blok_id');
        $tanggal = (string) $request->query('tanggal');

        if (! $blokId || ! $tanggal) {
            return response()->json(['exists' => false]);
        }

        $existing = ProduksiHarian::query()
            ->with('details.pemanen')
            ->where('blok_id', $blokId)
            ->where('tanggal', $tanggal)
            ->where('dicatat_oleh', $user->id)
            ->first();

        if (! $existing) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'id' => $existing->id,
            'status_validasi' => $existing->status_validasi,
            'catatan' => $existing->catatan,
            'items' => $existing->details->map(fn (ProduksiHarianDetail $d) => [
                'pemanen_id' => $d->pemanen_id,
                'nama' => $d->pemanen->nama,
                'kode_pemanen' => $d->pemanen->kode_pemanen,
                'jumlah_janjang' => $d->jumlah_janjang,
                'berat_kg' => (float) $d->berat_kg,
            ]),
        ]);
    }

    /**
     * Store or update a daily production record with harvester breakdown.
     */
    public function store(StoreProduksiHarianRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated) {
            /** @var ProduksiHarian|null $produksi */
            $produksi = ProduksiHarian::where('blok_id', $validated['blok_id'])
                ->where('tanggal', $validated['tanggal'])
                ->where('dicatat_oleh', $user->id)
                ->first();

            if ($produksi) {
                if ($produksi->status_validasi === 'disetujui') {
                    abort(403, 'Entri produksi untuk blok dan tanggal ini sudah tervalidasi dan tidak dapat diubah lagi.');
                }

                $produksi->update([
                    'catatan' => $validated['catatan'] ?? null,
                ]);

                // Hapus detail lama untuk digantikan dengan entri baru
                $produksi->details()->delete();
            } else {
                $produksi = ProduksiHarian::create([
                    'blok_id' => $validated['blok_id'],
                    'dicatat_oleh' => $user->id,
                    'tanggal' => $validated['tanggal'],
                    'status_validasi' => 'menunggu',
                    'catatan' => $validated['catatan'] ?? null,
                    'sumber' => 'web',
                ]);
            }

            foreach ($validated['items'] as $item) {
                ProduksiHarianDetail::create([
                    'produksi_harian_id' => $produksi->id,
                    'pemanen_id' => $item['pemanen_id'],
                    'jumlah_janjang' => (int) $item['jumlah_janjang'],
                    'berat_kg' => (float) $item['berat_kg'],
                ]);
            }
        });

        return redirect()->route('produksi.index')->with('success', 'Data produksi harian panen berhasil disimpan.');
    }
}
