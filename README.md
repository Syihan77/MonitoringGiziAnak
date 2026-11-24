# NutriTumbuh — Monitoring Gizi Anak (Pencegahan Stunting)

Aplikasi web pendampingan posyandu untuk memantau tumbuh kembang balita,
mencatat konsumsi harian, menganalisis kecukupan gizi (AKG), memberi
rekomendasi menu, dan menyajikan dashboard agregat kader — dengan kalkulasi
Z-score antropometri mengacu standar WHO/Kemenkes (Permenkes No. 2/2020).

Acuan kontrak: SKPL v2.0. Detail kontrak API: [`docs/kontrak-api.md`](docs/kontrak-api.md).
Panduan deploy & operasional server: [`docs/panduan-operasional.md`](docs/panduan-operasional.md).

**Status:** backend + frontend terintegrasi penuh, **153 automated test hijau**
(1097 assertions, `composer test`), build frontend sukses (`npm run build`).

---

## 1. Stack & dependencies

| Lapisan | Teknologi (versi terkunci) |
|---|---|
| Bahasa | PHP `^8.5` |
| Framework | Laravel `^13.17` (`laravel/framework`) |
| Auth API | Laravel Sanctum `^4.0` (token opaque, expiry 24 jam) |
| Database | PostgreSQL 14+ (`pgsql`; kompatibel MySQL untuk skema inti) |
| Frontend | Blade + Vite `^8` (`laravel-vite-plugin ^3`), vanilla JS + CSS per halaman |
| Dev/test | PHPUnit `^12.5`, Laravel Pint, Faker, Tinker |

Daftar lengkap: `composer.json` (PHP) dan `package.json` (Node).
Prasyarat di mesin: **PHP 8.3+, Composer 2, Node 24 + npm, PostgreSQL 14+,
Git** (opsional).

---

## 2. Setup dari nol (langkah berurutan, siap salin)

> Semua perintah dijalankan dari root proyek (sesuaikan dengan direktori
> instalasi Laragon di perangkat Anda).
### 2.1. Install dependensi

```sh
composer install
npm install
```

### 2.2. Berkas environment

```sh
cp .env.example .env
php artisan key:generate
```

`.env` **tidak di-commit** (masuk `.gitignore`). Isi manual yang wajib:

| Variabel | Contoh | Keterangan |
|---|---|---|
| `APP_URL` | `http://localhost:8000` | Base URL lokal |
| `DB_CONNECTION` | `pgsql` | Jangan diganti sqlite |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `5432` | Host PostgreSQL |
| `DB_DATABASE` | `gizi_db` | Database utama |
| `DB_USERNAME` / `DB_PASSWORD` | `gizi` / `••••••` | Kredensial asli hanya di `.env` lokal, **jangan** ke `.env.example` |
| `DESA_NAMA` | `Nama Desa` | Dipakai seeder wilayah |

### 2.3. Siapkan database (dua database!)

```sql
-- sebagai superuser postgres. Ganti <password-kuat-acak> dengan password
-- buatan sendiri, mis. hasil: openssl rand -base64 24
-- (JANGAN pakai password yang sama antar laptop):
CREATE ROLE gizi WITH LOGIN PASSWORD '<password-kuat-acak>' CREATEDB;
CREATE DATABASE gizi_db OWNER gizi;
CREATE DATABASE gizi_db_test OWNER gizi;
```

- `gizi_db` → dipakai aplikasi (`DB_DATABASE` di `.env`).
- `gizi_db_test` → **hanya** untuk test otomatis (`DB_DATABASE`-nya dikunci di
  `phpunit.xml`; user/password dibaca dari `.env` masing-masing mesin).

### 2.4. Migrasi + seed

```sh
php artisan migrate
php artisan db:seed
```

Urutan seeder (`database/seeders/DatabaseSeeder.php`):

1. `UserSeeder` — akun admin awal (`admin@gizi.local`, **segera ganti password!**).
2. `WilayahSeeder` — master posyandu dari `config/wilayah.php`.
   **WAJIB diganti daftar resmi desa** sebelum pilot (contoh bawaan:
   "Posyandu Melati", "Posyandu Mawar").
3. `WhoStandardsSeeder` — 729 baris baku WHO dari
   `database/seeders/data/Permenkes_2_Tahun_2020_Standar_Antropometri_Anak.md`
   (Tabel 1–5 & 8–12; tanpa IMT/U).
4. `BahanSeeder` — 1146 bahan TKPI 2020 dari
   `database/seeders/data/TKPI_2020_komposisi_pangan.csv` (upsert per nama,
   aman di-seed ulang).
5. `ResepSeeder` — 93 resep (62 `DATA_LENGKAP`, 31 `PERLU_VERIFIKASI`,
   0 `TERVERIFIKASI`; takaran/satuan terdokumentasi). Sumber gizi:
   `dataset_resep_gizi_terstandarisasi_berlabelusia_tkpi.csv` (33 lengkap,
   46 sebagian, 14 kosong semua; sel kosong menjadi NULL). `DATA_LENGKAP`
   mensyaratkan energi, karbohidrat, protein, dan lemak per porsi tidak NULL;
   14 resep tanpa gizi diturunkan ke `PERLU_VERIFIKASI` dengan catatan alasan.
   Verifikasi ahli gizi dilakukan lewat endpoint admin, bukan di seeder.

