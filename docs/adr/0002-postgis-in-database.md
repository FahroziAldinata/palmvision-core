# ADR 0002: PostGIS Directly in Relational Database

## Status
Diterima (Accepted)

## Konteks
Sistem perkebunan kelapa sawit sangat bergantung pada data spasial: batas poligon kebun, batas afdeling, dan poligon blok tanaman. Operasi spasial kritis seperti "hitung luas blok dari poligon (dalam hektar)", "deteksi poligon blok yang saling tumpang tindih (overlap)", dan "query blok dalam radius tertentu" menjadi fondasi validasi agronomi dan penentuan target taksasi (PRD Bagian 10.1 & 10.4).

Memisahkan fungsi GIS ke dalam layanan eksternal atau menarik seluruh koordinat poligon ke memori aplikasi Laravel untuk dihitung secara manual akan menimbulkan overhead transfer data GeoJSON besar dan inefisiensi komputasi yang masif.

## Keputusan
1. Menyimpan data spasial secara langsung di PostgreSQL menggunakan ekstensi **PostGIS**.
2. Seluruh komputasi spasial geometris/geografis (seperti `ST_Area`, `ST_Overlaps`, `ST_Intersects`, dan spatial indexing GiST) dieksekusi langsung di level database engine (PRD 10.1, 10.4).
3. Untuk penyajian peta web/mobile skala afdeling (volume kecil), API Laravel mengembalikan GeoJSON langsung dari database, sedangkan untuk skala besar (seluruh kebun/grup) disiapkan jalur vector tile server (`pg_tileserv` / `Martin`) di depan PostGIS (PRD 10.2 & 10.3).

## Konsekuensi
- **Positif**:
  - Operasi spasial terintegrasi penuh dalam transaksi ACID database; data atribut perkebunan dan geometrinya selalu konsisten.
  - Performa query spasial sangat tinggi berkat indeks spasial GiST (`geometry`/`geography`).
  - Tidak memerlukan dependensi layanan GIS terpisah yang rumit untuk operasi harian.
- **Negatif**:
  - PostgreSQL container dan production database memerlukan dependensi binaries PostGIS (`postgis/postgis:17-3.5`).
  - Backup/dump database harus mendukung tipe data spasial PostGIS (`pg_dump` standar).

## Alternatif yang Ditolak
1. **Layanan GIS Terpisah (Dedicated GIS Engine / GeoServer)**:
   Ditolak karena menambah komponen infrastruktur berat yang tidak sebanding untuk kebutuhan manipulasi poligon blok perkebunan sawit.
2. **Kalkulasi Spasial di Application Layer (Laravel PHP Memory)**:
   Ditolak karena sangat lambat, memboroskan memori server untuk poligon multi-vertex, dan tidak memanfaatkan akselerasi indeks spasial database.
