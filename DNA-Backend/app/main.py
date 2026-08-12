"""DNA Analysis API.

Design notes
------------
* Errors are returned as machine-readable codes, never localized prose, so the
  trilingual UI owns all wording (see ``app.errors``).
* Uploads are read in chunks and rejected the moment they exceed the limit,
  instead of buffering a hostile 2 GB body into memory first.
* Every response carries an ``X-Request-ID`` so a user-visible failure can be
  traced to a specific line in the logs.
"""

from __future__ import annotations

import logging
import time
import uuid
from collections import defaultdict, deque
from typing import Any

from fastapi import BackgroundTasks, Body, Depends, FastAPI, File, Request, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from .config import settings
from .errors import AnalysisError, ErrorCode
from .jobs import store
from .services import biocompiler, bionoise, fasta, memory

logging.basicConfig(
    level=getattr(logging, settings.log_level, logging.INFO),
    format="%(asctime)s %(levelname)s [%(name)s] %(message)s",
)
logger = logging.getLogger("dna.api")

app = FastAPI(
    title=settings.app_name,
    version=settings.version,
    description="FASTA analysis service: composition, thermodynamics, ORFs and variant calling.",
    docs_url="/docs",
    openapi_url="/openapi.json",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins,
    allow_credentials=False,
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)


# --------------------------------------------------------------------------
# Request identity + access logging
# --------------------------------------------------------------------------

@app.middleware("http")
async def request_context(request: Request, call_next):
    request_id = request.headers.get("x-request-id") or str(uuid.uuid4())[:8]
    started = time.perf_counter()
    request.state.request_id = request_id

    response = await call_next(request)

    duration_ms = (time.perf_counter() - started) * 1000
    response.headers["X-Request-ID"] = request_id
    logger.info(
        "%s %s -> %s (%.1f ms) [%s]",
        request.method, request.url.path, response.status_code, duration_ms, request_id,
    )
    return response


# --------------------------------------------------------------------------
# Rate limiting
# --------------------------------------------------------------------------

_hits: dict[str, deque[float]] = defaultdict(deque)


def rate_limit(request: Request) -> None:
    """Fixed-window-free sliding limiter, keyed by client address.

    Deliberately in-process: behind a single container this is enough to stop a
    naive flood. A multi-replica deployment should move this to the reverse proxy
    or Redis rather than trusting per-process counters.
    """
    if settings.rate_limit_per_minute <= 0:
        return

    client = request.client.host if request.client else "unknown"
    now = time.time()
    window = _hits[client]

    while window and now - window[0] > 60:
        window.popleft()

    if len(window) >= settings.rate_limit_per_minute:
        raise AnalysisError(
            ErrorCode.RATE_LIMITED,
            status_code=429,
            retry_after=int(60 - (now - window[0])) + 1,
        )

    window.append(now)


# --------------------------------------------------------------------------
# Error handling
# --------------------------------------------------------------------------

@app.exception_handler(AnalysisError)
async def analysis_error_handler(request: Request, exc: AnalysisError) -> JSONResponse:
    logger.warning(
        "analysis error %s %s [%s]",
        exc.code,
        exc.params,
        getattr(request.state, "request_id", "-"),
    )
    return JSONResponse(status_code=exc.status_code, content=exc.payload())


@app.exception_handler(Exception)
async def unhandled_error_handler(request: Request, exc: Exception) -> JSONResponse:
    logger.exception("unhandled error [%s]", getattr(request.state, "request_id", "-"))
    return JSONResponse(
        status_code=500,
        content={"error": {"code": ErrorCode.INTERNAL, "params": {}}},
    )


# --------------------------------------------------------------------------
# Upload handling
# --------------------------------------------------------------------------

async def read_upload(file: UploadFile) -> str:
    if not file or not file.filename:
        raise AnalysisError(ErrorCode.FILE_MISSING)

    chunks: list[bytes] = []
    total = 0
    while chunk := await file.read(1024 * 256):
        total += len(chunk)
        if total > settings.max_file_size:
            raise AnalysisError(
                ErrorCode.FILE_TOO_LARGE,
                status_code=413,
                max_bytes=settings.max_file_size,
                max_megabytes=round(settings.max_file_size / (1024 * 1024), 1),
            )
        chunks.append(chunk)

    payload = b"".join(chunks)
    try:
        # utf-8-sig transparently strips the BOM that Windows editors add.
        return payload.decode("utf-8-sig")
    except UnicodeDecodeError as exc:
        raise AnalysisError(ErrorCode.FILE_ENCODING) from exc


