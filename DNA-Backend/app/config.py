"""Central configuration for the DNA analysis service.

Every tunable is environment driven so the same image can run in local,
staging and production without code changes.
"""

from __future__ import annotations

import os
from dataclasses import dataclass, field


def _int_env(name: str, default: int) -> int:
    raw = os.getenv(name)
    if raw is None or not raw.strip():
        return default
    try:
        return int(raw)
    except ValueError:
        return default


def _list_env(name: str, default: str) -> list[str]:
    return [item.strip() for item in os.getenv(name, default).split(",") if item.strip()]


@dataclass(frozen=True)
class Settings:
    """Runtime settings, resolved once at import time."""

    app_name: str = "DNA Analysis API"
    version: str = "3.0.0"

    # --- Upload limits -------------------------------------------------
    max_file_size: int = field(default_factory=lambda: _int_env("MAX_FILE_SIZE", 10 * 1024 * 1024))
    max_records: int = field(default_factory=lambda: _int_env("MAX_RECORDS", 500))

    # --- Analysis limits -----------------------------------------------
    # Pairwise alignment is O(n*m) in memory. Above this length we fall back
    # to a positional diff and say so explicitly in the response.
    align_max_bp: int = field(default_factory=lambda: _int_env("ALIGN_MAX_BP", 3000))
    # ORF scanning is linear but still costly on whole chromosomes.
    orf_max_scan_bp: int = field(default_factory=lambda: _int_env("ORF_MAX_SCAN_BP", 200_000))
    # Oligo thermodynamics (nearest-neighbour) are only valid for short probes.
    tm_nn_max_bp: int = field(default_factory=lambda: _int_env("TM_NN_MAX_BP", 50))

    # --- Stochastic simulation ------------------------------------------
    # The simulator's cost is measured in reaction events, not in input size:
    # a hundred cells watched for four hours is millions of them. This ceiling
    # is the whole ensemble's budget, split across the cells and across the
    # control run, and a run that reaches it stops and says so rather than
    # holding the worker until the HTTP client gives up.
    sim_max_steps: int = field(default_factory=lambda: _int_env("SIM_MAX_STEPS", 6_000_000))

    # --- Job store ------------------------------------------------------
    job_ttl_seconds: int = field(default_factory=lambda: _int_env("JOB_TTL_SECONDS", 3600))
    job_store_max: int = field(default_factory=lambda: _int_env("JOB_STORE_MAX", 200))

    # --- Networking -----------------------------------------------------
    cors_origins: list[str] = field(default_factory=lambda: _list_env("CORS_ORIGINS", "*"))
    rate_limit_per_minute: int = field(default_factory=lambda: _int_env("RATE_LIMIT_PER_MINUTE", 30))

    log_level: str = field(default_factory=lambda: os.getenv("LOG_LEVEL", "INFO").upper())


settings = Settings()
