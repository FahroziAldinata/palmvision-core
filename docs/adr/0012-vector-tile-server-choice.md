# ADR 0012: Pemilihan Vector Tile Server (pg_tileserv) & Strategi Proteksi Authenticated Proxy

## Status
Diterima (Accepted)

## Konteks
Sesuai PRD bagian 10.3 dan Deliverable 3.2, penyajian visualisasi peta skala luas (seluruh kebun atau grup perusahaan dengan ratusan hingga ribuan poligon) memerlukan Vector Tile Server di depan PostGIS agar peramban (browser) tidak terbebani mengunduh seluruh poligon GeoJSON secara sekaligus (*bulk loading*). Sesuai arahan arsitektur, endpoint tile server tidak boleh diekspos secara publik tanpa autentikasi, serta harus konsisten dengan pola layanan internal mikro sebelumnya (forecasting, Gotenberg).

## Opsi yang Dipertimbangkan

1. **pg_tileserv (`pramsey/pg_tileserv`)**:
   - Microservice ringan berbasis Go yang dikembangkan langsung oleh Paul Ramsey (PostGIS Core Steering Committee / Crunchy Data).
   - Beroperasi secara native membaca tabel spasial dan function PostGIS tanpa konfigurasi schema manual.
   - Port default: `7800` (tidak bentrok dengan port internal Gotenberg di `3000`).

2. **Martin (`maplibre/martin`)**:
   - Tile server berbasis Rust berperforma tinggi dari MapLibre.
   - Port default: `3000` (bentrok langsung dengan Gotenberg kecuali dilakukan re-mapping konfigurasi port).

3. **Strategi Keamanan Endpoint Tile**:
   - *Opsi A (Ekspos Port Publik + Basic Auth)*: Port tile server dibuka ke publik dengan token statis. Kurang fleksibel karena menyulitkan penegakan otorisasi hirarkis dinamis (RBAC Spatie & hierarki User).
   - *Opsi B (Internal Container + Laravel Authenticated Proxy)*: Container tile server hanya hidup di dalam private network Docker `sail`. Permintaan tile dari Leaflet client dialirkan melalui route Laravel `/gis/tiles/{z}/{x}/{y}.pbf` dengan middleware otentikasi session/sanctum dan pengecekan otorisasi pengguna.

## Keputusan
1. **Memilih `pg_tileserv` (`pramsey/pg_tileserv:latest`)** di dalam `compose.yaml`, terhubung ke database `pgsql` (PostGIS) melalui port internal 7800.
2. **Menerapkan Proteksi Laravel Authenticated Proxy**:
   - Container `tileserv` tidak diekspos ke host luar.
   - Rute Laravel `GET /gis/tiles/{z}/{x}/{y}.pbf` dilindungi middleware `auth`.
   - Controller melakukan verifikasi otorisasi pengguna dan mem-proxy stream MVT (*Mapbox Vector Tile* / Protocol Buffer) dengan header `Content-Type: application/x-protobuf` serta caching HTTP sesuai kebutuhan.

## Konsekuensi
- **Positif**:
  - Nol akses unauthenticated ke sumber data spasial PostGIS.
  - Otorisasi dan audit trail terintegrasi penuh dengan ekosistem Laravel (Sanctum, Session, Spatie RBAC).
  - Port microservice terpisah rapi tanpa konflik port dengan Gotenberg.
- **Negatif**:
  - Ada sedikit latensi proxy di aplikasi Laravel (dapat dimitigasi dengan caching HTTP/Redis jika beban tile sangat tinggi).
