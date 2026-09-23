<?php

namespace App\Http\Controllers\Web;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Taksasi\Models\Taksasi;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaksasiRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaksasiController extends Controller
{
    /**
     * Display a listing of crop estimations.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('viewAny', Taksasi::class);

        $query = Taksasi::query()
            ->with(['blok.afdeling.kebun', 'pencatat'])
            ->latest('tanggal_taksasi');

        if ($user->hasRole('kerani_taksasi') || $user->hasRole('asisten_afdeling')) {
            $query->whereHas('blok', fn ($q) => $q->where('afdeling_id', $user->afdeling_id));
        } elseif ($user->hasRole('manajer_kebun')) {
            $query->whereHas('blok.afdeling', fn ($q) => $q->where('kebun_id', $user->kebun_id));
        }

        $taksasis = $query->paginate(15)->withQueryString();

        return Inertia::render('Taksasi/Index', [
            'taksasis' => $taksasis,
            'canCreate' => $user->can('create', Taksasi::class),
        ]);
    }

    /**
     * Show the form for creating a new crop estimation.
     */
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('create', Taksasi::class);

        $bloks = Blok::query()
            ->when($user->hasRole('kerani_taksasi') || $user->hasRole('asisten_afdeling'), function ($q) use ($user) {
                $q->where('afdeling_id', $user->afdeling_id);
            })
            ->orderBy('kode_blok')
            ->get(['id', 'kode_blok', 'luas_ha', 'jumlah_pokok', 'afdeling_id']);

        return Inertia::render('Taksasi/Create', [
            'bloks' => $bloks,
        ]);
    }

    /**
     * Store a newly created crop estimation record.
     */
    public function store(StoreTaksasiRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validated();

        $blok = Blok::findOrFail($validated['blok_id']);

        // Formula agronomi terkunci PALMVISION Tahap 2:
        // janjang_per_pokok = estimasi_janjang ÷ pokok_disampel
        // estimasi_total_kg = janjang_per_pokok × blok.jumlah_pokok × estimasi_bjr
        $janjangPerPokok = (float) $validated['estimasi_janjang'] / (int) $validated['pokok_disampel'];
        $estimasiTotalKg = $janjangPerPokok * (int) $blok->jumlah_pokok * (float) $validated['estimasi_bjr'];

        Taksasi::create([
            'blok_id' => $blok->id,
            'dicatat_oleh' => $user->id,
            'tanggal_taksasi' => $validated['tanggal_taksasi'],
            'pokok_disampel' => (int) $validated['pokok_disampel'],
            'estimasi_janjang' => (int) $validated['estimasi_janjang'],
            'estimasi_bjr' => (float) $validated['estimasi_bjr'],
            'estimasi_total_kg' => round($estimasiTotalKg, 2),
            'catatan' => $validated['catatan'] ?? null,
        ]);

        return redirect()->route('taksasi.index')->with('success', "Taksasi Blok {$blok->kode_blok} berhasil dicatat.");
    }

    /**
     * Display accuracy analysis comparing taksasi vs realisasi panen.
     */
    public function akurasi(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('viewAny', Taksasi::class);

        $bulan = (int) $request->query('bulan', now()->month);
        $tahun = (int) $request->query('tahun', now()->year);

        $bloksQuery = Blok::query()->with('afdeling.kebun');

        if ($user->hasRole('kerani_taksasi') || $user->hasRole('asisten_afdeling')) {
            $bloksQuery->where('afdeling_id', $user->afdeling_id);
        } elseif ($user->hasRole('manajer_kebun')) {
            $bloksQuery->whereHas('afdeling', fn ($q) => $q->where('kebun_id', $user->kebun_id));
        }

        $bloks = $bloksQuery->orderBy('kode_blok')->get();

        $rows = $bloks->map(function (Blok $b) use ($bulan, $tahun) {
            $taksasiKg = (float) Taksasi::where('blok_id', $b->id)
                ->whereMonth('tanggal_taksasi', $bulan)
                ->whereYear('tanggal_taksasi', $tahun)
                ->sum('estimasi_total_kg');

            // Ambil realisasi panen dari relasi detail
            $realisasiKg = (float) ProduksiHarian::where('blok_id', $b->id)
                ->whereMonth('tanggal', $bulan)
                ->whereYear('tanggal', $tahun)
                ->where('status_validasi', 'disetujui')
                ->with('details')
                ->get()
                ->sum(fn (ProduksiHarian $p) => $p->total_berat_kg);

            $selisihKg = $realisasiKg - $taksasiKg;
            $persentaseDeviasi = $taksasiKg > 0 ? (($realisasiKg - $taksasiKg) / $taksasiKg) * 100 : 0.0;
            $isHighDeviation = abs($persentaseDeviasi) > 15;

            return [
                'blok_id' => $b->id,
                'kode_blok' => $b->kode_blok,
                'afdeling' => $b->afdeling ? $b->afdeling->nama : '-',
                'kebun' => ($b->afdeling && $b->afdeling->kebun) ? $b->afdeling->kebun->nama : '-',
                'taksasi_kg' => round($taksasiKg, 1),
                'realisasi_kg' => round($realisasiKg, 1),
                'selisih_kg' => round($selisihKg, 1),
                'persentase_deviasi' => round($persentaseDeviasi, 1),
                'is_high_deviation' => $isHighDeviation,
            ];
        });

        return Inertia::render('Taksasi/Akurasi', [
            'rows' => $rows,
            'bulan' => $bulan,
            'tahun' => $tahun,
        ]);
    }
}
