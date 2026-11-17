# Audit Pemetaan Bahan 93 Resep

Tanggal audit akhir: 17 November 2026. Audit awal: 16 November 2026. Sumber resep:
`database/seeders/data/dataset_resep_gizi_terstandarisasi_berlabelusia_tkpi.csv`.
Audit dan penerapan dilakukan terhadap database PostgreSQL `gizi_db`; CSV dataset
dan paket ML tidak diubah.

Data rinci semua pasangan ada di `docs/audit-pemetaan-bahan-93-resep.csv`
(870 baris bahan). Definisi "enam zat lengkap" pada laporan ini: energi, protein,
lemak, karbohidrat, zat besi, dan zinc semuanya non-NULL. Bahan placeholder
`Dataset resep berlabel` dihitung tidak tersedia walaupun barisnya ada.

## Ringkasan

| Metrik | Sebelum perbaikan | Setelah perbaikan dan backfill |
| --- | ---: | ---: |
| Resep yang diperiksa | 93 | 93 |
| Baris bahan | 870 | 870 |
| Baris dengan enam zat lengkap | 613 | 640 |
| Baris NULL/MISSING | 257 | 230 |
| Resep dengan seluruh baris enam zat lengkap | 11 | 12 |
| Resep yang perlu diubah pemetaannya | - | 0 |

Perbaikan pertama menyentuh 56 resep/693 baris tampilan, dengan 134 baris yang
benar-benar berganti pemetaan. Perbaikan kedua menyentuh 27 resep/291 baris tampilan
setelah alias dan pencegahan fuzzy ditambah. Dry-run terakhir:
**93 resep diperiksa, 0 resep perlu diperbaiki, 0 baris ditampilkan**.

Placeholder saat ini: **154 nama**, terdiri dari **56 nama yang sudah di-backfill**
dan **98 nama yang tetap NULL**. Dry-run backfill terakhir:
**98 placeholder NULL, 0 pasangan eksplisit, 98 tanpa pasangan**. Backfill hanya
menyalin nilai placeholder yang dipakai riwayat lama; ia tidak memperbaiki sisa
placeholder pada resep secara otomatis.

## R086: Bubur Ayam Telur Puyuh

R086 di database dev sudah diperbaiki oleh re-seed sebelumnya dan tidak lagi masuk
daftar perubahan. Delapan baris bahan sudah memakai TKPI dengan enam zat non-NULL.
`Beras: 35 g` sekarang terpetakan ke `Beras giling, mentah` (AR001), bukan
`Tapai beras`.

| Bahan dataset | Bahan terpetakan | Gram | E | P | L | KH | Fe | Zn | Kode |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| Beras | Beras giling, mentah | 35 | 357 | 8.4 | 1.7 | 77.1 | 1.8 | 0.5 | AR001 |
| Daging ayam, rebus, potong kecil | Ayam, daging, segar | 15 | 298 | 18.2 | 25 | 0 | 1.5 | 0.6 | FR005 |
| Telur puyuh, kocok | Telur burung puyuh, segar | 22 | 116 | 10.7 | 7 | 1.6 | 3.5 | 1.2 | HR011 |
| Bayam | Bayam, segar | 20 | 16 | 0.9 | 0.4 | 2.9 | 3.5 | 0.4 | DR008 |
| Margarin | Margarin | 5 | 720 | 0.6 | 81 | 0.4 | 0 | 0 | KP001 |
| Bawang merah, dihaluskan (bumbu) | Bawang merah, segar | 5 | 46 | 1.5 | 0.3 | 9.2 | 0.8 | 0.2 | NR007 |
| Bawang putih, dihaluskan (bumbu) | Bawang putih, segar | 4 | 112 | 4.5 | 0.2 | 23.1 | 1 | 0.4 | NR008 |
| Daun salam (bumbu) | Daun salam, bubuk | 1 | 301 | 14.2 | 10.9 | 49 | 44.1 | 0.7 | NR017 |

## R010: Bubur Soto Lamongan

