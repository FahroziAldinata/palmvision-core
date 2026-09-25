# ADR 0013: Pemilihan Sumber Data Curah Hujan Historis (Open-Meteo Archive API) & Strategi Caching Database

## Status
Diterima (Accepted)

## Konteks
Pada PRD Bagian 11.2 dan US-07, model machine learning time-series (forecasting produksi) memerlukan data cuaca/curah hujan historis sebagai fitur tambahan (*external regressor*). Curah hujan bulanan merupakan salah satu faktor agronomis paling berpengaruh terhadap pembentukan bunga dan Tandan Buah Segar (TBS) kelapa sawit (dengan jeda waktu respons/lag sekitar beberapa bulan).

Sebelum implementasi, dilakukan evaluasi terhadap beberapa sumber penyedia data cuaca:
1. **API Publik BMKG Indonesia (`api.bmkg.go.id/publik/prakiraan-cuaca`)**:
   - Hanya menyediakan prakiraan cuaca (*forecast*) untuk 3 hari ke depan.
   - Tidak menyediakan data time-series historis masa lalu (*historical observations*) yang dapat diakses secara publik dan gratis tanpa permohonan birokrasi institusional.
   - Tidak memadai untuk melatih model time-series yang membutuhkan riwayat minimal 12–24 bulan ke belakang.

2. **Open-Meteo Historical Weather / Archive API (`https://archive-api.open-meteo.com/v1/archive`)**:
   - Menyediakan data historis harian (*daily precipitation sum*) global berbasis koordinat latitude & longitude presisi tinggi hingga puluhan tahun ke belakang.
   - Bebas biaya untuk penggunaan non-komersial/open-source dan tidak memerlukan pendaftaran API Key privat.
   - Sangat selaras dengan skema entitas `kebun` di PalmVision yang telah memiliki kolom PostGIS spasial `koordinat_pusat` (lat/lng).

3. **Strategi Caching vs Live Request**:
   - Memanggil live API pihak ketiga setiap kali proses training model atau kalkulasi inferensi berjalan sangat berisiko: lambat, rentan kegagalan koneksi (*rate-limit* / *network timeout*), dan pemborosan bandwidth.
   - Diperlukan tabel lokal `curah_hujan` sebagai *persistent cache layer* yang diisi via command/job berkala.

## Keputusan
1. **Menggunakan Open-Meteo Archive API** sebagai sumber data curah hujan historis perkebunan. Koordinat diambil langsung dari `kebun.koordinat_pusat` (PostGIS `ST_X` sebagai longitude dan `ST_Y` sebagai latitude).
2. **Membuat Tabel `curah_hujan`**:
   - Kolom: `id` (uuid), `kebun_id` (FK), `tanggal` (date), `curah_hujan_mm` (decimal), `sumber` (default 'open-meteo').
   - Diberi constraint `unique(['kebun_id', 'tanggal'])` untuk mencegah duplikasi data.
3. **Menerapkan Kebijakan Caching Database (Cache-First)**:
   - Dibuat Artisan command `rainfall:fetch` dan job terjadwal untuk mengunduh data rentang tanggal yang dibutuhkan satu kali saja (*bulk batch*).
   - Training dan inferensi model forecasting HANYA membaca data dari tabel `curah_hujan` lokal, tidak pernah memanggil API luar secara langsung.
4. **Data Historis Sintetis**:
   - Untuk keperluan demonstrasi dan pengujian terisolasi, disediakan `ForecastingHistoricalSeeder` yang menghasilkan data curah hujan sintetis realistis yang konsisten dengan siklus musiman produksi.

## Konsekuensi
- **Positif**:
  - Pelatihan model tidak memiliki ketergantungan jaringan (*zero network dependency*) saat training berlangsung.
  - Query agregat bulanan sangat cepat di PostgreSQL.
  - Bebas biaya dan tidak memerlukan manajemen token rahasia pihak ketiga.
- **Negatif**:
  - Perlu job pemeliharaan berkala untuk mengisi data hari-hari terbaru dari Open-Meteo.
