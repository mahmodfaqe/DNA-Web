"""BioNoise-Sim: crosstalk and noise in a stochastic cell.

    simulate({"preset": "crosstalk_pair", "cells": 60, "minutes": 60,
              "crosstalk": 0.4, "resource_coupling": True})

Runs a population of independent cells through an exact stochastic simulation of
their gene expression chemistry, and returns the trajectories, the steady-state
distributions, the noise decomposition, and an accounting of how much of each
gene's expression was driven by a signal meant for something else.

Where a coupling exists, a second ensemble is run with that coupling removed and
nothing else changed — same seeds, same cells. The difference between the two is
the measurement: it is what lets the result say "this much of the noise is
crosstalk" instead of only "this gene is noisy".
"""

from __future__ import annotations

import time
from typing import Any

from ...config import settings as runtime
from ..diagnostics import Report
from . import network, statistics
from .diagnostics import Code
from .gillespie import RunSettings, run_ensemble

__all__ = ["simulate", "Code", "PRESETS"]

PRESETS = tuple(network.PRESETS)

# --- limits ---------------------------------------------------------------
MIN_CELLS, MAX_CELLS = 4, 200
MIN_MINUTES, MAX_MINUTES = 5, 240
MAX_VARIABILITY = 0.6
SAMPLES = 120

# The first stretch of every run is discarded. The ensemble is seeded from the
# analytic steady state, so this only has to settle the couplings between genes
# rather than grow every protein from zero — but a mean taken over a window
# where the protein is still climbing is a trend, not a steady state.
BURN_IN_FRACTION = 0.4

# Thresholds at which a reading stops being reliable, or starts being the
# finding. Named rather than inlined because they are editorial judgements and
# should be arguable.
# Below roughly thirty copies the Poisson floor alone is already upwards of 18%
# of the mean, the distribution is visibly discrete, and the Gaussian intuitions
# behind "mean plus or minus a bit" stop applying.
LOW_COPY_THRESHOLD = 30
CROSSTALK_ALARM = 0.15           # foreign transcripts worth naming
LEAK_ALARM = 0.20
AVAILABILITY_ALARM = 0.85        # ribosome availability, below which sharing bites
PRECISION_ALARM = 0.20           # relative error on the noise figures
THEORY_GAP = 1.5                 # measured Fano this many times the analytic value


def _clamp(value: float, low: float, high: float) -> float:
    return low if value < low else (high if value > high else value)


def _read_request(payload: dict[str, Any], report: Report) -> dict[str, Any] | None:
    """Validate and clamp, saying so whenever a value is moved."""
    preset = str(payload.get("preset") or "independent")
    if preset not in network.PRESETS:
        report.error(Code.UNKNOWN_PRESET, preset=preset, available=", ".join(network.PRESETS))
        return None

    requested_cells = int(payload.get("cells") or 60)
    cells = int(_clamp(requested_cells, MIN_CELLS, MAX_CELLS))
    if cells != requested_cells:
        report.warn(Code.CELLS_CLAMPED, requested=requested_cells, used=cells,
                    minimum=MIN_CELLS, maximum=MAX_CELLS)

    requested_minutes = float(payload.get("minutes") or 60)
    minutes = _clamp(requested_minutes, MIN_MINUTES, MAX_MINUTES)
    if minutes != requested_minutes:
        report.warn(Code.DURATION_CLAMPED, requested=round(requested_minutes, 1),
                    used=round(minutes, 1), minimum=MIN_MINUTES, maximum=MAX_MINUTES)

    seed = payload.get("seed")
    seed = int(seed) if seed not in (None, "") else int(time.time_ns() % 1_000_000)

    return {
        "preset": preset,
        "cells": cells,
        "minutes": minutes,
        "induction": _clamp(float(payload.get("induction", 1.0)), 0.0, 1.0),
        "crosstalk": _clamp(float(payload.get("crosstalk", 0.4)), 0.0, 1.0),
        "variability": _clamp(float(payload.get("variability", 0.2)), 0.0, MAX_VARIABILITY),
        "resource_coupling": bool(payload.get("resource_coupling", True)),
        "seed": abs(seed) % 2_147_483_647,
    }


