# ADR 0003: Dual Authentication Model (Session for Web Dashboard & Sanctum Token for Mobile/API)

## Status
Diterima (Accepted)

## Konteks
Sistem PALMVISION memiliki dua kategori pengguna dengan karakteristik ancaman, siklus hidup klien, dan lingkungan konektivitas yang sangat berbeda (PRD Bagian 9.1 & 13.1):
1. Pengguna Dashboard Web (Direksi, GM Kebun, Asisten Afdeling, Tim GIS, Admin IT) yang mengakses aplikasi dari peramban desktop di lingkungan kantor dengan koneksi stabil.
2. Tenaga Kerja Lapangan / Mobile Worker (Mandor panen, Kerani taksasi, Kerani timbangan) yang membawa perangkat smartphone/tablet ke area afdeling perkebunan dengan kondisi sinyal lemah atau tanpa sinyal (offline-first sync).

Menerapkan model autentikasi tunggal (misalnya murni JWT/token untuk semua atau murni session-cookie untuk semua) menimbulkan masalah: JWT di peramban web rentan terhadap XSS dan sulit di-revoke secara instan, sedangkan cookie-session berbasis HTTP header tidak cocok untuk aplikasi mobile offline yang melakukan background sync berkala dan rentan kehilangan session saat koneksi terputus.

## Keputusan
1. Menggunakan **Session-Based Authentication** berbasis cookie (Laravel Fortify / Breeze session cookies) untuk dashboard web first-party.
2. Menggunakan **Token-Based Authentication** (Laravel Sanctum) untuk mobile worker dan integrasi eksternal (PKS/timbangan).
3. Token Sanctum diterbitkan per-device dengan kemampuan revoke spesifik per perangkat bila perangkat lapangan hilang atau dicuri (PRD 13.1).

## Konsekuensi
- **Positif**:
  - Dashboard web mendapatkan proteksi CSRF native, HttpOnly cookies, dan sesi yang aman tanpa beban token storage di frontend client.
  - Aplikasi mobile di lapangan dapat menyimpan token autentikasi di secure storage lokal dan melakukan sinkronisasi data kapan pun koneksi tersedia tanpa terhalang siklus sesi browser.
  - Bila HP mandor/kerani hilang di lapangan, admin/manajemen dapat mencabut token spesifik perangkat tersebut seketika tanpa memutus login pengguna lainnya.
- **Negatif**:
  - Terdapat dua jalur middleware autentikasi yang harus dikelola di aplikasi Laravel (`web` guard vs `sanctum` guard).

## Alternatif yang Ditolak
1. **Single JWT untuk Seluruh Klien (Web & Mobile)**:
   Ditolak karena kerumitan manajemen token refresh, risiko penyimpanan token di localStorage web, dan kelemahan dalam instant revocation.
2. **Session Cookies untuk Mobile**:
   Ditolak karena tidak andal untuk aplikasi lapangan offline-first dengan request sinkronisasi batch latar belakang.
