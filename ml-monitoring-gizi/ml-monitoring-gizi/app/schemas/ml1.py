"""Skema request ML1 (identik dengan layanan ML1 asli)."""
from typing import Literal

from pydantic import BaseModel, Field


class PrediksiRequest(BaseModel):
    usia_bulan: int = Field(..., ge=12, le=59, description="Usia saat pengukuran (bulan), 12–59")
    jk: Literal["L", "P"] = Field(..., description="L = laki-laki, P = perempuan")
    bb_kg: float = Field(..., ge=2.0, le=40.0, description="Berat badan (kg)")
    tb_cm: float = Field(..., ge=45.0, le=130.0, description="Tinggi/panjang badan (cm)")