def _interpret(
    net: network.Network,
    request: dict[str, Any],
    result: dict[str, Any],
    runs: list,
    report: Report,
) -> None:
    """Read the numbers and say what is worth knowing about them."""
    stats = result["statistics"]
    crosstalk = result["crosstalk"]

    for gene_id, entry in stats.items():
        if 0 < entry["mean_protein"] < LOW_COPY_THRESHOLD:
            report.warn(Code.LOW_COPY_NUMBER, span=gene_id, gene=gene_id,
                        mean=entry["mean_protein"])

        if entry["drift"] > entry["drift_threshold"]:
            report.warn(Code.NOT_AT_STEADY_STATE, span=gene_id, gene=gene_id,
                        drift=round(entry["drift"] * 100, 1),
                        expected=round(entry["drift_threshold"] * 100, 1))

        share = crosstalk["attribution"][gene_id]
        if share["crosstalk"] > CROSSTALK_ALARM:
            report.warn(Code.CROSSTALK_DOMINATES, span=gene_id, gene=gene_id,
                        percent=round(share["crosstalk"] * 100, 1))
        if share["leak"] > LEAK_ALARM:
            report.warn(Code.LEAK_DOMINATES, span=gene_id, gene=gene_id,
                        percent=round(share["leak"] * 100, 1))

        if entry["analytic_fano"] > 0 and entry["fano"] > entry["analytic_fano"] * THEORY_GAP:
            report.info(Code.NOISE_EXCEEDS_THEORY, span=gene_id, gene=gene_id,
                        measured=entry["fano"], predicted=entry["analytic_fano"],
                        ratio=round(entry["fano"] / entry["analytic_fano"], 1))

    availability = result["performance"]["availability"]
    if request["resource_coupling"] and availability < AVAILABILITY_ALARM:
        report.warn(Code.RESOURCES_LIMITING, percent=round(availability * 100, 1),
                    lost=round((1 - availability) * 100, 1))

    # Precision is reported from the worst-measured gene: a result is only as
    # trustworthy as its least certain number, and quoting the best one would
    # be flattering the run.
    worst = max(stats.values(), key=lambda entry: entry["precision"])
    if worst["precision"] > PRECISION_ALARM:
        report.warn(Code.IMPRECISE, percent=round(worst["precision"] * 100, 1),
                    independent=round(worst["effective_samples"]),
                    cells=request["cells"], minutes=round(request["minutes"]))
    else:
        report.info(Code.PRECISION, percent=round(worst["precision"] * 100, 1),
                    independent=round(worst["effective_samples"]))

    truncated = sum(1 for run in runs if run.truncated)
    if truncated:
        report.warn(Code.RUN_TRUNCATED, cells=truncated, total=len(runs))

    flips = result.get("switching")
    if flips is not None:
        if flips["switches"] == 0:
            report.warn(Code.NO_SWITCHING_OBSERVED, minutes=round(request["minutes"]))
        else:
            report.info(Code.SWITCHING_OBSERVED, switches=flips["switches"],
                        cells=flips["cells_that_switched"],
                        dwell=flips["mean_dwell_minutes"])

    if result["performance"]["control_ensemble"]:
        report.info(Code.CONTROL_ENSEMBLE, cells=request["cells"])

    report.info(Code.SEED_RECORDED, seed=request["seed"])
    report.info(Code.WELL_MIXED_ASSUMPTION)
    report.info(Code.NO_CELL_DIVISION)
    report.info(Code.PARAMETERS_ILLUSTRATIVE)


def simulate(payload: dict[str, Any]) -> dict[str, Any]:
    report = Report()
    request = _read_request(payload, report)

    if request is None:
        return {
            "ok": False,
            "request": payload,
            "diagnostics": report.as_list(),
            "diagnostic_counts": report.counts(),
        }

    started = time.perf_counter()

    net = network.build(request["preset"])
    seconds = request["minutes"] * 60.0

    coupled = net.has_crosstalk or (request["resource_coupling"] and net.ribosome_capacity > 0)
    ensembles = 2 if coupled else 1

    settings = RunSettings(
        seconds=seconds,
        samples=SAMPLES,
        induction=request["induction"],
        crosstalk=request["crosstalk"],
        resource_coupling=request["resource_coupling"],
        extrinsic_cv=request["variability"],
        # The budget is shared between the two ensembles so that asking for a
        # control run cannot double the wall time of a request.
        max_steps_per_cell=max(
            20_000, runtime.sim_max_steps // (request["cells"] * ensembles)
        ),
    )

    runs = run_ensemble(net, settings, request["seed"], request["cells"])
    control = (
        run_ensemble(network.isolate(net), settings, request["seed"], request["cells"])
        if coupled else None
    )

    start = int(SAMPLES * BURN_IN_FRACTION)
    grid_minutes = [round(seconds * index / (SAMPLES - 1) / 60.0, 3) for index in range(SAMPLES)]

    result: dict[str, Any] = {
        "ok": True,
        "request": request,
        "network": net.as_dict(),
        "time": {
            "grid_minutes": grid_minutes,
            "burn_in_index": start,
            "burn_in_minutes": round(grid_minutes[start], 2),
            "total_minutes": round(request["minutes"], 2),
        },
        "trajectories": statistics.trajectories(net, runs, SAMPLES),
        "distributions": statistics.distributions(net, runs, start),
        "statistics": statistics.gene_statistics(
            net, runs, control, start, settings, 1.0 - BURN_IN_FRACTION
        ),
        "crosstalk": statistics.crosstalk_report(net, runs, start),
        "decomposition": statistics.decomposition(net, runs, start),
        "switching": statistics.switching(net, runs, settings),
        "performance": {
            "cells": request["cells"],
            "events": sum(run.steps for run in runs) + sum(run.steps for run in (control or [])),
            "control_ensemble": control is not None,
            "availability": round(
                sum(run.availability for run in runs) / len(runs) if runs else 1.0, 4
            ),
        },
    }

    _interpret(net, request, result, runs, report)

    result["performance"]["wall_ms"] = round((time.perf_counter() - started) * 1000, 1)
    result["diagnostics"] = report.as_list()
    result["diagnostic_counts"] = report.counts()
    return result
