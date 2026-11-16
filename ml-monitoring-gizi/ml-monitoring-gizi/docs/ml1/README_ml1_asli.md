# ML #1 — Replikasi Kriteria Pemantauan Pertumbuhan Anak

Artefak inferensi + layanan FastAPI untuk **Sistem Monitoring dan Rekomendasi Gizi Anak untuk Pencegahan Stunting** (SKPL v2.0 §3.3.1).
Sumber: `ML1_baseline.ipynb` (skenario fitur **B — fitur mentah**, model RandomForest).

> **Baca dulu — apa yang BUKAN klaim paket ini**
> - Kategori (`Normal` / `At Risk` / `High Risk`) adalah **rancangan kategori sistem**, bukan diagnosis stunting dan bukan kategori WHO.
> - Label latih dibuat oleh **aturan sementara `v0-provisional`** (ambang −2,0 dan −3,0 SD, menunggu keputusan ahli gizi KONF-02). Model ML **hanya mereplikasi aturan itu**; kesesuaiannya bukan bukti validitas klinis.
> - Model **belum lolos evaluasi/validasi ahli** (`status: EKSPERIMEN`). Sesuai notebook (ML1-13 / KONF-14), **tampilkan `acuan.kategori` ke pengguna**; `prediksi_ml` hanya untuk pembanding/eksperimen.

## 1. Ruang lingkup
Menerima satu pengukuran (usia 12–59 bulan, jenis kelamin, BB, TB) dan mengembalikan:
1. **Kategori acuan** — aturan `v0-provisional` dari Z-score WHO (BB/U, TB/U, BB/TB via `pygrowup`); indikator terburuk menentukan kategori.
2. **Prediksi ML** — RandomForest yang dilatih meniru aturan itu **hanya dari fitur mentah** (tanpa Z-score), beserta probabilitas kelas.

## 2. Model, fitur, target
| Item | Nilai (dari notebook) |
|---|---|
| Algoritma | `RandomForestClassifier(n_estimators=300, class_weight="balanced", random_state=42)` |
| Fitur (urutan model) | `usia_bulan`, `jk_num` (L=1, P=0), `bb_kg`, `tb_cm` |
| Target | 0 = Normal, 1 = At Risk, 2 = High Risk (`min(waz,haz,whz) < −3` → 2; `< −2` → 1) |
| Preprocessing | Tidak ada scaling/imputasi. Nilai kosong **ditolak**. Z-score tak terhitung / di luar batas masuk akal (waz −6…5, haz −6…6, whz −5…5) **ditolak**, sama seperti baris yang dibuang notebook |
| Data latih | 3.635 baris / 478 anak (dari 3.642 baris): Normal 2.458 · At Risk 957 · High Risk 220 |
| Pemisahan evaluasi | `GroupKFold` 5-fold per `id_anak` |

Sumber data: tabel `anak_pra_bersih.csv` dari 9 sheet posyandu (tanpa nama/NIK). **Dataset tidak disertakan** dalam paket.

## 3. Struktur berkas
```
ml1_artifacts/
├── model.joblib            RandomForest terlatih (tanpa preprocessing tambahan)
├── metadata.json           versi, algoritma, fitur, ambang, rentang data latih, evaluasi, SHA-256, versi library
├── feature_schema.json     kontrak input + batas validasi
├── label_mapping.json      0/1/2 → nama kelas
├── inference.py            ML1Predictor / predict() — tanpa training, tanpa notebook
├── main.py                 FastAPI: GET /health, POST /predict
├── build_model.py          (opsional) reproduksi artefak dari CSV; tidak dipakai saat inferensi
├── requirements.txt · requirements-dev.txt
├── sample_request.json · sample_response.json · sample_response_422.json
└── tests/                  43 pengujian (pytest)
```

## 4. Instalasi
Diuji hanya pada Python 3.13.18 (notebook asli berjalan di Python 3.12.3; versi lain belum diuji). **`scikit-learn` harus 1.9.1** — file `.joblib` (pickle) tidak dijamin kompatibel lintas versi.

