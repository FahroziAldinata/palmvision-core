<?php

namespace App\Http\Controllers\Web;

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Pemanen\Models\Pemanen;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PemanenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('viewAny', Pemanen::class);

        $query = Pemanen::query()->with('afdeling.kebun');

        if ($user->hasRole('asisten_afdeling') || $user->hasRole('mandor')) {
            $query->where('afdeling_id', $user->afdeling_id);
        } elseif ($user->hasRole('manajer_kebun')) {
            $query->whereHas('afdeling', function ($q) use ($user) {
                $q->where('kebun_id', $user->kebun_id);
            });
        }

        $pemanens = $query->orderBy('nama')->paginate(20)->withQueryString();

        $afdelings = [];
        if ($user->hasRole('asisten_afdeling')) {
            $afdelings = Afdeling::where('id', $user->afdeling_id)->get(['id', 'nama', 'kode']);
        } elseif ($user->hasRole('manajer_kebun')) {
            $afdelings = Afdeling::where('kebun_id', $user->kebun_id)->get(['id', 'nama', 'kode']);
        } elseif ($user->hasAnyRole(['admin_it', 'direksi'])) {
            $afdelings = Afdeling::orderBy('nama')->get(['id', 'nama', 'kode']);
        }

        return Inertia::render('Pemanen/Index', [
            'pemanens' => $pemanens,
            'afdelings' => $afdelings,
            'canManage' => $user->can('create', Pemanen::class),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('create', Pemanen::class);

        $validated = $request->validate([
            'afdeling_id' => ['required', 'uuid', 'exists:afdeling,id'],
            'nama' => ['required', 'string', 'max:255'],
            'kode_pemanen' => [
                'required',
                'string',
                'max:50',
                Rule::unique('pemanen', 'kode_pemanen')->where(function ($query) use ($request) {
                    return $query->where('afdeling_id', $request->input('afdeling_id'));
                }),
            ],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);

        if ($user->hasRole('asisten_afdeling') && $validated['afdeling_id'] !== $user->afdeling_id) {
            abort(403, 'Anda hanya dapat mendaftarkan pemanen di afdeling Anda.');
        }

        Pemanen::create($validated);

        return redirect()->route('pemanen.index')->with('success', 'Data pemanen berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Pemanen $pemanen): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $pemanen);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kode_pemanen' => [
                'required',
                'string',
                'max:50',
                Rule::unique('pemanen', 'kode_pemanen')
                    ->where('afdeling_id', $pemanen->afdeling_id)
                    ->ignore($pemanen->id),
            ],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);

        $pemanen->update($validated);

        return redirect()->route('pemanen.index')->with('success', 'Data pemanen berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pemanen $pemanen): RedirectResponse
    {
        Gate::authorize('delete', $pemanen);

        $pemanen->delete();

        return redirect()->route('pemanen.index')->with('success', 'Data pemanen berhasil dihapus.');
    }
}
