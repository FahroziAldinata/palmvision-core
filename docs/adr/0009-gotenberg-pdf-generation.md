# ADR 0009: Pembangkitan Laporan PDF via Gotenberg & Ekspor Tabular Excel

## Status
Diterima (Accepted)

## Konteks
Sesuai PRD US-10, sistem harus mampu menghasilkan laporan produksi bulanan dan ringkasan taksasi dalam format cetak resmi (PDF dengan kop surat kebun, tabel produksi per blok, dan kolom tanda tangan Asisten & Manajer Kebun) serta format tabular untuk analisis lanjutan (Excel / CSV).

Untuk konversi HTML ke PDF di lingkungan Laravel, library PHP murni (seperti Dompdf atau TCPDF) sering menghadapi kendala kompatibilitas CSS modern (Flexbox, Grid, font kustom, atau tata letak kompleks). Di sisi lain, Puppeteer langsung di dalam container utama PHP membebani memori dan menambah dependensi browser Chrome yang berat.

## Keputusan

### 1. Pemanfaatan Microservice Gotenberg 8
Diputuskan menggunakan **Gotenberg 8** (`gotenberg/gotenberg:8`) sebagai microservice rendering PDF tanpa kepala (*headless Chromium*):
- Dijalankan sebagai container terisolasi di dalam jaringan internal Docker Compose (`palmvision-core-gotenberg-1` pada port internal 3000).
- Laravel merender view Blade standar (`reports/produksi_bulanan.blade.php`) dengan styling CSS murni dan tata letak cetak A4.
- `LaporanService` mengirim payload HTML melalui HTTP POST multipart ke endpoint `/forms/chromium/convert/html` milik Gotenberg.
- Gotenberg mengembalikan stream byte PDF dengan fidelitas tinggi sesuai tampilan web modern.

### 2. Format Tabular Excel dengan UTF-8 BOM
Untuk format spreadsheet/Excel:
- Dibuat format CSV berdelimeter koma yang diawali dengan byte penanda UTF-8 Byte Order Mark (`\xEF\xBB\xBF`).
- Hal ini menjamin file dapat langsung dibuka dengan sempurna di Microsoft Excel di berbagai sistem operasi tanpa teks rusak (*garbled text*), sekaligus menghemat dependensi berat pustaka PhpSpreadsheet di tahap ini.

## Konsekuensi
- **Positif**:
  - Tampilan cetak PDF sangat presisi dan mendukung fitur visual modern tanpa batasan parser HTML jadul.
  - Performa aplikasi PHP tetap cepat karena komputasi rendering browser didelegasikan ke container Gotenberg terpisah.
  - Format tabular ringan, cepat diunduh, dan ramah integrasi.
- **Negatif**:
  - Lingkungan produksi membutuhkan container Gotenberg aktif di jaringan internal.
