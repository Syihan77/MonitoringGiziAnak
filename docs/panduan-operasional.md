# Panduan Operasional & Serah Terima Deploy

Untuk penanggung jawab server (bukan backend dev).

## 1. Prasyarat

PHP 8.5 + Composer, Node 24, PostgreSQL 14+, web server + HTTPS (SKPL 4.1.5:
wajib TLS 1.2+ di produksi), Ubuntu LTS + 2 vCPU/4 GB (SKPL 5.2).

## 2. Instalasi pertama

```sh
composer install --no-dev --optimize-autoloader
cp .env.example .env   # lalu isi: APP_KEY, DB_*, DESA_NAMA, APP_URL https
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # UserSeeder + WilayahSeeder + WHO + TKPI + resep
php artisan config:cache && php artisan route:cache
npm ci && npm run build
```

Urutan seeder: admin (`admin@gizi.local`, password admin dari `ADMIN_PASSWORD`
di `.env` — wajib diisi sebelum seed), wilayah
(`config/wilayah.php` → **ganti daftar dengan data resmi desa sebelum seed**),
729 baris WHO, 1146 bahan TKPI, 93 resep (62 `DATA_LENGKAP`, 31
`PERLU_VERIFIKASI`, 0 `TERVERIFIKASI`). `DATA_LENGKAP` mensyaratkan energi,
karbohidrat, protein, dan lemak per porsi tidak NULL; 14 resep tanpa gizi
diturunkan ke `PERLU_VERIFIKASI` dengan catatan alasan. Verifikasi resep
dilakukan admin lewat API resmi, bukan lewat seeder.

## 3. Variabel `.env` penting

```
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432
DB_DATABASE=gizi_db DB_USERNAME=... DB_PASSWORD=...
DESA_NAMA="Nama Desa"   # tampil di seed wilayah
APP_TIMEZONE biarkan UTC (kontrak: timestamp ISO-8601 UTC)
NUTRITION_DEMO_ALLOW_DATA_LENGKAP=false   # wajib false di production
```

## 4. Backup (SKPL 4.3.2: minimal 1×/bulan; disarankan harian)

```sh
pg_dump -U <user> -h 127.0.0.1 gizi_db | gzip > backup-gizi-$(date +%F).sql.gz
```

Restore: `gunzip -c <file> | psql -U <user> -h 127.0.0.1 gizi_db`.

## 5. Operasional desa

- Akun: ortu/kader registrasi mandiri; admin dibuat via `UserSeeder`/tinker.
- Token kedaluwarsa 24 jam → kader login ulang tiap hari.
- Impor awal: siapkan CSV sesuai `docs/kontrak-api.md` §3 (admin), maks 1000
  baris/batch; periksa `galat[]` per respons.
- Rate limit 120 req/menit/token; log audit di tabel `audit_logs`
  (register, login, logout, anak_*, pertumbuhan_create, konsumsi_create,
  rekomendasi_minta, logbook_view, impor_*, bahan_*, resep_*).
- Kategori pemantauan bersifat **indikatif** — diagnosis tetap oleh nakes.

## 6. Verifikasi resep untuk production

Seed awal tidak berisi resep `TERVERIFIKASI`, sehingga production tanpa demo
akan menampilkan “Belum ada resep terverifikasi ...”. Minta ahli gizi
memverifikasi beberapa resep melalui:

```http
PATCH /api/v1/admin/resep/{resepId}/status
Authorization: Bearer <token admin>
Content-Type: application/json

{
  "status_verifikasi_data": "TERVERIFIKASI",
  "nama_pemverifikasi": "Nama Ahli Gizi",
  "tanggal_verifikasi": "YYYY-MM-DD"
}
```

Endpoint akan menolak verifikasi bila takaran/satuan/bahan belum lengkap atau
salah satu dari energi, karbohidrat, protein, dan lemak per porsi masih NULL.
Resep yang tidak memenuhi empat makro otomatis diturunkan ke
`PERLU_VERIFIKASI` dengan catatan alasan.

## 7. Mode demo untuk presentasi

