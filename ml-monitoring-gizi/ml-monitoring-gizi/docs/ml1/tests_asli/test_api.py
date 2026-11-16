"""Pengujian API (in-process via TestClient)."""
import pytest
from fastapi.testclient import TestClient

import main

NORMAL = {"usia_bulan": 36, "jk": "P", "bb_kg": 14.0, "tb_cm": 96.0}
HIGH = {"usia_bulan": 25, "jk": "P", "bb_kg": 7.5, "tb_cm": 72.0}


@pytest.fixture()  # per-tes: `app` global berbagi state, tes "model gagal dimuat" mengosongkannya
def client():
    with TestClient(main.app) as c:
        yield c


def test_health_model_siap(client):
    r = client.get("/health")
    assert r.status_code == 200
    j = r.json()
    assert j["service"] == "running" and j["model_loaded"] is True and j["status"] == "ok"


def test_health_model_gagal_dimuat(monkeypatch, tmp_path):
    monkeypatch.setenv("ML1_ARTIFACT_DIR", str(tmp_path))  # folder kosong -> artefak tidak ada
    with TestClient(main.app) as c:
        r = c.get("/health")
        assert r.status_code == 503
        j = r.json()
        assert j["service"] == "running" and j["model_loaded"] is False and j["status"] == "degraded"
        assert str(tmp_path) not in r.text  # tidak membocorkan path
        p = c.post("/predict", json=NORMAL)
        assert p.status_code == 503 and p.json()["kode"] == "model_tidak_siap"


def test_predict_sukses(client):
    r = client.post("/predict", json=HIGH)
    assert r.status_code == 200
    j = r.json()
    assert j["status"] == "ok"
    assert j["acuan"]["kategori"] == "High Risk" and j["prediksi_ml"]["kategori"] == "High Risk"
    assert set(j["acuan"]["zscore"]) == {"waz", "haz", "whz"}
    assert abs(sum(j["prediksi_ml"]["probabilitas"].values()) - 1) < 1e-3


def test_predict_konsisten(client):
    assert client.post("/predict", json=NORMAL).json() == client.post("/predict", json=NORMAL).json()


@pytest.mark.parametrize("body", [
    {}, {"usia_bulan": 36, "jk": "P", "bb_kg": 14.0},                  # field hilang
    {**NORMAL, "bb_kg": "abc"}, {**NORMAL, "jk": "X"},                 # tipe/nilai salah
    {**NORMAL, "tb_cm": None}, {**NORMAL, "usia_bulan": 5},            # kosong / di luar cakupan
    {**NORMAL, "usia_bulan": 24.5},
])
def test_predict_input_tidak_valid_422(client, body):
    r = client.post("/predict", json=body)
    assert r.status_code == 422
    j = r.json()
    assert j["status"] == "error" and j["kode"] == "input_tidak_valid" and j["errors"]
    assert "Traceback" not in r.text and ".py" not in r.text


def test_predict_nilai_tak_masuk_akal_422(client):
    r = client.post("/predict", json={**NORMAL, "tb_cm": 60.0})
    assert r.status_code == 422 and r.json()["kode"] == "input_tidak_valid"


def test_predict_422_tidak_membocorkan_nilai(client):
    r = client.post("/predict", json={**NORMAL, "bb_kg": "RAHASIA-123"})
    assert r.status_code == 422 and "RAHASIA-123" not in r.text


def test_predict_json_rusak_422(client):
    r = client.post("/predict", content="{bukan json", headers={"Content-Type": "application/json"})
    assert r.status_code == 422


def test_kesalahan_internal_500_tanpa_detail(client, monkeypatch):
    def meledak(self, data):
        raise RuntimeError("detail rahasia /srv/path")

    monkeypatch.setattr(main.ML1Predictor, "predict", meledak)
    r = client.post("/predict", json=NORMAL)
    assert r.status_code == 500 and r.json()["kode"] == "kesalahan_internal"
    assert "rahasia" not in r.text and "/srv" not in r.text
