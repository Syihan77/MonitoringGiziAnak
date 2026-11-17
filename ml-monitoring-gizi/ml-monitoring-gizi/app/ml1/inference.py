"""Inferensi ML #1 — Replikasi Kriteria Pemantauan Pertumbuhan.

Modul ini memuat artefak (model.joblib, metadata.json, feature_schema.json,
label_mapping.json) dan menghasilkan prediksi TANPA menjalankan notebook dan TANPA
training. Dapat dipakai dari program Python biasa maupun dari FastAPI (main.py).

PENTING (lihat README):
- Kategori adalah rancangan kategori sistem (aturan v0-provisional), BUKAN diagnosis
  stunting dan bukan kategori WHO.
- Model ML hanya mereplikasi aturan acuan; belum lolos evaluasi/validasi ahli.
  Aturan acuan tetap menjadi sumber kategori untuk tampilan pengguna.
"""
from __future__ import annotations

import json
import math
import numbers
from pathlib import Path
from typing import Any

import joblib
import numpy as np

try:  # pygrowup (tabel WHO) wajib untuk Z-score; bila tidak ada, ML1 dilaporkan TIDAK siap (bukan diam-diam).
    from pygrowup import Calculator
    _PYGROWUP_ERROR: str | None = None
except Exception as _e:  # pragma: no cover - bergantung pada lingkungan
    Calculator = None  # type: ignore[assignment]
    _PYGROWUP_ERROR = type(_e).__name__

# [Paket gabungan] artefak ML1 berada di subfolder `model/` (aslinya sejajar dengan berkas ini).
ARTIFACT_DIR = Path(__file__).resolve().parent / "model"
FITUR_URUT = ["usia_bulan", "jk_num", "bb_kg", "tb_cm"]  # urutan fitur saat training (notebook, skenario B)
INDIKATOR = ["waz", "haz", "whz"]


class InputError(ValueError):
    """Input tidak valid. `errors` berisi daftar {field, pesan} tanpa menyertakan nilai input."""

    def __init__(self, errors: list[dict[str, str]]):
        self.errors = errors
        super().__init__("; ".join(f"{e['field']}: {e['pesan']}" for e in errors))


class ModelLoadError(RuntimeError):
    """Artefak tidak ditemukan / gagal dimuat / tidak konsisten."""


# --------------------------------------------------------------------------- aturan acuan
def _z(fn, *args) -> float:
    """Sama dengan notebook: kegagalan pygrowup -> NaN."""
    try:
        return float(fn(*args))
    except Exception:
        return float("nan")


def kategori_acuan(waz: float, haz: float, whz: float, ambang: dict[str, float]) -> int:
    """0 = Normal, 1 = At Risk, 2 = High Risk. Indikator terburuk menentukan kategori
    (identik dengan `kategori_acuan` di notebook)."""
    terburuk = min(waz, haz, whz)
    if terburuk < ambang["high_risk"]:
        return 2
    if terburuk < ambang["at_risk"]:
        return 1
    return 0


