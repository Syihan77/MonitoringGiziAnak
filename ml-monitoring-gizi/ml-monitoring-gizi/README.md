# ML Monitoring & Rekomendasi Gizi Anak — Paket Deployment ML1 + ML2

Satu paket Python dengan **satu pintu masuk FastAPI** untuk dua modul Machine Learning yang **tetap independen**, siap diintegrasikan oleh backend Laravel. Proyek: *Sistem Monitoring dan Rekomendasi Gizi Anak untuk Pencegahan Stunting*.

> **Status jujur paket ini (baca dulu)**
> - Verifikasi ulang 15 November 2026 di venv lengkap: **94 tes lulus, 1 dilewati, 0 gagal** (Python 3.14.6, scikit-learn 1.9.1, pygrowup 0.8.2). ML1 dan ML2 dimuat; satu-satunya skip adalah tes fallback “tanpa pygrowup” yang memang tidak relevan di venv ini. Jalankan `pytest` di server Anda sebelum integrasi (bagian 11). Paket ini **belum boleh dinyatakan siap produksi**.
> - Basis ML2 sudah diperbarui ke `1.1.0-tkpi` dan `ml:verify-basis` Laravel bernilai 93 cocok / 0 selisih. Label/metrik K-Means lama ditandai `USANG_DATA_LAMA` dan **bukan** analisis gizi.
> - ML1 berstatus **EKSPERIMEN** (belum lolos evaluasi/validasi). ML2 **belum divalidasi ahli gizi/pengguna**.
> - Tidak ada klaim diagnosis medis. Kategori ML1 adalah rancangan sistem dari aturan sementara `v0-provisional`.

---

## 1. Fungsi ML1 dan ML2

| | ML1 — Kategori pemantauan pertumbuhan | ML2 — Rekomendasi resep gizi |
|---|---|---|
| Tujuan | Menerima satu pengukuran anak (usia 12–59 bulan, jenis kelamin, BB, TB) dan mengembalikan kategori `Normal` / `At Risk` / `High Risk`. | Mengurutkan resep (93 resep) menurut prioritas zat gizi + ketersediaan bahan. |
| Isi | (a) **Kategori acuan**: aturan `v0-provisional` dari Z-score WHO (indikator terburuk dari BB/U, TB/U, BB/TB; < −3 → High Risk, < −2 → At Risk). (b) **Prediksi ML**: RandomForest yang meniru aturan itu hanya dari fitur mentah. | K-Means 4 cluster (artefak lama, `USANG_DATA_LAMA`), **content-based ranking** (persentil nutrisi, tidak dilatih), dan **filter bahan** berbasis aturan. |
| Yang ditampilkan ke pengguna | **`acuan.kategori`**. `prediksi_ml` hanya pembanding/eksperimen. | Daftar saran menu. **Bukan** catatan konsumsi anak. |
| Metode bawaan | — | `baseline` (re-ranking keragaman cluster diuji dan **tidak** lolos aturan keputusan; tetap tersedia sebagai `metode: "cluster_diverse"`). |

Kedua modul **tidak saling memanggil** dan tidak digabung menjadi satu model. Satu gagal dimuat ≠ yang lain mati.

## 2. Struktur direktori

```
ml-monitoring-gizi/
├── README.md                    (dokumen ini)
├── requirements.txt · requirements-dev.txt · .env.example · pytest.ini
├── artifacts_manifest.json      nama berkas, fungsi, versi, SHA-256, status verifikasi
├── app/
│   ├── main.py                  create_app() + lifespan (muat model SATU kali saat start)
│   ├── settings.py              konfigurasi dari environment
│   ├── services.py              pemuatan modul, kesiapan, health, pemanggilan inferensi (tanpa FastAPI)
│   ├── api/routes.py            endpoint HTTP
│   ├── schemas/ml1.py · ml2.py  validasi request (Pydantic)
│   ├── ml1/
│   │   ├── inference.py         ML1Predictor (mandiri, tanpa training/notebook)
│   │   └── model/               model.joblib, metadata.json, feature_schema.json, label_mapping.json
│   └── ml2/
│       ├── rekomendasi_gizi.py  modul ML2 asli (tidak diubah)
│       └── model/               kmeans_pipeline.joblib, preprocessing.joblib, config_model.json, basis_resep.csv
├── examples/                    ml1_input/output(.422).json, ml2_input/output.json
├── tests/                       pytest (lihat bagian 11)
├── tools/buat_manifest.py       membuat ulang artifacts_manifest.json
└── docs/                        arsip asli ML1 & ML2 (README, build_model_asli.py, tes asli, evaluasi, gambar,
                                 dataset_dengan_cluster.csv) — tidak dibutuhkan saat menjalankan API
```

