<?php

namespace App\Services;

use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\PoligonBlok;
use App\Domain\Produksi\Models\ProduksiHarian;
use App\Domain\Taksasi\Models\Taksasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GisMapService
{
    /**
     * Build GeoJSON FeatureCollection with enriched productivity status and agronomy data.
     *
     * @return array<string, mixed>
     */
    public function getGeoJsonFeatures(?string $kebunId = null, ?string $afdelingId = null): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Query only latest polygon version per block
        $query = DB::table('blok')
            ->join('afdeling', 'afdeling.id', '=', 'blok.afdeling_id')
            ->join('kebun', 'kebun.id', '=', 'afdeling.kebun_id')
            ->joinSub(
                DB::table('poligon_blok')
                    ->select('blok_id', 'poligon', 'versi', 'diperbarui_pada')
                    ->distinct('blok_id')
                    ->orderBy('blok_id')
                    ->orderByDesc('versi'),
                'latest_poligon',
                'latest_poligon.blok_id',
                '=',
                'blok.id'
            )
            ->whereNull('blok.deleted_at')
            ->select([
                'blok.id as blok_id',
                'blok.kode_blok',
                'blok.luas_ha',
                'blok.kategori_tanah',
                'blok.jumlah_pokok',
                'blok.tanggal_tanam',
                'afdeling.id as afdeling_id',
                'afdeling.nama as afdeling_nama',
                'afdeling.kode as afdeling_kode',
                'kebun.id as kebun_id',
                'kebun.nama as kebun_nama',
                'kebun.siklus_rotasi_hari',
                'latest_poligon.versi as poligon_versi',
                DB::raw('ST_AsGeoJSON(latest_poligon.poligon) as geojson'),
            ]);

        if ($afdelingId) {
            $query->where('afdeling.id', $afdelingId);
        } elseif ($kebunId) {
            $query->where('kebun.id', $kebunId);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            return [
                'type' => 'FeatureCollection',
                'features' => [],
            ];
        }

        $blokIds = $records->pluck('blok_id')->all();

        // 1. Fetch realisasi panen bulan ini per blok
        $realisasiMap = DB::table('produksi_harian')
            ->join('produksi_harian_detail', 'produksi_harian_detail.produksi_harian_id', '=', 'produksi_harian.id')
            ->whereIn('produksi_harian.blok_id', $blokIds)
            ->where('produksi_harian.status_validasi', 'disetujui')
            ->whereMonth('produksi_harian.tanggal', $currentMonth)
            ->whereYear('produksi_harian.tanggal', $currentYear)
            ->groupBy('produksi_harian.blok_id')
            ->select('produksi_harian.blok_id', DB::raw('SUM(produksi_harian_detail.berat_kg) as total_realisasi_kg'))
            ->pluck('total_realisasi_kg', 'blok_id')
            ->all();

        // 2. Fetch taksasi panen bulan ini per blok
        $taksasiMap = DB::table('taksasi')
            ->whereIn('blok_id', $blokIds)
            ->whereMonth('tanggal_taksasi', $currentMonth)
            ->whereYear('tanggal_taksasi', $currentYear)
            ->groupBy('blok_id')
            ->select('blok_id', DB::raw('SUM(estimasi_total_kg) as total_taksasi_kg'))
            ->pluck('total_taksasi_kg', 'blok_id')
            ->all();

        // 3. Fetch latest production date per block for rotation date calculation
        $latestProduksiMap = DB::table('produksi_harian')
            ->whereIn('blok_id', $blokIds)
            ->groupBy('blok_id')
            ->select('blok_id', DB::raw('MAX(tanggal) as max_tanggal'))
            ->pluck('max_tanggal', 'blok_id')
            ->all();

        $features = [];
        foreach ($records as $row) {
            if (! $row->geojson) {
                continue;
            }

            $realisasiKg = isset($realisasiMap[$row->blok_id]) ? (float) $realisasiMap[$row->blok_id] : 0.0;
            $taksasiKg = isset($taksasiMap[$row->blok_id]) ? (float) $taksasiMap[$row->blok_id] : 0.0;

            // Productivity color threshold logic (ADR 0010)
            $statusWarna = 'netral';
            $persentaseDeviasi = null;

            if ($taksasiKg > 0 && $realisasiKg > 0) {
                $deviasi = (($realisasiKg - $taksasiKg) / $taksasiKg) * 100.0;
                $persentaseDeviasi = round($deviasi, 1);
                $absDeviasi = abs($deviasi);

                if ($absDeviasi <= 5.0) {
                    $statusWarna = 'hijau';
                } elseif ($absDeviasi <= 15.0) {
                    $statusWarna = 'kuning';
                } else {
                    $statusWarna = 'merah';
                }
            }

            // Usia tanaman calculation
            $usiaTanaman = 'Belum ada data';
            if ($row->tanggal_tanam) {
                $tanam = Carbon::parse($row->tanggal_tanam);
                $diff = $tanam->diff(now());
                $usiaTanaman = "{$diff->y} Thn {$diff->m} Bln";
            }

            // Tanggal rotasi berikutnya calculation (ADR 0010)
            $siklusHari = $row->siklus_rotasi_hari ?? 10;
            $tanggalRotasiBerikutnya = null;
            if (isset($latestProduksiMap[$row->blok_id]) && $latestProduksiMap[$row->blok_id]) {
                $tanggalRotasiBerikutnya = Carbon::parse($latestProduksiMap[$row->blok_id])
                    ->addDays((int) $siklusHari)
                    ->toDateString();
            }

            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'blok_id' => $row->blok_id,
                    'kode_blok' => $row->kode_blok,
                    'luas_ha' => (float) $row->luas_ha,
                    'kategori_tanah' => $row->kategori_tanah,
                    'jumlah_pokok' => $row->jumlah_pokok,
                    'tanggal_tanam' => $row->tanggal_tanam,
                    'usia_tanaman' => $usiaTanaman,
                    'afdeling_id' => $row->afdeling_id,
                    'afdeling_nama' => $row->afdeling_nama,
                    'afdeling_kode' => $row->afdeling_kode,
                    'kebun_id' => $row->kebun_id,
                    'kebun_nama' => $row->kebun_nama,
                    'poligon_versi' => $row->poligon_versi,
                    'realisasi_kg' => round($realisasiKg, 1),
                    'taksasi_kg' => round($taksasiKg, 1),
                    'persentase_deviasi' => $persentaseDeviasi,
                    'status_warna' => $statusWarna,
                    'tanggal_rotasi_berikutnya' => $tanggalRotasiBerikutnya,
                ],
                'geometry' => json_decode($row->geojson, true),
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * Get detailed information for a single block for the detail modal / panel.
     *
     * @return array<string, mixed>
     */
    public function getBlockDetail(Blok $blok): array
    {
        $blok->loadMissing(['afdeling.kebun', 'latestPoligon']);
        $kebun = $blok->afdeling?->kebun;

        // 1. Usia tanaman
        $usiaTanaman = 'Belum ada data';
        if ($blok->tanggal_tanam) {
            $tanam = Carbon::parse($blok->tanggal_tanam);
            $diff = $tanam->diff(now());
            $usiaTanaman = "{$diff->y} Tahun {$diff->m} Bulan";
        }

        // 2. Tanggal rotasi berikutnya
        $lastProduksi = ProduksiHarian::where('blok_id', $blok->id)
            ->orderByDesc('tanggal')
            ->first();

        $siklusHari = $kebun ? $kebun->siklus_rotasi_hari : 10;
        $tanggalRotasiBerikutnya = null;
        $tanggalRotasiLabel = 'Belum ada data';

        if ($lastProduksi && $lastProduksi->tanggal) {
            $rotasiCarbon = Carbon::parse($lastProduksi->tanggal)->addDays((int) $siklusHari);
            $tanggalRotasiBerikutnya = $rotasiCarbon->toDateString();
            $tanggalRotasiLabel = $rotasiCarbon->isoFormat('D MMMM Y');
        }

        // 3. Taksasi terakhir
        $latestTaksasi = Taksasi::where('blok_id', $blok->id)
            ->with('pencatat:id,name')
            ->orderByDesc('tanggal_taksasi')
            ->orderByDesc('created_at')
            ->first();

        $taksasiData = null;
        if ($latestTaksasi) {
            $taksasiData = [
                'id' => $latestTaksasi->id,
                'tanggal_taksasi' => Carbon::parse($latestTaksasi->tanggal_taksasi)->isoFormat('D MMMM Y'),
                'pokok_disampel' => $latestTaksasi->pokok_disampel,
                'estimasi_janjang' => $latestTaksasi->estimasi_janjang,
                'estimasi_bjr' => (float) $latestTaksasi->estimasi_bjr,
                'estimasi_total_kg' => (float) $latestTaksasi->estimasi_total_kg,
                'kerani_nama' => $latestTaksasi->pencatat->name ?? '-',
            ];
        }

        // 4. Riwayat produksi ringkas (5 entri terakhir)
        $riwayatProduksi = ProduksiHarian::where('blok_id', $blok->id)
            ->with(['pencatat:id,name', 'details'])
            ->orderByDesc('tanggal')
            ->limit(5)
            ->get()
            ->map(function (ProduksiHarian $p) {
                return [
                    'id' => $p->id,
                    'tanggal' => Carbon::parse($p->tanggal)->isoFormat('D MMMM Y'),
                    'mandor' => $p->pencatat->name ?? '-',
                    'total_janjang' => $p->total_janjang,
                    'total_berat_kg' => (float) $p->total_berat_kg,
                    'status_validasi' => $p->status_validasi,
                ];
            });

        // 5. Status Produktivitas bulan berjalan
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $taksasiBulanIni = (float) Taksasi::where('blok_id', $blok->id)
            ->whereMonth('tanggal_taksasi', $currentMonth)
            ->whereYear('tanggal_taksasi', $currentYear)
            ->sum('estimasi_total_kg');

        $realisasiBulanIni = (float) DB::table('produksi_harian')
            ->join('produksi_harian_detail', 'produksi_harian_detail.produksi_harian_id', '=', 'produksi_harian.id')
            ->where('produksi_harian.blok_id', $blok->id)
            ->where('produksi_harian.status_validasi', 'disetujui')
            ->whereMonth('produksi_harian.tanggal', $currentMonth)
            ->whereYear('produksi_harian.tanggal', $currentYear)
            ->sum('produksi_harian_detail.berat_kg');

        $statusWarna = 'netral';
        $persentaseDeviasi = null;

        if ($taksasiBulanIni > 0 && $realisasiBulanIni > 0) {
            $deviasi = (($realisasiBulanIni - $taksasiBulanIni) / $taksasiBulanIni) * 100.0;
            $persentaseDeviasi = round($deviasi, 1);
            $absDeviasi = abs($deviasi);

            if ($absDeviasi <= 5.0) {
                $statusWarna = 'hijau';
            } elseif ($absDeviasi <= 15.0) {
                $statusWarna = 'kuning';
            } else {
                $statusWarna = 'merah';
            }
        }

        // 6. Riwayat Versi Poligon
        $riwayatVersi = PoligonBlok::where('blok_id', $blok->id)
            ->orderByDesc('versi')
            ->get()
            ->map(function (PoligonBlok $pb) {
                return [
                    'id' => $pb->id,
                    'versi' => $pb->versi,
                    'diperbarui_pada' => $pb->diperbarui_pada ? Carbon::parse($pb->diperbarui_pada)->isoFormat('D MMMM Y') : '-',
                    'created_at' => $pb->created_at ? $pb->created_at->isoFormat('D MMMM Y HH:mm') : '-',
                ];
            });

        return [
            'blok' => [
                'id' => $blok->id,
                'kode_blok' => $blok->kode_blok,
                'luas_ha' => (float) $blok->luas_ha,
                'jumlah_pokok' => $blok->jumlah_pokok,
                'kategori_tanah' => $blok->kategori_tanah,
                'tanggal_tanam' => $blok->tanggal_tanam ? Carbon::parse($blok->tanggal_tanam)->isoFormat('D MMMM Y') : null,
                'usia_tanaman' => $usiaTanaman,
                'afdeling_id' => $blok->afdeling_id,
                'afdeling_nama' => $blok->afdeling->nama ?? '-',
                'kebun_id' => $kebun?->id,
                'kebun_nama' => $kebun->nama ?? '-',
                'siklus_rotasi_hari' => $siklusHari,
                'tanggal_rotasi_berikutnya' => $tanggalRotasiBerikutnya,
                'tanggal_rotasi_label' => $tanggalRotasiLabel,
                'status_warna' => $statusWarna,
                'persentase_deviasi' => $persentaseDeviasi,
                'realisasi_bulan_ini_kg' => round($realisasiBulanIni, 1),
                'taksasi_bulan_ini_kg' => round($taksasiBulanIni, 1),
            ],
            'taksasi_terakhir' => $taksasiData,
            'riwayat_produksi' => $riwayatProduksi,
            'riwayat_versi' => $riwayatVersi,
        ];
    }
}
