# ADR 0004: Penggunaan Spatie Laravel-Permission untuk Role-Based Access Control (RBAC)

## Status
Diterima (Accepted)

## Konteks
Sistem PALMVISION memiliki beragam pemangku kepentingan (*stakeholders*) dengan wewenang operasional dan hierarki data yang berbeda-beda, mulai dari level korporat holding (Direksi), level manajerial kebun (Manajer Kebun), operasional divisi (Asisten Afdeling), pengawasan lapangan (Mandor, Kerani Taksasi), data spasial (Tim GIS), hingga pemeliharaan sistem (Admin IT).

Sistem membutuhkan pengelolaan peran (*roles*) dan izin (*permissions*) yang terstruktur, standar, aman dari eskalasi hak istimewa, serta terintegrasi mulus dengan ekosistem autentikasi Laravel (Gate dan Policy) tanpa harus membangun ulang engine RBAC dari nol.

## Keputusan
1. Mengadopsi paket industri standar `spatie/laravel-permission` untuk pengelolaan roles and permissions.
2. Memanfaatkan tabel bawaan Spatie (`roles`, `permissions`, `model_has_roles`, dsb.) dengan guard `web` sebagai fondasi hak akses.
3. Mengombinasikan `HasRoles` trait pada model `User` dengan Laravel Policies untuk membatasi akses data pada level hierarki organisasi.

## Konsekuensi
- **Positif**:
  - Pustaka battle-tested, memiliki dokumentasi lengkap, dan didukung penuh oleh komunitas Laravel.
  - Memudahkan sinkronisasi, caching izin di memori/Redis, dan pemberian peran secara fleksibel.
  - Sangat bersih diintegrasikan dengan Gate dan Policy bawaan Laravel (`$user->hasRole(...)`, `$user->hasAnyRole(...)`).
- **Negatif**:
  - Menambah dependensi pihak ketiga dan beberapa tabel metadata permission ke skema basis data.

## Alternatif yang Ditolak
1. **Implementasi RBAC Manual (Custom Roles Table & Middleware)**:
   Ditolak karena rentan terhadap bug keamanan hak akses (*access control vulnerabilities*), memerlukan pemeliharaan kode boilerplate yang tidak perlu, dan membuang waktu pengembangan fitur bisnis inti.
2. **Hardcoded Role Check di Kolom User (`users.role = 'enum'`)**:
   Ditolak karena tidak fleksibel untuk skenario penugasan multi-peran atau evolusi izin granular di tahap-tahap selanjutnya.