- Jangan memakai `APP_ENV=production` untuk demo. Flag demo sengaja diabaikan
  di production.
- Gunakan `APP_ENV=staging` atau `APP_ENV=demo`, lalu set
  `NUTRITION_DEMO_ALLOW_DATA_LENGKAP=true`, kemudian:
  ```sh
  php artisan config:clear
  ```
- Mode ini menampilkan label `DEMO_BELUM_TERVERIFIKASI` dan peringatan demo.
  Setelah presentasi, kembalikan flag ke `false` dan `APP_ENV=production`.
- Alternatif yang lebih aman: verifikasi resep resmi seperti bagian 6.

## 8. Sumber gizi TKPI, muat ulang, verifikasi basis ML2, dan smoke test

Sumber gizi resep adalah
`database/seeders/data/dataset_resep_gizi_terstandarisasi_berlabelusia_tkpi.csv`
(93 resep, 26 kolom, 10 kolom gizi). CSV lama
`dataset_resep_gizi_terstandarisasi_berlabelusia.csv` hanya arsip dan tidak
dibaca seeder. Audit nilai ada di `docs/audit_gizi_tkpi_semua_resep.csv`
(930 baris: 736 `terhitung`, 194 `tidak_terhitung`). Komposisi: 33 resep
lengkap, 46 sebagian, 14 kosong semua. Ke-14 resep kosong berstatus
`PERLU_VERIFIKASI` dengan catatan alasan karena empat makro tidak lengkap;
jangan menganggap gizi NULL sebagai nol atau defisiensi.

Kolom `*_per_porsi` pada `reseps` sudah nullable; tidak ada migrasi baru.
Deploy baru cukup `php artisan migrate --force` lalu `php artisan db:seed --force`.
Untuk database yang sudah terisi, muat ulang hanya kolom gizi dan catatan:

```sh
php artisan resep:muat-gizi-tkpi --dry-run
php artisan resep:muat-gizi-tkpi
```

Perintah ini idempoten, tidak mengubah `resep_bahans`, dan mengganti catatan
normalisasi lama dengan `Gizi dihitung dari TKPI 2020 (estimasi), belum
diverifikasi ahli gizi`. Status hanya diturunkan ke `PERLU_VERIFIKASI` bila
empat makro tidak lengkap, disertai catatan alasan. Pada database yang sudah
terisi, 14 resep all-NULL diturunkan sehingga komposisi menjadi 62
`DATA_LENGKAP` / 31 `PERLU_VERIFIKASI`; dry-run berikutnya 0/0/0.

Basis ML2 tetap milik paket ML dan tidak boleh diubah dari Laravel. Seeder dan
command lama `resep:normalisasi-gizi` sudah dihapus. Basis sudah dibangun ulang
dari CSV TKPI (versi `1.1.0-tkpi`, 93 resep):

```sh
php artisan ml:verify-basis
```

Perintah read-only ini membandingkan 10 kolom gizi. Hasil 15 November 2026:
93 baris, 93 cocok, 0 selisih, exit code 0. Jangan melonggarkan gate.

Cek kesiapan layanan dan kedua modul (exit non-zero hanya bila service/modul
tidak siap; catatan kompatibilitas scikit-learn hanya peringatan):

```sh
php artisan ml:health
```

Label dan metrik K-Means masih berasal dari basis lama. `config_model.json`
menandainya `status_cluster: "USANG_DATA_LAMA"`; refit menunggu kebijakan nilai
kosong. Jangan menampilkan label cluster sebagai analisis gizi di Laravel.
`ML_ENABLED` tetap `false` dan flag demo `false` sampai integrasi disetujui;
rekomendasi memakai fallback aturan.

Smoke test ML2 dengan FastAPI boleh dijalankan karena gate basis sudah exit 0,
tetapi bukan alasan untuk mengaktifkan `ML_ENABLED` pada aplikasi Laravel.

## 9. Perbaikan pemetaan bahan dan backfill gizi (database existing)

