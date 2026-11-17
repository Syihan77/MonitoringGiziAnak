# Kontrak API NutriTumbuh v1 (Backend)

Acuan: SKPL v2.0 §5.4. Base URL: `/api/v1`. Format: JSON UTF-8.
Autentikasi: `Authorization: Bearer <token>` — wajib kecuali `POST auth/register`
dan `POST auth/login`. Token Sanctum kedaluwarsa 24 jam.

## 1. Envelope

Sukses: `{ "success": true, "data": {...}, "message": "..." }`
Galat: `{ "success": false, "error_code": "...", "message": "...", "errors?": {...} }`

## 2. Kode galat (SKPL §5.4.11 persis)

| Kode | HTTP | Arti |
|---|---|---|
| `CONSENT_REQUIRED` | 400 | `consent_approved` false saat registrasi |
| `VALIDATION_ERROR` | 400 | Field tidak valid; rincian di `errors` |
| `INVALID_CREDENTIALS` | 401 | Email/password salah (tanpa token) |
| `TOKEN_EXPIRED` | 401 | Bearer tercatat tetapi `expires_at` lewat |
| `UNAUTHENTICATED` | 401 | Tanpa/bearer tidak valid |
| `FORBIDDEN_RESOURCE` | 403 | RBAC/IDOR: bukan pemilik, lintas wilayah, non-admin |
| `RESOURCE_NOT_FOUND` | 404 | ID tidak ditemukan |
| `RATE_LIMIT_EXCEEDED` | 429 | > 120 req/menit/token |
| `INTERNAL_ERROR` | 500 | Kegagalan tak terduga |

Konvensi umum (SKPL §5.4.1): `?page` (default 1) & `?limit` (default 20, maks 100)
untuk endpoint daftar; timestamp ISO-8601 UTC (`config/app.php` timezone UTC).

## 3. Endpoint

### Auth (SKPL-F-01/F-02)
- `POST /auth/register` → 201 `{user_id, role}`. Body: `nama, email (unik),
  password (min 8, huruf+angka), role (orang_tua|kader), wilayah_posyandu
  (wajib jika kader + harus terdaftar di master wilayahs), no_telp?, consent_approved`.
- `POST /auth/login` → 200 `{token, role, user_id}`.
- `POST /auth/logout`, `GET /me` (auth).

### Anak (SKPL-F-03, ortu = pemilik; kader = baca sewilayah; admin = semua)
- `POST /anak` → 201 `{anak_id, nama, tanggal_lahir, usia_bulan, jenis_kelamin}`.
  Body: `nama, tanggal_lahir (≤ hari ini), jenis_kelamin (L|P), alamat?`.
- `GET /anak?page&limit` → paginator Laravel (`data.data[]` + `total`).
  Tambahan `?ringkas_ukur=1`: tiap item sertakan `pemilik_nama`, `wilayah`,
  dan `ukur_terakhir {..., kategori_pemantauan, metode_klasifikasi,
  versi_aturan, status_validitas_pengukuran, pesan}` (null bila belum diukur).
- `GET /anak/{id}` → detail + `alamat` + `pemilik_wilayah`.
- `PUT|PATCH /anak/{id}` (pemilik/admin), `DELETE /anak/{id}` (pemilik/admin,
  superset SKPL — hapus измерения ikut cascade).

### Pertumbuhan (SKPL-F-04/F-05)
- `POST /anak/{id}/pertumbuhan` → 201 `{pertumbuhan_id, usia_bulan_saat_ukur,
  umur_bulan, zscore_bb_u, zscore_tb_u, zscore_bb_tb, kategori_pemantauan,
  metode_klasifikasi, versi_aturan, status_validitas_pengukuran, pesan,
  status_rincian{bb_u,tb_u,bb_tb}}`.
  Body: `berat_badan_kg (0.5–40), tinggi_badan_cm (30–150),
  tanggal_ukur (≤ hari ini, umur 0–60 bln), posisi_ukur? (terlentang|berdiri)`.
  Aturan: umur bulan-penuh; koreksi +0,7 cm (0–24 bln berdiri) / −0,7 cm
  (> 24 bln terlentang); varian rujukan PB (< 24 bln) / TB (≥ 24 bln).
  Nilai `kategori_pemantauan`: `NORMAL_LOW_RISK | AT_RISK_MONITORING |
  HIGH_RISK_REFERRAL` (indikatif, bukan diagnosis).
- `GET /anak/{id}/pertumbuhan?dari_tanggal&sampai_tanggal&limit` → riwayat
  terurut tanggal (kosong → 200 `[]`).

### Konsumsi & gizi (SKPL-F-06/F-07, tulis = pemilik/admin)
- `POST /anak/{id}/konsumsi` → 201. Body: `tanggal_konsumsi`,
  `items[{bahan_id, porsi_gram, satuan? (gram|kg, default gram)}]` (gram disimpan).
