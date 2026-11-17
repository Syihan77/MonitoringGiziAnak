"""Pintu masuk FastAPI tunggal untuk ML1 dan ML2 (dua modul independen).

Jalankan dari folder root paket:
    uvicorn app.main:app --host 127.0.0.1 --port 8000
"""
from __future__ import annotations

import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse

from app import services
from app.api.routes import error_response, router
from app.settings import Settings

logger = logging.getLogger("ml_gizi")


def create_app(settings: Settings | None = None) -> FastAPI:
    cfg = settings or Settings.from_env()
    logging.basicConfig(level=cfg.log_level, format="%(asctime)s %(levelname)s %(name)s: %(message)s")

    @asynccontextmanager
    async def lifespan(app: FastAPI):
        # Model dimuat SATU kali di sini; tidak ada training saat request.
        app.state.registry = services.load_all(cfg.ml1_model_dir, cfg.ml2_model_dir)
        yield

    app = FastAPI(
        title="ML Monitoring & Rekomendasi Gizi Anak",
        version="1.0.0",
        description=("Satu pintu masuk untuk dua modul ML independen: ML1 (kategori pemantauan pertumbuhan, "
                     "alat bantu — BUKAN diagnosis) dan ML2 (saran resep gizi — BUKAN catatan konsumsi anak)."),
        lifespan=lifespan,
        docs_url="/docs" if cfg.docs_enabled else None,
        redoc_url="/redoc" if cfg.docs_enabled else None,
        openapi_url="/openapi.json" if cfg.docs_enabled else None,
    )
    # Cadangan bila lifespan tidak dijalankan: semua modul dilaporkan belum siap (bukan siap).
    app.state.registry = services.Registry()
    app.include_router(router)

    @app.exception_handler(RequestValidationError)
    async def _validasi(request: Request, exc: RequestValidationError):
        # Nilai input (data anak) tidak pernah dikembalikan maupun dicatat.
        if request.url.path.startswith("/ml1"):
            errors = [{"field": ".".join(str(p) for p in e["loc"] if p != "body") or "(body)", "pesan": e["msg"]}
                      for e in exc.errors()]
            return error_response(422, "input_tidak_valid", "Input tidak valid.", errors)
        # ML2: bentuk {"detail": [{loc,msg,type}]} seperti default FastAPI, tanpa `input`/`ctx`.
        detail = [{"loc": list(e["loc"]), "msg": e["msg"], "type": e["type"]} for e in exc.errors()]
        return JSONResponse(status_code=422, content={"detail": detail})

    @app.exception_handler(Exception)
    async def _tak_terduga(request: Request, exc: Exception):
        logger.error("Kesalahan tak terduga: %s", type(exc).__name__)
        return error_response(500, "kesalahan_internal", "Terjadi kesalahan internal.")

    return app


app = create_app()
