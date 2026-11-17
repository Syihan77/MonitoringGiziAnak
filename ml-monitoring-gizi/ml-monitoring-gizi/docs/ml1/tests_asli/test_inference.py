"""Pengujian artefak & fungsi inferensi. Sampel = sampel teknis demonstrasi (bukan data anak sebenarnya)."""
import inspect
import json
from pathlib import Path

import pytest

import inference as inf

ART = Path(inf.ARTIFACT_DIR)
NORMAL = {"usia_bulan": 36, "jk": "P", "bb_kg": 14.0, "tb_cm": 96.0}
ATRISK = {"usia_bulan": 30, "jk": "L", "bb_kg": 11.2, "tb_cm": 85.0}
HIGH = {"usia_bulan": 25, "jk": "P", "bb_kg": 7.5, "tb_cm": 72.0}


@pytest.fixture(scope="module")
def pred():
    return inf.ML1Predictor(ART)


def test_01_artefak_ada_dan_termuat(pred):
    for f in ["model.joblib", "metadata.json", "feature_schema.json", "label_mapping.json"]:
        assert (ART / f).stat().st_size > 0, f
    assert list(pred.model.classes_) == [0, 1, 2]
    assert pred.metadata["fitur"] == inf.FITUR_URUT


def test_02_prediksi_satu_input_valid(pred):
    r = pred.predict(NORMAL)
    assert r["status_model"].startswith("EKSPERIMEN")
    assert r["acuan"]["kategori"] == "Normal" and r["prediksi_ml"]["kategori"] == "Normal"
    assert pred.predict(ATRISK)["acuan"]["kategori"] == "At Risk"
    assert pred.predict(HIGH)["acuan"]["kategori"] == "High Risk"
    assert pred.predict(HIGH)["prediksi_ml"]["kategori"] == "High Risk"


@pytest.mark.parametrize("hilang", ["usia_bulan", "jk", "bb_kg", "tb_cm"])
def test_03_fitur_wajib_hilang(pred, hilang):
    d = {k: v for k, v in NORMAL.items() if k != hilang}
    with pytest.raises(inf.InputError) as e:
        pred.predict(d)
    assert e.value.errors[0]["field"] == hilang


@pytest.mark.parametrize("field,nilai", [("bb_kg", "berat"), ("tb_cm", [1]), ("usia_bulan", True),
                                          ("usia_bulan", 24.5), ("jk", "X"), ("jk", 1), ("bb_kg", float("nan"))])
def test_04_tipe_atau_nilai_salah(pred, field, nilai):
    with pytest.raises(inf.InputError) as e:
        pred.predict({**NORMAL, field: nilai})
    assert e.value.errors[0]["field"] == field


def test_04b_input_bukan_dict(pred):
    for bad in (None, {}, [], "abc"):
        with pytest.raises(inf.InputError):
            pred.predict(bad)


@pytest.mark.parametrize("field", ["usia_bulan", "jk", "bb_kg", "tb_cm"])
def test_05_nilai_kosong_ditolak_bukan_diimputasi(pred, field):
    for kosong in (None, ""):
        with pytest.raises(inf.InputError):
            pred.predict({**NORMAL, field: kosong})


def test_05b_usia_di_luar_cakupan_dan_nilai_tak_masuk_akal(pred):
    with pytest.raises(inf.InputError):
        pred.predict({**NORMAL, "usia_bulan": 5})
    with pytest.raises(inf.InputError):
        pred.predict({**NORMAL, "usia_bulan": 60})
    with pytest.raises(inf.InputError) as e:  # TB 60 cm pada usia 36 bln: Z-score tak masuk akal (disaring seperti di notebook)
        pred.predict({**NORMAL, "tb_cm": 60.0})
    assert any(d["field"] in ("haz", "whz") for d in e.value.errors)


def test_06_output_kelas_dikenal(pred):
    for d in (NORMAL, ATRISK, HIGH):
        r = pred.predict(d)
        assert r["prediksi_ml"]["kategori"] in ("Normal", "At Risk", "High Risk")
        assert r["prediksi_ml"]["kategori_id"] in (0, 1, 2)
        assert r["acuan"]["kategori"] in ("Normal", "At Risk", "High Risk")


def test_07_probabilitas_didukung_dan_valid(pred):
    assert hasattr(pred.model, "predict_proba")
    p = pred.predict(ATRISK)["prediksi_ml"]["probabilitas"]
    assert set(p) == {"Normal", "At Risk", "High Risk"}
    assert abs(sum(p.values()) - 1) < 1e-3 and all(0 <= v <= 1 for v in p.values())


def test_08_deterministik(pred):
    a = pred.predict(ATRISK)
    assert all(pred.predict(ATRISK) == a for _ in range(3))
    assert inf.ML1Predictor(ART).predict(ATRISK) == a  # muat ulang artefak -> hasil sama


def test_08b_batch(pred):
    out = pred.predict_batch([NORMAL, ATRISK, HIGH])
    assert [o["acuan"]["kategori_id"] for o in out] == [0, 1, 2]
    with pytest.raises(inf.InputError) as e:
        pred.predict_batch([NORMAL, {**ATRISK, "jk": "Z"}])
    assert e.value.errors[0]["field"].startswith("[1].")


def test_08c_pesan_error_tidak_membocorkan_nilai(pred):
    with pytest.raises(inf.InputError) as e:
        pred.predict({**NORMAL, "bb_kg": "RAHASIA-123"})
    assert "RAHASIA-123" not in str(e.value) and "RAHASIA-123" not in json.dumps(e.value.errors)


def test_08d_peringatan_di_luar_rentang_latih(pred):
    r = None
    # BB di bawah min data latih (5.9 kg) tetapi Z-score masih masuk akal
    for bb in (5.5, 5.8):
        try:
            r = pred.predict({"usia_bulan": 12, "jk": "P", "bb_kg": bb, "tb_cm": 66.0})
            break
        except inf.InputError:
            continue
    assert r is not None and any("rentang data latih" in w for w in r["peringatan"])


def test_12_tidak_ada_training_saat_muat(monkeypatch):
    from sklearn.ensemble import RandomForestClassifier

    def terlarang(*a, **k):
        raise AssertionError("fit() dipanggil saat memuat/prediksi!")

    monkeypatch.setattr(RandomForestClassifier, "fit", terlarang)
    p = inf.ML1Predictor(ART)
    assert p.predict(HIGH)["prediksi_ml"]["kategori"] == "High Risk"
    src = inspect.getsource(inf)
    assert ".fit(" not in src


def test_artefak_hilang_memberi_error_jelas(tmp_path):
    with pytest.raises(inf.ModelLoadError):
        inf.ML1Predictor(tmp_path)