Database yang sudah terisi dapat dimuat ulang tanpa mengubah bahan; status
hanya diturunkan ke `PERLU_VERIFIKASI` bila empat makro belum lengkap:

```sh
php artisan resep:muat-gizi-tkpi --dry-run
php artisan resep:muat-gizi-tkpi
```

Audit nilai: `docs/audit_gizi_tkpi_semua_resep.csv` (930 baris). CSV lama
`dataset_resep_gizi_terstandarisasi_berlabelusia.csv` hanya arsip. Basis ML2
sudah dibangun ulang dari CSV TKPI (versi `1.1.0-tkpi`, 93 resep). Pada
15 November 2026, `php artisan ml:verify-basis` memberi 93 cocok / 0 selisih
(exit 0). Label dan metrik K-Means tetap berasal dari basis lama dan ditandai
`USANG_DATA_LAMA`; refit menunggu kebijakan nilai kosong. Jangan menampilkan
label cluster sebagai analisis gizi. `ML_ENABLED` tetap `false` sampai
integrasi disetujui.

> File di `database/seeders/data/` adalah **sumber seed mandiri**. Arsip asli
> (`1_MonitoringGizi/`) sudah dipindah keluar proyek; **dilarang membaca file
> `*.pdf`** — sumber angka hanya CSV/MD di atas.

### 2.5. Build frontend + jalan

```sh
npm run build
php artisan serve          # web + API, mis. http://localhost:8000
```

Untuk develop frontend, jalankan juga (terminal kedua):

```sh
npm run dev                # Vite dev server :5173 + HMR
```

> ⚠️ **Penting (pelajaran dari pengerjaan):** selama `npm run dev` berjalan
> (ditandai file `public/hot`), halaman memuat CSS/JS dari dev server, BUKAN
> dari `public/build`. Perubahan file langsung tersaji via watcher + reload
> biasa. Bila perubahan tak muncul: pastikan proses `vite` hidup; bila ragu,
> matikan dev server (hapus `public/hot`) lalu `npm run build` + hard-refresh.
> Jangan menebak-nebak — verifikasi via fetch/DevTools bila perlu.

### 2.6. Verifikasi instalasi

```sh
php artisan test           # seluruh test harus hijau
curl http://localhost:8000/api/v1/ping
```

Akun uji cepat (via `POST /api/v1/auth/register` → `/auth/login`): role
`orang_tua`/`kader` + `wilayah_posyandu` dari master + `consent_approved: true`.

---

## 3. Peta proyek

```
app/
  Http/Controllers/Api/V1/   Auth, Anak, Pertumbuhan, Konsumsi,
                             Rekomendasi, Kader, AdminImpor, AdminReferensi
  Http/Requests/             RegisterRequest, LoginRequest (+ validasi inline)
  Models/                    User, Anak, Pertumbuhan, Konsumsi, BahanMakanan,
                             Resep(+Bahan,Log), WhoStandard, Wilayah, AuditLog
  Policies/                  AnakPolicy (pemilik penuh; kader baca sewilayah)
  Services/                  ZScoreService, NutritionService, RecommendationService
  Support/                   ApiResponse (envelope), AuditLogger (sinkron)
routes/
  web.php                    17 Route::view (Blade) + /ukur-kader
  api.php                    /api/v1 (throttle 120/mnt), auth:sanctum grup
resources/
  views/                     17 Blade + components/icon.blade.php (29 ikon SVG)
  css/js                     per halaman + js/api.js (fetch wrapper + logout)
database/
  migrations/                users, tokens(uuidMorphs), audit_logs, anaks,
                             who_standards(+varian), pertumbuhans,
                             wilayahs, bahan_makanans, konsumsis,
                             reseps(+bahan, rekomendasi_logs)
  seeders/ (+ data/)         lihat §2.4
config/                      nutrition.php (AKG, cutoff, Z-score, kader,
                             rekomendasi), wilayah.php
docs/                        kontrak-api.md, panduan-operasional.md,
                             deviasi-skpl.md
tests/                       Feature/ + Unit/ (ZScoreServiceTest)
```

---

## 4. Aturan bisnis terkunci (jangan diubah tanpa diskusi)

- **Envelope & error persis SKPL**: `{success,data,message}` /
  `{success:false,error_code,message}`; kode `CONSENT_REQUIRED`,
  `VALIDATION_ERROR` (400), `INVALID_CREDENTIALS`, `TOKEN_EXPIRED`,
  `UNAUTHENTICATED` (401), `FORBIDDEN_RESOURCE` (403),
  `RESOURCE_NOT_FOUND` (404), `RATE_LIMIT_EXCEEDED` (429), `INTERNAL_ERROR` (500).
- **Register**: `wilayah_posyandu` wajib **hanya bila role=kader** + harus ada di
  master `wilayahs`; password min 8 huruf+angka; consent false → 400.
