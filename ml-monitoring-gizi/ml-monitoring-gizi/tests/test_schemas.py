"""Skema request (hanya butuh pydantic): rentang ML1 dan kontrak ML2 sama dengan layanan asli."""
import pytest
from pydantic import ValidationError

from app.schemas.ml1 import PrediksiRequest
from app.schemas.ml2 import RekomendasiRequest
from tests.conftest import muat_json


def test_ml1_contoh_valid():
    r = PrediksiRequest(**muat_json("ml1_input.json"))
    assert r.model_dump() == muat_json("ml1_input.json")


@pytest.mark.parametrize("data", [
    {"usia_bulan": 11, "jk": "L", "bb_kg": 10, "tb_cm": 80},
    {"usia_bulan": 60, "jk": "L", "bb_kg": 10, "tb_cm": 80},
    {"usia_bulan": 30, "jk": "l", "bb_kg": 10, "tb_cm": 80},       # huruf kecil ditolak (sama dgn layanan asli)
    {"usia_bulan": 30, "jk": "L", "bb_kg": 1.9, "tb_cm": 80},
    {"usia_bulan": 30, "jk": "L", "bb_kg": 10, "tb_cm": 131},
    {"usia_bulan": 30, "jk": "L", "bb_kg": 10},
])
def test_ml1_tidak_valid(data):
    with pytest.raises(ValidationError):
        PrediksiRequest(**data)


def test_ml2_contoh_input_valid_dan_nilai_bawaan_sama_dengan_api_asli():
    r = RekomendasiRequest(**muat_json("ml2_input.json"))
    d = RekomendasiRequest(prioritas_gizi={"protein": 1}).model_dump(exclude_none=True)
    assert d == {"prioritas_gizi": {"protein": 1.0}, "bahan_tersedia": [], "min_kecocokan": 0.5, "top_n": 5,
                 "bahan_dihindari": [], "kebijakan_nilai_hilang": "penalti"}
    assert r.top_n == 3


def test_ml2_kunci_tak_dikenal_ditolak_dan_top_n_dibatasi():
    with pytest.raises(ValidationError):
        RekomendasiRequest(prioritas_gizi={"protein": 1}, top_m=3)
    for n in (0, 51):
        with pytest.raises(ValidationError):
            RekomendasiRequest(top_n=n)