Notebook pengembangan **tidak** disertakan dan **tidak** dibutuhkan untuk inferensi. `docs/ml1/build_model_asli.py` hanya untuk mereproduksi model dari CSV data latih (data latih tidak disertakan karena berisi data anak).

**Perubahan dari artefak asli** (semuanya kecil dan terdokumentasi):
1. `ml1/inference.py`: path bawaan artefak → subfolder `model/`; impor `pygrowup` dijaga agar bila pustaka tidak ada, ML1 dilaporkan **tidak siap** (bukan crash tanpa penjelasan). Logika inferensi tidak diubah.
2. `ml2/rekomendasi_gizi.py`: **tidak diubah**. Basis resep dibangun ulang ke `1.1.0-tkpi`; model KMeans **tidak di-refit** dan label cluster lama ditandai `USANG_DATA_LAMA`.
3. API ML1 asli (`/predict`) → `POST /ml1/prediksi`. API ML2 asli (`/rekomendasi`) → `POST /ml2/rekomendasi`. Skema ML1 sama; kontrak sukses ML2 sama (lihat bagian 6 untuk satu pengetatan).
4. Endpoint dibuat `def` (threadpool) bukan `async def` agar inferensi CPU-bound tidak memblokir event loop.

## 3. Versi Python dan dependensi

- **Python 3.13** untuk produksi (diuji 3.13.18). Verifikasi 15 November 2026 dilakukan di venv lengkap Python 3.14.6; notebook ML1 asli berjalan di 3.12.3. Python 3.14.6 baru diverifikasi untuk pengujian, bukan rekomendasi produksi.
- Lingkungan venv lengkap: scikit-learn 1.9.1 dan `pygrowup` 0.8.2 (seluruh `requirements-dev.txt` terpasang). Jalur Z-score WHO ikut teruji. `requirements.txt` tetap mengunci scikit-learn 1.9.1 untuk ML1 dan tidak diubah pada pembaruan basis ini.

**Konflik versi scikit-learn (nyata, sudah diperiksa):**

| Modul | Dibuat dengan | Dampak |
|---|---|---|
| ML1 (RandomForest) | 1.9.1 | `requirements.txt` mengunci `scikit-learn==1.9.1`. |
| ML2 (KMeans pipeline) | 1.8.0 | Dimuat di 1.9.1 memunculkan *peringatan versi* ("might lead to breaking code"). **Diverifikasi historis**: 93/93 label cluster lama identik dengan `dataset_dengan_cluster.csv`; output contoh lama juga identik. Pada basis TKPI 1.1.0, label/metrik tersebut ditandai `USANG_DATA_LAMA`. Peringatan **tidak disembunyikan**: dicatat di log startup dan muncul pada `GET /ml2/health` → `catatan_kompatibilitas`. |

Keputusan historis: satu lingkungan dengan scikit-learn 1.9.1. Verifikasi 15 November 2026 dijalankan dua kali: dengan scikit-learn 1.8.0 yang tersedia, lalu di venv lengkap (`pygrowup` 0.8.2 + scikit-learn 1.9.1) — ML1 dan ML2 lulus. `requirements.txt` tetap mengunci 1.9.1 dan **tidak diubah**. Ranking ML2 `baseline` tidak memakai scikit-learn; hanya `prediksi_cluster` dan label cluster tersimpan yang menyentuh artefak lama. Bila tim ingin nol peringatan versi, opsi tetap: (a) latih ulang pipeline ML2, atau (b) jalankan dua lingkungan/layanan terpisah.

## 4. Instalasi dan menjalankan

```bash
cd ml-monitoring-gizi
python3.13 -m venv .venv && source .venv/bin/activate        # Windows: .venv\Scripts\activate
pip install --upgrade pip setuptools wheel six
pip install -r requirements.txt
# bila build pygrowup gagal ("No module named six" / "install_layout"):
pip install --no-build-isolation -r requirements.txt

uvicorn app.main:app --host 127.0.0.1 --port 8000
```

