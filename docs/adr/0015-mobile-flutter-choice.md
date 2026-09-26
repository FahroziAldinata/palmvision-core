# ADR 0015: Pemilihan Flutter dan Drift (SQLite) untuk Aplikasi Mobile Worker Offline-First

## Status
Diterima (Accepted)

## Konteks
Sesuai PRD Bagian 8.3 dan US-01, PalmVision memerlukan aplikasi mobile worker untuk mandor dan kerani di kebun sawit yang sering mengalami ketiadaan koneksi internet (blank spot / zero connectivity).
PRD Bagian 8.3 menyebutkan dua opsi: **PWA** (*Progressive Web App*) atau **Flutter**.
Selain itu, diperlukan strategi penyimpanan lokal dan antrean sinkronisasi yang andal dan aman dari kehilangan data akibat force-kill atau kehabisan baterai di lapangan.

## Perbandingan Opsi
1. **PWA (Progressive Web App dengan IndexedDB/LocalStorage)**:
   - Kelebihan: Basis kode tunggal dengan web app, update instan tanpa instalasi APK.
   - Kekurangan: Di perangkat Android kelas menengah ke bawah yang umum dipakai mandor kebun, browser engine sering membersihkan cache IndexedDB/storage saat RAM penuh (storage eviction). Akses hardware terbatas dan lifecycle service worker di background kurang deterministik saat offline berkepanjangan.

2. **Flutter dengan Drift (SQLite) Native**:
   - Kelebihan:
     - Akses langsung ke SQLite engine lokal via FFI (`sqlite3_flutter_libs`), menjamin transaksi ACID yang tangguh terhadap force close atau baterai habis.
     - Drift menyediakan *compile-time type safety*, query builder deklaratif, dan auto-generated DAO/schema.
     - Performa UI tinggi (60 FPS) bahkan pada perangkat entry-level berkat rendering Skia/Impeller.
     - Repositori terpisah (`palmvision-mobile`) menjaga batas tanggung jawab arsitektural yang bersih dari backend Laravel monolith (`palmvision-core`).
   - Kekurangan: Memerlukan kompilasi binary/APK dan tooling Flutter SDK tersendiri.

## Keputusan
Memilih **Flutter** dengan **Drift (SQLite)** sebagai arsitektur aplikasi mobile worker PalmVision (`palmvision-mobile`):
1. Menggunakan basis data SQLite lokal via Drift untuk menyimpan master data offline (`LocalBloks`, `LocalPemanens`), transaksi panen (`LocalProduksi`), taksasi (`LocalTaksasi`), dan antrean sinkronisasi (`SyncQueue`).
2. Setiap transaksi offline di-generate dengan `client_uuid` (v4) untuk menjamin idempotensi di level server.
3. Sinkronisasi batch dikirim via endpoint `/api/v1/produksi/sync` dan `/api/v1/taksasi/sync` dengan autentikasi Laravel Sanctum Personal Access Token.
4. Tooling Flutter SDK dipasang native di Linux (`~/tools/flutter`) untuk menghindari disk boundary issues antara Windows dan WSL.

## Konsekuensi
- **Positif**:
  - Jaminan persistensi data 100% offline tanpa risiko storage eviction dari browser.
  - Type-safe data access layer meminimalkan runtime error di lapangan.
  - UX cepat, responsif, dan stabil di berbagai tipe smartphone Android lapangan.
- **Negatif**:
  - Diperlukan pipeline CI terpisah untuk build dan test Flutter di GitHub Actions (`.github/workflows/mobile.yml`).
