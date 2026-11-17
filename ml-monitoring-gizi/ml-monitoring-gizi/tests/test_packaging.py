"""Pengemasan: tidak ada ketergantungan Colab/path absolut, manifest konsisten dengan berkas."""
import hashlib
import json
import re

from tests.conftest import ROOT

KODE = [p for p in (ROOT / "app").rglob("*.py")]


def test_tidak_ada_ketergantungan_google_colab_atau_path_absolut():
    pola = re.compile(r"google\.colab|/content/|/mnt/|drive\.mount|/home/claude")
    for p in KODE:
        assert not pola.search(p.read_text(encoding="utf-8")), p


def test_tidak_ada_kode_training_di_paket_app():
    pola = re.compile(r"\.fit\(|fit_transform\(|GridSearchCV|cross_val")
    for p in KODE:
        if p.name == "rekomendasi_gizi.py":
            continue  # modul ML2 asli (tidak diubah); diperiksa terpisah pada tes berikutnya
        assert not pola.search(p.read_text(encoding="utf-8")), p


def test_rekomendasi_gizi_tidak_memanggil_fit():
    assert ".fit(" not in (ROOT / "app" / "ml2" / "rekomendasi_gizi.py").read_text(encoding="utf-8")


def test_manifest_hash_sesuai_berkas():
    m = json.loads((ROOT / "artifacts_manifest.json").read_text(encoding="utf-8"))
    assert m["berkas"], "manifest kosong"
    for b in m["berkas"]:
        if b.get("sha256"):
            assert hashlib.sha256((ROOT / b["path"]).read_bytes()).hexdigest() == b["sha256"], b["path"]