# --------------------------------------------------------------------------
# Routes
# --------------------------------------------------------------------------

@app.get("/health", tags=["ops"])
async def health() -> dict[str, Any]:
    return {
        "status": "ok",
        "service": "dna-backend",
        "version": settings.version,
        "jobs_in_memory": store.size(),
    }


@app.post("/api/v1/analyze", tags=["analysis"], dependencies=[Depends(rate_limit)])
async def analyze(file: UploadFile = File(...)) -> dict[str, Any]:
    contents = await read_upload(file)
    return fasta.analyse(contents)


@app.post("/api/v1/analyze-async", tags=["analysis"], dependencies=[Depends(rate_limit)])
async def analyze_async(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
) -> dict[str, Any]:
    contents = await read_upload(file)
    job_id = store.create()

    def run(job: str, payload: str) -> None:
        try:
            store.complete(job, fasta.analyse(payload))
        except AnalysisError as exc:
            store.fail(job, exc.payload()["error"])
        except Exception:  # pragma: no cover
            logger.exception("background job %s failed", job)
            store.fail(job, {"code": ErrorCode.INTERNAL, "params": {}})

    background_tasks.add_task(run, job_id, contents)
    return {"status": "accepted", "job_id": job_id}


@app.post("/api/v1/compile", tags=["compiler"], dependencies=[Depends(rate_limit)])
async def compile_circuit(
    text: str = Body(..., embed=True, max_length=biocompiler.MAX_INPUT_CHARS),
) -> dict[str, Any]:
    """Compile a natural-language description into a genetic circuit.

    Never raises for an unparseable sentence: a failed compile is a normal
    result with `ok: false` and diagnostics saying which clause did not map onto
    biology. That is information the user needs, not an error to swallow.
    """
    return biocompiler.compile_text(text)


@app.post("/api/v1/simulate", tags=["simulator"], dependencies=[Depends(rate_limit)])
def simulate(request: dict[str, Any] = Body(...)) -> dict[str, Any]:
    """Run a stochastic simulation of gene expression noise and crosstalk.

    Declared `def` rather than `async def` deliberately. This is seconds of
    tight CPU-bound loop with no await in it; on the event loop it would block
    every other request in the process for the duration. FastAPI runs a sync
    handler in a worker thread, which keeps `/health` answering while a
    simulation is in flight.

    Like the compiler, it does not raise for a result the user will not like:
    an unusable parameter is clamped and a diagnostic says so, and a run that
    exhausts its budget returns what it measured plus a warning that it stopped
    early.
    """
    return bionoise.simulate(request)


@app.get("/api/v1/simulate/presets", tags=["simulator"])
async def simulation_presets() -> dict[str, Any]:
    """The networks this service can simulate, for a UI building its own form."""
    return {"presets": list(bionoise.PRESETS)}


@app.post("/api/v1/memory", tags=["memory"], dependencies=[Depends(rate_limit)])
def design_memory(request: dict[str, Any] = Body(...)) -> dict[str, Any]:
    """Compare genetic memory architectures and emit the DNA for the better one.

    Sync rather than async for the same reason as the simulator: the ODE
    integration and the sequence scan are CPU-bound with nothing to await, so
    FastAPI's worker thread keeps the rest of the service responsive.

    A design the user will not like is still a 200. Only a request the tool
    cannot honour — an unknown signal, or a host this parts library does not
    serve — comes back with `ok: false` and a diagnostic naming the reason.
    """
    return memory.design(request)


@app.get("/api/v1/memory/options", tags=["memory"])
async def memory_options() -> dict[str, Any]:
    """Signals, hosts and recombinases this service can design for."""
    return {
        "signals": list(memory.SIGNALS),
        "chassis": list(memory.CHASSIS),
        "recombinases": list(memory.RECOMBINASES),
    }


@app.get("/api/v1/job/{job_id}", tags=["analysis"])
async def job_status(job_id: str) -> dict[str, Any]:
    job = store.get(job_id)
    if job is None:
        raise AnalysisError(ErrorCode.JOB_NOT_FOUND, status_code=404, job_id=job_id)

    job.pop("created_at", None)
    return job
