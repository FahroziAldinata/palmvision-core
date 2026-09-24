# ADR 0011: Pemilihan Library Parser Shapefile untuk Impor Poligon GIS

## Status
Diterima (Accepted)

## Konteks
Sesuai PRD US-06 AC1, tim GIS memerlukan kemampuan untuk mengimpor poligon batas blok dari berkas ESRI Shapefile (`.shp`, didampingi `.dbf` dan `.shx`) selain format standar GeoJSON. Diperlukan penentuan arsitektur parser di sisi backend Laravel: apakah memanggil binary sistem eksternal (`GDAL/ogr2ogr` via process exec) atau memanfaatkan library murni PHP (*pure PHP shapefile reader*).

## Opsi yang Dipertimbangkan

1. **GDAL / ogr2ogr CLI Utility**:
   - Kelebihan: Standar industri GIS global, mendukung berbagai proyeksi dan format file spasial.
   - Kekurangan: Memerlukan paket sistem level OS (`gdal-bin`, `libgdal-dev` berukuran 500MB+) di Docker runtime Sail, runner GitHub Actions CI, dan server produksi. Menimbulkan overhead *process execution* dan potensi celah keamanan command execution.

2. **Pure PHP Reader (`gasparesganga/php-shapefile`)**:
   - Kelebihan: Ditulis murni dalam PHP tanpa dependensi library C/C++ eksternal. Portabel di semua lingkungan (Sail, CI runner, production), berbobot ringan, PSR-4 autoloaded, berlisensi MIT, serta langsung menghasilkan format WKT (*Well-Known Text*) dan GeoJSON yang kompatibel dengan fungsi `ST_GeomFromGeoJSON()` / `ST_GeomFromText()` di PostGIS.
   - Kekurangan: Terfokus pada berkas ESRI Shapefile standar (Point, Polyline, Polygon) dan membaca langsung atribut dBASE (`.dbf`).

## Keputusan
Diputuskan untuk menggunakan **`gasparesganga/php-shapefile`**.

Alasan:
- Tidak membebani container Docker Sail dan runner GitHub Actions dengan dependensi OS GDAL yang besar dan lambat di-build.
- Membaca langsung archive ZIP (atau file `.shp` + `.dbf` + `.shx`) yang diunggah pengguna dan mengekstrak poligon menjadi GeoJSON/WKT secara instan di dalam memori/temporary directory PHP.
- Hasil ekstraksi poligon langsung divalidasi dan dihitung ulang luasnya melalui PostGIS native (`ST_Area()`, `ST_Overlaps()`).

## Konsekuensi
- **Positif**:
  - Implementasi 100% portabel dan testable di PHPUnit / Pest tanpa setup container tambahan.
  - Alur impor poligon konsisten antara berkas GeoJSON maupun Shapefile.
- **Negatif**:
  - Berkas Shapefile yang diunggah harus dalam proyeksi WGS84 (EPSG:4326) standar sistem PALMVISION atau poligon 2D yang didukung.