| Bahan dataset | Sebelum | Sesudah target | Gram | E | P | L | KH | Fe | Zn | Status sesudah |
| --- | --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| Nasi | Nasi | Nasi | 60 | 180 | 3 | 0.3 | 39.8 | 0.4 | 0.6 | OK |
| Ayam cincang | Ayam cincang (NULL) | Ayam, daging, segar | 30 | 298 | 18.2 | 25 | 0 | 1.5 | 0.6 | OK |
| Telur puyuh | Telur burung puyuh, segar | Telur burung puyuh, segar | 30 | 116 | 10.7 | 7 | 1.6 | 3.5 | 1.2 | OK |
| Wortel | Wortel, segar | Wortel, segar | 15 | 36 | 1 | 0.6 | 7.9 | 1 | 0.3 | OK |
| Sereh | Pisang raja sereh, segar (salah) | Serai | 10 | NULL | NULL | NULL | NULL | NULL | NULL | NULL |
| Salam | Daun salam, bubuk | Daun salam, bubuk | 1 | 301 | 14.2 | 10.9 | 49 | 44.1 | 0.7 | OK |
| Daun jeruk | Daun jeruk (NULL) | Daun jeruk | 1 | NULL | NULL | NULL | NULL | NULL | NULL | NULL |
| Kunyit | Kunyit, segar | Kunyit, segar | 3 | 69 | 2 | 2.7 | 9.1 | 3.3 | 0.4 | OK |
| Jahe | Jahe, segar | Jahe, segar | 3 | 51 | 1.5 | 1 | 10.1 | 1.6 | 0.7 | OK |
| Bawang merah | Bawang merah, segar | Bawang merah, segar | 5 | 46 | 1.5 | 0.3 | 9.2 | 0.8 | 0.2 | OK |
| Bawang putih | Bawang putih, segar | Bawang putih, segar | 4 | 112 | 4.5 | 0.2 | 23.1 | 1 | 0.4 | OK |

Catatan R010: `Ayam cincang` menjadi tersedia. `Sereh` tidak lagi salah menjadi
pisang, tetapi `Serai` belum punya nilai TKPI sehingga tetap NULL. `Daun jeruk`
juga belum punya padanan TKPI. Karena itu R010 belum dapat dianalisis penuh untuk
enam zat.

## Perubahan Pemetaan Utama

- `Beras` dan `Beras putih` -> `Beras giling, mentah` (AR001).
- `Ayam cincang`, `Ayam fillet`, `Daging ayam cincang` -> `Ayam, daging, segar`
  (FR005).
- `Minyak`, `Minyak untuk menumis`, `Minyak sayur untuk menumis` -> `Minyak kelapa`
  (bukan `Minyak ikan`).
- `Tahu`, `Tahu putih`, dan potongan tahu -> `Tahu, mentah` (bukan `Tahu telur`).
- `Buncis` -> `Buncis, segar`; `Tomat` -> `Tomat merah, segar`.
- `Telur` -> `Telur ayam ras, segar` (bukan `Telur penyu`); `Susu` ->
  `Susu sapi, segar` (bukan `Susu bubuk`).
- `Singkong` -> `Ketela pohon/ singkong, segar`; `Kelor` -> `Daun kelor, segar`;
  `Cakalang` -> `Ikan cakalang, segar`; `Udang cincang`/`Udang kupas` ->
  `Udang, segar`.
- `Kentang`, `Keju parut`, `Terigu serbaguna`, dan varian pepaya diarahkan ke
  bahan TKPI yang sesuai.
- `Sereh` -> `Serai` (placeholder NULL), bukan `Pisang raja sereh, segar`.
- Nama generik `Ragi` dan `Jagung` tidak lagi dicocokkan fuzzy agar tidak mengambil
  `Jeruk ragi` atau `Jagung titi`; keduanya menjadi placeholder NULL sampai ada
  padanan TKPI yang disetujui.
- Varian potong/iris bawang merah, bawang putih, bawang daun, seledri, dan wortel
  diarahkan ke bahan segar yang sama karena pemotongan tidak mengubah komposisi.

