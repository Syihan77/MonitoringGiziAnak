import json
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]
ML1_DIR = ROOT / "app" / "ml1" / "model"
ML2_DIR = ROOT / "app" / "ml2" / "model"
EXAMPLES = ROOT / "examples"


def muat_json(nama: str):
    return json.loads((EXAMPLES / nama).read_text(encoding="utf-8"))


try:
    import pygrowup  # noqa: F401
    ADA_PYGROWUP = True
except Exception:
    ADA_PYGROWUP = False

butuh_pygrowup = pytest.mark.skipif(not ADA_PYGROWUP, reason="pygrowup tidak terpasang di lingkungan ini")


@pytest.fixture(scope="session")
def ml2():
    from app.ml2.rekomendasi_gizi import PemberiRekomendasi
    return PemberiRekomendasi.muat(ML2_DIR)
