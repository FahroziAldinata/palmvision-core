# PALMVISION — Plantation Monitoring System

> **Status Proyek: Tahap 3 (Visualisasi Spasial Penuh GIS, Vector Tile Server, Import Batas Lahan) — SELESAI**  
> Repositori saat ini telah menyelesaikan **Tahap 3**. Telah terimplementasi modul:
> 1. Import Poligon Batas Blok: Mendukung berkas GeoJSON (`.geojson`, `.json`) dan ESRI Shapefile (`.zip`, `.shp`) berbasis parser pure-PHP (`gasparesganga/php-shapefile`).
> 2. Perhitungan Luas Area Otomatis: Dihitung secara presisi via PostGIS `ST_Area(poligon::geography) / 10000` dan memperbarui `blok.luas_ha`.
> 3. Validasi Kualitas GIS & Overlap Detection: Mendeteksi tumpang tindih batas lahan antar blok (`ST_Overlaps`) sebelum disimpan dan menolak dengan pesan jelas.
> 4. Non-Overwriting Version History: Penyimpanan poligon baru meng-increment kolom `versi` secara permanen untuk audit jejak perubahan lahan.
> 5. Vector Tile Server (`pg_tileserv`): Microservice MVT di port internal `7800` terproteksi melalui Laravel Authenticated Proxy (`/gis/tiles/{z}/{x}/{y}.pbf`) dengan scoping hierarki organisasi.
> 6. Ambang Batas 3 Tingkat Warna Status Produktivitas (ADR 0010): Hijau (≤ 5%), Kuning (5–15%), Merah (> 15%), dan Netral/Abu-abu.
> 7. Panel Detail Blok Interaktif (US-05 AC2): Klik poligon membuka panel ringkasan produksi 5 panen terakhir, taksasi terakhir, usia tanaman (tahun + bulan), tanggal rotasi panen berikutnya (pola 8/10 standar kebun sawit), dan riwayat versi GIS.
> 8. Filter Reaktif Peta: Filter dinamis per afdeling, per warna status produktivitas, dan rentang tanggal rotasi panen.
> 9. Strategi Render Dual-Mode Adaptif & Benchmark 1 Kebun Penuh (250 Blok, PRD Bagian 15): Otomatis menggunakan GeoJSON untuk ≤50 blok dan Vector Tile (`pg_tileserv`) untuk skala ratusan blok (>50 blok), menghasilkan reduksi payload hingga 47.7x (3.63 KB vs 173.25 KB).
> Seluruh 70 unit & feature tests lulus 100% (442 assertions). Pint dan Larastan Level 6 bersih 0 error.

---

## 1. Struktur Workspace

```
palmvision/
├── palmvision-core/          # Laravel 13 Modular Monolith (Git Repo 1)
│   ├── app/Domain/           # Domain Organisasi, Pemanen, Produksi, Taksasi
│   ├── docs/                 # PRD dan ADR arsitektur (ADR 0001 - 0009)
│   ├── compose.yaml          # Konfigurasi Sail + Gotenberg + Forecasting container
│   └── .github/workflows/    # CI untuk Laravel, PostGIS, Gotenberg, Pest, Pint, Larastan
├── palmvision-forecasting/   # FastAPI Time-Series Service (Git Repo 2)
│   ├── app/                  # REST API stub (/health, /api/v1/forecast)
│   ├── Dockerfile            # Container Python 3.12-slim
│   └── .github/workflows/    # CI untuk Ruff & Pytest
├── docker-compose.yml        # Orkestrasi dev tingkat root
├── .env.example              # Template environment root
└── README.md                 # Dokumentasi panduan instalasi & pengujian
```

---

## 2. Prasyarat Sistem

- **OS**: Linux / WSL2 Ubuntu
- **Docker Engine**: `>= 24.0` (terverifikasi v29.x)
- **Docker Compose**: `>= 2.20` (terverifikasi v5.5.x)
- **Git**: `>= 2.40`
- *(Catatan: Host tidak memerlukan instalasi lokal PHP, Composer, maupun Node.js karena seluruh eksekusi berjalan di dalam container Docker via Laravel Sail).*

---

## 3. Daftar Port yang Digunakan

| Service | Port Host / Internal | Protokol | Keterangan |
|---|---|---|---|
| `laravel.test` | `80` | HTTP | Web App & API Gateway |
| `laravel.test` (Vite) | `5173` | TCP | Vite HMR Dev Server |
| `pgsql` (PostGIS) | `5432` | TCP | PostgreSQL 17 + PostGIS 3.5 |
| `redis` | `6379` | TCP | Cache & Queue Worker |
| `mailpit` | `1025` / `8025` | SMTP / HTTP | Mailpit Web UI (`http://localhost:8025`) |
| `gotenberg` | `3000` *(internal)* | HTTP | Microservice Chromium HTML-to-PDF Converter |
| `tileserv` | `7800` *(internal)* | HTTP | Microservice Vector Tile Server (`pg_tileserv`) terproteksi proxy Laravel |
| `forecasting` | `8000` *(internal)* | HTTP | FastAPI time-series service (**internal only**, tidak diekspos ke publik) |

---

## 4. Panduan Setup dari Nol

### Langkah 1: Clone Repositori & Persiapkan Environment
```bash
# Salin konfigurasi environment
cp palmvision-core/.env.example palmvision-core/.env
```

