<?php

namespace App\Http\Controllers\Web;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Pemupukan\Models\Pemupukan;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PemupukanController extends Controller
{
    /**
     * Display a listing of fertilizer application logs.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $query = Pemupukan::query()
            ->with(['blok.afdeling.kebun', 'pencatat'])
            ->latest('tanggal_aplikasi');

        if ($user->hasRole('mandor') || $user->hasRole('asisten_afdeling')) {
            if ($user->afdeling_id) {
                $query->whereHas('blok', fn ($q) => $q->where('afdeling_id', $user->afdeling_id));
            }
        } elseif ($user->hasRole('manajer_kebun')) {
            if ($user->kebun_id) {
                $query->whereHas('blok.afdeling', fn ($q) => $q->where('kebun_id', $user->kebun_id));
            }
        }

        $pemupukans = $query->paginate(15)->withQueryString();

        $bloksQuery = Blok::query()->orderBy('kode_blok');
        if ($user->hasRole('mandor') || $user->hasRole('asisten_afdeling')) {
            if ($user->afdeling_id) {
                $bloksQuery->where('afdeling_id', $user->afdeling_id);
            }
        } elseif ($user->hasRole('manajer_kebun')) {
            if ($user->kebun_id) {
                $bloksQuery->whereHas('afdeling', fn ($q) => $q->where('kebun_id', $user->kebun_id));
            }
        }
        $bloks = $bloksQuery->get(['id', 'kode_blok', 'jumlah_pokok']);

        return Inertia::render('Pemupukan/Index', [
            'pemupukans' => $pemupukans,
            'bloks' => $bloks,
            'jenisPupukOptions' => ['Urea', 'NPK', 'MOP-KCl', 'Kieserit', 'Dolomit', 'Borate', 'Lainnya'],
            'caraAplikasiOptions' => ['piringan', 'benam', 'tabur', 'daun'],
        ]);
    }

    /**
     * Store a newly created fertilizer application record.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'blok_id' => 'required|uuid|exists:blok,id',
            'tanggal_aplikasi' => 'required|date',
            'jenis_pupuk' => 'required|string|max:50',
            'dosis_kg_per_pokok' => 'required|numeric|min:0.01',
            'jumlah_pokok_dipupuk' => 'required|integer|min:1',
            'cara_aplikasi' => 'required|string|max:50',
            'catatan' => 'nullable|string',
        ]);

        $totalKg = round((float) $validated['dosis_kg_per_pokok'] * (int) $validated['jumlah_pokok_dipupuk'], 2);

        Pemupukan::create([
            'blok_id' => $validated['blok_id'],
            'dicatat_oleh' => $request->user()->id,
            'tanggal_aplikasi' => $validated['tanggal_aplikasi'],
            'jenis_pupuk' => $validated['jenis_pupuk'],
            'dosis_kg_per_pokok' => $validated['dosis_kg_per_pokok'],
            'jumlah_pokok_dipupuk' => $validated['jumlah_pokok_dipupuk'],
            'total_kg_terpakai' => $totalKg,
            'cara_aplikasi' => $validated['cara_aplikasi'],
            'catatan' => $validated['catatan'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Data aplikasi pemupukan berhasil dicatat.');
    }
}
