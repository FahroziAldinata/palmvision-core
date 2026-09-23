# ADR 0007: Granularitas Produksi Harian Per Pemanen & Batasan Sistem Non-Payroll

## Status
Diterima (Accepted)

## Konteks
Pada dokumen PRD awal (US-01), entitas `produksi_harian` dirancang sederhana di level agregat blok per tanggal. Namun, berdasarkan riset mendalam praktik operasional nyata industri perkebunan kelapa sawit di Indonesia (khususnya standar BUMN Perkebunan / PTPN):
1. Pencatatan panen lapangan oleh Mandor Panen selalu dilakukan berbasis pemanen individu (pekerja borongan/harian lepas) untuk memantau kehadiran dan produktivitas kerja tiap pemanen (berapa janjang dan kilogram yang diperoleh masing-masing pemanen pada ancak panen hari itu).
2. Agregat per blok diperoleh dari penjumlahan riil hasil kerja seluruh pemanen yang bertugas di blok tersebut pada hari bersangkutan.
3. Kendati demikian, PALMVISION berfokus pada efisiensi operasional agronomi dan kecerdasan perkebunan, **BUKAN** sebagai Human Resource Information System (HRIS) atau Payroll Engine. Menghitung upah, premi basis, denda ancak, asuransi, atau slip gaji akan memperluas cakupan sistem di luar visi intinya.

## Keputusan

### 1. Struktur Relasi Dua Tingkat (Header-Detail)
Diputuskan menerapkan skema dua tingkat untuk pencatatan produksi panen:
- **Tabel `pemanen`**: Master data pekerja panen di bawah naungan Afdeling (`id`, `afdeling_id`, `nik`, `nama`, `is_active`). Dikelola oleh Asisten Afdeling.
- **Tabel `produksi_harian`** (Header): Mewakili satu sesi pencatatan panen per blok per tanggal oleh mandor (`id`, `blok_id`, `tanggal`, `status_validasi`, `dicatat_oleh`, `divalidasi_oleh`, `catatan_validasi`). Unik per kombinasi `(blok_id, tanggal)`.
- **Tabel `produksi_harian_detail`** (Detail): Rincian hasil panen per pemanen (`id`, `produksi_harian_id`, `pemanen_id`, `jumlah_janjang`, `berat_kg`).
- Agregasi `total_janjang` dan `total_berat_kg` pada level header dihitung dinamis melalui accessor Eloquent atau query database, menjamin integritas data tanpa redundansi kolom statis yang rentan desinkronisasi.

### 2. Batasan Tegas: Zero Payroll & Zero Separate Attendance
- **Tidak Ada Modul Penggajian / Premi**: Sistem secara sadar **TIDAK** menyediakan kalkulasi tarif per janjang, premi lebih basis, denda buah mentah/tangkai panjang, maupun ekspor slip gaji.
- **Presensi Tersirat (*Implicit Attendance*)**: Kehadiran pemanen tercatat secara otomatis melalui keterlibatan mereka dalam pencatatan panen harian (`produksi_harian_detail`). Tidak ada tabel atau fitur presensi terpisah (clock-in/clock-out) di Tahap 2.

## Konsekuensi
- **Positif**:
  - Merefleksikan alur kerja nyata di kebun sawit Indonesia secara presisi, memudahkan adopsi mandor dan asisten di lapangan.
  - Data produktivitas individu pemanen tersimpan rapi untuk analisis historis tanpa membebani sistem dengan kerumitan aturan penggajian.
  - Form input mandor intuitif dengan kalkulasi real-time di sisi antarmuka pengguna (Vue Inertia).
- **Negatif**:
  - Penambahan tabel dan relasi baru memerlukan validasi berlapis (pemanen harus aktif dan terdaftar di afdeling yang sama dengan blok yang dipanen).