```bash
python -m venv .venv && source .venv/bin/activate      # Windows: .venv\Scripts\activate
pip install --upgrade pip setuptools wheel six
pip install -r requirements.txt
# bila build pygrowup gagal ("No module named six" / "install_layout"):
pip install --no-build-isolation -r requirements.txt
```
Jika versi scikit-learn tidak bisa disamakan: `pip install pandas` lalu `python build_model.py anak_pra_bersih.csv` untuk membuat ulang artefak (hasilnya akan sama bila data & versi sama).

## 5. Pemakaian dari Python
```python
from inference import ML1Predictor, InputError
p = ML1Predictor()                      # muat sekali, pakai berulang
try:
    hasil = p.predict({"usia_bulan": 30, "jk": "L", "bb_kg": 11.2, "tb_cm": 85.0})
except InputError as e:
    print(e.errors)                     # [{"field": ..., "pesan": ...}]
print(hasil["acuan"]["kategori"], hasil["prediksi_ml"]["probabilitas"])
# p.predict_batch([...]) untuk beberapa data
```

## 6. Menjalankan API
```bash
uvicorn main:app --host 127.0.0.1 --port 8001      # dari folder ini
curl http://127.0.0.1:8001/health
curl -X POST http://127.0.0.1:8001/predict -H "Content-Type: application/json" -d @sample_request.json
```
Env opsional: `ML1_ARTIFACT_DIR` (default: folder `main.py`). Model dimuat **sekali** saat start. Dokumentasi interaktif FastAPI ada di `/docs` (matikan di produksi bila perlu).

### `GET /health`
`200` → `{"status":"ok","service":"running","model_loaded":true,"versi_model":...}`
`503` → layanan hidup tetapi model gagal dimuat: `{"status":"degraded","service":"running","model_loaded":false,"detail":...}`

### `POST /predict`
Request (JSON number untuk angka; `jk` huruf besar `"L"`/`"P"`):

| Field | Tipe | Aturan |
|---|---|---|
| `usia_bulan` | integer | 12–59, wajib |
| `jk` | string | `"L"` atau `"P"`, wajib |
| `bb_kg` | number | 2–40, wajib |
| `tb_cm` | number | 45–130, wajib |

Batas BB/TB adalah batas teknis kasar; kewajaran biologis dicek lewat Z-score (422 bila tak masuk akal).

Response `200` (contoh nyata: `sample_response.json`):
```json
{
  "status": "ok",
  "versi_model": "ml1-rf-fitur-mentah-1.0.0",
  "status_model": "EKSPERIMEN - belum lolos evaluasi/validasi, jangan dipakai sebagai kategori di tampilan pengguna",
  "acuan": { "aturan_versi": "v0-provisional", "kategori_id": 1, "kategori": "At Risk",
             "zscore": {"waz": -1.46, "haz": -2.04, "whz": -0.51}, "indikator_terburuk": "haz" },
  "prediksi_ml": { "kategori_id": 1, "kategori": "At Risk",
                   "probabilitas": {"Normal": 0.0967, "At Risk": 0.9033, "High Risk": 0.0} },
  "ml_sama_dengan_acuan": true,
  "peringatan": [],
  "catatan": "Alat bantu pemantauan, BUKAN diagnosis stunting ..."
}
```
Error: `422` `{"status":"error","kode":"input_tidak_valid","pesan":...,"errors":[{"field","pesan"}]}` (nilai input tidak pernah di-echo); `500` `kesalahan_internal`; `503` `model_tidak_siap`. Tidak ada stack trace/path di response.

## 7. Integrasi Laravel
Laravel memanggil layanan Python lewat HTTP JSON; jangan menaruh kode Laravel di proyek Python.

