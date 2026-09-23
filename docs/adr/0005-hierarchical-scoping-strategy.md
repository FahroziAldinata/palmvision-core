# ADR 0005: Strategi Hierarchical Scoping Data Organisasi & Pemisahan Wewenang

## Status
Diterima (Accepted)

## Konteks
Aplikasi perkebunan kelapa sawit memiliki struktur fisik berjenjang: **Grup Perusahaan → Kebun → Afdeling → Blok**. Sesuai kebutuhan bisnis (PRD US-09), setiap peran operasional memiliki batas (*scope*) akses yang ketat terhadap data hierarki ini:
- Eksekutif korporat membutuhkan pandangan menyeluruh lintas kebun.
- Pimpinan operasional kebun hanya berhak melihat kebun yang diamanahkan kepadanya.
- Asisten divisi lapangan hanya boleh melihat afdeling tugasnya (US-09 AC3: Asisten Afdeling A dilarang keras mengakses data Afdeling B sekalipun di kebun yang sama).
- Administrator IT bertugas mengelola sistem teknis tanpa wewenang membaca data operasional bisnis rahasia.

Sistem membutuhkan mekanisme penegakan batas akses yang deklaratif, aman secara mutlak, mudah diuji secara otomatis, dan tidak bocor saat query dijalankan.

## Keputusan

### 1. Implementasi Scoping via Laravel Policy
Diputuskan menggunakan **Laravel Policy** (`KebunPolicy`, `AfdelingPolicy`, `BlokPolicy`) yang dipadukan dengan otorisasi eksplisit (`Gate::authorize` / `$this->authorize`):
- Pengecekan dilakukan langsung terhadap model domain bersangkutan dan relasi pengguna (`user.kebun_id`, `user.afdeling_id`).
- Diterapkan pada `viewAny` dan `view` di masing-masing Policy.

### 2. Deviasi Sadar Terminologi: `manajer_kebun` (Bukan `gm_kebun`)
- **Deviasi dari PRD**: Dokumen PRD bagian 4 menggunakan istilah "GM Kebun".
- **Koreksi Berdasarkan Riset Lapangan**: Berdasarkan referensi struktur organisasi industri perkebunan kelapa sawit nyata di Indonesia (seperti standar BUMN Perkebunan / PTPN), posisi pimpinan tertinggi di unit kebun secara riil disebut **Manajer Kebun** atau **ADM (Administratur)**. Istilah "Direksi" mewakili pimpinan holding/korporat lintas kebun. Oleh karena itu, nama role sistem dibakukan menjadi **`manajer_kebun`** di seluruh kode, seeder, policy, dan pengujian.

### 3. Pemisahan Wewenang (*Separation of Concerns*): `admin_it` vs `direksi`
- **`direksi`**: Memegang wewenang eksekutif bisnis penuh. Diberikan bypass otorisasi lintas kebun untuk data bisnis (`Kebun`, `Afdeling`, `Blok`).
- **`admin_it`**: Murni administrator teknis sistem (pengelolaan akun, pembagian role, reset password, audit log). **TIDAK diberikan bypass otomatis** ke data operasional perkebunan. Pada `KebunPolicy`, `AfdelingPolicy`, dan `BlokPolicy`, akses `admin_it` ditolak (mengembalikan `false` / HTTP 403 Forbidden). Hal ini mencegah kebocoran data rahasia produksi/taksasi kepada staf administrasi sistem di tahap-tahap selanjutnya.

## Konsekuensi
- **Positif**:
  - Batas tanggung jawab (Separation of Concerns) antara teknis TI dan bisnis operasional terjaga dengan ketat sejak fondasi awal.
  - Penegakan wewenang tersentralisasi pada Policy kelas masing-masing, bukan tersebar di logika controller yang rawan kelupaan.
  - Sangat mudah diuji secara komprehensif lewat pengujian fitur (Pest) yang memverifikasi kode status HTTP 403 Forbidden.
- **Negatif**:
  - Pengembang harus disiplin memanggil `Gate::authorize(...)` atau Policy check pada setiap endpoint controller yang mengakses resource domain.

## Alternatif yang Ditolak
1. **Global Scopes (Eloquent Global Scope otomatis menyaring query)**:
   Ditolak untuk Tahap 1 karena Global Scope sering memicu *hidden side-effects*, menyulitkan query agregasi di level Direksi/GIS, dan dapat menyebabkan bug tidak terduga saat background job/seeder dijalankan tanpa konteks user sesi aktif.
2. **Middleware Berbasis URL Segment (Route Middleware manual)**:
   Ditolak karena tidak fleksibel untuk query bertingkat (misal: blok di dalam afdeling di dalam kebun) dan memisahkan logika aturan otorisasi dari model domain terkait.
