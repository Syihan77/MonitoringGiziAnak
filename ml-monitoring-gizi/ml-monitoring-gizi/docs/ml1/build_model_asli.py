"""Membuat ulang artefak ML #1 dari dataset (HANYA untuk reproduksi; tidak dipakai saat inferensi/API).

Logika data & model disalin dari ML1_baseline.ipynb tanpa perubahan:
  - Z-score WHO via pygrowup (wfa, lhfa, wfh), buang Z-score tak terhitung / tak masuk akal
  - label = aturan acuan v0-provisional (indikator terburuk; ambang -3 dan -2)
  - fitur skenario B: usia_bulan, jk_num (L=1), bb_kg, tb_cm
  - RandomForestClassifier(300, class_weight="balanced", random_state=42, n_jobs=-1)

Pemakaian:  python build_model.py /path/anak_pra_bersih.csv
Dataset TIDAK ikut dikemas dalam artefak (berisi data anak).
"""
import datetime as dt
import hashlib
import json
import platform
import sys
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
import sklearn
from sklearn.ensemble import RandomForestClassifier

import inference as inf
import pygrowup

HERE = Path(__file__).resolve().parent
ATURAN_VERSI = "v0-provisional"
AMBANG = {"high_risk": -3.0, "at_risk": -2.0}  # MENUNGGU VALIDASI AHLI (KONF-02)
BATAS_PLAUSIBEL = {"waz": (-6, 5), "haz": (-6, 6), "whz": (-5, 5)}
USIA_MIN, USIA_MAX = 12, 59
SEED = 42
FITUR = inf.FITUR_URUT
VERSI_ARTEFAK = "ml1-rf-fitur-mentah-1.0.0"
CATATAN = ("Alat bantu pemantauan, BUKAN diagnosis stunting dan bukan kategori WHO. Kategori adalah rancangan "
           "sistem dari aturan sementara v0-provisional (belum divalidasi ahli gizi, KONF-02). Model ML hanya "
           "mereplikasi aturan tersebut dan belum lolos evaluasi/validasi; tampilkan kategori acuan kepada pengguna.")

# Hasil evaluasi aktual (GroupKFold 5-fold per anak, label = aturan acuan v0-provisional), model RandomForest.
# Diambil dari output notebook (Colab) dan dari eksekusi ulang notebook untuk finalisasi (lingkungan berbeda).
EVALUASI = {
    "skema": "GroupKFold(n_splits=5) pada id_anak, cross_val_predict; bukan hold-out terpisah",
    "catatan": ("Estimasi CV TIDAK identik antar eksekusi: akurasi RF sama (0.914) tetapi macro-F1 dan recall High Risk "
                "berbeda (0.505 vs 0.641). Penyebab belum diverifikasi (dugaan: perbedaan versi library/penugasan fold). "
                "Gunakan kisaran, jangan satu angka. Recall High Risk rendah pada kedua eksekusi."),
    "RandomForest_fitur_mentah_B": {
        "output_notebook_tersimpan": {"akurasi": 0.914, "macro_F1": 0.813, "recall_AtRisk": 0.838,
                                      "recall_HighRisk": 0.505, "presisi_HighRisk": 0.867},
        "eksekusi_ulang_finalisasi": {"akurasi": 0.914, "macro_F1": 0.842, "recall_AtRisk": 0.879,
                                      "recall_HighRisk": 0.641, "presisi_HighRisk": 0.834,
                                      "lingkungan": "python 3.13, scikit-learn 1.9.1"},
    },
    "RandomForest_zscore_A_output_notebook": {"akurasi": 0.998, "macro_F1": 0.996,
                                              "catatan": "Label adalah fungsi deterministik dari Z-score; BUKAN bukti validitas klinis."},
}