`.env` (dev lokal; alamat produksi ditentukan tim deployment — jangan hardcode):
```
ML1_API_URL=http://127.0.0.1:8001
ML1_API_TIMEOUT=5
```
`config/services.php`:
```php
'ml1' => ['url' => env('ML1_API_URL', 'http://127.0.0.1:8001'), 'timeout' => (int) env('ML1_API_TIMEOUT', 5)],
```
Pemanggilan (mis. di service class):
```php
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

try {
    $res = Http::baseUrl(config('services.ml1.url'))
        ->acceptJson()->asJson()
        ->timeout(config('services.ml1.timeout'))
        ->post('/predict', [
            'usia_bulan' => (int) $anak->usia_bulan,
            'jk'         => $anak->jk,            // "L" | "P"
            'bb_kg'      => (float) $pengukuran->bb_kg,
            'tb_cm'      => (float) $pengukuran->tb_cm,
        ]);
} catch (ConnectionException $e) {
    return null; // layanan tidak tersedia / timeout → jalur cadangan
}

if ($res->successful()) { $hasil = $res->json(); /* tampilkan $hasil['acuan']['kategori'] */ }
elseif ($res->status() === 422) { /* tampilkan $res->json('errors') ke petugas: salah input/ukur */ }
else { /* 500/503 → jalur cadangan; catat tanpa data anak */ }
```
Health check: `Http::get(config('services.ml1.url').'/health')->ok()`.

**Perlu diputuskan tim:** `acuan` (sumber tampilan) hanya tersedia bila API hidup. Bila API tidak tersedia, SKPL-NF-REL-03 meminta fallback aturan acuan; paket ini **tidak** menyertakan perhitungan Z-score WHO untuk PHP, jadi tim perlu memilih: implementasi aturan di Laravel, atau menampilkan "kategori belum tersedia".

Dev vs deployment: dev memakai `127.0.0.1`; saat deployment jalankan uvicorn sebagai proses/servis terpisah (mis. di belakang reverse proxy, jaringan privat), set `ML1_API_URL` ke alamat internal itu. Google Colab bukan server produksi. Layanan ini tidak punya autentikasi — batasi aksesnya di tingkat jaringan.

## 8. Pengujian
```bash
pip install -r requirements-dev.txt
python -m pytest -v          # 43 tes: artefak, inferensi, validasi, API in-process, dan uvicorn nyata via HTTP
```
Sampel uji adalah **sampel teknis demonstrasi**, bukan data anak sebenarnya. Lolosnya tes = keberhasilan teknis, bukan validitas ilmiah/klinis.

## 9. Evaluasi (CV per-anak, label = aturan acuan) — RandomForest, fitur mentah
| | Akurasi | Macro-F1 | Recall At Risk | Recall High Risk | Presisi High Risk |
|---|---|---|---|---|---|
| Output tersimpan di notebook | 0,914 | 0,813 | 0,838 | **0,505** | 0,867 |
| Eksekusi ulang saat finalisasi (sklearn 1.9.1) | 0,914 | 0,842 | 0,879 | **0,641** | 0,834 |

Dua eksekusi memberi hasil berbeda pada metrik tertentu; penyebab belum diverifikasi (dugaan: versi library/penugasan fold). Perlakukan sebagai kisaran. Pada skenario Z-score (A) akurasi ±0,998 — itu hanya karena label adalah fungsi dari fitur, **bukan** akurasi model.

## 10. Keterbatasan
- Label = aturan sementara; belum ada validasi ahli gizi atau label klinis. Model tidak memprediksi risiko klinis.
- **Recall High Risk hanya ±0,5–0,64**: model melewatkan sebagian anak High Risk. Jangan menggantikan aturan acuan dengan model.
- Prediksi ML pada data latih tampak hampir sempurna (kecocokan ±99,9%) karena RandomForest menghafal; gunakan angka CV, bukan angka ini.
- Data dari 9 posyandu; belum ada uji pada populasi/posyandu lain. Usia di luar 12–59 bulan ditolak.
- Ambang bisa berubah (KONF-02): ubah di `build_model.py`, latih ulang, naikkan `versi_artefak`. Hanya mengubah `metadata.json` akan membuat aturan acuan dan model tidak sinkron.
- Tidak ada autentikasi/rate limit; batasi di jaringan.

## 11. Versi
Dibuat 2026-10-29 · Python 3.13.18 · scikit-learn 1.9.1 · numpy 2.5 · pandas 3.0 · pygrowup 0.8.2 · fastapi 0.162 · pydantic 2.x. SHA-256 model ada di `metadata.json`.
