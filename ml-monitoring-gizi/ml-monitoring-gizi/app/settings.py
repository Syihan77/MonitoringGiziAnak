"""Konfigurasi layanan dari environment variable (lihat .env.example).

Tidak ada pustaka tambahan: nilai dibaca dari os.environ. Atur variabel lewat proses manager
(systemd EnvironmentFile, Docker env, supervisor, dsb.) atau `export` di shell.
"""
from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

APP_DIR = Path(__file__).resolve().parent


def _bool(nilai: str | None, default: bool) -> bool:
    if nilai is None or nilai.strip() == "":
        return default
    return nilai.strip().lower() in {"1", "true", "yes", "ya", "on"}


@dataclass(frozen=True)
class Settings:
    ml1_model_dir: Path
    ml2_model_dir: Path
    docs_enabled: bool
    log_level: str

    @classmethod
    def from_env(cls) -> "Settings":
        return cls(
            ml1_model_dir=Path(os.environ.get("ML1_MODEL_DIR") or APP_DIR / "ml1" / "model"),
            ml2_model_dir=Path(os.environ.get("ML2_MODEL_DIR") or APP_DIR / "ml2" / "model"),
            docs_enabled=_bool(os.environ.get("API_DOCS_ENABLED"), True),
            log_level=(os.environ.get("LOG_LEVEL") or "INFO").upper(),
        )
