# ADR 0006: Pemilihan Leaflet sebagai Pustaka Peta Dasar Spasial (Tahap 1)

## Status
Diterima (Accepted)

## Konteks
Pada Tahap 1 (US-05 versi minimal), sistem membutuhkan satu komponen peta web untuk memvalidasi visualisasi poligon blok spasial hasil migrasi PostGIS dari data seed. Kebutuhan Tahap 1 sangat fokus dan terarah:
1. Menampilkan poligon blok dari format GeoJSON.
2. Styling seragam (belum membutuhkan pewarnaan heatmap/produktivitas Tahap 3).
3. Interaktivitas minimal berupa popup nama/kode blok saat diklik (belum membutuhkan panel detail riwayat produksi penuh Tahap 3).
4. Tidak membutuhkan vector tile server (yang baru dijadwalkan di Tahap 3).

Terdapat dua kandidat utama di ekosistem frontend: **Leaflet** dan **MapLibre GL JS**.

## Keputusan
Memilih **Leaflet** (`leaflet` + `@types/leaflet`) sebagai pustaka peta dasar untuk Tahap 1.

Alasan pertimbangan teknis:
1. **Ringan dan Efisien**: Ukuran bundle Leaflet sangat ramping (~40 KB gzipped) dibandingkan MapLibre GL (~250+ KB gzipped).
2. **Dukungan GeoJSON Native Sederhana**: Leaflet memiliki API bawaan `L.geoJSON` yang sangat langsung, elegan, dan minim boilerplate untuk mem-parsing koordinat polygon dari PostGIS.
3. **Bebas Ketergantungan WebGL/Token Eksternal**: Tidak memerlukan WebGL pipeline yang kompleks, shader overhead, ataupun token API pihak ketiga (cukup memakai base tile OpenStreetMap / Carto gratis).
4. **Stabilitas & Kecepatan Integrasi Vue 3**: Sangat mudah diintegrasikan dalam siklus hidup komponen Vue 3 (`onMounted`, `onBeforeUnmount`, `watch`).

## Konsekuensi
- **Positif**:
  - Implementasi cepat, stabil, tanpa isu kompatibilitas browser headless saat automated testing.
  - Memenuhi 100% kriteria penerimaan US-05 minimal untuk Tahap 1.
- **Negatif**:
  - Untuk Tahap 3 nanti, jika volume data poligon mencapai puluhan ribu blok dan memerlukan visualisasi 3D atau rendering vector tile berkecepatan 60 FPS, arsitektur peta dapat dievaluasi ulang untuk migrasi ke MapLibre GL dengan vector tile pipeline.

## Alternatif yang Ditolak
1. **MapLibre GL JS**:
   Ditolak untuk Tahap 1 karena menambahkan *unnecessary complexity* (kebutuhan style JSON, WebGL canvas, overhead konfigurasi sumber vector tile) untuk fitur yang di Tahap 1 hanya membutuhkan visualisasi poligon GeoJSON 2D statis dari database seeder.
2. **Google Maps JS API**:
   Ditolak karena membutuhkan kartu kredit/billing API key eksternal, berbiaya komersial, dan bertentangan dengan prinsip open-source self-hosted stack PALMVISION.
