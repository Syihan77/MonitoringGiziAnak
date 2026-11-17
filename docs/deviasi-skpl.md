# Deviasi Terdokumentasi dari SKPL

Dokumen ini mencatat perbedaan implementasi yang disengaja terhadap
`SKPL_Sistem_Monitoring_Gizi_Anak_Stunting_v2.0_revisi.md` beserta alasannya.
Semua deviasi harus disetujui pemilik produk sebelum pilot.

## 1. Token Sanctum opaque, bukan JWT

- SKPL: login menerbitkan `jwt_string`.
- Implementasi: Laravel Sanctum menerbitkan token opaque.
- Alasan: Sanctum sudah tersedia dan cukup untuk API token berbasis Bearer;
  tidak menambah dependensi JWT.
- Dampak: masa berlaku tetap 24 jam, header tetap
  `Authorization: Bearer <token>`, dan logout mencabut token aktif.
  Klien tidak boleh mengurai isi token.

## 2. `PATCH` dan `DELETE` pada anak

- SKPL: pembaruan profil anak memakai `PUT`.
- Implementasi: `PUT`, `PATCH`, dan `DELETE /anak/{id}` tersedia untuk pemilik
  atau admin.
- Alasan: `PATCH` memudahkan pembaruan sebagian; `DELETE` diperlukan untuk
  koreksi data yang salah saat pendampingan.
- Dampak: ini superset kontrak. Klien lama yang hanya memakai `PUT` tetap
  kompatibel. Penghapusan tetap melalui policy kepemilikan (anti-IDOR).

## 3. `energi_per100g` dan kolom gizi TKPI NULLABLE

- SKPL: kolom gizi per 100 g dinyatakan NOT NULL.
- Implementasi: `energi_per100g` ditambahkan; kolom gizi lain boleh NULL bila
  sel sumber TKPI 2020 kosong.
- Alasan: CSV TKPI memang memiliki sel kosong; memaksa angka akan menciptakan
  data palsu. `energi_per100g` dibutuhkan untuk menghitung %AKG energi.
- Dampak: analisis harus memperlakukan NULL sebagai “tidak diketahui”, bukan
  nol. Sejak aturan status disetujui, `DATA_LENGKAP` mensyaratkan empat makro
  per porsi (energi, karbohidrat, protein, lemak) tidak NULL; nilai mikro NULL
  tetap boleh dan tidak boleh ditampilkan sebagai 0/defisiensi.
- Sumber gizi sejak 14 November 2026:
  `database/seeders/data/dataset_resep_gizi_terstandarisasi_berlabelusia_tkpi.csv`
  (93 resep, 26 kolom, 10 kolom gizi). Audit per zat:
  `docs/audit_gizi_tkpi_semua_resep.csv` (930 baris; 736 terhitung,
  194 tidak terhitung). Komposisi: 33 lengkap, 46 sebagian, 14 kosong semua.
  CSV lama hanya arsip; `resep:normalisasi-gizi` dihapus dan
  `resep:muat-gizi-tkpi` menggantikannya untuk database lama.
- Asumsi estimasi TKPI: tuna dipetakan ke ikan cakalang; abon tuna ke abon ikan;
  soun ke bihun (R016, R022, R046, R067, R068, R082, R092); gula merah ke Gula
  kelapa; gula pasir ke Gula putih; vitamin A (RE) = retinol + β-karoten/6;
  beberapa nilai zinc diimputasi; 18 resep memperoleh >50% massa dari konversi
  takaran rumah tangga. Hasil tepat 0 untuk kalsium, zinc, zat besi, dan serat
  dianggap tidak tersedia. Semua nilai adalah estimasi dan belum divalidasi
  ahli gizi.
- 14 resep dengan seluruh gizi NULL diturunkan ke `PERLU_VERIFIKASI` dengan
  catatan alasan karena empat makro tidak lengkap. Komposisi status setelah
  aturan disetujui: 62 `DATA_LENGKAP` dan 31 `PERLU_VERIFIKASI`; tidak ada
  resep `TERVERIFIKASI` dari seeder. `resep:muat-gizi-tkpi` menerapkan aturan
  yang sama secara idempoten pada database lama. Terlepas dari status dan mode
  demo, `filterWajib()` membuang resep tanpa empat makro dari kandidat, sehingga
  resep tersebut tidak dapat mengisi kekurangan hasil ML2 atau direkomendasikan.
- Basis ML2 sudah dibangun ulang dari CSV TKPI (versi `1.1.0-tkpi`, 93 resep).
  `ml:verify-basis` pada 15 November 2026: 93 cocok / 0 selisih / exit 0.
  Nilai kosong tetap NULL; 14 resep tanpa gizi tidak diganti nol dan tidak
  mendapat kredit ranking. Kebijakan `penalti` dipertahankan, sehingga respons
  mentah ML2 pada `top_n=50` yang hanya memprioritaskan vitamin C masih dapat
  memuat mereka di ekor; Laravel membuangnya lewat `filterWajib()` sebelum
  kandidat dikirim maupun dipakai. Ini diuji pada skenario vitamin C `top_n=50`.

- Label dan metrik K-Means masih berasal dari basis lama dan ditandai
  `status_cluster: "USANG_DATA_LAMA"`. Refit KMeans menunggu kebijakan nilai
  kosong yang disetujui; label cluster tidak boleh ditampilkan sebagai analisis
  gizi di Laravel.

## 4. Dukungan usia 0–60 bulan

- SKPL: target utama 12–59 bulan.
- Implementasi: validasi pertumbuhan menerima 0–60 bulan, sedangkan resep
  memiliki batas `usia_min_bulan` sendiri.
- Alasan: baku WHO menyediakan data 0–60 bulan; membatasi input akan
  menghalangi pemantauan bayi dan anak tepat 60 bulan.
- Dampak: rekomendasi menu tetap memakai batas usia resep. Resep pada dataset
  saat ini memiliki `usia_min_bulan=12`, sehingga tidak ditawarkan untuk bayi
  di bawah 12 bulan.

## Catatan implementasi (bukan perubahan kontrak)

- Label usia resep (`usia_label_bulan`) berasal dari label CSV dan tidak
  dihitung ulang; `usia_min_bulan` divalidasi 12 bulan.
- Ambang `kategori_pemantauan` di `config/nutrition.php` masih baseline
  sementara dan perlu validasi ahli gizi.
- Mode demo `DATA_LENGKAP` hanya untuk non-production. Flag
  `NUTRITION_DEMO_ALLOW_DATA_LENGKAP` diabaikan saat `APP_ENV=production`.
- Dataset awal tidak berisi resep `TERVERIFIKASI`; verifikasi dilakukan lewat
  endpoint admin setelah validasi ahli.