Semua konfigurasi opsional lewat environment (lihat `.env.example`): `ML1_MODEL_DIR`, `ML2_MODEL_DIR`, `API_DOCS_ENABLED`, `LOG_LEVEL`. Muat variabel dari proses manager (mis. `EnvironmentFile` systemd) atau `export`; aplikasi tidak membaca berkas `.env` sendiri.

**Swagger / dokumentasi interaktif:** `http://127.0.0.1:8000/docs` (ReDoc: `/redoc`). Matikan di server publik dengan `API_DOCS_ENABLED=false`.

## 5. Endpoint

| Metode | Path | Fungsi |
|---|---|---|
| GET | `/health` | Status layanan + kesiapan tiap modul. `200` bila **semua** siap, `503` bila ada yang belum siap. |
| GET | `/ml1/health` | Kesiapan ML1. `200` / `503`. |
| POST | `/ml1/prediksi` | Prediksi ML1. |
| GET | `/ml2/health` | Kesiapan ML2. `200` / `503`. |
| POST | `/ml2/rekomendasi` | Rekomendasi resep ML2. |

Model dimuat sekali saat startup. Bila satu modul gagal dimuat, layanan **tetap hidup**, modul itu dilaporkan `ready: false` (health `503`; endpoint modul `503 model_tidak_siap`), dan modul lain tetap berfungsi. Untuk health check per-modul gunakan `/ml1/health` dan `/ml2/health`.

Contoh `GET /health` (semua siap):
```json
{"status":"ok","service":"running","modules":{
  "ml1":{"status":"ok","ready":true,"model_loaded":true,"versi_model":"ml1-rf-fitur-mentah-1.0.0","status_model":"EKSPERIMEN - ...","aturan_versi":"v0-provisional"},
  "ml2":{"status":"ok","ready":true,"versi_model":"1.1.0-tkpi","jumlah_resep":93}}}
```

## 6. ML1 — `POST /ml1/prediksi`

**Fitur wajib dikirim Laravel** (semua wajib; nilai kosong **ditolak**, tidak diimputasi):

| Field | Tipe JSON | Aturan |
|---|---|---|
| `usia_bulan` | integer | 12–59 |
| `jk` | string | `"L"` atau `"P"` (huruf besar) |
| `bb_kg` | number | 2–40 (batas teknis kasar) |
| `tb_cm` | number | 45–130 (batas teknis kasar) |

Kewajaran biologis dicek lewat Z-score: Z-score yang tak terhitung atau di luar batas masuk akal (WAZ −6…5, HAZ −6…6, WHZ −5…5) → `422`, sama seperti baris yang dibuang pada notebook.

Request (`examples/ml1_input.json`):
```json
{"usia_bulan": 30, "jk": "L", "bb_kg": 11.2, "tb_cm": 85.0}
```
Response `200` (`examples/ml1_output.json`, ditambah `"status":"ok"`):
```json
{
  "status": "ok",
  "versi_model": "ml1-rf-fitur-mentah-1.0.0",
  "status_model": "EKSPERIMEN - belum lolos evaluasi/validasi, jangan dipakai sebagai kategori di tampilan pengguna",
  "acuan": {"aturan_versi": "v0-provisional", "kategori_id": 1, "kategori": "At Risk",
            "zscore": {"waz": -1.46, "haz": -2.04, "whz": -0.51}, "indikator_terburuk": "haz"},
  "prediksi_ml": {"kategori_id": 1, "kategori": "At Risk",
                  "probabilitas": {"Normal": 0.0967, "At Risk": 0.9033, "High Risk": 0.0}},
  "ml_sama_dengan_acuan": true,
  "peringatan": [],
  "catatan": "Alat bantu pemantauan, BUKAN diagnosis stunting dan bukan kategori WHO. ..."
}
```

**Arti output**
- `acuan.kategori` / `kategori_id` (0 Normal, 1 At Risk, 2 High Risk): **kategori untuk ditampilkan**. Bukan diagnosis stunting dan bukan kategori WHO.
- `acuan.zscore`: WAZ (BB/U), HAZ (TB/U), WHZ (BB/TB) dari tabel WHO (`pygrowup`), dibulatkan 2 desimal. `indikator_terburuk` menentukan kategori.
- `prediksi_ml`: tebakan RandomForest + probabilitas; **hanya pembanding**. `ml_sama_dengan_acuan=false` → gunakan aturan acuan.
- `peringatan`: mis. BB/TB di luar rentang data latih (prediksi ML kurang andal) atau ML berbeda dari acuan.

