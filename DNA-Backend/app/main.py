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
from typing import Any

from fastapi import BackgroundTasks, Body, Depends, FastAPI, File, Request, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from .config import settings
from .errors import AnalysisError, ErrorCode
from .jobs import store
from .ratelimit import RateLimiter
from .services import biocompiler, bionoise, cloning, exports, fasta, memory
from .services.cloning import restriction as cloning_restriction

logging.basicConfig(
    level=getattr(logging, settings.log_level, logging.INFO),
    format="%(asctime)s %(levelname)s [%(name)s] %(message)s",
)
logger = logging.getLogger("dna.api")

app = FastAPI(
    title=settings.app_name,
    version=settings.version,
    description="FASTA analysis service: composition, thermodynamics, ORFs and variant calling.",
    # Off unless ENABLE_DOCS says otherwise. The service is reachable only from
    # the frontend container, so nothing in production needs to browse the
    # schema; leaving it on would publish an endpoint and payload inventory to
    # anyone who ever gets a foothold on that network.
    docs_url="/docs" if settings.enable_docs else None,
    redoc_url="/redoc" if settings.enable_docs else None,
    openapi_url="/openapi.json" if settings.enable_docs else None,
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

limiter = RateLimiter(
    per_minute=settings.rate_limit_per_minute,
    max_clients=settings.rate_limit_max_clients,
)


def rate_limit(request: Request) -> None:
    """Reject a caller that is over its per-minute allowance.

    Keyed on the socket peer rather than on a header, so it cannot be evaded by
    sending a different `X-Forwarded-For`. Behind a reverse proxy that makes
    every request look like it comes from the proxy — which is correct here,
    because the frontend is the only legitimate caller and it is throttled per
    visitor in Laravel before it ever reaches this service.
    """
    client = request.client.host if request.client else "unknown"
    retry_after = limiter.check(client)

    if retry_after is not None:
        raise AnalysisError(
            ErrorCode.RATE_LIMITED,
            status_code=429,
            retry_after=int(retry_after) + 1,
        )


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
    return JSONResponse(
        status_code=exc.status_code,
        content=exc.payload(),
        headers=exc.headers(),
    )


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
        # Both in-memory tables are reported so a deployment can be watched for
        # the growth that used to be invisible until the process ran out of room.
        "rate_limit_clients": limiter.tracked_clients(),
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


@app.post("/api/v1/compile/export", tags=["compiler"], dependencies=[Depends(rate_limit)])
async def export_circuit(
    request: dict[str, Any] = Body(...),
) -> dict[str, Any]:
    """Compile a description and return it in a machine-readable design format.

    FASTA carries bases; these carry what the bases are *for*. SBOL is what the
    design ecosystem reads (SynBioHub, SBOLCanvas, iBioSim); GenBank is what the
    software on a student's laptop reads (SnapGene, ApE, Benchling).

    Returned as a string in a JSON envelope rather than as a file download, so
    the frontend owns the filename, the Content-Type and the translated label —
    the same division of labour as every other endpoint here.
    """
    text = str(request.get("text") or "")
    fmt = str(request.get("format") or "sbol").lower()

    if fmt not in exports.FORMATS:
        raise AnalysisError(ErrorCode.UNSUPPORTED_FORMAT, format=fmt,
                            supported=sorted(exports.FORMATS))

    compiled = biocompiler.compile_text(text)
    if not compiled.get("ok"):
        return {
            "ok": False,
            "format": fmt,
            "document": "",
            "diagnostics": compiled.get("diagnostics", []),
        }

    return {
        "ok": True,
        "format": fmt,
        **exports.FORMATS[fmt],
        "document": exports.render(compiled, fmt, source=text) or "",
        "units": compiled["totals"]["units"],
        "length": compiled["totals"]["length"],
        "diagnostics": compiled.get("diagnostics", []),
    }


@app.get("/api/v1/compile/formats", tags=["compiler"])
async def export_formats() -> dict[str, Any]:
    """The design formats a compiled circuit can be exported as."""
    return {"formats": exports.FORMATS}


@app.post("/api/v1/cloning", tags=["cloning"], dependencies=[Depends(rate_limit)])
def plan_cloning(request: dict[str, Any] = Body(...)) -> dict[str, Any]:
    """Restriction analysis and primer design for one template.

    Sync for the same reason as the simulator and the memory designer: the work
    is a sequence scan and a bounded candidate search, both CPU-bound with
    nothing to await.

    A design the user will not like is still a 200 — a primer pair that cannot
    share an annealing step, or a tail whose enzyme also cuts the insert, comes
    back with the design *and* the diagnostic. Only a request that cannot be
    honoured at all is `ok: false`.
    """
    return cloning.plan(request)


@app.get("/api/v1/cloning/panels", tags=["cloning"])
async def cloning_panels() -> dict[str, Any]:
    """The enzyme panels this service searches, for a UI building its own form."""
    return {"panels": cloning_restriction.available_panels()}


@app.get("/api/v1/job/{job_id}", tags=["analysis"])
async def job_status(job_id: str) -> dict[str, Any]:
    job = store.get(job_id)
    if job is None:
        raise AnalysisError(ErrorCode.JOB_NOT_FOUND, status_code=404, job_id=job_id)

    job.pop("created_at", None)
    return job
