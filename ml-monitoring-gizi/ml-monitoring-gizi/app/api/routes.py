"""Endpoint HTTP. Logika inferensi ada di app/services.py dan modul ml1/ml2 (bukan di sini)."""
from __future__ import annotations

import logging

from fastapi import APIRouter, Request
from fastapi.responses import JSONResponse

from app import services
from app.ml1.inference import InputError
from app.schemas.ml1 import PrediksiRequest
from app.schemas.ml2 import RekomendasiRequest

logger = logging.getLogger("ml_gizi")
router = APIRouter()


def error_response(status: int, kode: str, pesan: str, errors: list | None = None) -> JSONResponse:
    body = {"status": "error", "kode": kode, "pesan": pesan}
    if errors:
        body["errors"] = errors
    return JSONResponse(status_code=status, content=body)


def _reg(request: Request) -> services.Registry:
    return request.app.state.registry


def _kirim(status: int, body: dict) -> JSONResponse:
    return JSONResponse(status_code=status, content=body)


# --------------------------------------------------------------------------- kesehatan
@router.get("/health", tags=["kesehatan"])
def health(request: Request):
    return _kirim(*services.health_all(_reg(request)))


@router.get("/ml1/health", tags=["ML1"])
def ml1_health(request: Request):
    return _kirim(*services.health_ml1(_reg(request).ml1))


@router.get("/ml2/health", tags=["ML2"])
def ml2_health(request: Request):
    return _kirim(*services.health_ml2(_reg(request).ml2))


# --------------------------------------------------------------------------- ML1
# `def` (bukan `async def`): inferensi bersifat CPU-bound; FastAPI menjalankannya di threadpool.
@router.post("/ml1/prediksi", tags=["ML1"])
def ml1_prediksi(req: PrediksiRequest, request: Request):
    try:
        hasil = services.predict_ml1(_reg(request).ml1, req.model_dump())
    except services.ModuleNotReady:
        return error_response(503, "model_tidak_siap", "Model ML1 belum siap.")
    except InputError as e:
        return error_response(422, "input_tidak_valid", "Input tidak valid.", e.errors)
    except Exception as e:  # jangan bocorkan detail internal; jangan log data anak
        logger.error("Kesalahan saat prediksi ML1: %s", type(e).__name__)
        return error_response(500, "kesalahan_internal", "Terjadi kesalahan internal pada layanan prediksi.")
    return {"status": "ok", **hasil}


# --------------------------------------------------------------------------- ML2
@router.post("/ml2/rekomendasi", tags=["ML2"])
def ml2_rekomendasi(req: RekomendasiRequest, request: Request):
    payload = req.model_dump(exclude_none=True)  # sama dengan API ML2 asli
    try:
        return services.recommend_ml2(_reg(request).ml2, payload)
    except services.ModuleNotReady:
        return error_response(503, "model_tidak_siap", "Model ML2 belum siap.")
    except ValueError as e:  # masukan tidak valid -> 422 dengan {"detail": "..."} seperti API ML2 asli
        return JSONResponse(status_code=422, content={"detail": str(e)})
    except Exception as e:
        logger.error("Kesalahan saat rekomendasi ML2: %s", type(e).__name__)
        return error_response(500, "kesalahan_internal", "Terjadi kesalahan internal pada layanan rekomendasi.")
