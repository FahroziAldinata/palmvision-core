# ADR 0016: Batasan Ruang Lingkup Modul Pemupukan (Log Aplikasi Minimal)

## Status
Diterima (Accepted)

## Konteks
Pada PRD PalmVision (Tahap 5), modul pemupukan tercantum sebagai pencatatan operasional agronomi.
Namun, sistem pemupukan di industri kelapa sawit dapat berkembang menjadi sangat kompleks, mencakup analisis laboratorium daun/tanah (LSU - *Leaf Sampling Unit*, SSU - *Soil Sampling Unit*), kalkulasi defisiensi hara (N, P, K, Mg, B), dan rekomendasi dosis agronomi otomatis berbasis formula agronomis spesifik.

Perlu ditetapkan batas ruang lingkup yang jelas untuk Tahap 5 agar implementasi tetap terfokus, realistis, dan tepat guna bagi pencatatan operasional kebun.

## Keputusan
Memutuskan bahwa modul **Pemupukan pada Tahap 5 dibatasi pada Pencatatan Log Aplikasi Pemupukan Riil (Minimal Scope)**:
1. Skema entitas `pemupukan` mencakup:
   - Identitas blok (`blok_id`)
   - Tanggal pemupukan (`tanggal_pemupukan`)
   - Jenis pupuk (`jenis_pupuk`: misal Urea, NPK 13-6-27, MOP, Kieserit, Rock Phosphate, Borate)
   - Jumlah/dosis yang diaplikasikan (`dosis_kg` atau kuantitas total dalam kg)
   - Keterangan kondisi aplikasi (`keterangan`)
2. Tidak mengimplementasikan:
   - Modul rekomendasi dosis berbasis hasil laboratorium LSU/SSU (analisis kimia daun/tanah).
   - Penjadwalan otomatis pupuk berbasis neraca hara.
   - Modul inventori gudang pupuk multi-gudang (FIFO/LIFO balance).
3. Menyediakan antarmuka web Inertia/Vue (`/pemupukan`) dan endpoint API untuk pencatatan dan rekapitulasi riwayat aplikasi pupuk per blok.

## Konsekuensi
- **Positif**:
  - Implementasi cepat, stabil, dan langsung menjawab kebutuhan operasional pencatatan pemupukan lapangan.
  - Skema data tabel `pemupukan` tetap dapat dijadikan variabel regressor tambahan untuk model forecasting masa depan.
- **Negatif**:
  - Tim agronomis masih mengandalkan rekomendasi pupuk tahunan eksternal sebelum menginput data realisasi aplikasi ke sistem.
