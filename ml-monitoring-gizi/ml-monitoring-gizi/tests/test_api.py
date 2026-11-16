"""Kontrak HTTP gabungan. Butuh fastapi + httpx (dilewati bila tidak terpasang)."""
import pytest

pytest.importorskip("fastapi")
pytest.importorskip("httpx")
from fastapi.testclient import TestClient  # noqa: E402

from app import services  # noqa: E402
from app.main import create_app  # noqa: E402
from app.settings import Settings  # noqa: E402
from tests.conftest import ML1_DIR, ML2_DIR, butuh_pygrowup, muat_json  # noqa: E402


def _client(ml1=ML1_DIR, ml2=ML2_DIR, docs=True):
    return TestClient(create_app(Settings(ml1, ml2, docs, "WARNING")), raise_server_exceptions=False)


@pytest.fixture(scope="module")
def client():
    with _client() as c:
        yield c


# ---------------------------------------------------------------- ML2 (tidak butuh pygrowup)
def test_ml2_health(client):
    r = client.get("/ml2/health")
    assert r.status_code == 200 and r.json()["ready"] is True and r.json()["jumlah_resep"] == 93


def test_ml2_kontrak_sama_dengan_contoh_output(client):
    r = client.post("/ml2/rekomendasi", json=muat_json("ml2_input.json"))
    assert r.status_code == 200 and r.json() == muat_json("ml2_output.json")


def test_ml2_masukan_tidak_valid_422_detail_string(client):
    r = client.post("/ml2/rekomendasi", json={"prioritas_gizi": {"vitamin_z": 1}})
    assert r.status_code == 422 and isinstance(r.json()["detail"], str)


def test_ml2_kunci_tak_dikenal_422(client):
    r = client.post("/ml2/rekomendasi", json={"prioritas_gizi": {"protein": 1}, "top_m": 3})
    assert r.status_code == 422


def test_ml2_422_pydantic_tidak_mengembalikan_nilai_input(client):
    r = client.post("/ml2/rekomendasi", json={"prioritas_gizi": {"protein": 1}, "top_n": "NILAI_RAHASIA"})
    assert r.status_code == 422 and "NILAI_RAHASIA" not in r.text and "input" not in str(r.json()["detail"][0])


# ---------------------------------------------------------------- ML1 validasi (tanpa pygrowup)
def test_ml1_422_format_dan_tidak_echo_nilai(client):
    r = client.post("/ml1/prediksi", json={"usia_bulan": 30, "jk": "NILAI_RAHASIA", "bb_kg": 11.2})
    b = r.json()
    assert r.status_code == 422 and b["status"] == "error" and b["kode"] == "input_tidak_valid"
    assert {e["field"] for e in b["errors"]} == {"jk", "tb_cm"} and "NILAI_RAHASIA" not in r.text


def test_ml1_usia_di_luar_rentang_422(client):
    r = client.post("/ml1/prediksi", json={"usia_bulan": 5, "jk": "L", "bb_kg": 6, "tb_cm": 60})
    assert r.status_code == 422


# ---------------------------------------------------------------- kesiapan jujur
def test_modul_gagal_dimuat_503_dan_modul_lain_tetap_jalan(tmp_path):
    with _client(ml1=tmp_path / "x") as c:
        assert c.get("/ml1/health").status_code == 503 and c.get("/ml1/health").json()["ready"] is False
        assert c.post("/ml1/prediksi", json=muat_json("ml1_input.json")).status_code == 503
        assert c.get("/health").status_code == 503
        assert c.get("/ml2/health").status_code == 200
        assert c.post("/ml2/rekomendasi", json=muat_json("ml2_input.json")).status_code == 200
        assert str(tmp_path) not in c.get("/ml1/health").text


def test_ml2_503_bila_gagal_dimuat(tmp_path):
    with _client(ml2=tmp_path / "x") as c:
        assert c.get("/ml2/health").status_code == 503
        assert c.post("/ml2/rekomendasi", json=muat_json("ml2_input.json")).status_code == 503


def test_kesalahan_internal_500_tanpa_detail(monkeypatch):
    def meledak(*a, **k):
        raise RuntimeError("RAHASIA /srv/internal/path")
    monkeypatch.setattr(services, "recommend_ml2", meledak)
    with _client() as c:
        r = c.post("/ml2/rekomendasi", json=muat_json("ml2_input.json"))
    assert r.status_code == 500 and r.json()["kode"] == "kesalahan_internal"
    assert "RAHASIA" not in r.text and "Traceback" not in r.text and "/srv" not in r.text


def test_docs_dapat_dimatikan():
    with _client(docs=False) as c:
        assert c.get("/docs").status_code == 404 and c.get("/openapi.json").status_code == 404


def test_openapi_memuat_semua_endpoint_yang_diharapkan(client):
    paths = set(client.get("/openapi.json").json()["paths"])
    assert {"/health", "/ml1/health", "/ml1/prediksi", "/ml2/health", "/ml2/rekomendasi"} <= paths


# ---------------------------------------------------------------- ML1 end-to-end (butuh pygrowup)
@butuh_pygrowup
def test_ml1_prediksi_sama_dengan_contoh_output(client):
    r = client.post("/ml1/prediksi", json=muat_json("ml1_input.json"))
    assert r.status_code == 200 and r.json() == muat_json("ml1_output.json")


@butuh_pygrowup
def test_health_gabungan_200_bila_semua_siap(client):
    r = client.get("/health")
    assert r.status_code == 200 and r.json()["status"] == "ok"
