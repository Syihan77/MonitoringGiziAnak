# Kontrak API NutriTumbuh v1 (Backend)

Acuan: SKPL v1.0 §5.4. Base URL: `/api/v1`. Format: JSON UTF-8.
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
  dan `ukur_terakhir {..., status_gizi_antropometri}` (null bila belum diukur).
- `GET /anak/{id}` → detail + `alamat` + `pemilik_wilayah`.
- `PUT|PATCH /anak/{id}` (pemilik/admin), `DELETE /anak/{id}` (pemilik/admin,
  superset SKPL — hapus измерения ikut cascade).

### Pertumbuhan (SKPL-F-04/F-05)
- `POST /anak/{id}/pertumbuhan` → 201 `{pertumbuhan_id, umur_bulan,
  zscore_bb_u, zscore_tb_u, zscore_bb_tb, status_gizi_antropometri,
  status_rincian{bb_u,tb_u,bb_tb}}`.
  Body: `berat_badan_kg (0.5–40), tinggi_badan_cm (30–150),
  tanggal_ukur (≤ hari ini, umur 0–60 bln), posisi_ukur? (terlentang|berdiri)`.
  Aturan: umur bulan-penuh; koreksi +0,7 cm (0–24 bln berdiri) / −0,7 cm
  (> 24 bln terlentang); varian rujukan PB (< 24 bln) / TB (≥ 24 bln).
  Nilai `status_gizi_antropometri`: `normal | kurang | stunting_terindikasi |
  gizi_buruk | lebih` (indikatif, bukan diagnosis).
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
  Tanpa data → `ringkasan: [], analisis_status: tidak_dapat_dianalisis`.
  AKG (Permenkes 28/2019, tanpa beda L/P): 1–3th E1350/P20/L45/KH215/Fe7/Zn3;
  4–6th E1400/P25/L50/KH220/Fe10/Zn5 (+ kelompok 0–5/6–11 bln).
- `GET /anak/{id}/gizi-bulanan?bulan=YYYY-MM` → 200 `{bulan, hari_tercatat,
  rata_persen_akg, status (TERPENUHI|KURANG TERPENUHI|TIDAK TERPENUHI|
  belum_tercatat), zat_terendah, rata_per_zat}` untuk panel Status Pemenuhan Gizi.
  Rata dihitung dari % harian yang di-cap 100%/zat (kelebihan ekstrem tak
  menyeret mean).

### Rekomendasi & resep (SKPL-F-08/F-09, pemilik/admin)
- `POST /anak/{id}/rekomendasi-menu` → 201 `{jumlah_rekomendasi,
  menu[{resep_id, nama_resep, estimasi_biaya, kategori_gizi, kecocokan_bahan}],
  defisiensi_terdeteksi, saran, tanpa_info_biaya}`.
  Body: `bahan_tersedia? [uuid], anggaran_max? (salah satu wajib),
  tanggal?`. Resep tanpa info biaya dilewati pada mode anggaran (jujur).
  Tercatat di `rekomendasi_logs`.
- `GET /resep/{resepId}` (semua peran login) → bahan + takaran +
  `biaya_lengkap` (false bila ada harga NULL) + `gizi_per_porsi`
  {energi, protein, lemak, karbohidrat}.

### Kader (SKPL-F-10, kader/admin; ortu ditolak)
- `GET /kader/dashboard?wilayah=` (wajib; harus = wilayah profil kader) →
  `{wilayah, total_anak_binaan, anak_terukur,
  distribusi_status_gizi{baik,kurang,sangat_kurang,+lebih},
  distribusi_stunting_indikatif{normal,pendek,sangat_pendek,+tinggi}}`
  dari pengukuran terakhir tiap anak; anonim (tanpa nama).
- `GET /kader/anak/{id}/logbook` → profil + riwayat ukur + `konsumsi_terakhir`
  (7 hari: tanggal + bahan/porsi) — sinkron dua arah dengan input ortu.

### Referensi (SKPL-F-11/F-12; baca semua peran, tulis admin)
- `GET /bahan-makanan?q&limit`, `POST`, `PUT|PATCH`, `DELETE /bahan-makanan/{id}`
  (tolak hapus bila dipakai resep/konsumsi).
- `GET /resep?kategori_gizi&limit`, `POST`, `PUT|PATCH`, `DELETE /resep/{id}`
  (`bahans[{bahan_id, takaran_gram}]`; biaya auto-hitung ulang).

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
4. Kolom gizi/harga bahan NULLABLE (sel TKPI kosong; harga belum ada sumber).
5. Umur didukung 0–60 bln (SKPL sebut target 12–59).
6. Riwayat/analisis menerima `limit`; respons rekomendasi/logbook/impor
   menambahkan kunci info (`saran`, `tanpa_info_biaya`, dsb.).
