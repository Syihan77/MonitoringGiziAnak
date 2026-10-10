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
729 baris WHO, 1146 bahan TKPI, 3 resep awal.

## 3. Variabel `.env` penting

```
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432
DB_DATABASE=gizi_db DB_USERNAME=... DB_PASSWORD=...
DESA_NAMA="Nama Desa"   # tampil di seed wilayah
APP_TIMEZONE biarkan UTC (kontrak: timestamp ISO-8601 UTC)
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
- Status gizi sistem bersifat **indikatif** — diagnosis tetap oleh nakes.

## 6. Verifikasi serah terima

```sh
php artisan test     # wajib hijau (74 test)
curl https://<host>/api/v1/ping
```