## Penyebab NULL Terbanyak Setelah Perbaikan

| Bahan target | Baris | Resep |
| --- | ---: | ---: |
| Garam | 38 | 29 |
| Serai | 22 | 21 |
| Daun jeruk | 12 | 11 |
| Daun pandan | 10 | 10 |
| Kaldu ayam | 6 | 6 |
| Gula merah | 5 | 5 |
| Bawang goreng | 4 | 4 |
| Bawang merah goreng | 4 | 4 |
| Kayu manis | 4 | 4 |
| Putih telur | 4 | 4 |
| Ubi rambat | 3 | 3 |
| Air kaldu ayam | 3 | 3 |
| Bawang putih goreng | 3 | 3 |
| Kaldu jamur | 3 | 3 |
| Saus tiram | 3 | 3 |

Garam dan bumbu kecil adalah penyebab dominan. Aturan khusus bumbu **belum**
diterapkan sesuai keputusan untuk melaporkan dampaknya terlebih dahulu.

## Pemetaan yang Masih Mencurigakan

CSV menandai 65 baris dengan catatan kecurigaan. Ringkasan yang perlu dibahas:

- `Air` masih dipetakan otomatis ke `Labu air, segar` karena tidak ada entri air
  murni di TKPI. Ini bukan bahan yang sama dan perlu keputusan ahli gizi; jangan
  diterapkan sebagai aturan otomatis tanpa persetujuan.
- `Pisang` dipetakan ke `Pisang ua, segar`. Varietas pisang sebaiknya dipilih
  eksplisit atau dilaporkan sebagai perkiraan.
- `Jagung pipil` dipetakan ke `Jagung kuning pipil, kering, mentah`. Label proses
  seperti `rebus` atau `kukus` hilang saat nama dasar diambil, sehingga hasil masak
  belum dapat dibedakan; perlu keputusan pemetaan khusus.
- `Bawang merah goreng` dan `Bawang putih goreng` sekarang menjadi placeholder
  NULL, bukan bawang segar. Ini lebih jujur secara gizi, tetapi menambah resep yang
  belum lengkap sampai ada padanan TKPI.
- `Ragi` dan `Jagung` kini placeholder NULL; jangan dikembalikan ke pencocokan
  fuzzy karena hasilnya salah (`Jeruk ragi`, `Jagung titi`).
- `Sereh` -> `Serai`, `Daun jeruk`, `Daun pandan`, `Garam`, `Kaldu ayam`, dan
  `Gula merah` belum punya nilai TKPI yang dapat dipakai.
- `Daun bawang` -> `Daun bawang merah, segar` diterima sebagai padanan, tetapi
  tetap perlu verifikasi ahli gizi bila varietas berbeda.

## Rekomendasi

1. Tinjau 65 baris bertanda catatan pada CSV audit dan 230 baris NULL; pemetaan
   yang mencurigakan jangan diubah otomatis tanpa keputusan ahli gizi.
2. Backup `pg_dump` di luar repo sebelum perubahan data. Backup yang dipakai:
   `gizi_db-sebelum-perbaikan-20261117.dump`,
   `gizi_db-sebelum-perbaikan2-20261117.dump`, dan
   `gizi_db-sebelum-backfill-20261117.dump`.
3. Backfill 56 pasangan eksplisit sudah diterapkan; 98 placeholder tetap NULL.
   Tinjau daftar tersebut bila akan menambah alias baru.
4. Putuskan kebijakan garam/air/bumbu. Salah satu opsi yang perlu persetujuan ahli
   gizi adalah mengeluarkan air dan garam dari perhitungan zat gizi, bukan
   mengisinya dengan nol atau bahan lain.
5. Setelah perbaikan dan backfill, ulangi analisis konsumsi lama. Baris konsumsi
   memakai `bahan_id` yang sama, jadi nilai placeholder yang sudah di-backfill
   langsung terbaca tanpa menulis ulang riwayat.
