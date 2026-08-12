"""Production-hardening behaviour: throttling, exposure, error contract.

These cover the properties that only matter once the service has been running
unattended for a while — memory that grows with every caller ever seen, a schema
browser nobody meant to publish, a 429 that no HTTP client can act on.
"""

from __future__ import annotations

from app.errors import AnalysisError, ErrorCode
from app.main import app
from app.ratelimit import RateLimiter
from fastapi.testclient import TestClient

# ---------------------------------------------------------------------------
# Sliding window
# ---------------------------------------------------------------------------

def test_requests_under_the_limit_are_allowed():
    limiter = RateLimiter(per_minute=3)

    assert [limiter.check("10.0.0.1", now=t) for t in (0.0, 1.0, 2.0)] == [None, None, None]


def test_the_request_over_the_limit_is_told_how_long_to_wait():
    limiter = RateLimiter(per_minute=3)
    for t in (0.0, 1.0, 2.0):
        limiter.check("10.0.0.1", now=t)

    wait = limiter.check("10.0.0.1", now=10.0)

    # The window opened at t=0, so it clears at t=60: fifty seconds from now.
    assert wait == 50.0


def test_the_window_slides_rather_than_resetting_on_the_minute():
    """A fixed window lets a client spend its whole allowance twice across the
    boundary. The oldest hit must expire on its own schedule, not the clock's."""
    limiter = RateLimiter(per_minute=2)
    limiter.check("10.0.0.1", now=0.0)
    limiter.check("10.0.0.1", now=30.0)

    assert limiter.check("10.0.0.1", now=59.0) is not None  # still two in window
    assert limiter.check("10.0.0.1", now=61.0) is None      # the t=0 hit expired
    assert limiter.check("10.0.0.1", now=61.5) is not None  # t=30 has not


def test_clients_are_counted_separately():
    limiter = RateLimiter(per_minute=1)

    assert limiter.check("10.0.0.1", now=0.0) is None
    assert limiter.check("10.0.0.2", now=0.0) is None
    assert limiter.check("10.0.0.1", now=0.1) is not None


def test_a_limit_of_zero_disables_throttling():
    limiter = RateLimiter(per_minute=0)

    assert all(limiter.check("10.0.0.1", now=float(i)) is None for i in range(100))
    assert limiter.tracked_clients() == 0


# ---------------------------------------------------------------------------
# Bounded memory — the regression this class exists for
# ---------------------------------------------------------------------------

def test_quiet_clients_are_forgotten():
    """The old limiter kept one dict entry per address for the life of the
    process. Memory grew with every caller ever seen, not with active ones."""
    limiter = RateLimiter(per_minute=10)

    for i in range(500):
        limiter.check(f"10.0.{i // 256}.{i % 256}", now=0.0)
    assert limiter.tracked_clients() == 500

    # One request, two minutes later. Everyone else has gone quiet.
    limiter.check("10.9.9.9", now=120.0)

    assert limiter.tracked_clients() == 1


def test_an_active_client_survives_the_sweep():
    limiter = RateLimiter(per_minute=10)
    limiter.check("10.0.0.1", now=0.0)
    limiter.check("10.0.0.2", now=0.0)

    limiter.check("10.0.0.1", now=90.0)   # sweeps, and .2 is stale
    limiter.check("10.0.0.1", now=100.0)

    assert limiter.tracked_clients() == 1


def test_the_table_has_a_hard_ceiling():
    """A flood from many addresses inside one window outruns the sweep, so the
    table is capped as well as expired."""
    limiter = RateLimiter(per_minute=10, max_clients=50)

    for i in range(400):
        limiter.check(f"10.1.{i // 256}.{i % 256}", now=float(i) * 0.01)

    assert limiter.tracked_clients() <= 50


def test_eviction_takes_the_least_recently_seen_first():
    limiter = RateLimiter(per_minute=10, max_clients=2)

    limiter.check("old", now=0.0)
    limiter.check("recent", now=1.0)
    limiter.check("newest", now=2.0)
    limiter.check("trigger", now=3.0)

    # "old" is the one that should have gone.
    assert limiter.check("newest", now=3.1) is None
    assert limiter.tracked_clients() <= 2


def test_reset_clears_the_table():
    limiter = RateLimiter(per_minute=1)
    limiter.check("10.0.0.1", now=0.0)
    limiter.reset()

    assert limiter.tracked_clients() == 0
    assert limiter.check("10.0.0.1", now=0.1) is None


# ---------------------------------------------------------------------------
# Error contract
# ---------------------------------------------------------------------------

def test_rate_limited_errors_carry_a_retry_after_header():
    error = AnalysisError(ErrorCode.RATE_LIMITED, status_code=429, retry_after=42)

    assert error.headers() == {"Retry-After": "42"}


def test_other_errors_carry_no_extra_headers():
    assert AnalysisError(ErrorCode.FASTA_EMPTY).headers() == {}


# ---------------------------------------------------------------------------
# Exposure
# ---------------------------------------------------------------------------

def test_the_schema_browser_is_closed_by_default():
    """ENABLE_DOCS is unset in the test environment, as it is in production."""
    client = TestClient(app)

    assert client.get("/docs").status_code == 404
    assert client.get("/openapi.json").status_code == 404


def test_health_reports_both_in_memory_tables():
    client = TestClient(app)

    body = client.get("/health").json()

    assert body["status"] == "ok"
    assert "jobs_in_memory" in body
    assert "rate_limit_clients" in body