Perbaikan ini hanya mengubah `resep_bahans` dan baris placeholder lama; CSV dataset
dan paket ML tidak diubah. Laporan audit ada di
`docs/audit-pemetaan-bahan.md` dan pasangan rincinya di
`docs/audit-pemetaan-bahan-93-resep.csv`.

Alur wajib untuk database yang sudah berisi data:

```sh
php artisan resep:perbaiki-bahan --dry-run
# Tinjau semua resep/baris yang ditampilkan, terutama air, pisang, dan bumbu.
# Backup sebelum mengubah data:
pg_dump -U <user> -h 127.0.0.1 gizi_db | gzip > backup-gizi-sebelum-perbaikan-$(date +%F).sql.gz
php artisan resep:perbaiki-bahan
php artisan resep:perbaiki-bahan --dry-run   # harus 0 resep/0 baris

php artisan bahan:backfill-gizi
# Tinjau pasangan placeholder -> TKPI beserta kode dan enam nilai gizinya.
# Hanya setelah pasangan disetujui:
pg_dump -U <user> -h 127.0.0.1 gizi_db | gzip > backup-gizi-sebelum-backfill-$(date +%F).sql.gz
php artisan bahan:backfill-gizi --apply
php artisan bahan:backfill-gizi             # harus 0 pasangan
```

Kedua command bersifat idempoten. `bahan:backfill-gizi` tanpa `--apply` adalah
dry-run dan hanya menyalin nilai untuk placeholder dengan alias eksplisit
berkepercayaan tinggi. Placeholder tanpa alias tetap NULL dan dilaporkan.

Setelah perbaikan, analisis konsumsi lama otomatis memakai nilai baru karena baris
konsumsi menunjuk `bahan_id` yang sama; tidak ada penulisan ulang riwayat. Pencarian
bahan untuk input konsumsi memakai `untuk_konsumsi=1`, sehingga placeholder dan
bahan dengan enam zat NULL tidak muncul. Pencarian admin tanpa parameter tetap dapat
melihat seluruh katalog.

Hasil akhir 17 November 2026: 640 dari 870 baris enam zat lengkap, 12 resep lengkap,
0 resep yang masih perlu diperbaiki, 154 placeholder (56 sudah di-backfill dan 98
tetap NULL), serta 0 pasangan backfill tersisa. Penyebab NULL terbanyak adalah
garam, serai, daun jeruk, dan daun pandan. Aturan khusus bumbu/air belum diterapkan
dan harus diputuskan ahli gizi sebelum diubah.

## 10. Migrasi `konsumsis.kondisi_id`

Migration baru mengubah `kondisi_id` menjadi NOT NULL dan memakai
`ON DELETE RESTRICT`. Sebelum deploy, cek:

```sql
SELECT COUNT(*) FROM konsumsis WHERE kondisi_id IS NULL;
```

Jika hasilnya > 0, migration akan berhenti dengan pesan diagnostik. Backfill
data lama terlebih dahulu; jangan memaksa NOT NULL pada data yang belum siap.

## 11. Verifikasi serah terima

```sh
php artisan test     # seluruh test harus hijau
curl https://<host>/api/v1/ping
```

## 12. Serah terima: layanan ML