### Langkah 2: Jalankan Seluruh Stack Service
Jalankan salah satu dari dua opsi berikut:

**Opsi A (dari root workspace):**
```bash
docker compose up -d
```

**Opsi B (via Sail dari `palmvision-core`):**
```bash
cd palmvision-core
./vendor/bin/sail up -d
```

### Langkah 3: Install Dependensi & Jalankan Migrasi
```bash
cd palmvision-core

# Install dependensi PHP (jika clone bersih)
./vendor/bin/sail composer install

# Generate application key
./vendor/bin/sail artisan key:generate

# Jalankan migrasi database (termasuk PostGIS extension)
./vendor/bin/sail artisan migrate

# Install frontend dependencies & build bundle
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

### Langkah 4: Buka Aplikasi di Browser
- **Web Dashboard**: Buka [http://localhost](http://localhost) (tersedia form registrasi & login menuju Dashboard Phase 0).
- **Health Check Status**: Buka [http://localhost/health](http://localhost/health) untuk melihat status koneksi seluruh service (Database, PostGIS, Redis, Forecasting).
- **Mailpit Dashboard**: Buka [http://localhost:8025](http://localhost:8025) untuk memantau email development.

---

## 5. Cara Menjalankan Test, Lint, dan Static Analysis

### A. Backend Core (`palmvision-core`)
```bash
cd palmvision-core

# 1. Menjalankan Test Suite (Pest)
./vendor/bin/sail artisan test

# 2. Menjalankan Code Formatter Check (Laravel Pint)
./vendor/bin/sail bin pint --test

# 3. Menjalankan Static Analysis (Larastan / PHPStan Level 6)
./vendor/bin/sail bin phpstan analyse

# 4. Menjalankan Frontend Linter & Type Check
./vendor/bin/sail npm run lint
./vendor/bin/sail npm run build
```

### B. Forecasting Service (`palmvision-forecasting`)
```bash
# 1. Menjalankan Linter (Ruff)
docker compose exec forecasting ruff check .

# 2. Menjalankan Test Suite (Pytest)
docker compose exec forecasting pytest -v

# 3. Uji Kontrak API Forecasting (PRD 9.3)
docker compose exec forecasting curl -s -X POST http://localhost:8000/api/v1/forecast \
  -H "Content-Type: application/json" \
  -H "X-API-Key: palmvision-dev-key" \
  -d '{"blok_id": "c1a2b3c4-d5e6-7f8a-9b0c-1d2e3f4a5b6c", "horizon_bulan": 3}'
```

### C. Hasil Uji Performa GIS (PRD Bagian 15 — Skala 1 Kebun Penuh / 250 Blok)

Dijalankan secara otomatis melalui `GisPerformanceTest.php` dengan dataset 250 blok (`PerformanceBenchmarkSeeder`):

| Parameter | Strategi A: GeoJSON Bulk | Strategi B: Vector Tile (`pg_tileserv`) | Manfaat / Catatan |
|---|---|---|---|
| **Cakupan Data** | 1 Kebun Penuh (250 Blok) | 1 Kebun Penuh (250 Blok, Tile z=12) | 100% poligon valid (`ST_IsValid = true`) |
| **Ukuran Data (Payload)** | **173.25 KB** (177.403 bytes) | **3.63 KB** (3.717 bytes) | **Vector Tile ~47.7x lebih ringan** |
| **Waktu Pemrosesan DB** | ~29 - 35 ms (JSON agregat) | ~140 - 220 ms (PostGIS `ST_AsMVT`) | `ST_AsMVT` memotong & meng-encode Protobuf |
| **Waktu Proxy Laravel** | - | ~10 - 28 ms | Dilindungi middleware otentikasi & scoping |
| **Total Waktu Pipeline** | ~30 ms | ~170 - 246 ms | Di bawah batas wajar UX interaktif |
| **Penggunaan Memori Klien** | Seluruh 250 poligon dimuat ke DOM | Hanya tile pada viewport aktif | Mencegah browser mobile kehabisan RAM |
| **Logika Dual-Mode Adaptif** | Dipilih saat kebun ≤ 50 blok | **Dipilih otomatis saat kebun > 50 blok** | Menyeimbangkan latensi vs beban bandwidth |

---

## 6. Peta Roadmap PALMVISION

| Tahap | Fokus Utama | Status |
|---|---|---|
| **Phase 0** | **Project Setup, Multi-Service Scaffolding, PostGIS, Quality Gates & CI** | **SELESAI** |
| **Tahap 1** | **Fondasi Organisasi (Grup, Kebun, Afdeling, Blok), Spasial Poligon, RBAC & Dashboard Leaflet** | **SELESAI** |
| **Tahap 2** | **Modul Bisnis: Produksi Harian (Pemanen), Taksasi Panen, Validasi Berjenjang, Laporan On-Demand** | **SELESAI** |
| **Tahap 3** | **Visualisasi Spasial Penuh GIS (Import GeoJSON & Shapefile, pg_tileserv, Status Deviasi 3-Warna, Filter Reaktif)** | **SELESAI (Current)** |
| **Tahap 4** | Machine Learning Forecasting (Integrasi Model Prophet/SARIMA di Python) | Mendatang |
| **Tahap 5** | PWA Mobile Offline-First untuk Mandor & Kerani Lapangan, Notifikasi & Webhook | Mendatang |
