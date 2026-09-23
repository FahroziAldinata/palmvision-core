<?php

namespace App\Http\Controllers\Web;

use App\Domain\Organisasi\Models\Kebun;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LaporanService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LaporanController extends Controller
{
    public function __construct(
        protected LaporanService $laporanService
    ) {}

    /**
     * Display report selection and download hub.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $kebuns = Kebun::query()
            ->when($user->hasRole('manajer_kebun'), function ($q) use ($user) {
                $q->where('id', $user->kebun_id);
            })
            ->when($user->hasRole('asisten_afdeling'), function ($q) use ($user) {
                $q->where('id', $user->kebun_id);
            })
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode_kebun']);

        $firstKebun = $kebuns->first();
        $defaultKebunId = $firstKebun ? $firstKebun->id : '';

        return Inertia::render('Laporan/Index', [
            'kebuns' => $kebuns,
            'defaultKebunId' => $defaultKebunId,
            'currentMonth' => now()->month,
            'currentYear' => now()->year,
        ]);
    }

    /**
     * Generate on-demand official PDF report via Gotenberg.
     */
    public function downloadPdf(Request $request): HttpResponse
    {
        $validated = $request->validate([
            'kebun_id' => ['required', 'uuid', 'exists:kebun,id'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2020,2035'],
        ]);

        $kebun = Kebun::findOrFail($validated['kebun_id']);
        $pdfContent = $this->laporanService->generatePdf(
            $kebun,
            (int) $validated['bulan'],
            (int) $validated['tahun']
        );

        $filename = "Laporan_Produksi_{$kebun->kode_kebun}_{$validated['tahun']}_{$validated['bulan']}.pdf";

        return response($pdfContent, SymfonyResponse::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Generate on-demand tabular Excel/CSV report.
     */
    public function downloadExcel(Request $request): HttpResponse
    {
        $validated = $request->validate([
            'kebun_id' => ['required', 'uuid', 'exists:kebun,id'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2020,2035'],
        ]);

        $kebun = Kebun::findOrFail($validated['kebun_id']);
        $csvContent = $this->laporanService->generateExcel(
            $kebun,
            (int) $validated['bulan'],
            (int) $validated['tahun']
        );

        $filename = "Laporan_Produksi_{$kebun->kode_kebun}_{$validated['tahun']}_{$validated['bulan']}.csv";

        return response($csvContent, SymfonyResponse::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