Lokasi paket ML dipertahankan di `ml-monitoring-gizi/ml-monitoring-gizi/`
(Windows: `ml-monitoring-gizi\ml-monitoring-gizi\`). Layanan ML adalah proses
Python terpisah dari Laravel.

**(a) Perubahan proyek**

- Gizi resep memakai estimasi dataset TKPI 2020: 93 resep, 62 `DATA_LENGKAP`,
  31 `PERLU_VERIFIKASI`, 0 `TERVERIFIKASI`.
- `DATA_LENGKAP` mensyaratkan energi, karbohidrat, protein, dan lemak per porsi
  tidak NULL. Resep tanpa empat makro tidak pernah direkomendasikan.
- Command baru: `resep:muat-gizi-tkpi`, `ml:verify-basis`, dan `ml:health`.
- Integrasi ML bersifat opsional: `ML_ENABLED=false` tetap memakai ranking
  aturan lokal. Jika diaktifkan, Laravel memakai ranking ML2 hanya untuk
  resep yang lolos filter lokal.

**(b) Variabel `.env` Laravel (default aman)**

```dotenv
ML_ENABLED=false
ML1_BASE_URL=http://127.0.0.1:8000
ML1_CONNECT_TIMEOUT=1
ML1_TIMEOUT=2
ML2_BASE_URL=http://127.0.0.1:8000
ML2_CONNECT_TIMEOUT=1
ML2_TIMEOUT=5
NUTRITION_DEMO_ALLOW_DATA_LENGKAP=false
```

Layanan Python memakai environment proses (bukan `.env` Laravel):

```dotenv
API_DOCS_ENABLED=false
LOG_LEVEL=INFO
```

`ML1_MODEL_DIR` dan `ML2_MODEL_DIR` opsional; biarkan kosong untuk memakai
folder model di dalam paket.

**(c) Kebutuhan layanan ML**

- Python 3.13 (diuji 3.13.18); scikit-learn `1.9.1`; `pygrowup 0.8.2`.
- Windows:
  ```bat
  cd ml-monitoring-gizi\ml-monitoring-gizi
  py -3.13 -m venv .venv
  .venv\Scripts\activate
  python -m pip install --upgrade pip
  python -m pip install setuptools six wheel
  python -m pip install --no-build-isolation -r requirements.txt
  python -m uvicorn app.main:app --host 127.0.0.1 --port 8000
  ```
- Linux:
  ```sh
  cd ml-monitoring-gizi/ml-monitoring-gizi
  python3.13 -m venv .venv
  source .venv/bin/activate
  python -m pip install --upgrade pip
  python -m pip install setuptools six wheel
  python -m pip install --no-build-isolation -r requirements.txt
  python -m uvicorn app.main:app --host 127.0.0.1 --port 8000
  ```
- **Layanan ML tidak memiliki autentikasi.** Jangan buka port `8000` ke publik;
  gunakan `127.0.0.1` atau jaringan privat. Aktifkan Swagger hanya saat
  debugging lokal (`API_DOCS_ENABLED=false` di server).

**(d) Urutan setelah deploy kode**

```sh
php artisan migrate --force
php artisan resep:muat-gizi-tkpi --dry-run
php artisan resep:muat-gizi-tkpi
php artisan ml:verify-basis   # harus 93 cocok / 0 selisih / exit 0
php artisan ml:health         # exit 0 hanya bila service + ML1 + ML2 siap
```

Di lingkungan baru boleh memakai `php artisan migrate:fresh --seed --force`
sebagai pengganti dua langkah pertama, lalu lanjutkan ke `ml:verify-basis`.
Jalankan `ml:health` setelah layanan Python hidup. Jangan melonggarkan gate
`ml:verify-basis`.

**(e) Bila layanan ML mati**

- Rekomendasi tetap berjalan memakai ranking aturan lokal
  (`BASELINE_ATURAN`); pengguna tidak menerima error karena ML.
- Blok `ml2` berisi `tersedia=false` beserta `alasan_tidak_tersedia`;
  prediksi pertumbuhan juga memakai fallback dan menandai ML tidak tersedia.
- `ml:health` boleh exit non-zero untuk operator; ini bukan error aplikasi
  pengguna.

**(f) Aktivasi ML**

`ML_ENABLED=true` hanya diputuskan tim deploy setelah `ml:verify-basis` dan
`ml:health` lulus. Proyek diserahkan dalam keadaan `ML_ENABLED=false`.

**(g) Status data dan demo**

- Semua nilai gizi resep adalah estimasi TKPI 2020, belum divalidasi ahli gizi.
- Tidak ada resep `TERVERIFIKASI` dari seeder; verifikasi lewat endpoint admin.
- `NUTRITION_DEMO_ALLOW_DATA_LENGKAP=true` hanya untuk non-production dan
  diabaikan saat `APP_ENV=production`.
- Resep tanpa empat makro dibuang oleh `filterWajib()`; resep tersebut tidak
  dapat direkomendasikan, termasuk dalam mode demo.
