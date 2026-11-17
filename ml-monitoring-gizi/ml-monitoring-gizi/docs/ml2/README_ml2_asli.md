# ML2 — Rekomendasi Resep Gizi Anak (Pencegahan Stunting)

> **Catatan pembaruan 15 November 2026:** dokumen ini adalah arsip asli basis 1.0.0. Basis aktif kini `1.1.0-tkpi` (93 resep TKPI); `ml:verify-basis` Laravel = 93 cocok / 0 selisih. Label dan metrik K-Means di bawah berasal dari basis lama dan ditandai `USANG_DATA_LAMA`; refit menunggu kebijakan nilai kosong. Jangan menampilkan label cluster sebagai analisis gizi. Angka 28/29 dan 4 sel dicurigai di bawah adalah historis.

Dibuat: 2026-10-29T05:57:00+00:00 | Versi model asli: 1.0.0 | Jumlah resep diproses: 93 | scikit-learn 1.8.0

## Isi folder
| Berkas | Fungsi |
|---|---|
| `rekomendasi_gizi.py` | Modul ranking + filter bahan + kelas `PemberiRekomendasi` (dipakai notebook dan API) |
| `kmeans_pipeline.joblib` | Pipeline terlatih: `log1p` -> `StandardScaler` -> `KMeans` (preprocessing dan model satu paket) |
| `preprocessing.joblib` | Bagian preprocessing saja |
| `config_model.json` | Versi, fitur, parameter K-Means, metadata nilai tidak diketahui, metrik, metode bawaan |
| `basis_resep.csv` | Tabel resep untuk ranking (nutrisi tidak diketahui = kosong/NaN, label cluster, bahan terurai JSON) |
| `dataset_dengan_cluster.csv` | Dataset asli + kolom `cluster` |
| `contoh_input.json`, `contoh_output.json` | Contoh kontrak permintaan/jawaban |
| `contoh_penggunaan.py` | Pemakaian dari Python biasa |
| `api_fastapi.py` | Demo minimal FastAPI (bukan konfigurasi produksi) |
| `evaluasi_*.csv`, `hasil_uji_teknis.csv`, `gambar/` | Hasil evaluasi dan visualisasi |

## Metode (tiga komponen berbeda)
1. **K-Means (unsupervised, dilatih)** — mengelompokkan resep berdasarkan profil nutrisi (energi, karbohidrat, protein, lemak, zat_besi, vitamin_c, vitamin_a, serat). Strategi S2 (8 fitur lengkap, log1p + standardisasi), K=4. Tidak memprediksi resep terbaik dan tidak mempelajari preferensi pengguna.
2. **Content-based ranking (tidak dilatih)** — skor = jumlah (bobot ternormalisasi x persentil nilai gizi per porsi) hanya atas nilai yang DIKETAHUI. Skor = peringkat relatif dalam dataset, bukan kecukupan gizi.
3. **Rule-based filtering** — penyaringan bahan wajib yang tersedia (+ waktu masak dan bahan yang dihindari).

Eksperimen cluster (re-ranking keragaman): Aturan keputusan TIDAK terpenuhi: tidak ada peningkatan keragaman yang konsisten pada kedua metrik dengan biaya skor yang dapat diterima. Baseline dipertahankan; K-Means tetap menjadi komponen analisis ML yang terpisah. Metode bawaan: **baseline**.

## Data dan penanganan nilai tidak diketahui
* Basis aktif `1.1.0-tkpi` memakai 93 resep TKPI. Nilai kosong tetap **tidak diketahui (NaN)**, tidak diisi nol, tidak diimputasi, dan tidak dianggap memenuhi prioritas. Jumlah kosong: kalsium 15, zinc 21, vitamin C 54, serat 18, vitamin A 15, zat besi 15, makronutrien 14.
* 14 resep tanpa gizi sama sekali tidak mendapat kredit ranking dan tidak diganti nol. Dengan kebijakan `penalti`, pada `top_n=50` yang hanya memprioritaskan vitamin C mereka dapat muncul di ekor karena hanya 39 nilai vitamin C yang diketahui.
* Label cluster dan metrik K-Means berasal dari basis 1.0.0 dan ditandai `USANG_DATA_LAMA`; refit menunggu kebijakan nilai kosong. Nilai nol yang valid dipertahankan dan diberi catatan "nilai 0 diragukan" bila data resep lain tidak lengkap.
* Daftar 4 sel dicurigai lama (R024, R031, R063) tidak diterapkan pada basis TKPI; nilai R024/R031/R063 yang baru wajar dan dipertahankan. Sensitivitas ARI 0.762 adalah angka historis basis lama.

