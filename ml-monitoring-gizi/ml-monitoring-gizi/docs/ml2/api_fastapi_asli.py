"""Contoh minimal layanan FastAPI (demonstrasi integrasi; bukan konfigurasi produksi).
Jalankan:  pip install fastapi uvicorn   lalu   uvicorn api_fastapi:app --port 8000
Laravel cukup memanggil:  POST http://<host>:8000/rekomendasi  (JSON sesuai contoh_input.json)."""
import sys
from pathlib import Path
from typing import Dict, List, Optional

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

FOLDER = Path(__file__).resolve().parent
sys.path.insert(0, str(FOLDER))
from rekomendasi_gizi import PemberiRekomendasi            # noqa: E402

app = FastAPI(title="ML2 Rekomendasi Resep Gizi Anak", version="1.0.0")
MODEL = PemberiRekomendasi.muat(FOLDER)                    # dimuat sekali saat server start


class PermintaanRekomendasi(BaseModel):
    prioritas_gizi: Optional[Dict[str, float]] = None
    asupan_harian: Optional[Dict[str, float]] = None
    acuan_harian: Optional[Dict[str, float]] = None
    bahan_tersedia: List[str] = []
    mode_bahan: Optional[str] = None
    min_kecocokan: float = 0.5
    top_n: int = Field(5, ge=1, le=50)
    maks_waktu_menit: Optional[float] = None
    bahan_dihindari: List[str] = []
    metode: Optional[str] = None
    lambda_keragaman: Optional[float] = None
    kebijakan_nilai_hilang: str = "penalti"


@app.get("/health")
def health():
    return {"status": "ok", "versi_model": MODEL.config.get("versi_model"), "jumlah_resep": len(MODEL.basis)}


@app.post("/rekomendasi")
def rekomendasi(req: PermintaanRekomendasi):
    data = req.model_dump(exclude_none=True) if hasattr(req, "model_dump") else req.dict(exclude_none=True)
    try:
        return MODEL.rekomendasi(data)
    except ValueError as e:                                # masukan tidak valid -> 422, bukan 500
        raise HTTPException(status_code=422, detail=str(e))
