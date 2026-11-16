"""ML1: pemuatan model, konsistensi fitur/urutan, validasi, dan (bila pygrowup ada) prediksi end-to-end."""
import hashlib
import json

import joblib
import numpy as np
import pytest

from app.ml1 import inference as inf
from tests.conftest import ML1_DIR, butuh_pygrowup, muat_json

META = json.loads((ML1_DIR / "metadata.json").read_text(encoding="utf-8"))


def _predictor_tanpa_zscore():
    """Predictor tanpa kalkulator WHO: hanya untuk menguji validasi & model (tidak memanggil predict())."""
    p = inf.ML1Predictor.__new__(inf.ML1Predictor)
    p.schema = json.loads((ML1_DIR / "feature_schema.json").read_text(encoding="utf-8"))
    p._f = p.schema["fitur"]
    p.model = joblib.load(ML1_DIR / "model.joblib")
    return p


def test_sha256_model_sesuai_metadata():
    assert hashlib.sha256((ML1_DIR / "model.joblib").read_bytes()).hexdigest() == META["sha256_model"]


def test_model_dimuat_dan_konsisten_dengan_metadata():
    m = joblib.load(ML1_DIR / "model.joblib")
    assert m.n_features_in_ == len(META["fitur"]) == 4
    assert list(m.classes_) == [0, 1, 2]
    assert META["fitur"] == inf.FITUR_URUT == ["usia_bulan", "jk_num", "bb_kg", "tb_cm"]
    assert type(m).__name__ == "RandomForestClassifier" and m.n_estimators == 300
    assert m.class_weight == "balanced" and m.random_state == 42


def test_probabilitas_sampel_sama_dengan_contoh_output_asli():
    """Vektor fitur [30, L=1, 11.2, 85.0] -> probabilitas harus sama dengan sample_response.json."""
    m = joblib.load(ML1_DIR / "model.joblib")
    proba = m.predict_proba(np.array([[30, 1, 11.2, 85.0]]))[0]
    exp = muat_json("ml1_output.json")["prediksi_ml"]["probabilitas"]
    got = {"Normal": round(proba[0], 4), "At Risk": round(proba[1], 4), "High Risk": round(proba[2], 4)}
    assert got == exp


def test_validasi_menerima_input_valid_dan_menormalkan_jk():
    p = _predictor_tanpa_zscore()
    out = p._validasi({"usia_bulan": 30, "jk": " l ", "bb_kg": 11.2, "tb_cm": 85})
    assert out == {"usia_bulan": 30, "jk": "L", "bb_kg": 11.2, "tb_cm": 85.0}


@pytest.mark.parametrize("data,field", [
    ({"usia_bulan": 30, "jk": "L", "bb_kg": 11.2}, "tb_cm"),               # kosong
    ({"usia_bulan": 30, "jk": "L", "bb_kg": None, "tb_cm": 85}, "bb_kg"),   # None tidak diimputasi
    ({"usia_bulan": 11, "jk": "L", "bb_kg": 11.2, "tb_cm": 85}, "usia_bulan"),
    ({"usia_bulan": 60, "jk": "L", "bb_kg": 11.2, "tb_cm": 85}, "usia_bulan"),
    ({"usia_bulan": 30.5, "jk": "L", "bb_kg": 11.2, "tb_cm": 85}, "usia_bulan"),
    ({"usia_bulan": 30, "jk": "X", "bb_kg": 11.2, "tb_cm": 85}, "jk"),
    ({"usia_bulan": 30, "jk": "L", "bb_kg": 1.0, "tb_cm": 85}, "bb_kg"),
    ({"usia_bulan": 30, "jk": "L", "bb_kg": 11.2, "tb_cm": 200}, "tb_cm"),
    ({"usia_bulan": 30, "jk": "L", "bb_kg": "11,2", "tb_cm": 85}, "bb_kg"),
    ({"usia_bulan": 30, "jk": "L", "bb_kg": float("nan"), "tb_cm": 85}, "bb_kg"),
    ({"usia_bulan": 30, "jk": "L", "bb_kg": True, "tb_cm": 85}, "bb_kg"),
])
def test_validasi_menolak_input_tidak_valid(data, field):
    with pytest.raises(inf.InputError) as ei:
        _predictor_tanpa_zscore()._validasi(data)
    assert field in [e["field"] for e in ei.value.errors]


@pytest.mark.parametrize("bukan_dict", [None, [], "x", {}])
def test_validasi_menolak_body_bukan_objek(bukan_dict):
    with pytest.raises(inf.InputError):
        _predictor_tanpa_zscore()._validasi(bukan_dict)


def test_pesan_error_tidak_memuat_nilai_input():
    with pytest.raises(inf.InputError) as ei:
        _predictor_tanpa_zscore()._validasi({"usia_bulan": 30, "jk": "RAHASIA", "bb_kg": 11.2, "tb_cm": 85})
    assert "RAHASIA" not in json.dumps(ei.value.errors) and "RAHASIA" not in str(ei.value)


def test_kategori_acuan_ambang():
    a = META["ambang"]
    assert inf.kategori_acuan(-3.01, 0, 0, a) == 2
    assert inf.kategori_acuan(-3.0, 0, 0, a) == 1      # tepat -3.0 bukan High Risk (aturan '< -3')
    assert inf.kategori_acuan(0, -2.01, 0, a) == 1
    assert inf.kategori_acuan(0, -2.0, 0, a) == 0
    assert inf.kategori_acuan(1, 1, 1, a) == 0


def test_direktori_artefak_tidak_ada_ditolak_sebagai_modelloaderror(tmp_path):
    with pytest.raises(inf.ModelLoadError):
        inf.ML1Predictor(tmp_path)


@pytest.mark.skipif(inf.Calculator is not None, reason="hanya relevan bila pygrowup tidak ada")
def test_tanpa_pygrowup_ml1_tidak_dilaporkan_siap():
    with pytest.raises(inf.ModelLoadError):
        inf.ML1Predictor()


# ----------------------------------------------------------------- end-to-end (butuh pygrowup)
@butuh_pygrowup
def test_predict_sampel_sama_dengan_contoh_output():
    p = inf.ML1Predictor()
    assert p.predict(muat_json("ml1_input.json")) == {k: v for k, v in muat_json("ml1_output.json").items() if k != "status"}


@butuh_pygrowup
def test_predict_menolak_zscore_tidak_masuk_akal():
    p = inf.ML1Predictor()
    with pytest.raises(inf.InputError):
        p.predict({"usia_bulan": 30, "jk": "L", "bb_kg": 39.0, "tb_cm": 46.0})


@butuh_pygrowup
def test_tidak_ada_training_saat_predict(monkeypatch):
    from sklearn.ensemble import RandomForestClassifier
    p = inf.ML1Predictor()
    monkeypatch.setattr(RandomForestClassifier, "fit", lambda *a, **k: (_ for _ in ()).throw(AssertionError("fit dipanggil")))
    p.predict(muat_json("ml1_input.json"))
