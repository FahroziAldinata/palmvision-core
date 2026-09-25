# PALMVISION — Plantation Monitoring System

> **Status Proyek: Tahap 4 (Forecasting Produksi: Prophet, Open-Meteo Rainfall, Model Registry & Dashboard Proyeksi) — SELESAI**  
> Repositori saat ini telah menyelesaikan **Tahap 4**. Telah terimplementasi modul:
> 1. Integrasi Curah Hujan Open-Meteo: Fetch historis harian dari Open-Meteo Archive API berbasis koordinat pusat kebun (`curah_hujan` table), ter-cache permanen di database tanpa pemanggilan live berulang.
> 2. Seeder Historis Sintetis Terisolasi (`ForecastingHistoricalSeeder`): 18 bulan histori produksi sawit berfluktuasi musiman realistis (peak crop Okt-Des, trek Feb-Apr), taksasi berkorelasi wajar, dan curah hujan selaras untuk portofolio demo.
> 3. Pipeline Model Time-Series Prophet (`palmvision-forecasting`): Model Meta Prophet per blok dengan regressor eksternal (curah hujan historis, usia tanaman, kategori tanah), seasonal adaptif, model registry (`storage/model_registry/{blok_id}/`), dan backtesting MAPE otomatis menjaga kontrak API `/api/v1/forecast`.
> 4. Penyimpanan Hasil & Jejak Audit (Aturan D5 PRD 7.2): Tabel `forecast_result` mencatat proyeksi, interval bawah/atas, `mape_model`, dan `versi_model` secara non-overwriting (riwayat lama tidak terhapus saat retraining).
> 5. Antrean Asinkronus Horizon: `POST /api/v1/forecast/generate` menjadwalkan `GenerateForecastJob` ke queue Horizon tanpa memblokir request HTTP.
> 6. Retraining Bulanan & Manual Trigger: `php artisan forecast:retrain` (dengan opsi `--queue`, `--kebun`, `--blok`) terjadwal otomatis setiap tanggal 1 awal bulan via Laravel Scheduler.
> 7. Dashboard Eksekutif & Manajer Kebun: Komponen `ForecastingSection.vue` interaktif dengan kartu proyeksi 3 bulan ke depan, interval kepercayaan 80%, skor akurasi MAPE transparan, dan penafian wajib PRD 11.5.
> Seluruh 81 Pest tests di `palmvision-core` dan 6 Pytest di `palmvision-forecasting` lulus 100%. Pint, Larastan Level 6, ESLint, dan Ruff bersih 0 error.

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
| **Tahap 3** | **Visualisasi Spasial Penuh GIS (Import GeoJSON & Shapefile, pg_tileserv, Status Deviasi 3-Warna, Filter Reaktif)** | **SELESAI** |
| **Tahap 4** | **Machine Learning Forecasting (Prophet, Open-Meteo, Horizon Queue, Model Registry, Dashboard Proyeksi)** | **SELESAI (Current)** |
| **Tahap 5** | PWA Mobile Offline-First untuk Mandor & Kerani Lapangan, Notifikasi & Webhook | Mendatang |
