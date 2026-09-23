<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Produksi Bulanan - {{ $kebun->nama }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 20mm 15mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #1a1a1a;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #1b4d3e;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }
        .header h1 {
            font-size: 14pt;
            margin: 0 0 3px 0;
            color: #1b4d3e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header h2 {
            font-size: 12pt;
            margin: 0 0 4px 0;
            color: #2e7d32;
        }
        .header p {
            font-size: 8pt;
            margin: 0;
            color: #555;
        }
        .report-title {
            text-align: center;
            margin-bottom: 16px;
        }
        .report-title h3 {
            font-size: 11pt;
            margin: 0 0 4px 0;
            text-decoration: underline;
        }
        .report-title p {
            font-size: 9pt;
            margin: 0;
            font-weight: bold;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #777;
            padding: 5px 6px;
            font-size: 8.5pt;
        }
        th {
            background-color: #e8f5e9;
            color: #1b4d3e;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: "Courier New", Courier, monospace; }
        .bg-total {
            background-color: #f1f8e9;
            font-weight: bold;
        }
        .signatures {
            margin-top: 35px;
            width: 100%;
            page-break-inside: avoid;
        }
        .signatures table {
            border: none;
        }
        .signatures td {
            border: none;
            width: 50%;
            text-align: center;
            padding: 10px 20px;
            font-size: 9pt;
        }
        .signature-space {
            height: 60px;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 75%;
            margin: 0 auto 3px auto;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>PT Palma Nusantara Jaya</h1>
        <h2>{{ $kebun->nama }} ({{ $kebun->kode_kebun }})</h2>
        <p>Sistem Operasional Perkebunan Kelapa Sawit Terintegrasi - PALMVISION</p>
    </div>

    <div class="report-title">
        <h3>REKAPITULASI PRODUKSI DAN TAKSASI PANEN BULANAN</h3>
        <p>Periode: {{ $namaBulan }} {{ $tahun }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Afdeling</th>
                <th style="width: 12%;">Blok</th>
                <th style="width: 10%;">Luas (Ha)</th>
                <th style="width: 10%;">Pokok</th>
                <th style="width: 16%;">Taksasi (Kg)</th>
                <th style="width: 16%;">Realisasi (Kg)</th>
                <th style="width: 16%;">Selisih (Kg)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalLuas = 0;
                $totalPokok = 0;
                $totalTaksasi = 0;
                $totalRealisasi = 0;
            @endphp
            @forelse($rows as $idx => $r)
                @php
                    $totalLuas += $r['luas_ha'];
                    $totalPokok += $r['jumlah_pokok'];
                    $totalTaksasi += $r['taksasi_kg'];
                    $totalRealisasi += $r['realisasi_kg'];
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $r['afdeling'] }}</td>
                    <td class="text-center font-mono"><strong>{{ $r['kode_blok'] }}</strong></td>
                    <td class="text-right font-mono">{{ number_format($r['luas_ha'], 2, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ number_format($r['jumlah_pokok'], 0, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ number_format($r['taksasi_kg'], 1, ',', '.') }}</td>
                    <td class="text-right font-mono"><strong>{{ number_format($r['realisasi_kg'], 1, ',', '.') }}</strong></td>
                    <td class="text-right font-mono">{{ ($r['selisih_kg'] > 0 ? '+' : '') . number_format($r['selisih_kg'], 1, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px;">Tidak ada data produksi untuk periode ini.</td>
                </tr>
            @endforelse
            <tr class="bg-total">
                <td colspan="3" class="text-center"><strong>TOTAL KEBUN</strong></td>
                <td class="text-right font-mono">{{ number_format($totalLuas, 2, ',', '.') }}</td>
                <td class="text-right font-mono">{{ number_format($totalPokok, 0, ',', '.') }}</td>
                <td class="text-right font-mono">{{ number_format($totalTaksasi, 1, ',', '.') }}</td>
                <td class="text-right font-mono">{{ number_format($totalRealisasi, 1, ',', '.') }}</td>
                <td class="text-right font-mono">{{ ($totalRealisasi - $totalTaksasi > 0 ? '+' : '') . number_format($totalRealisasi - $totalTaksasi, 1, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signatures">
        <table>
            <tr>
                <td>
                    Disiapkan oleh,<br>
                    <strong>Asisten Afdeling</strong>
                    <div class="signature-space"></div>
                    <div class="signature-line"></div>
                    ( .................................................. )
                </td>
                <td>
                    Disetujui oleh,<br>
                    <strong>Manajer Kebun / ADM</strong>
                    <div class="signature-space"></div>
                    <div class="signature-line"></div>
                    ( .................................................. )
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
