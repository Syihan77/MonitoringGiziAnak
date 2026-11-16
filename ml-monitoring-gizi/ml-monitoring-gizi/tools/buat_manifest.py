"""Membuat ulang artifacts_manifest.json (SHA-256 berkas + status verifikasi). Jalankan dari root paket:
    python tools/buat_manifest.py
Status verifikasi di bawah dicatat dari pengujian yang BENAR-BENAR dijalankan (lihat README bagian Pengujian)."""
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def sha(p: str) -> str:
    return hashlib.sha256((ROOT / p).read_bytes()).hexdigest()


V_ML1_MODEL = "terverifikasi: SHA-256 = metadata.json; dimuat tanpa peringatan di scikit-learn 1.9.1; probabilitas sampel = sample_response asli"
V_ML2_MODEL = ("USANG_DATA_LAMA: dibuat di scikit-learn 1.8.0 dari basis 1.0.0; 93/93 label cluster lama identik "
               "dengan dataset_dengan_cluster.csv, tetapi label tidak dihitung ulang pada basis TKPI 1.1.0")
B = [
    ("app/ml1/model/model.joblib", "ML1", "Model RandomForest terlatih (fitur mentah, skenario B)", "ml1-rf-fitur-mentah-1.0.0", V_ML1_MODEL),
    ("app/ml1/model/metadata.json", "ML1", "Metadata: fitur, ambang, rentang data latih, evaluasi, SHA-256", "ml1-rf-fitur-mentah-1.0.0", "diaudit; konsisten dengan model"),
    ("app/ml1/model/feature_schema.json", "ML1", "Kontrak input & batas validasi", "ml1-rf-fitur-mentah-1.0.0", "diaudit; dipakai validasi (diuji)"),
    ("app/ml1/model/label_mapping.json", "ML1", "Pemetaan kelas 0/1/2 -> nama", "ml1-rf-fitur-mentah-1.0.0", "diaudit; dipakai inferensi"),
    ("app/ml1/inference.py", "ML1", "Modul inferensi mandiri (ML1Predictor). Perubahan dari asli: path artefak ke model/, impor pygrowup dijaga", "1.0.0 (+penyesuaian path)", "validasi & pemuatan diuji; jalur Z-score belum dijalankan (pygrowup tidak terpasang di sandbox)"),
    ("app/ml2/model/kmeans_pipeline.joblib", "ML2", "Pipeline log1p -> StandardScaler -> KMeans(K=4)", "1.0.0", V_ML2_MODEL),
    ("app/ml2/model/preprocessing.joblib", "ML2", "Bagian preprocessing saja (tidak dipakai saat inferensi API)", "1.0.0", "artefak lama; tidak di-refit saat pembaruan basis"),
    ("app/ml2/model/config_model.json", "ML2", "Konfigurasi, fitur clustering, metadata nilai tidak diketahui, metrik", "1.1.0-tkpi", "diuji: status_cluster USANG_DATA_LAMA; metadata_missing TKPI; sel_dicurigai kosong"),
    ("app/ml2/model/basis_resep.csv", "ML2", "Basis 93 resep TKPI (nilai gizi tidak diketahui = kosong/NaN; cluster lama dipertahankan)", "1.1.0-tkpi", "diuji: 93/93 bahan_json identik; 15 kalsium, 21 zinc, 54 vitamin C NaN; 14 resep all-NULL"),
    ("app/ml2/rekomendasi_gizi.py", "ML2", "Modul ranking, filter bahan, PemberiRekomendasi (TIDAK DIUBAH dari asli)", "1.0.0", "contoh_output baru direproduksi; 32 tes ML2 lulus"),
    ("app/main.py", "API", "Aplikasi FastAPI gabungan (lifespan memuat ML1 & ML2 terpisah)", "1.0.0", "diuji: 14 tes HTTP berjalan; jalur pygrowup dilewati"),
    ("app/api/routes.py", "API", "Endpoint /health, /ml1/*, /ml2/*", "1.0.0", "diuji: 14 tes HTTP berjalan"),
    ("app/services.py", "API", "Pemuatan modul, kesiapan, health, pemanggilan inferensi (tanpa FastAPI)", "1.0.0", "diuji"),
    ("app/settings.py", "API", "Konfigurasi dari environment", "1.0.0", "diuji tidak langsung"),
    ("app/schemas/ml1.py", "API", "Skema request ML1", "1.0.0", "diuji; 2 tes ML1 dilewati tanpa pygrowup"),
    ("app/schemas/ml2.py", "API", "Skema request ML2 (extra=forbid)", "1.0.0", "diuji"),
    ("examples/ml1_input.json", "contoh", "Contoh request ML1 (sampel teknis)", "-", "dari artefak ML1 asli"),
    ("examples/ml1_output.json", "contoh", "Contoh response ML1 (tanpa kunci status)", "-", "dari artefak ML1 asli; probabilitas ML diverifikasi ulang"),
    ("examples/ml1_output_422.json", "contoh", "Contoh response error ML1", "-", "dari artefak ML1 asli"),
    ("examples/ml2_input.json", "contoh", "Contoh request ML2", "-", "dari artefak ML2 asli"),
    ("examples/ml2_output.json", "contoh", "Contoh response ML2 (basis TKPI 1.1.0)", "-", "diregenerasi dan direproduksi oleh modul"),
]
out = {
    "nama_paket": "ml-monitoring-gizi",
    "versi_paket": "1.1.0",
    "dibuat": "2026-11-15",
    "catatan": "SHA-256 dihitung saat paket dikemas. Status verifikasi hanya mencatat apa yang benar-benar diuji.",
    "berkas": [{"path": p, "modul": m, "fungsi": f, "versi": v, "sha256": sha(p), "status_verifikasi": s} for p, m, f, v, s in B],
}
(ROOT / "artifacts_manifest.json").write_text(json.dumps(out, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
print("manifest:", len(B), "berkas")
