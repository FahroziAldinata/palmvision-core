# ADR 0008: Formula Taksasi Panen & Desain Penyimpanan Historis

## Status
Diterima (Accepted)

## Konteks
Sesuai PRD US-03, sistem harus mencatat taksasi panen (estimasi buah siap panen) yang dilakukan oleh Kerani Taksasi sebelum hari pelaksanaan panen (biasanya H-1). Namun, PRD tidak merinci rumus baku agronomi untuk mengonversi hasil sensus pokok sampel menjadi perkiraan berat tandan buah segar (TBS) dalam kilogram. Selain itu, US-03 AC3 menuntut bahwa entri taksasi baru tidak boleh menimpa riwayat taksasi sebelumnya.

## Keputusan

### 1. Rumus Baku Agronomi Taksasi Panen
Berdasarkan standar praktik baku agronomi perkebunan kelapa sawit nasional, estimasi berat panen dihitung melalui formula:
1. **Kerapatan Janjang per Pokok**:
   $$\text{janjang\_per\_pokok} = \frac{\text{estimasi\_janjang}}{\text{pokok\_disampel}}$$
2. **Estimasi Total Produksi Blok (Kg)**:
   $$\text{estimasi\_total\_kg} = \text{janjang\_per\_pokok} \times \text{blok.jumlah\_pokok} \times \text{estimasi\_bjr}$$

Di mana:
- `estimasi_janjang`: Jumlah total janjang matang yang ditemukan pada pohon sampel saat sensus.
- `pokok_disampel`: Jumlah pohon yang disampel (misal: 10% atau 20 pohon).
- `blok.jumlah_pokok`: Populasi tegakan pohon kelapa sawit aktual di blok bersangkutan (diambil dari master data `blok.jumlah_pokok` Tahap 1).
- `estimasi_bjr`: Berat Janjang Rata-rata estimasi dalam satuan kilogram.

### 2. Penyimpanan Permanen dan Riwayat Tidak Menimpa (*Non-Overwriting Records*)
- Kolom `estimasi_total_kg` dihitung di backend saat penyimpanan dan disimpan secara permanen di baris tabel `taksasi`.
- Setiap submit data taksasi dicatat sebagai baris baru (`INSERT`) tanpa mengubah atau menimpa baris sebelumnya.
- Hal ini memungkinkan evaluasi deviasi dan analisis akurasi taksasi vs realisasi panen (US-04) dengan toleransi deviasi >15% secara objektif dan akurat sepanjang waktu.

## Konsekuensi
- **Positif**:
  - Perhitungan matematis terstandarisasi dan dapat diverifikasi secara transparan oleh manajemen kebun.
  - Audit trail historis taksasi terjaga utuh untuk kebutuhan audit agronomi (ISPO/RSPO).
  - Tampilan form input menyediakan kalkulasi interaktif otomatis di sisi client sebelum disimpan.
- **Negatif**:
  - Estimasi sangat bergantung pada akurasi input BJR dan keterwakilan sampel pohon di lapangan.