- **Z-score 3 indeks** (`bb_u`, `tb_u`, `bb_tb`, tanpa IMT/U); **umur bulan-penuh**;
  **koreksi posisi ±0,7 cm** (`posisi_ukur` opsional, wajib di form kader);
  rentang ukur 0–60 bulan.
- **AKG** (Permenkes 28/2019, tanpa beda L/P): 1–3th E1350/P20/L45/KH215/Fe7/Zn3;
  4–6th E1400/P25/L50/KH220/Fe10/Zn5 (+0–5/6–11 bln). Status %AKG:
  ≥80 cukup, 70–80 kurang, <70 sangat_kurang (`config/nutrition.php`); rata
  bulanan di-cap 100%/zat.
- **Rekomendasi hanya resep `TERVERIFIKASI`**; field biaya/anggaran sudah
  dihapus. Mode demo non-production boleh memakai `DATA_LENGKAP` lewat
  `NUTRITION_DEMO_ALLOW_DATA_LENGKAP=true`, tetapi ditolak di production.
  `filterWajib()` selalu membuang resep tanpa energi/karbohidrat/protein/lemak
  (termasuk saat demo), sehingga resep itu tidak masuk kandidat, tidak mengisi
  kekurangan hasil ML2, dan tidak pernah direkomendasikan.
  Energi tidak memicu rekomendasi sendirian: hanya defisiensi non-energi yang
  menjadi fokus dan `prioritas_gizi` ML2, sedangkan energi tetap masuk skor
  gizi umum sebagai faktor sekunder.
- **RBAC**: ortu CRUD milik sendiri (IDOR→403); kader baca sewilayah + catat
  ukur binaan + dashboard/logbook; admin baca-saja data anak, tulis referensi
  & impor = admin.
- **Deviasi SKPL yang disengaja** (tercatat di `docs/deviasi-skpl.md`):
  token Sanctum opaque (bukan JWT), `DELETE/PATCH` anak, kolom
  `energi_per100g`, kolom gizi nullable, umur 0–60, admin baca-saja data anak,
  audit log sinkron, kunci respons tambahan, label usia resep dan ambang
  kategori pemantauan masih baseline sementara.

---

## 5. Alur kerja harian & perintah penting

```sh
php artisan test [--filter=NamaTest]   # unit/integration (DB gizi_db_test)
php artisan migrate                    # tambah tabel baru
php artisan db:seed --class=NamaSeeder --force
php artisan resep:muat-gizi-tkpi --dry-run   # cek muat ulang gizi DB terisi
php artisan resep:muat-gizi-tkpi             # terapkan (idempoten)
php artisan ml:health                        # cek layanan + kesiapan ML1/ML2
npm run dev                            # develop FE (lihat peringatan §2.5)
npm run build                          # sebelum serah terima/uji final
php artisan view:cache && php artisan view:clear   # validasi sintaks Blade
vendor/bin/pint --test                 # cek gaya kode (opsional)
```

- Test memakai `actingAs($user, 'sanctum')` (bukan header Bearer) bila satu test
  memakai multi-user — guard Sanctum di-cache per instance aplikasi dalam test.
- Hapus `public/hot` bila ingin halaman memakai hasil `npm run build`.
- Frontend memanggil API via `window.NutriApi` (`resources/js/api.js`);
  penambahan endpoint baru → tambah helper + pakai pola yang sama.

## 6. Troubleshooting cepat

| Gejala | Penyebab umum → solusi |
|---|---|
| Perubahan CSS/JS tak muncul | Dev server mati/watcher macet → restart `npm run dev`, atau hapus `public/hot` + `npm run build` + hard-refresh |
| Test 403/401 tak terduga multi-user | Pakai `actingAs` per user, pisahkan test tanpa-token |
| `SQLSTATE max(uuid)` | Jangan pakai `latestOfMany`/`ofMany` di PK UUID PostgreSQL — query + group di PHP (lihat `AnakController@index`) |
| Mojibake `ðŸŽ¯` di Blade | File terkontaminasi encoding — jangan paste mentah; perbaiki via skrip decode Win-1252→UTF-8 |
| ` streak 404` aset `/img/*.svg` | Cek nama file vs referensi; gunakan `<x-icon>` |
| Rate-limit saat testing manual | 120 req/menit/token; tunggu 1 menit |

## 7. Checklist serah terima pilot desa

1. Ganti `config/wilayah.php` dengan daftar posyandu resmi + `DESA_NAMA`, re-seed.
2. Ganti password `admin@gizi.local`; buat akun kader per posyandu.
3. Impor register via `POST /admin/impor-anak` + `POST /admin/impor-ukur` (CSV ≤1000
   baris; format di `docs/kontrak-api.md`); periksa `galat[]` per respons.
4. Latih kader: ukur benar + `posisi_ukur` wajib + login harian (token 24 jam).
5. Backup: `pg_dump` terjadwal (detail di `docs/panduan-operasional.md`).
6. Ingat: status gizi sistem bersifat **indikatif** — diagnosis tetap oleh nakes.