Error:

| Kode | Kondisi | Body |
|---|---|---|
| 422 | input tidak valid | `{"status":"error","kode":"input_tidak_valid","pesan":"Input tidak valid.","errors":[{"field":"jk","pesan":"Input should be 'L' or 'P'"}]}` — **nilai input tidak pernah dikembalikan** |
| 503 | ML1 belum siap | `{"status":"error","kode":"model_tidak_siap","pesan":"Model ML1 belum siap."}` |
| 500 | kesalahan internal | `{"status":"error","kode":"kesalahan_internal","pesan":"..."}` — tanpa traceback/path |

## 7. ML2 — `POST /ml2/rekomendasi`

Kontrak sama dengan API ML2 yang telah diuji. Request (`examples/ml2_input.json`):
```json
{"prioritas_gizi": {"protein": 0.5, "zat_besi": 0.5},
 "bahan_tersedia": ["nasi","ayam","tahu","wortel","telur","tempe","santan","buncis","kentang","tomat","ikan","ubi rambat"],
 "mode_bahan": "sebagian", "min_kecocokan": 0.6, "top_n": 3, "maks_waktu_menit": 60}
```

| Field | Keterangan |
|---|---|
| `prioritas_gizi` | `{zat_gizi: bobot ≥ 0}` (dinormalisasi). Zat gizi: `energi, karbohidrat, protein, lemak, zat_besi, vitamin_c, kalsium, zinc, vitamin_a, serat`. **Wajib**, atau pakai pasangan di bawah. |
| `asupan_harian` + `acuan_harian` | Alternatif: bobot ∝ kekurangan asupan terhadap acuan. **Acuan harus dikirim pemanggil** (modul tidak menyertakan angka kebutuhan klinis). |
| `bahan_tersedia` | Daftar bahan; bumbu dapur diasumsikan tersedia. |
| `mode_bahan` | `nonaktif` / `semua_wajib` / `sebagian` (bawaan `sebagian` bila ada bahan). |
| `min_kecocokan` | 0–1, bawaan 0.5 (mode `sebagian`). |
| `top_n` | 1–50, bawaan 5. |
| `maks_waktu_menit` | batas waktu masak (opsional). |
| `bahan_dihindari` | daftar bahan yang tidak boleh muncul. |
| `metode` | `baseline` (bawaan) / `cluster_diverse` (eksperimen). |
| `lambda_keragaman` | ≥ 0, hanya untuk `cluster_diverse`. |
| `kebijakan_nilai_hilang` | `penalti` (bawaan; resep yang nilainya tidak diketahui tidak dapat kredit) / `keluarkan`. |

Response `200`: `meta` (versi, bobot ternormalisasi, `jumlah_kandidat_layak`, `dikeluarkan` per alasan, `peringatan`) dan `hasil[]` (`rank`, `id_resep`, `nama_menu`, `score`, `cluster`, `porsi`, `waktu_menit`, `nutrisi_prioritas`, `nutrisi_tidak_tersedia`, `kecocokan_bahan`, `alasan`). Contoh lengkap: `examples/ml2_output.json`.

**Arti output & nilai yang tidak tersedia**
- `score` (0–1) = **peringkat relatif** (persentil) di antara 93 resep, **bukan** kecukupan gizi anak.
- Nilai gizi **tidak diketahui = `null`** (`tersedia: false`, `persentil: null`). **Tidak pernah dianggap nol** dan tidak dianggap memenuhi prioritas; Laravel harus menampilkan "data tidak tersedia", bukan 0. Pada basis TKPI 1.1.0: kalsium kosong 15 resep, zinc 21, vitamin C 54, serat 18, vitamin A 15, zat besi 15, makronutrien 14. Nilai nol yang valid dipertahankan; hanya sel kosong yang menjadi NULL.
- `nutrisi_prioritas[x].catatan = "nilai 0 diragukan ..."` → nilai 0 tercatat pada resep yang datanya tidak lengkap.
- 14 resep tanpa gizi sama sekali tetap tidak diganti 0 dan tidak mendapat kredit. Dengan kebijakan `penalti`, pada `top_n=50` yang hanya memprioritaskan vitamin C mereka dapat muncul di ekor karena hanya 39 nilai vitamin C yang diketahui; ini bukan klaim kecukupan gizi.
- Label `cluster` pada respons ML2 adalah artefak lama (`USANG_DATA_LAMA`). Jangan menampilkannya di Laravel sebagai analisis gizi.
- **Hasil hanya SARAN menu**; jangan otomatis disimpan sebagai makanan yang telah dikonsumsi anak.