- `GET /anak/{id}/konsumsi?tanggal=` → item + nama bahan.
- `GET /anak/{id}/analisis-gizi?tanggal=` → 200 `{tanggal, kelompok_umur,
  ringkasan[{zat_gizi, total, akg, persentase_akg, status}],
  rekomendasi_tersedia, analisis_status}`.
  Zat: `energi (info), protein, lemak, karbohidrat, zat_besi, seng`.
  Status: `≥80 cukup, 70–80 kurang, <70 sangat_kurang` (`config/nutrition.php`).
  Jika salah satu bahan memiliki zat NULL, `total` dan `persentase_akg` menjadi
  `null` serta `status: tidak_tersedia`; nilai itu tidak dihitung sebagai 0.
  Tanpa data → `ringkasan: [], analisis_status: tidak_dapat_dianalisis`.
  AKG (Permenkes 28/2019, tanpa beda L/P): 1–3th E1350/P20/L45/KH215/Fe7/Zn3;
  4–6th E1400/P25/L50/KH220/Fe10/Zn5 (+ kelompok 0–5/6–11 bln).
- `GET /anak/{id}/gizi-bulanan?bulan=YYYY-MM` → 200 `{bulan, hari_tercatat,
  rata_persen_akg, status (TERPENUHI|KURANG TERPENUHI|TIDAK TERPENUHI|
  DATA_TIDAK_LENGKAP|belum_tercatat|tidak_tersedia), zat_terendah,
  rata_per_zat}` untuk panel Status Pemenuhan Gizi. Rata dihitung dari %
  harian yang di-cap 100%/zat (kelebihan ekstrem tak menyeret mean). Zat
  harian NULL tidak dianggap 0; `rata_per_zat` zat tersebut `null` dan tidak
  ikut rata-rata. Bila ada zat yang tidak tersedia pada hari mana pun dalam
  bulan itu, status menjadi `DATA_TIDAK_LENGKAP` meskipun rata-rata zat
  tersedia mencapai 80%; status `TERPENUHI` hanya dipakai bila semua zat
  lengkap pada semua hari tercatat. Jika semua zat tidak tersedia,
  `rata_persen_akg=null` dan status `tidak_tersedia`.

### Rekomendasi & resep (SKPL-F-08/F-09, pemilik/admin)
- `POST /anak/{id}/rekomendasi-menu` → 200 `{jumlah_rekomendasi,
  metode_ranking (MODEL_ML|BASELINE_ATURAN),
  ml2{tersedia, alasan_tidak_tersedia, status_model, jenis_ranking,
  status_validasi, versi_model, metode, jumlah_dikembalikan, jumlah_dipakai},
  menu[{peringkat, resep_id, nama_resep, kategori_gizi, tekstur_sajian,
  jumlah_porsi_hasil, gizi_per_porsi, skor_gizi_umum, skor_relevansi,
  skor_akhir, sumber_ranking (ML2|LOKAL), skor_ml2?, alasan_peringkat,
  kecocokan_bahan?, alergen,
  usia_min_bulan, usia_label_bulan,
  usia_minimal_bulan_jika_dimodifikasi, label_data, status_verifikasi_data,
  cara_memasak, bahans[{bahan_id, nama_bahan, takaran_jumlah, takaran_satuan,
  takaran_gram_ekuivalen, catatan_bahan}], perlu_modifikasi,
  instruksi_modifikasi, catatan_resep, pemeriksaan_pantangan,
  peringatan_modifikasi}], defisiensi_terdeteksi, saran, peringatan,
  pemeriksaan_pantangan}`.
  Body: `bahan_tersedia? [uuid], top_k? (1–5), tanggal?`. Tidak ada
  `anggaran_max`; field itu ditolak 400. Default hanya resep `TERVERIFIKASI`;
  resep tanpa bahan, tanpa satuan, tanpa cara memasak, atau tanpa salah satu
  dari energi/karbohidrat/protein/lemak per porsi tidak masuk kandidat (juga
  saat mode demo). `DATA_LENGKAP` mensyaratkan keempat makro tersebut tidak
  NULL; resep yang tidak memenuhi diturunkan ke `PERLU_VERIFIKASI` dengan
  catatan alasan. Gizi NULL tetap ditampilkan `null` di `gizi_per_porsi` dan
  tidak dianggap 0; zat NULL diabaikan saat menghitung skor.
  Mode demo non-production dapat mengizinkan `DATA_LENGKAP` lewat
  `NUTRITION_DEMO_ALLOW_DATA_LENGKAP=true`, dengan `mode_demo`,
  `label_data=DEMO_BELUM_TERVERIFIKASI`, dan `peringatan_demo`. Flag ini
  diabaikan saat `APP_ENV=production`. Jika kosong, `saran` berisi
  “Belum ada resep terverifikasi yang cocok untuk kriteria ini.”
  Tercatat di `rekomendasi_logs`.

  Integrasi ML2: Laravel mengumpulkan kandidat lokal lebih dulu
  (verifikasi/demo, usia, alergen/pantangan, kelengkapan data, bahan tersedia).
  ML2 dipanggil dengan `top_n=50`, `metode=baseline`,
  `kebijakan_nilai_hilang=penalti`, `prioritas_gizi` dari defisiensi lokal
  (`seng` menjadi `zinc`), nama bahan tersedia, dan `bahan_dihindari`; tanpa
  bahan terpilih `mode_bahan=nonaktif`. Laravel hanya memakai ID ML2 yang ada
  di kandidat lokal (resep tanpa empat makro sudah dibuang), mengikuti urutan
  rank ML2, lalu melengkapi kekurangan dari
  ranking lokal sampai `top_k`. `metode_ranking=MODEL_ML` hanya bila minimal
  satu item berasal dari ML2; jika tidak, seluruh menu berlabel `LOKAL` dan
  metode `BASELINE_ATURAN`. `filterWajib()` tetap penentu akhir
  alergen/pantangan; `bahan_dihindari` ke ML2 hanya lapisan tambahan.
  `status_model=EKSPERIMEN`, `jenis_ranking=PERSENTIL_CONTENT_BASED`, dan
  `status_validasi=BELUM_DIVALIDASI_AHLI_GIZI` adalah metadata Laravel,
  bukan klaim dari paket ML.
