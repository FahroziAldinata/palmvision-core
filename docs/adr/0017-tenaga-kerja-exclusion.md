# ADR 0017: Penegasan Batasan Ruang Lingkup Modul Tenaga Kerja (Pengecualian HRIS/Payroll Mandiri)

## Status
Diterima (Accepted)

## Konteks
Pada PRD PalmVision (Tahap 5), salah satu topik yang dibahas adalah operasional tenaga kerja kebun.
Sejak Tahap 2, keputusan fundamental terkait tenaga kerja telah ditetapkan secara jelas pada **ADR 0007 (Granularitas Produksi Harian Per Pemanen & Batasan Sistem Non-Payroll)**.
Untuk mencegah perluasan cakupan yang tidak terarah (*scope creep*) pada Tahap 5, diperlukan penegasan arsitektural resmi mengenai batas implementasi fitur tenaga kerja.

## Keputusan
Menegaskan bahwa pada **Tahap 5 TIDAK ADA kode baru untuk sistem Tenaga Kerja / HRIS / Payroll**:
1. **Reuse Implementasi Tahap 2**:
   - Master data pekerja panen tetap menggunakan entitas `pemanen` (di bawah naungan `afdeling`) yang sudah diimplementasikan di Tahap 2.
   - Kehadiran pemanen tetap bersifat tersirat (*implicit attendance*) melalui relasi `produksi_harian_detail` yang mencatat siapa saja yang bekerja pada ancak dan blok panen bersangkutan.
2. **Pengecualian Eksplisit**:
   - **Payroll / Penggajian**: Tidak ada kalkulasi upah harian, premi basis panen, denda brondolan/tangkai panjang, BPJS, maupun modul slip gaji.
   - **Absensi Terpisah**: Tidak ada modul biometric clock-in/out atau sistem cuti/ijin terpisah di luar konteks panen harian.
   - **Kontrak Kerja & Recruitment**: Tidak mencakup pengelolaan dokumen kontrak kerja atau administrasi HR.
3. Kebutuhan tenaga kerja pada aplikasi mobile (`palmvision-mobile`) terpenuhi secara penuh melalui endpoint bootstrap `/api/v1/mobile/bootstrap` yang menyinkronkan daftar pemanen aktif ke SQLite lokal (`LocalPemanens`) untuk pemilihan pemanen saat input produksi panen offline.

## Konsekuensi
- **Positif**:
  - Arsitektur sistem tetap ramping dan konsisten dengan domain inti PalmVision (Agronomi & Presisi Panen).
  - Menghindari duplikasi modul dengan sistem ERP/HRIS perusahaan perkebunan yang biasanya sudah ada secara terpisah.
- **Negatif**:
  - Pengguna yang mengharapkan integrasi penggajian langsung harus mengekspor data produksi harian (CSV/PDF) untuk diolah di sistem payroll eksternal.