Error: `422 {"detail": "<pesan>"}` untuk input tidak valid (sama dengan API asli; pesan tidak memuat nilai asupan). Kesalahan bentuk tipe oleh Pydantic → `422 {"detail":[{"loc","msg","type"}]}` (tanpa `input`). `503` / `500` berformat sama dengan ML1.

**Satu pengetatan kontrak (disengaja, terdokumentasi):** kunci request yang tidak dikenal kini ditolak `422` (`extra="forbid"`). README ML2 asli menyatakan kunci tak dikenal ditolak, tetapi pada API asli Pydantic membuangnya diam-diam sebelum sampai ke modul (salah ketik seperti `top_m` diabaikan). Bila Laravel mengirim kunci tambahan, hapus; bila perlu kompatibilitas longgar, hapus `ConfigDict(extra="forbid")` di `app/schemas/ml2.py`.

## 8. Integrasi Laravel

Laravel yang bertanggung jawab atas: **mengirim input yang sesuai** (tipe & rentang di atas), **autentikasi dan otorisasi pengguna**, **menyimpan data** sesuai kebutuhan aplikasi, dan jalur cadangan bila API tidak tersedia. API Python ini **tidak** menangani semuanya otomatis: tidak ada autentikasi, rate limit, maupun penyimpanan.

`.env` Laravel (alamat produksi ditentukan tim deployment; jangan di-hardcode):
```
ML_API_URL=http://127.0.0.1:8000
ML_API_TIMEOUT=5
```
`config/services.php`:
```php
'ml' => ['url' => env('ML_API_URL', 'http://127.0.0.1:8000'), 'timeout' => (int) env('ML_API_TIMEOUT', 5)],
```
Pemanggilan:
```php
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

$http = Http::baseUrl(config('services.ml.url'))->acceptJson()->asJson()->timeout(config('services.ml.timeout'));
try {
    $ml1 = $http->post('/ml1/prediksi', [
        'usia_bulan' => (int) $anak->usia_bulan,
        'jk'         => $anak->jk,                 // "L" | "P"
        'bb_kg'      => (float) $ukur->bb_kg,
        'tb_cm'      => (float) $ukur->tb_cm,
    ]);
    $ml2 = $http->post('/ml2/rekomendasi', ['prioritas_gizi' => ['protein' => 0.5, 'zat_besi' => 0.5], 'top_n' => 5]);
} catch (ConnectionException $e) {
    // layanan tidak tersedia / timeout -> jalur cadangan
}
// ML1: $ml1->successful() -> tampilkan $ml1->json('acuan.kategori'); status 422 -> tampilkan errors ke petugas.
// ML2: tampilkan $ml2->json('hasil') sebagai SARAN; 'nilai' null -> tulis "data tidak tersedia".
// 500/503 -> jalur cadangan; catat tanpa data anak.
```
**Perlu diputuskan tim:** SKPL-NF-REL-03 meminta fallback aturan acuan bila API mati; paket ini tidak menyertakan perhitungan Z-score WHO untuk PHP. Pilih: implementasi aturan di Laravel, atau tampilkan "kategori belum tersedia".

## 9. Batasan model dan hal yang belum tervalidasi

**ML1**
- Label = aturan sementara `v0-provisional` (ambang −2,0 / −3,0 SD; menunggu keputusan ahli gizi KONF-02). Model hanya mereplikasi aturan itu; kesesuaiannya **bukan** bukti validitas klinis.
- Evaluasi (GroupKFold 5-fold per anak, 3.635 baris / 478 anak): akurasi 0,914; macro-F1 0,813–0,842; recall At Risk 0,838–0,879; **recall High Risk hanya 0,505–0,641** (model melewatkan sebagian anak High Risk). Dua eksekusi memberi hasil berbeda; penyebab belum diverifikasi — pakai kisaran. Jangan menggantikan aturan acuan dengan model.
- Data dari 9 posyandu; belum diuji pada populasi lain. Usia di luar 12–59 bulan ditolak. Skenario Z-score (akurasi ±0,998) hanya karena label adalah fungsi dari fitur.

