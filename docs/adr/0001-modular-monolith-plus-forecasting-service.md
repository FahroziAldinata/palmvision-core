# ADR 0001: Modular Monolith (Laravel) + Dedicated Forecasting Service (FastAPI)

## Status
Diterima (Accepted)

## Konteks
Sistem monitoring perkebunan kelapa sawit PALMVISION membutuhkan pengelolaan proses bisnis transaksional yang kompleks (produksi harian, sensus panen/taksasi, GIS, kepegawaian, pemupukan, integrasi timbangan/PKS) sekaligus pemrosesan komputasi statistik machine learning time-series (Prophet / SARIMA) untuk peramalan panen jangka menengah (1–6 bulan).

Ekosistem PHP/Laravel sangat unggul dalam produktivitas, integritas relasional, arsitektur domain-driven, dan integrasi frontend (Inertia.js). Namun, ekosistem data science, machine learning, dan pustaka time-series modern (Prophet, Statsmodels, Pandas) berbasis dominan di Python. Memaksakan model machine learning di PHP atau sebaliknya memecah seluruh domain bisnis menjadi microservices penuh akan memicu kompleksitas distributed transaction dan overhead operasional yang tidak perlu (PRD Bagian 8 & 12.3).

## Keputusan
1. Mengadopsi arsitektur **Modular Monolith** untuk repositori utama (`palmvision-core`) menggunakan Laravel, di mana setiap domain bisnis diisolasi dalam subfolder `app/Domain/`.
2. Memisahkan modul peramalan deret waktu ke dalam repositori layanan terpisah (`palmvision-forecasting`) berbasis Python / FastAPI.
3. Menghubungkan kedua sistem melalui REST API internal privat yang diamankan via API key internal antar-layanan (PRD 9.3).

## Konsekuensi
- **Positif**:
  - Batas domain bisnis operasional tetap bersih dan transaksional dalam satu database monolit tanpa beban distributed transaction (Two-Phase Commit).
  - Layanan forecasting dapat diskalakan secara independen (CPU-intensive saat retraining berkala bulanan) tanpa mengganggu throughput server aplikasi transaksional (PRD 12.3).
  - Memanfaatkan pustaka time-series Python terbaik (Prophet, Scikit-learn) secara native.
- **Negatif**:
  - Diperlukan orkestrasi dua runtime berbeda (PHP & Python) di lingkungan deployment dan CI/CD.
  - Perlu memelihara kontrak API dan skema transfer data eksplisit antara kedua repositori.

## Alternatif yang Ditolak
1. **Full Microservices (memecah Produksi, Taksasi, GIS, Kepegawaian ke service terpisah)**:
   Ditolak karena menambah overhead distributed system, kompleksitas jaringan, dan latensi antar-service untuk ukuran beban kerja operasional perkebunan tahap awal–menengah.
2. **Pure PHP Monolith (menjalankan komputasi forecasting langsung di Laravel/PHP)**:
   Ditolak karena minimnya pustaka time-series mature untuk musiman panen sawit (Prophet/SARIMA) di ekosistem PHP.
