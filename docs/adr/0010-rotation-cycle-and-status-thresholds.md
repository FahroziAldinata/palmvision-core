# ADR 0010: Siklus Rotasi Panen & Ambang Batas Warna Status Produktivitas

## Status
Diterima (Accepted)

## Konteks
Pada PRD PALMVISION US-05:
1. AC2 mensyaratkan panel detail blok menampilkan "tanggal rotasi berikutnya", namun dokumen PRD tidak mendefinisikan formula atau parameter panjang siklus rotasi panen.
2. AC1 mensyaratkan peta menampilkan poligon blok dengan warna status produktivitas merujuk ke "token status di bagian design" yang tidak dijabarkan lebih lanjut di PRD.

## Keputusan

### 1. Siklus Rotasi Panen (Pola 8/10 Standar Kebun Sawit)
- Standar industri perkebunan kelapa sawit di Indonesia umumnya menggunakan interval rotasi panen 7–10 hari (paling umum 10 hari / pola rotasi 8-10 hari).
- Ditambahkan kolom `siklus_rotasi_hari` bertipe `integer` dengan nilai default `10` pada tabel `kebun`. Konfigurasi ini dapat disesuaikan per kebun.
- Perhitungan **Tanggal Rotasi Berikutnya**:
  $$\text{tanggal\_rotasi\_berikutnya} = \text{tanggal\_input\_produksi\_terakhir} + \text{kebun.siklus\_rotasi\_hari}$$
- Apabila suatu blok belum memiliki riwayat catatan produksi harian sama sekali, sistem menampilkan `"belum ada data"` secara eksplisit (tidak mengarang tanggal asal).

### 2. Ambang Batas Tiga Tingkat Warna Status Produktivitas (Reuse Pola Tahap 2)
Mengikuti pola konsisten evaluasi akurasi taksasi vs realisasi panen (US-04) yang dibangun di Tahap 2:
- **Deviasi Persentase**:
  $$\text{deviasi} = \frac{|\text{realisasi\_kg} - \text{taksasi\_kg}|}{\text{taksasi\_kg}} \times 100\%$$
- **Threshold & Warna Visual**:
  - **Hijau (`#10b981` / `emerald-500`)**: Deviasi $\le 5\%$ (Sangat Akurat / Sangat Baik).
  - **Kuning (`#f59e0b` / `amber-500`)**: Deviasi $> 5\%$ dan $\le 15\%$ (Waspada / Deviasi Sedang).
  - **Merah (`#ef4444` / `rose-500`)**: Deviasi $> 15\%$ (Anomali / Deviasi Tinggi / Warning Badge).
  - **Abu-abu/Netral (`#9ca3af` / `gray-400`)**: Blok tanpa data taksasi atau realisasi yang memadai.

## Konsekuensi
- **Positif**:
  - Terdapat kejelasan matematis dan agronomi yang seragam di seluruh peta interaktif dan panel detail blok.
  - Skema warna konsisten dengan modul evaluasi akurasi taksasi Tahap 2.
  - Parameter siklus fleksibel per unit kebun.
- **Negatif**:
  - Blok yang baru ditanami atau belum memasuki masa panen awal akan berstatus netral (abu-abu) sampai siklus panen pertama tercatat.
