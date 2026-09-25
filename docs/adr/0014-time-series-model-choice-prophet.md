# ADR 0014: Pemilihan Algoritma Time-Series Forecasting (Facebook/Meta Prophet)

## Status
Diterima (Accepted)

## Konteks
Sesuai PRD Bagian 11.2 dan US-07, PalmVision memerlukan model machine learning per blok untuk memproyeksikan estimasi produksi Tandan Buah Segar (TBS) 1–3 bulan ke depan. Dua alternatif utama yang dipertimbangkan adalah **Prophet** (Meta) dan **SARIMA** (*Seasonal Autoregressive Integrated Moving Average*).

## Perbandingan Opsi
1. **Facebook/Meta Prophet**:
   - Didesain khusus untuk time-series bisnis dengan komponen musiman kuat (*yearly seasonality* seperti siklus panen puncak / *peak crop* dan musim trek sawit).
   - Mendukung penambahan variabel regressor eksternal (*extra regressors*) dengan API deklaratif (`add_regressor`), misalnya curah hujan historis bulanan dan usia tanaman.
   - Robust terhadap data yang bolong (*missing values*) atau pergeseran tren (*trend changepoints*).
   - Menghasilkan interval kepercayaan prediksi (*uncertainty intervals*: `yhat_lower`, `yhat_upper`) secara otomatis tanpa perlu bootstrap manual.
   - Model mudah disimpan per blok ke model registry dalam format serialisasi standar (`joblib` / `json`).

2. **SARIMA (Statsmodels)**:
   - Model statistik klasik yang sangat kuat untuk data stasioner dengan parameter $(p, d, q) \times (P, D, Q)_s$.
   - Membutuhkan uji stasioneritas (ADF test) dan penentuan parameter musiman yang sensitif per blok; kegagalan konvergensi sering terjadi jika data relatif pendek (12–18 bulan).
   - Penambahan variabel eksogen (SARIMAX) memerlukan asumsi multivariat ketat.

## Keputusan
Memilih **Facebook/Meta Prophet** sebagai mesin forecasting utama di layanan `palmvision-forecasting`:
1. Model dilatih **per blok** (bukan model tunggal agregat kebun) sesuai PRD 11.2.
2. Memanfaatkan regressor eksternal: curah hujan bulanan, usia tanaman (bulan), dan kategori tanah.
3. Model terlatih disimpan dalam *Model Registry* di direktori `storage/model_registry/{blok_id}/` dengan format nama terversi `prophet-v{n}-{periode}` (misal `prophet-v1-2026Q4`).
4. Evaluasi model menggunakan metrik MAPE (*Mean Absolute Percentage Error*) yang dihitung melalui prosedur backtesting (holdout period) dan nilainya disimpan di setiap baris tabel `forecast_result` untuk transparansi audit ke manajemen.

## Konsekuensi
- **Positif**:
  - Konvergensi cepat dan stabil pada horizon 1–12 bulan.
  - Interval ketidakpastian (batas atas dan bawah) tersedia langsung untuk ditampilkan di grafik dashboard.
  - Memudahkan integrasi fitur agronomis masa depan (misal pemupukan atau defisit air).
- **Negatif**:
  - Dependensi library Python (`prophet`, `cmdstanpy`) membutuhkan image Docker Python yang sedikit lebih besar dibanding library time-series murni.