# --------------------------------------------------------------------------- predictor
class ML1Predictor:
    """Memuat artefak sekali; `predict` dapat dipanggil berulang kali."""

    def __init__(self, artifact_dir: str | Path | None = None):
        self.dir = Path(artifact_dir) if artifact_dir else ARTIFACT_DIR
        if Calculator is None:
            raise ModelLoadError(f"Pustaka pygrowup tidak dapat dimuat ({_PYGROWUP_ERROR}); Z-score WHO tidak tersedia.")
        try:
            self.metadata = json.loads((self.dir / "metadata.json").read_text(encoding="utf-8"))
            self.schema = json.loads((self.dir / "feature_schema.json").read_text(encoding="utf-8"))
            self.label_mapping = json.loads((self.dir / "label_mapping.json").read_text(encoding="utf-8"))
            self.model = joblib.load(self.dir / "model.joblib")
        except FileNotFoundError as e:
            raise ModelLoadError(f"Berkas artefak tidak ditemukan: {Path(e.filename).name}") from e
        except Exception as e:  # pickle/versi tidak cocok, JSON rusak, dsb.
            raise ModelLoadError(f"Artefak gagal dimuat ({type(e).__name__}).") from e

        if self.metadata.get("fitur") != FITUR_URUT:
            raise ModelLoadError("Urutan fitur pada metadata.json tidak sama dengan yang diharapkan inference.py.")
        if getattr(self.model, "n_features_in_", None) != len(FITUR_URUT):
            raise ModelLoadError("Jumlah fitur model tidak sesuai dengan skema.")
        if list(getattr(self.model, "classes_", [])) != [0, 1, 2]:
            raise ModelLoadError("Kelas model tidak sesuai dengan label_mapping.json.")
        # n_jobs=-1 dipakai saat training; untuk inferensi per-request thread ekstra hanya menambah
        # latensi. Parameter ini tidak mengubah hasil prediksi.
        if hasattr(self.model, "n_jobs"):
            self.model.n_jobs = 1

        self.ambang = self.metadata["ambang"]
        self.batas_plausibel = {k: tuple(v) for k, v in self.metadata["batas_plausibel_zscore"].items()}
        self.kelas = {int(k): v["nama"] for k, v in self.label_mapping["kelas"].items()}
        self.rentang_latih = self.metadata["rentang_data_latih"]
        self._f = self.schema["fitur"]
        # Kalkulator WHO: konfigurasi identik dengan notebook.
        self.calc = Calculator(adjust_height_data=False, adjust_weight_scores=False,
                               include_cdc=False, log_level="CRITICAL")

    # ------------------------------------------------------------------ validasi
    @staticmethod
    def _is_num(v: Any) -> bool:
        return isinstance(v, numbers.Real) and not isinstance(v, bool) and math.isfinite(float(v))

    def _validasi(self, data: Any) -> dict[str, Any]:
        if not isinstance(data, dict) or not data:
            raise InputError([{"field": "(body)", "pesan": "Input harus berupa objek/dictionary yang tidak kosong."}])
        errs: list[dict[str, str]] = []
        out: dict[str, Any] = {}
        for nama, spec in self._f.items():
            if nama not in data or data[nama] is None or (isinstance(data[nama], str) and not data[nama].strip()):
                errs.append({"field": nama, "pesan": "Wajib diisi (tidak boleh kosong). Nilai kosong tidak diimputasi."})
                continue
            v = data[nama]
            if spec["tipe"] == "kategori":
                if not isinstance(v, str) or v.strip().upper() not in spec["nilai_valid"]:
                    errs.append({"field": nama, "pesan": f"Harus salah satu dari {spec['nilai_valid']}."})
                else:
                    out[nama] = v.strip().upper()
                continue
            if not self._is_num(v):
                errs.append({"field": nama, "pesan": "Harus berupa angka."})
                continue
            v = float(v)
            if spec["tipe"] == "integer":
                if v != int(v):
                    errs.append({"field": nama, "pesan": "Harus berupa bilangan bulat."})
                    continue
                v = int(v)
            if not (spec["min"] <= v <= spec["maks"]):
                errs.append({"field": nama, "pesan": f"Harus berada pada rentang {spec['min']}–{spec['maks']} ({spec['satuan']})."})
                continue
            out[nama] = v
        if errs:
            raise InputError(errs)
        return out

    # ------------------------------------------------------------------ prediksi
    def _zscore(self, x: dict[str, Any]) -> dict[str, float]:
        sx = "M" if x["jk"] == "L" else "F"
        u = str(int(x["usia_bulan"]))
        bb, tb = str(x["bb_kg"]), str(x["tb_cm"])
        return {"waz": _z(self.calc.wfa, bb, u, sx),
                "haz": _z(self.calc.lhfa, tb, u, sx),
                "whz": _z(self.calc.wfh, bb, u, sx, tb)}

    def predict(self, data: dict[str, Any]) -> dict[str, Any]:
        x = self._validasi(data)

        # 1) Penyaringan identik dengan notebook: Z-score harus terhitung dan masuk akal secara biologis.
        z = self._zscore(x)
        errs = []
        for k in INDIKATOR:
            if math.isnan(z[k]):
                errs.append({"field": k, "pesan": "Z-score WHO tidak dapat dihitung dari usia/jenis kelamin/BB/TB ini; periksa ulang pengukuran."})
            else:
                lo, hi = self.batas_plausibel[k]
                if z[k] < lo or z[k] > hi:
                    errs.append({"field": k, "pesan": f"Z-score di luar batas masuk akal ({lo} s.d. {hi}); kemungkinan salah ukur/salah input."})
        if errs:
            raise InputError(errs)

        # 2) Aturan acuan (sumber kategori untuk tampilan pengguna).
        kid = kategori_acuan(z["waz"], z["haz"], z["whz"], self.ambang)
        terburuk = min(INDIKATOR, key=lambda k: z[k])

        # 3) Model ML (fitur & urutan sama dengan training skenario B).
        vec = np.array([[x["usia_bulan"], 1 if x["jk"] == "L" else 0, x["bb_kg"], x["tb_cm"]]], dtype=float)
        proba = self.model.predict_proba(vec)[0]
        mid = int(self.model.classes_[int(np.argmax(proba))])

        peringatan = []
        for f in ("bb_kg", "tb_cm"):
            r = self.rentang_latih[f]
            if not (r["min"] <= x[f] <= r["maks"]):
                peringatan.append(f"{f} berada di luar rentang data latih ({r['min']}–{r['maks']}); prediksi ML kurang dapat diandalkan.")
        if mid != kid:
            peringatan.append("Prediksi ML berbeda dari aturan acuan; gunakan aturan acuan.")

        return {
            "versi_model": self.metadata["versi_artefak"],
            "status_model": self.metadata["status"],
            "acuan": {
                "aturan_versi": self.metadata["aturan_versi"],
                "kategori_id": kid,
                "kategori": self.kelas[kid],
                "zscore": {k: round(z[k], 2) for k in INDIKATOR},
                "indikator_terburuk": terburuk,
            },
            "prediksi_ml": {
                "kategori_id": mid,
                "kategori": self.kelas[mid],
                "probabilitas": {self.kelas[int(c)]: round(float(p), 4) for c, p in zip(self.model.classes_, proba)},
            },
            "ml_sama_dengan_acuan": mid == kid,
            "peringatan": peringatan,
            "catatan": self.metadata["catatan_penggunaan"],
        }

    def predict_batch(self, rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
        """Beberapa data sekaligus. Jika ada yang tidak valid, InputError memuat nomor baris."""
        hasil, errs = [], []
        for i, r in enumerate(rows):
            try:
                hasil.append(self.predict(r))
            except InputError as e:
                errs += [{"field": f"[{i}].{d['field']}", "pesan": d["pesan"]} for d in e.errors]
        if errs:
            raise InputError(errs)
        return hasil


_predictor: ML1Predictor | None = None


def load_predictor(artifact_dir: str | Path | None = None, force: bool = False) -> ML1Predictor:
    """Muat artefak sekali dan gunakan ulang pada pemanggilan berikutnya."""
    global _predictor
    if _predictor is None or force:
        _predictor = ML1Predictor(artifact_dir)
    return _predictor


def predict(data: dict[str, Any]) -> dict[str, Any]:
    """Pintasan: muat (sekali) lalu prediksi satu data."""
    return load_predictor().predict(data)


if __name__ == "__main__":
    contoh = {"usia_bulan": 30, "jk": "L", "bb_kg": 11.2, "tb_cm": 85.0}
    print(json.dumps(predict(contoh), indent=2, ensure_ascii=False))
