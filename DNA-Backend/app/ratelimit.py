"""Per-client request throttling.

Built to the same discipline as `JobStore`: bounded, expiring, lock guarded.

The previous implementation was a module-level `defaultdict(deque)`. It had two
faults that only show up after a service has been running for a while:

* it never forgot a client. One address that made one request in March still
  held a dict entry in September, so memory grew with the number of distinct
  callers ever seen rather than with the number currently active;
* it was read-modify-written from uvicorn's thread pool without a lock. The
  synchronous endpoints (`/simulate`, `/memory`) are dispatched to worker
  threads, so two of them could prune and append to the same deque at once.

Neither is dramatic. Both are the kind of thing that is cheap to fix now and
expensive to diagnose from a memory graph six months from now.

Like the job store this is process-local. With more than one uvicorn worker each
process counts separately, so the effective limit is `workers x limit`. A
multi-replica deployment should throttle at the reverse proxy instead; this
class is the single seam that would change.
"""

from __future__ import annotations

import threading
import time
from collections import deque


class RateLimiter:
    """Sliding-window limiter keyed by client address.

    `check` is the whole interface: it returns `None` when the caller may
    proceed, or the number of seconds until they may try again.
    """

    def __init__(
        self,
        per_minute: int,
        window_seconds: float = 60.0,
        max_clients: int = 4096,
    ) -> None:
        self._per_minute = per_minute
        self._window = window_seconds
        self._max_clients = max(1, max_clients)
        self._lock = threading.Lock()
        self._hits: dict[str, deque[float]] = {}
        self._last_sweep = 0.0

    # -- internals ---------------------------------------------------------

    def _sweep(self, now: float) -> None:
        """Drop clients that have gone quiet. Caller holds the lock.

        Skipped unless a full window has passed since the last sweep, so the
        common path stays O(1) and only the periodic pass is O(clients).
        """
        if now - self._last_sweep < self._window and len(self._hits) <= self._max_clients:
            return

        self._last_sweep = now

        for client in [c for c, w in self._hits.items() if not w or now - w[-1] >= self._window]:
            del self._hits[client]

    def _enforce_ceiling(self) -> None:
        """Cap the table. Caller holds the lock.

        A flood from many distinct addresses inside a single window outruns the
        sweep, because none of those entries is stale yet. Past the ceiling the
        least recently seen go first: evicting a client only forgives requests
        it has already made, never requests it has yet to make.

        Runs after insertion rather than before it, so the ceiling is a property
        the table actually holds rather than one it holds most of the time.
        """
        overflow = len(self._hits) - self._max_clients
        if overflow <= 0:
            return

        oldest = sorted(self._hits.items(), key=lambda item: item[1][-1])
        for client, _ in oldest[:overflow]:
            del self._hits[client]

    # -- interface ---------------------------------------------------------

    def check(self, client: str, now: float | None = None) -> float | None:
        """Record a request. Returns `None` if allowed, else seconds to wait."""
        if self._per_minute <= 0:
            return None

        moment = time.time() if now is None else now

        with self._lock:
            self._sweep(moment)

            window = self._hits.get(client)
            if window is None:
                window = deque()
                self._hits[client] = window

            while window and moment - window[0] >= self._window:
                window.popleft()

            if len(window) >= self._per_minute:
                return max(0.0, self._window - (moment - window[0]))

            window.append(moment)
            self._enforce_ceiling()
            return None

    def tracked_clients(self) -> int:
        """How many addresses currently hold state. Exposed for /health."""
        with self._lock:
            return len(self._hits)

    def reset(self) -> None:
        with self._lock:
            self._hits.clear()
            self._last_sweep = 0.0
