<?php

namespace App\Services;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\Kebun;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Taksasi\Models\Taksasi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use RuntimeException;

class LaporanService
{
    /**
     * Month names in Indonesian.
     *
     * @var array<int, string>
     */
    protected array $namaBulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * Aggregate monthly data per block in the kebun.
     *
     * @return array<string, mixed>
     */
    public function getLaporanData(Kebun $kebun, int $bulan, int $tahun): array
    {
        $bloks = Blok::query()
            ->with('afdeling')
            ->whereHas('afdeling', fn ($q) => $q->where('kebun_id', $kebun->id))
            ->orderBy('kode_blok')
            ->get();

        $rows = $bloks->map(function (Blok $b) use ($bulan, $tahun) {
            $taksasiKg = (float) Taksasi::where('blok_id', $b->id)
                ->whereMonth('tanggal_taksasi', $bulan)
                ->whereYear('tanggal_taksasi', $tahun)
                ->sum('estimasi_total_kg');

            $realisasiKg = (float) ProduksiHarian::where('blok_id', $b->id)
                ->whereMonth('tanggal', $bulan)
                ->whereYear('tanggal', $tahun)
                ->where('status_validasi', 'disetujui')
                ->with('details')
                ->get()
                ->sum(fn (ProduksiHarian $p) => $p->total_berat_kg);

            $selisihKg = $realisasiKg - $taksasiKg;

            return [
                'kode_blok' => $b->kode_blok,
                'afdeling' => $b->afdeling ? $b->afdeling->nama : '-',
                'luas_ha' => (float) $b->luas_ha,
                'jumlah_pokok' => (int) $b->jumlah_pokok,
                'taksasi_kg' => round($taksasiKg, 1),
                'realisasi_kg' => round($realisasiKg, 1),
                'selisih_kg' => round($selisihKg, 1),
            ];
        })->all();

        return [
            'kebun' => $kebun,
            'namaBulan' => $this->namaBulan[$bulan] ?? "Bulan $bulan",
            'tahun' => $tahun,
            'rows' => $rows,
        ];
    }

    /**
     * Generate official PDF report via Gotenberg microservice.
     */
    public function generatePdf(Kebun $kebun, int $bulan, int $tahun): string
    {
        $data = $this->getLaporanData($kebun, $bulan, $tahun);
        $html = View::make('reports.produksi_bulanan', $data)->render();

        $gotenbergUrl = (string) config('services.gotenberg.url', 'http://gotenberg:3000');

        $response = Http::timeout(30)
            ->attach('files', $html, 'index.html')
            ->post("{$gotenbergUrl}/forms/chromium/convert/html");

        if (! $response->successful()) {
            throw new RuntimeException("Gagal menghasilkan PDF dari Gotenberg: {$response->status()} - {$response->body()}");
        }

        return $response->body();
    }

    /**
     * Generate structured tabular Excel/CSV report for spreadsheet reprocessing.
     */
    public function generateExcel(Kebun $kebun, int $bulan, int $tahun): string
    {
        $data = $this->getLaporanData($kebun, $bulan, $tahun);

        $output = fopen('php://temp', 'r+');
        if (! $output) {
            throw new RuntimeException('Gagal membuka buffer memori untuk ekspor Excel.');
        }

        // UTF-8 BOM for Microsoft Excel auto-detect UTF-8
        fwrite($output, "\xEF\xBB\xBF");

        // Metadata Header
        fputcsv($output, ['LAPORAN BULANAN REKAPITULASI PRODUKSI DAN TAKSASI']);
        fputcsv($output, ['Kebun', $kebun->nama." ({$kebun->kode_kebun})"]);
        fputcsv($output, ['Periode', ($this->namaBulan[$bulan] ?? $bulan).' '.$tahun]);
        fputcsv($output, []); // empty line

        // Table Columns
        fputcsv($output, [
            'No',
            'Afdeling',
            'Kode Blok',
            'Luas (Ha)',
            'Jumlah Pokok',
            'Taksasi (Kg)',
            'Realisasi (Kg)',
            'Selisih (Kg)',
        ]);

        foreach ($data['rows'] as $idx => $row) {
            fputcsv($output, [
                $idx + 1,
                $row['afdeling'],
                $row['kode_blok'],
                $row['luas_ha'],
                $row['jumlah_pokok'],
                $row['taksasi_kg'],
                $row['realisasi_kg'],
                $row['selisih_kg'],
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }
}