## Hasil evaluasi (dari eksekusi notebook)
* Silhouette 0.336, Davies-Bouldin 1.129, Calinski-Harabasz 47.8; ukuran cluster: C0: 42, C1: 5, C2: 28, C3: 18; stabilitas antar seed (ARI) 0.984. Silhouette ~0,3 berarti struktur cluster lemah-menengah.
* Uji teknis ranking/filter: 25 dari 25 lulus.
* TIDAK ada validasi terhadap ahli gizi/pengguna; accuracy/precision/recall tidak dihitung karena tidak ada label.

## Cara memakai (Python biasa)
```python
from rekomendasi_gizi import PemberiRekomendasi
model = PemberiRekomendasi.muat("folder_artefak")      # sekali saat start
hasil = model.rekomendasi({"prioritas_gizi": {"protein": 0.5, "zat_besi": 0.5},
                           "bahan_tersedia": ["ayam", "tahu", "wortel"], "top_n": 3})
```
Parameter payload: `prioritas_gizi` ATAU (`asupan_harian` + `acuan_harian` yang diberikan pemanggil), `bahan_tersedia`, `mode_bahan` (`nonaktif`/`semua_wajib`/`sebagian`), `min_kecocokan`, `top_n` (1-50), `maks_waktu_menit`, `bahan_dihindari`, `metode` (`baseline`/`cluster_diverse`), `lambda_keragaman`, `kebijakan_nilai_hilang` (`penalti`/`keluarkan`). Kunci tidak dikenal ditolak (ValueError -> HTTP 422 pada API).

## Integrasi Laravel (kontrak)
Jalankan `uvicorn api_fastapi:app --host 0.0.0.0 --port 8000` pada server Python (bukan sesi Colab). Laravel memanggil:
```
POST /rekomendasi   Content-Type: application/json   (body: lihat contoh_input.json)
GET  /health
```
Jawaban: `meta` (versi, bobot ternormalisasi, kandidat layak, alasan pengeluaran, peringatan) dan `hasil` (rank, id_resep, nama_menu, score, cluster, nutrisi_prioritas, nutrisi_tidak_tersedia, kecocokan_bahan, alasan). Model tidak dilatih ulang per permintaan. Simpan hasil rekomendasi sebagai SARAN menu; jangan otomatis menjadi catatan konsumsi anak.

## Keterbatasan
* Belum tervalidasi secara klinis/empiris; tidak ada label relevansi. Skor relatif terhadap 93 resep, bukan kebutuhan gizi anak.
* Nilai gizi per porsi tidak disesuaikan umur/berat anak; ukuran porsi antar resep sangat bervariasi.
* Filter bahan berbasis teks bebas: pencocokan longgar (kata inti), bumbu dapur diasumsikan tersedia, kuantitas tidak diperiksa, kemungkinan salah ejaan/sinonim tidak tertangani.
* Cluster lemah-menengah dan sebagian dipengaruhi ukuran/jenis hidangan; nama cluster hanya deskriptif.
* Kalsium/zinc tidak diketahui untuk sebagian resep (basis aktif: 15 kalsium, 21 zinc) sehingga resep tersebut tidak dapat dinilai pada zat gizi itu. Angka 27 + sel dicurigai adalah historis basis lama.

## Dependensi
Lihat `requirements.txt` (numpy, pandas, scikit-learn, joblib; fastapi/uvicorn opsional). Model joblib sebaiknya dimuat dengan versi scikit-learn yang sama (1.8.0).
