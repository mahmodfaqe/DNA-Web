"""Job store for asynchronous analyses.

The previous version used a bare module-level dict that was never pruned: every
async job leaked its full result until the process was restarted. This store is
bounded, expires entries, and is guarded by a lock.

It is still process-local. That is a deliberate, documented limitation: with more
than one uvicorn worker a job submitted to worker A is invisible to worker B. The
migration path is Redis, and `JobStore` is the single seam that has to change.
"""

from __future__ import annotations

import threading
import time
import uuid
from typing import Any

from .config import settings


class JobStore:
    def __init__(self, ttl_seconds: int, max_entries: int) -> None:
        self._ttl = ttl_seconds
        self._max = max_entries
        self._lock = threading.Lock()
        self._jobs: dict[str, dict[str, Any]] = {}

    def _prune(self) -> None:
        now = time.time()
        expired = [key for key, job in self._jobs.items() if now - job["created_at"] > self._ttl]
        for key in expired:
            self._jobs.pop(key, None)

        overflow = len(self._jobs) - self._max
        if overflow > 0:
            oldest = sorted(self._jobs.items(), key=lambda item: item[1]["created_at"])
            for key, _ in oldest[:overflow]:
                self._jobs.pop(key, None)

    def create(self) -> str:
        job_id = str(uuid.uuid4())
        with self._lock:
            self._prune()
            self._jobs[job_id] = {
                "status": "processing",
                "created_at": time.time(),
                "result": None,
                "error": None,
            }
        return job_id

    def complete(self, job_id: str, result: dict[str, Any]) -> None:
        with self._lock:
            job = self._jobs.get(job_id)
            if job is not None:
                job.update(status="completed", result=result)

    def fail(self, job_id: str, error: dict[str, Any]) -> None:
        with self._lock:
            job = self._jobs.get(job_id)
            if job is not None:
                job.update(status="failed", error=error)

    def get(self, job_id: str) -> dict[str, Any] | None:
        with self._lock:
            self._prune()
            job = self._jobs.get(job_id)
            return dict(job) if job else None

    def size(self) -> int:
        with self._lock:
            return len(self._jobs)


store = JobStore(settings.job_ttl_seconds, settings.job_store_max)