**ML2**
- Tidak ada validasi ahli gizi/pengguna; tidak ada label relevansi (accuracy/precision/recall tidak dihitung). Skor relatif terhadap 93 resep.
- Nilai gizi per porsi tidak disesuaikan umur/berat anak; ukuran porsi bervariasi.
- Filter bahan berbasis teks bebas (kata inti); tidak menangani salah eja/sinonim lengkap; kuantitas tidak diperiksa.
- K-Means: metrik lama (silhouette 0,336; ARI 0,762) berasal dari basis 1.0.0 dan ditandai `USANG_DATA_LAMA`. Refit KMeans belum dilakukan karena menunggu kebijakan nilai kosong.
- Daftar 4 sel dicurigai lama tidak diterapkan pada basis TKPI; nilai baru R024/R031/R063 wajar dan dipertahankan.
- 14 resep tanpa gizi tidak diimputasi dan tidak mendapat kredit; bukan berarti nol atau defisiensi.

**Paket**
- Peringatan versi scikit-learn pada ML2 (bagian 3). Tidak ada autentikasi/rate limit. Lolosnya tes = keberhasilan teknis, bukan validitas ilmiah/klinis.

## 10. Deployment server

- Jalankan sebagai servis terpisah (bukan Colab), di jaringan privat atau di belakang reverse proxy; `ML_API_URL` Laravel menunjuk alamat internal. **Batasi akses di tingkat jaringan** (tidak ada autentikasi). Set `API_DOCS_ENABLED=false` di server publik.
- Contoh systemd:
```ini
[Service]
WorkingDirectory=/opt/ml-monitoring-gizi
EnvironmentFile=/opt/ml-monitoring-gizi/.env
ExecStart=/opt/ml-monitoring-gizi/.venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 8000 --workers 2
Restart=on-failure
```
- Setiap worker memuat model sendiri (ML1 ±3,6 MB terkompresi, RandomForest 300 pohon). Mulai dengan 1–2 worker; ukur memori.
- Pasca-deploy: `curl http://127.0.0.1:8000/health` harus `200` dan `status: ok`; bila `503`, baca `modules.*.detail`.
- Log tidak memuat data anak. Jangan mengaktifkan log akses yang mencatat body request.
- Mengganti ambang ML1 (KONF-02) mewajibkan latih ulang dan menaikkan `versi_artefak` (`docs/ml1/build_model_asli.py`); mengubah `metadata.json` saja membuat aturan dan model tidak sinkron.

## 11. Pengujian

```bash
pip install -r requirements-dev.txt
python -m pytest -v
```

**Hasil verifikasi 15 November 2026 di venv lengkap (Python 3.14.6, scikit-learn 1.9.1, pygrowup 0.8.2) — 94 lulus, 1 dilewati, 0 gagal:**

| Berkas | Dijalankan & lulus | Catatan |
|---|---|---|
| `test_ml1_inference.py` | 25 dari 26 | model dimuat; SHA-256; fitur & urutan = metadata; probabilitas sampel = contoh asli; validasi input; aturan ambang; prediksi Z-score end-to-end. **1 dilewati**: tes fallback tanpa `pygrowup` tidak relevan. |
| `test_ml2.py` | 32 | output contoh baru; 93/93 cluster lama; 15 kalsium/21 zinc/54 vitamin C NaN; `bahan_json` identik dengan basis lama; 14 resep all-NULL tidak mendapat kredit; sanity top-5 kalsium/zinc/besi; **tidak ada `fit` saat request**. |
| `test_services.py` | 10 | modul independen; kesiapan jujur; error tanpa path; log tanpa data input; ML1+ML2 siap di venv lengkap. |
| `test_schemas.py` | 9 | skema Pydantic ML1/ML2. |
| `test_packaging.py` | 4 | tanpa ketergantungan Colab/path absolut; tanpa `.fit(`; hash manifest cocok. |
| `test_api.py` | 14 | Endpoint, 422/503/500, format error, tanpa echo input, `/docs`; ML1+ML2 di lifespan. |

**Uji nyata tambahan:** `uvicorn` + `GET /health` siap; `php artisan ml:health` exit 0 dengan ML1/ML2 siap. Peringatan versi scikit-learn pada ML2 hanya dicatat sebagai `catatan_kompatibilitas`; integrasi Laravel → API sungguhan tetap belum diuji.
