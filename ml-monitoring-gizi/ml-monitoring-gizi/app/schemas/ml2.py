"""Skema request ML2.

Field, tipe, dan nilai bawaan sama dengan `api_fastapi.py` ML2 asli. Satu-satunya perbedaan:
`extra="forbid"` — kunci yang tidak dikenal ditolak (HTTP 422). Ini sesuai README ML2 asli
("Kunci tidak dikenal ditolak"), tetapi pada API asli kunci tak dikenal justru dibuang diam-diam
oleh Pydantic sebelum sampai ke modul rekomendasi.
"""
from typing import Dict, List, Optional

from pydantic import BaseModel, ConfigDict, Field


class RekomendasiRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

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