- `GET /resep/{resepId}` (semua peran login) → bahan + takaran +
  `gizi_per_porsi` {energi, protein, lemak, karbohidrat, serat, kalsium,
  zat_besi, zinc, vitamin_a, vitamin_c} + `status_verifikasi_data`.
  Nilai gizi `null` berarti data tidak tersedia, bukan 0.

### Kader (SKPL-F-10, kader/admin; ortu ditolak)
- `GET /kader/dashboard?wilayah=&bulan=` (wajib; harus = wilayah profil kader) →
  `{wilayah, total_anak_binaan, anak_terukur,
  distribusi_kategori_pemantauan{NORMAL_LOW_RISK,AT_RISK_MONITORING,
  HIGH_RISK_REFERRAL}, tren_prevalensi[{bulan,label,normal,at_risk,high_risk,
  tidak_terklasifikasi,total_diukur,persen_perlu_perhatian,denominator_kecil}]}`
  dari pengukuran terakhir tiap anak; anonim (tanpa nama). `bulan` 1–12,
  default 6; `persen_perlu_perhatian=null` bila tidak ada data bulan itu.
- `GET /anak/{id}/tren-pertumbuhan?dari_tanggal&sampai_tanggal` → riwayat
  ukur + acuan `bb_u`/`tb_u`; varian `pb` (<24 bln) atau `tb` (≥24 bln).
- `GET /kader/anak/{id}/logbook` → profil + riwayat ukur + `konsumsi_terakhir`
  (7 hari: tanggal + bahan/porsi) — sinkron dua arah dengan input ortu.

### Referensi (SKPL-F-11/F-12; baca semua peran, tulis admin)
- `GET /bahan-makanan?q&limit`, `POST`, `PUT|PATCH`, `DELETE /bahan-makanan/{id}`
  (tolak hapus bila dipakai resep/konsumsi).
- `GET /resep?kategori_gizi&limit`, `POST`, `PUT|PATCH`, `DELETE /resep/{id}`
  (`bahans[{bahan_id, takaran_jumlah, takaran_satuan,
  takaran_gram_ekuivalen?, catatan_bahan?}]`; tanpa field biaya).

### Admin impor (tambahan pilot, di luar SKPL)
- `POST /admin/impor-anak` (CSV: `nama,tanggal_lahir,jenis_kelamin,
  wilayah_posyandu,user_id` + opsional `alamat`).
- `POST /admin/impor-ukur` (CSV: `anak_id,tanggal_ukur,berat_badan_kg,
  tinggi_badan_cm` + opsional `posisi_ukur`).
- Maks 1000 baris; respons `{total_baris, berhasil, gagal, ids,
  galat[{baris, pesan}]}`; file via multipart `file` (csv/txt ≤ 2 MB).

## 4. RBAC ringkas

| Area | Orang tua | Kader | Admin |
|---|---|---|---|
| anak | CRUD milik sendiri | baca sewilayah | semua |
| pertumbuhan/konsumsi/rekomendasi | tulis milik sendiri | baca | semua |
| dashboard/logbook/impor | — (403) | dashboard+logbook | semua |
| bahan/resep | baca | baca | CRUD |

## 5. Deviasi terdokumentasi dari SKPL

1. Token Sanctum opaque (bukan JWT) — perilaku 24 jam + Bearer identik.
2. `DELETE /anak/{id}` + `PATCH` — superset SKPL (hanya PUT).
3. `energi_per100g` di bahan — tambahan untuk %AKG energi.
4. Kolom gizi bahan NULLABLE (sel TKPI kosong); field biaya/anggaran dihapus.
5. Umur didukung 0–60 bln (SKPL sebut target 12–59).
6. Riwayat/analisis menerima `limit`; respons rekomendasi/logbook/impor
   menambahkan kunci info (`saran`, `peringatan`, dsb.).
7. Label usia resep mengikuti CSV; ambang `kategori_pemantauan` masih
   baseline sementara.

Rincian alasan dan dampak: [`deviasi-skpl.md`](deviasi-skpl.md).
