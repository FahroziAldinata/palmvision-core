# PALMVISION — Core Service (Backend Monolith)

> **Status Proyek: Tahap 5 — SELESAI**  
> Backend core Laravel 13 Modular Monolith dengan PostGIS, Sail, Gotenberg, pg_tileserv proxy, dan integrasi time-series forecasting service.

---

## Modul Tahap 5 yang Telah Selesai:
1. **Endpoint Mobile Offline Synchronization (`SyncController`)**:
   - `GET /api/v1/mobile/bootstrap`: Download master data offline (kebun, afdeling, blok, pemanen).
   - `POST /api/v1/produksi/sync`: Batch sync produksi panen dengan `client_uuid` idempotency dan deteksi deviasi waktu jam perangkat (`perlu_tinjauan_waktu: true` jika selisih > 24 jam).
   - `POST /api/v1/taksasi/sync`: Batch sync taksasi panen dengan `client_uuid` idempotency.
   - `POST /api/v1/auth/login`: Sanctum token auth untuk mobile worker.
2. **Notifikasi Ambang Batas Otomatis (UC-13)**:
   - Command `palmvision:check-thresholds` terjadwal setiap 06:00 pagi.
   - Deteksi deviasi taksasi > 15% (`TaksasiDeviationNotification`).
   - Deteksi rotasi panen terlambat $\ge 1$ hari (`MissedHarvestRotationNotification`).
   - Dual-channel: `database` + `mail` (Mailpit).
   - Indikator lonceng notifikasi interaktif di UI web (`AuthenticatedLayout.vue`).
3. **Webhook PKS / Timbangan Mock**:
   - `POST /api/v1/webhooks/pks/rendemen` (dan `/webhooks/pks/rendemen`).
   - Header autentikasi: `X-API-Key` dan `X-Signature-SHA256` (HMAC SHA-256).
   - Pencatatan data ke tabel `pks_rendemen`.
4. **Modul Pemupukan Minimal Scope (ADR 0016)**:
   - Skema tabel `pemupukan` (blok, tanggal, jenis pupuk, dosis kg, keterangan).
   - Web view `/pemupukan` dengan form input dan tabel rekapitulasi riwayat aplikasi pupuk.
5. **ADR Arsitektur Baru**:
   - [ADR 0015: Pemilihan Flutter dan Drift (SQLite)](file:///home/rozi/projects/palmvision/palmvision-core/docs/adr/0015-mobile-flutter-choice.md)
   - [ADR 0016: Batasan Ruang Lingkup Modul Pemupukan](file:///home/rozi/projects/palmvision/palmvision-core/docs/adr/0016-pemupukan-minimal-scope.md)
   - [ADR 0017: Penegasan Batasan Ruang Lingkup Modul Tenaga Kerja](file:///home/rozi/projects/palmvision/palmvision-core/docs/adr/0017-tenaga-kerja-exclusion.md)

---

## Verifikasi Kualitas & Test
```bash
./vendor/bin/sail artisan test
./vendor/bin/sail bin pint --test
./vendor/bin/sail bin phpstan analyse
./vendor/bin/sail npm run build
```
Hasil: **98/98 tests lulus 100% (704 assertions)**.
