"""Lapisan layanan: memuat modul ML1/ML2 dan memanggil inferensinya TANPA bergantung pada FastAPI.

Dipisah dari endpoint HTTP agar dapat diuji tanpa menjalankan server. ML1 dan ML2 dimuat
SECARA TERPISAH: kegagalan satu modul tidak mematikan modul lain, dan modul yang gagal
dimuat selalu dilaporkan `ready = False` (tidak pernah dianggap siap secara diam-diam).
Tidak ada data anak yang ditulis ke log.
"""
from __future__ import annotations

import logging
import warnings
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

logger = logging.getLogger("ml_gizi")


class ModuleNotReady(RuntimeError):
    """Modul belum siap (gagal dimuat saat startup)."""

    def __init__(self, modul: str):
        self.modul = modul
        super().__init__(f"Modul {modul} belum siap.")


@dataclass
class ModuleState:
    nama: str
    instance: Any = None
    error: str | None = None
    catatan: list[str] = field(default_factory=list)

    @property
    def ready(self) -> bool:
        return self.instance is not None and self.error is None


@dataclass
class Registry:
    ml1: ModuleState = field(default_factory=lambda: ModuleState("ML1", error="Belum dimuat."))
    ml2: ModuleState = field(default_factory=lambda: ModuleState("ML2", error="Belum dimuat."))


# --------------------------------------------------------------------------- pemuatan
def _pesan_aman(e: Exception) -> str:
    """Pesan error tanpa path internal/traceback."""
    if isinstance(e, FileNotFoundError):
        nama = Path(e.filename).name if getattr(e, "filename", None) else ""
        if not nama:  # PemberiRekomendasi.muat membungkus path ke dalam teks pesan
            nama = Path(str(e).rsplit(":", 1)[-1].strip()).name
        return f"Berkas artefak tidak ditemukan: {nama}"
    return f"{type(e).__name__}: modul gagal dimuat."


def load_ml1(model_dir: Path) -> ModuleState:
    st = ModuleState("ML1")
    try:
        from app.ml1.inference import ML1Predictor, ModelLoadError  # impor lambat: kegagalan impor tidak mematikan ML2
        try:
            st.instance = ML1Predictor(model_dir)
        except ModelLoadError as e:
            raise RuntimeError(str(e)) from e
    except Exception as e:
        st.instance, st.error = None, (str(e) if isinstance(e, RuntimeError) else _pesan_aman(e))
        logger.error("ML1 GAGAL dimuat: %s", st.error)
        return st
    logger.info("ML1 dimuat: %s", st.instance.metadata["versi_artefak"])
    return st


def load_ml2(model_dir: Path) -> ModuleState:
    st = ModuleState("ML2")
    try:
        from app.ml2.rekomendasi_gizi import PemberiRekomendasi
        with warnings.catch_warnings(record=True) as w:
            warnings.simplefilter("always")
            model = PemberiRekomendasi.muat(model_dir)
        # Peringatan kompatibilitas versi (mis. scikit-learn 1.8.0 -> 1.9.1) TIDAK disembunyikan.
        for x in w:
            teks = f"{x.category.__name__}: {str(x.message).split(' Use ')[0].strip()}"
            if teks not in st.catatan:
                st.catatan.append(teks)
        total = len(model.basis)
        harapan = model.config.get("sumber_data", {}).get("jumlah_resep")
        if total == 0 or (harapan is not None and total != harapan):
            raise ValueError("Jumlah resep pada basis tidak sesuai dengan config_model.json.")
        st.instance = model
    except Exception as e:
        st.instance, st.error = None, _pesan_aman(e)
        logger.error("ML2 GAGAL dimuat: %s", st.error)
        return st
    logger.info("ML2 dimuat: versi %s, %d resep", model.config.get("versi_model"), total)
    if st.catatan:
        logger.warning("ML2: %d catatan kompatibilitas saat memuat model (lihat /ml2/health).", len(st.catatan))
    return st


def load_all(ml1_dir: Path, ml2_dir: Path) -> Registry:
    return Registry(ml1=load_ml1(ml1_dir), ml2=load_ml2(ml2_dir))


# --------------------------------------------------------------------------- inferensi
def predict_ml1(st: ModuleState, data: dict) -> dict:
    """Satu prediksi ML1. Melempar ModuleNotReady / app.ml1.inference.InputError."""
    if not st.ready:
        raise ModuleNotReady("ML1")
    return st.instance.predict(data)


def recommend_ml2(st: ModuleState, payload: dict) -> dict:
    """Satu rekomendasi ML2. Melempar ModuleNotReady / ValueError (input tidak valid)."""
    if not st.ready:
        raise ModuleNotReady("ML2")
    return st.instance.rekomendasi(payload)


# --------------------------------------------------------------------------- kesehatan
def health_ml1(st: ModuleState) -> tuple[int, dict]:
    if not st.ready:
        return 503, {"status": "degraded", "ready": False, "model_loaded": False,
                     "detail": st.error or "Model belum dimuat."}
    md = st.instance.metadata
    return 200, {"status": "ok", "ready": True, "model_loaded": True, "versi_model": md["versi_artefak"],
                 "status_model": md["status"], "aturan_versi": md["aturan_versi"]}


def health_ml2(st: ModuleState) -> tuple[int, dict]:
    if not st.ready:
        return 503, {"status": "degraded", "ready": False, "detail": st.error or "Model belum dimuat."}
    body = {"status": "ok", "ready": True, "versi_model": st.instance.config.get("versi_model"),
            "jumlah_resep": len(st.instance.basis)}
    if st.catatan:
        body["catatan_kompatibilitas"] = list(st.catatan)
    return 200, body


def health_all(reg: Registry) -> tuple[int, dict]:
    s1, b1 = health_ml1(reg.ml1)
    s2, b2 = health_ml2(reg.ml2)
    ok = s1 == 200 and s2 == 200
    return (200 if ok else 503), {"status": "ok" if ok else "degraded", "service": "running",
                                  "modules": {"ml1": b1, "ml2": b2}}
