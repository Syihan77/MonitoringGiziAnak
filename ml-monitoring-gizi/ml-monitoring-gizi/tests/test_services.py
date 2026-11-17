"""Lapisan layanan (tanpa FastAPI): pemuatan independen, kesiapan jujur, pesan error aman."""
import logging

import pytest

from app import services
from tests.conftest import ADA_PYGROWUP, ML1_DIR, ML2_DIR, butuh_pygrowup, muat_json


def test_ml2_siap_walau_ml1_gagal_dimuat(tmp_path):
    reg = services.load_all(tmp_path / "tidak_ada", ML2_DIR)
    assert not reg.ml1.ready and reg.ml2.ready
    code, body = services.health_all(reg)
    assert code == 503 and body["status"] == "degraded"
    assert body["modules"]["ml1"]["ready"] is False and body["modules"]["ml2"]["ready"] is True


@butuh_pygrowup
def test_ml1_siap_walau_ml2_gagal_dimuat(tmp_path):
    reg = services.load_all(ML1_DIR, tmp_path / "tidak_ada")
    assert reg.ml1.ready and not reg.ml2.ready
    assert services.health_ml1(reg.ml1)[0] == 200 and services.health_ml2(reg.ml2)[0] == 503


@butuh_pygrowup
def test_semua_siap_health_200():
    reg = services.load_all(ML1_DIR, ML2_DIR)
    code, body = services.health_all(reg)
    assert code == 200 and body["status"] == "ok"


def test_registry_awal_tidak_dilaporkan_siap():
    reg = services.Registry()
    assert not reg.ml1.ready and not reg.ml2.ready
    assert services.health_all(reg)[0] == 503


def test_modul_gagal_dimuat_menolak_pemanggilan(tmp_path):
    reg = services.load_all(tmp_path, tmp_path)
    with pytest.raises(services.ModuleNotReady):
        services.predict_ml1(reg.ml1, muat_json("ml1_input.json"))
    with pytest.raises(services.ModuleNotReady):
        services.recommend_ml2(reg.ml2, muat_json("ml2_input.json"))


def test_pesan_error_pemuatan_tidak_membocorkan_path(tmp_path):
    reg = services.load_all(tmp_path / "rahasia_internal", tmp_path / "rahasia_internal")
    for st in (reg.ml1, reg.ml2):
        assert st.error and "rahasia_internal" not in st.error and "/" not in st.error and "Traceback" not in st.error


def test_ml2_artefak_rusak_tidak_dilaporkan_siap(tmp_path):
    import shutil
    for f in ML2_DIR.iterdir():
        shutil.copy(f, tmp_path / f.name)
    (tmp_path / "kmeans_pipeline.joblib").write_bytes(b"bukan joblib")
    st = services.load_ml2(tmp_path)
    assert not st.ready and st.error


def test_ml2_jumlah_resep_tidak_sesuai_config_ditolak(tmp_path):
    import shutil
    for f in ML2_DIR.iterdir():
        shutil.copy(f, tmp_path / f.name)
    import pandas as pd
    pd.read_csv(ML2_DIR / "basis_resep.csv").iloc[:-1].to_csv(tmp_path / "basis_resep.csv", index=False)
    assert not services.load_ml2(tmp_path).ready


def test_catatan_kompatibilitas_versi_dilaporkan_bukan_disembunyikan():
    import sklearn
    st = services.load_ml2(ML2_DIR)
    assert st.ready
    dibuat = st.instance.config["versi_library"]["scikit-learn"]
    if sklearn.__version__ != dibuat:
        assert any(dibuat in c for c in st.catatan)
        assert "catatan_kompatibilitas" in services.health_ml2(st)[1]


def test_log_tidak_memuat_data_input(caplog):
    caplog.set_level(logging.DEBUG)
    st = services.load_ml2(ML2_DIR)
    services.recommend_ml2(st, {"prioritas_gizi": {"protein": 1}, "bahan_tersedia": ["bahan_unik_xyz"]})
    assert "bahan_unik_xyz" not in caplog.text