def main(csv_path: str) -> None:
    df = pd.read_csv(csv_path)
    calc = pygrowup.Calculator(adjust_height_data=False, adjust_weight_scores=False,
                               include_cdc=False, log_level="CRITICAL")

    def hitung(r):
        sx = "M" if r.jk == "L" else "F"
        u = str(int(r.usia_bulan))
        return pd.Series({"waz": inf._z(calc.wfa, str(r.bb_kg), u, sx),
                          "haz": inf._z(calc.lhfa, str(r.tb_cm), u, sx),
                          "whz": inf._z(calc.wfh, str(r.bb_kg), u, sx, str(r.tb_cm))})

    n_awal = len(df)
    df = pd.concat([df, df.apply(hitung, axis=1)], axis=1)
    tak_terhitung = df[inf.INDIKATOR].isna().any(axis=1)
    implausibel = pd.Series(False, index=df.index)
    for k, (lo, hi) in BATAS_PLAUSIBEL.items():
        implausibel |= (df[k] < lo) | (df[k] > hi)
    df = df[~(tak_terhitung | implausibel)].copy()
    df["jk_num"] = (df.jk == "L").astype(int)
    df["y"] = [inf.kategori_acuan(w, h, z, AMBANG) for w, h, z in zip(df.waz, df.haz, df.whz)]
    df = df.sort_values(["id_anak", "bulan_ukur"]).reset_index(drop=True)  # urutan sama dgn notebook

    model = RandomForestClassifier(300, class_weight="balanced", random_state=SEED, n_jobs=-1)
    model.fit(df[FITUR].values, df.y.values)
    joblib.dump(model, HERE / "model.joblib", compress=3)
    sha = hashlib.sha256((HERE / "model.joblib").read_bytes()).hexdigest()

    dist = df.y.value_counts().sort_index()
    metadata = {
        "versi_artefak": VERSI_ARTEFAK,
        "tujuan": "ML #1 — replikasi kriteria pemantauan pertumbuhan anak (kategori Normal / At Risk / High Risk)",
        "algoritma": "RandomForestClassifier(n_estimators=300, class_weight='balanced', random_state=42)",
        "skenario_fitur": "B_mentah",
        "fitur": FITUR,
        "target": "y = kategori_acuan(waz, haz, whz) — aturan acuan v0-provisional",
        "aturan_versi": ATURAN_VERSI,
        "ambang": AMBANG,
        "batas_plausibel_zscore": {k: list(v) for k, v in BATAS_PLAUSIBEL.items()},
        "cakupan_usia_bulan": [USIA_MIN, USIA_MAX],
        "status": "EKSPERIMEN - belum lolos evaluasi/validasi, jangan dipakai sebagai kategori di tampilan pengguna",
        "catatan_penggunaan": CATATAN,
        "data_latih": {
            "baris_input": int(n_awal), "baris_dipakai": int(len(df)), "anak": int(df.id_anak.nunique()),
            "distribusi_kelas": {str(k): int(v) for k, v in dist.items()},
        },
        "rentang_data_latih": {
            "usia_bulan": {"min": int(df.usia_bulan.min()), "maks": int(df.usia_bulan.max())},
            "bb_kg": {"min": float(df.bb_kg.min()), "maks": float(df.bb_kg.max())},
            "tb_cm": {"min": float(df.tb_cm.min()), "maks": float(df.tb_cm.max())},
        },
        "preprocessing": "Tidak ada scaling/imputasi. jk: L->1, P->0. Nilai kosong ditolak (tidak diimputasi). "
                         "Z-score WHO (pygrowup) dipakai untuk penyaringan nilai tak masuk akal dan aturan acuan, bukan sebagai fitur model.",
        "evaluasi": EVALUASI,
        "tanggal_dibuat": dt.date.today().isoformat(),
        "sha256_model": sha,
        "lingkungan": {"python": platform.python_version(), "scikit-learn": sklearn.__version__,
                       "numpy": np.__version__, "pandas": pd.__version__, "pygrowup": "0.8.2"},
    }
    (HERE / "metadata.json").write_text(json.dumps(metadata, indent=2, ensure_ascii=False), encoding="utf-8")

    r = metadata["rentang_data_latih"]
    schema = {
        "fitur": {
            "usia_bulan": {"tipe": "integer", "satuan": "bulan", "wajib": True, "min": USIA_MIN, "maks": USIA_MAX,
                           "keterangan": "Usia anak saat pengukuran (cakupan SKPL 12–59 bulan)."},
            "jk": {"tipe": "kategori", "wajib": True, "nilai_valid": ["L", "P"],
                   "keterangan": "L = laki-laki (dikodekan 1), P = perempuan (0)."},
            "bb_kg": {"tipe": "angka", "satuan": "kg", "wajib": True, "min": 2.0, "maks": 40.0,
                      "keterangan": "Batas teknis kasar, bukan batas klinis. Kewajaran biologis dicek via Z-score."},
            "tb_cm": {"tipe": "angka", "satuan": "cm", "wajib": True, "min": 45.0, "maks": 130.0,
                      "keterangan": "Batas teknis kasar, bukan batas klinis. Kewajaran biologis dicek via Z-score."},
        },
        "urutan_fitur_model": FITUR,
        "kebijakan_nilai_kosong": "Ditolak (HTTP 422 / InputError); tidak diimputasi, sesuai notebook yang membuang baris kosong.",
        "rentang_data_latih": {"bb_kg": r["bb_kg"], "tb_cm": r["tb_cm"], "usia_bulan": r["usia_bulan"]},
    }
    (HERE / "feature_schema.json").write_text(json.dumps(schema, indent=2, ensure_ascii=False), encoding="utf-8")

    labels = {
        "kelas": {
            "0": {"nama": "Normal", "deskripsi": "Normal / Low Risk"},
            "1": {"nama": "At Risk", "deskripsi": "At Risk / Needs Monitoring"},
            "2": {"nama": "High Risk", "deskripsi": "High Risk / Referral"},
        },
        "aturan": "Indikator terburuk = min(waz, haz, whz). < -3.0 → High Risk; < -2.0 → At Risk; selain itu Normal.",
        "peringatan": "Rancangan kategori sistem (v0-provisional), bukan diagnosis dan bukan kategori WHO.",
    }
    (HERE / "label_mapping.json").write_text(json.dumps(labels, indent=2, ensure_ascii=False), encoding="utf-8")
    print("OK:", len(df), "baris,", df.id_anak.nunique(), "anak | kelas:", dict(dist), "| sha256:", sha[:16])


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit("Pemakaian: python build_model.py /path/anak_pra_bersih.csv")
    main(sys.argv[1])
