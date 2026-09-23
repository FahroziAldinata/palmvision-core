<?php

namespace App\Http\Controllers\Web;

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\Kebun;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Produksi\Models\ProduksiHarianDetail;
use App\Domain\Taksasi\Models\Taksasi;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole('direksi')) {
            return $this->direksiDashboard();
        }

        if ($user->hasRole('manajer_kebun')) {
            return $this->manajerKebunDashboard($user);
        }

        if ($user->hasRole('asisten_afdeling')) {
            return $this->asistenAfdelingDashboard($user);
        }

        if ($user->hasRole('admin_it')) {
            return $this->adminItDashboard();
        }

        return $this->defaultDashboard($user);
    }

    /**
     * Dashboard view for Direksi (cross-plantation enterprise overview).
     */
    protected function direksiDashboard(): Response
    {
        $kebuns = Kebun::query()
            ->withCount(['afdelings', 'bloks'])
            ->get()
            ->map(function (Kebun $kebun) {
                $totalLuas = Blok::whereHas('afdeling', fn ($q) => $q->where('kebun_id', $kebun->id))->sum('luas_ha');

                return [
                    'id' => $kebun->id,
                    'kode_kebun' => $kebun->kode_kebun,
                    'nama' => $kebun->nama,
                    'total_afdeling' => $kebun->afdelings_count,
                    'total_blok' => $kebun->bloks_count,
                    'total_luas_ha' => round((float) $totalLuas, 2),
                ];
            });

        $geoJson = $this->buildGeoJson();

        $currentMonth = now()->month;
        $currentYear = now()->year;

        $totalProduksi = (float) ProduksiHarianDetail::whereHas('produksiHarian', function ($q) use ($currentMonth, $currentYear) {
            $q->where('status_validasi', 'disetujui')
                ->whereMonth('tanggal', $currentMonth)
                ->whereYear('tanggal', $currentYear);
        })->sum('berat_kg');

        $totalTaksasi = (float) Taksasi::whereMonth('tanggal_taksasi', $currentMonth)
            ->whereYear('tanggal_taksasi', $currentYear)
            ->sum('estimasi_total_kg');

        return Inertia::render('Dashboard', [
            'role' => 'direksi',
            'title' => 'Dashboard Eksekutif Direksi — Seluruh Kebun',
            'summary' => [
                'total_kebun' => $kebuns->count(),
                'total_afdeling' => $kebuns->sum('total_afdeling'),
                'total_blok' => $kebuns->sum('total_blok'),
                'total_luas_ha' => round((float) $kebuns->sum('total_luas_ha'), 2),
                'total_produksi_kg' => round($totalProduksi, 1),
                'total_taksasi_kg' => round($totalTaksasi, 1),
            ],
            'kebuns' => $kebuns,
            'geoJson' => $geoJson,
        ]);
    }

    /**
     * Dashboard view for Manajer Kebun (plantation-scoped overview).
     */
    protected function manajerKebunDashboard(User $user): Response
    {
        $kebun = $user->kebun_id ? Kebun::find($user->kebun_id) : null;

        $afdelings = $kebun
            ? Afdeling::where('kebun_id', $kebun->id)
                ->withCount('bloks')
                ->get()
                ->map(function (Afdeling $afdeling) {
                    $totalLuas = Blok::where('afdeling_id', $afdeling->id)->sum('luas_ha');

                    return [
                        'id' => $afdeling->id,
                        'kode' => $afdeling->kode,
                        'nama' => $afdeling->nama,
                        'total_blok' => $afdeling->bloks_count,
                        'total_luas_ha' => round((float) $totalLuas, 2),
                    ];
                })
            : collect();

        $geoJson = $kebun ? $this->buildGeoJson(kebunId: $kebun->id) : ['type' => 'FeatureCollection', 'features' => []];

        $currentMonth = now()->month;
        $currentYear = now()->year;

        $totalProduksi = $kebun ? (float) ProduksiHarianDetail::whereHas('produksiHarian.blok.afdeling', function ($q) use ($kebun, $currentMonth, $currentYear) {
            $q->where('kebun_id', $kebun->id)
                ->where('status_validasi', 'disetujui')
                ->whereMonth('tanggal', $currentMonth)
                ->whereYear('tanggal', $currentYear);
        })->sum('berat_kg') : 0.0;

        $totalTaksasi = $kebun ? (float) Taksasi::whereHas('blok.afdeling', function ($q) use ($kebun, $currentMonth, $currentYear) {
            $q->where('kebun_id', $kebun->id)
                ->whereMonth('tanggal_taksasi', $currentMonth)
                ->whereYear('tanggal_taksasi', $currentYear);
        })->sum('estimasi_total_kg') : 0.0;

        $menungguValidasi = $kebun ? ProduksiHarian::whereHas('blok.afdeling', function ($q) use ($kebun) {
            $q->where('kebun_id', $kebun->id)
                ->where('status_validasi', 'menunggu');
        })->count() : 0;

        return Inertia::render('Dashboard', [
            'role' => 'manajer_kebun',
            'title' => 'Dashboard Operasional — '.($kebun ? $kebun->nama : 'Kebun Belum Ditugaskan'),
            'kebun' => $kebun ? [
                'id' => $kebun->id,
                'kode_kebun' => $kebun->kode_kebun,
                'nama' => $kebun->nama,
            ] : null,
            'summary' => [
                'total_afdeling' => $afdelings->count(),
                'total_blok' => $afdelings->sum('total_blok'),
                'total_luas_ha' => round((float) $afdelings->sum('total_luas_ha'), 2),
                'total_produksi_kg' => round($totalProduksi, 1),
                'total_taksasi_kg' => round($totalTaksasi, 1),
                'menunggu_validasi' => $menungguValidasi,
            ],
            'afdelings' => $afdelings,
            'geoJson' => $geoJson,
        ]);
    }

    /**
     * Dashboard view for Asisten Afdeling (division-scoped overview).
     */
    protected function asistenAfdelingDashboard(User $user): Response
    {
        $afdeling = $user->afdeling_id ? Afdeling::with('kebun')->find($user->afdeling_id) : null;

        $bloks = $afdeling
            ? Blok::where('afdeling_id', $afdeling->id)
                ->orderBy('kode_blok')
                ->get()
                ->map(function (Blok $blok) {
                    $tanggalTanam = $blok->tanggal_tanam instanceof \DateTimeInterface
                        ? $blok->tanggal_tanam->format('Y-m-d')
                        : ($blok->tanggal_tanam ? (string) $blok->tanggal_tanam : null);

                    return [
                        'id' => $blok->id,
                        'kode_blok' => $blok->kode_blok,
                        'luas_ha' => (float) $blok->luas_ha,
                        'tanggal_tanam' => $tanggalTanam,
                        'jumlah_pokok' => $blok->jumlah_pokok,
                        'kategori_tanah' => $blok->kategori_tanah,
                    ];
                })
            : collect();

        $geoJson = $afdeling ? $this->buildGeoJson(afdelingId: $afdeling->id) : ['type' => 'FeatureCollection', 'features' => []];

        $currentMonth = now()->month;
        $currentYear = now()->year;

        $totalProduksi = $afdeling ? (float) ProduksiHarianDetail::whereHas('produksiHarian.blok', function ($q) use ($afdeling, $currentMonth, $currentYear) {
            $q->where('afdeling_id', $afdeling->id)
                ->where('status_validasi', 'disetujui')
                ->whereMonth('tanggal', $currentMonth)
                ->whereYear('tanggal', $currentYear);
        })->sum('berat_kg') : 0.0;

        $totalTaksasi = $afdeling ? (float) Taksasi::whereHas('blok', function ($q) use ($afdeling, $currentMonth, $currentYear) {
            $q->where('afdeling_id', $afdeling->id)
                ->whereMonth('tanggal_taksasi', $currentMonth)
                ->whereYear('tanggal_taksasi', $currentYear);
        })->sum('estimasi_total_kg') : 0.0;

        $menungguValidasi = $afdeling ? ProduksiHarian::whereHas('blok', function ($q) use ($afdeling) {
            $q->where('afdeling_id', $afdeling->id)
                ->where('status_validasi', 'menunggu');
        })->count() : 0;

        return Inertia::render('Dashboard', [
            'role' => 'asisten_afdeling',
            'title' => 'Dashboard Lapangan — '.($afdeling ? $afdeling->nama.' ('.$afdeling->kebun?->nama.')' : 'Afdeling Belum Ditugaskan'),
            'afdeling' => $afdeling ? [
                'id' => $afdeling->id,
                'kode' => $afdeling->kode,
                'nama' => $afdeling->nama,
                'kebun_nama' => $afdeling->kebun?->nama,
            ] : null,
            'summary' => [
                'total_blok' => $bloks->count(),
                'total_luas_ha' => round((float) $bloks->sum('luas_ha'), 2),
                'total_pokok' => $bloks->sum('jumlah_pokok'),
                'total_produksi_kg' => round($totalProduksi, 1),
                'total_taksasi_kg' => round($totalTaksasi, 1),
                'menunggu_validasi' => $menungguValidasi,
            ],
            'bloks' => $bloks,
            'geoJson' => $geoJson,
        ]);
    }

    /**
     * Dashboard view for Admin IT (system administration placeholder).
     */
    protected function adminItDashboard(): Response
    {
        return Inertia::render('Dashboard', [
            'role' => 'admin_it',
            'title' => 'Portal Administrator Sistem (IT)',
            'message' => 'Anda masuk sebagai Admin IT. Hak akses Anda difokuskan pada pemeliharaan infrastruktur, pengelolaan akun pengguna, dan konfigurasi sistem. Sesuai prinsip separation of concerns, data operasional bisnis dan perkebunan tidak ditampilkan di dashboard ini.',
            'summary' => [
                'status_sistem' => 'Optimal',
                'modul' => 'Manajemen Pengguna & Konfigurasi Teknis',
            ],
            'geoJson' => null,
        ]);
    }

    /**
     * Fallback dashboard for other roles.
     */
    protected function defaultDashboard(User $user): Response
    {
        $roleName = $user->getRoleNames()->first() ?? 'Pengguna';

        $summary = [];
        if ($user->hasRole('mandor')) {
            $today = now()->toDateString();
            $summary = [
                'total_panen_hari_ini_kg' => (float) ProduksiHarianDetail::whereHas('produksiHarian', function ($q) use ($user, $today) {
                    $q->where('dicatat_oleh', $user->id)->where('tanggal', $today);
                })->sum('berat_kg'),
                'total_janjang_hari_ini' => (int) ProduksiHarianDetail::whereHas('produksiHarian', function ($q) use ($user, $today) {
                    $q->where('dicatat_oleh', $user->id)->where('tanggal', $today);
                })->sum('jumlah_janjang'),
            ];
        }

        return Inertia::render('Dashboard', [
            'role' => $roleName,
            'title' => 'Dashboard '.ucwords(str_replace('_', ' ', $roleName)),
            'message' => 'Selamat datang di Portal Operasional PALMVISION.',
            'summary' => $summary,
            'geoJson' => null,
        ]);
    }

    /**
     * Build GeoJSON FeatureCollection for block polygons.
     *
     * @return array<string, mixed>
     */
    protected function buildGeoJson(?string $kebunId = null, ?string $afdelingId = null): array
    {
        $query = DB::table('poligon_blok')
            ->join('blok', 'blok.id', '=', 'poligon_blok.blok_id')
            ->join('afdeling', 'afdeling.id', '=', 'blok.afdeling_id')
            ->join('kebun', 'kebun.id', '=', 'afdeling.kebun_id')
            ->whereNull('blok.deleted_at')
            ->select([
                'blok.id as blok_id',
                'blok.kode_blok',
                'blok.luas_ha',
                'blok.kategori_tanah',
                'blok.jumlah_pokok',
                'afdeling.nama as afdeling_nama',
                'kebun.nama as kebun_nama',
                DB::raw('ST_AsGeoJSON(poligon_blok.poligon) as geojson'),
            ]);

        if ($afdelingId) {
            $query->where('afdeling.id', $afdelingId);
        } elseif ($kebunId) {
            $query->where('kebun.id', $kebunId);
        }

        $records = $query->get();

        $features = [];
        foreach ($records as $row) {
            if (! $row->geojson) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'blok_id' => $row->blok_id,
                    'kode_blok' => $row->kode_blok,
                    'luas_ha' => (float) $row->luas_ha,
                    'kategori_tanah' => $row->kategori_tanah,
                    'jumlah_pokok' => $row->jumlah_pokok,
                    'afdeling_nama' => $row->afdeling_nama,
                    'kebun_nama' => $row->kebun_nama,
                ],
                'geometry' => json_decode($row->geojson, true),
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
