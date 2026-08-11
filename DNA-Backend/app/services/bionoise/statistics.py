"""Turning trajectories into measurements.

A cloud of stochastic traces is not a result. These are the numbers that make it
one, and each was chosen because it answers a question a deterministic model
cannot:

  Fano factor      variance / mean. Exactly 1 for a Poisson process, so it reads
                   as "how many times noisier than pure random arrival". A gene
                   with Fano 12 is making its protein in bursts of about 12.
  CV squared       variance / mean^2. The dimensionless noise used throughout
                   the single-cell literature, and the quantity that decomposes
                   additively into independent sources.
  Noise budget     that decomposition: how much of the measured noise is the
                   unavoidable floor of counting molecules, how much is burst
                   size, how much is the promoter switching on and off, and how
                   much is other genes.
  Crosstalk share  of the transcripts this gene actually made, the fraction
                   that were driven by an input meant for something else.
  Intrinsic vs
  extrinsic        for two identical reporters in one cell: noise that makes
                   them differ from each other, versus noise that moves them
                   together.

The last one only appears where it is valid. A tool that printed an
intrinsic/extrinsic split for a single reporter would be inventing a number.
"""

from __future__ import annotations

import math
from collections.abc import Sequence
from typing import Any

from .gillespie import CellRun, RunSettings
from .network import Network

__all__ = [
    "moments", "correlation", "histogram", "pooled", "deviations", "effective_samples",
    "analytic_fano", "gene_statistics", "noise_budget", "crosstalk_report",
    "decomposition", "switching", "trajectories", "distributions",
]

HISTOGRAM_BINS = 22
EXAMPLE_TRACES = 3


# --------------------------------------------------------------------------
# Small numeric helpers
# --------------------------------------------------------------------------

def moments(values: Sequence[float]) -> tuple[float, float]:
    """Mean and (population) variance in one pass over the data."""
    count = len(values)
    if count == 0:
        return 0.0, 0.0

    total = 0.0
    total_squares = 0.0
    for value in values:
        total += value
        total_squares += value * value

    mean = total / count
    variance = max(0.0, total_squares / count - mean * mean)
    return mean, variance


def correlation(left: Sequence[float], right: Sequence[float]) -> float:
    count = len(left)
    if count < 2:
        return 0.0

    mean_left, var_left = moments(left)
    mean_right, var_right = moments(right)
    if var_left <= 0.0 or var_right <= 0.0:
        return 0.0

    covariance = 0.0
    for index in range(count):
        covariance += (left[index] - mean_left) * (right[index] - mean_right)
    covariance /= count

    return max(-1.0, min(1.0, covariance / math.sqrt(var_left * var_right)))


def histogram(values: list[int], bins: int = HISTOGRAM_BINS) -> dict[str, Any]:
    if not values:
        return {"edges": [], "counts": [], "min": 0, "max": 0}

    low = min(values)
    high = max(values)
    if high == low:
        high = low + 1

    width = (high - low) / bins
    counts = [0] * bins
    for value in values:
        slot = int((value - low) / width)
        counts[min(slot, bins - 1)] += 1

    return {
        "edges": [round(low + width * index, 2) for index in range(bins + 1)],
        "counts": counts,
        "min": low,
        "max": high,
    }


def pooled(runs: list[CellRun], gene: int, start: int, series: str = "protein") -> list[int]:
    """Every post-burn-in sample from every cell, in a stable (cell, time) order.

    The order matters: two genes pooled the same way stay aligned sample for
    sample, which is what lets the correlation and the intrinsic/extrinsic
    split be computed from the same arrays.
    """
    values: list[int] = []
    for run in runs:
        trace = run.protein[gene] if series == "protein" else run.mrna[gene]
        values.extend(trace[start:])
    return values


def deviations(runs: list[CellRun], gene: int, start: int) -> list[float]:
    """Post-burn-in samples with the ensemble mean at each time point removed.

    Correlation between genes has to be measured this way rather than from the
    raw pooled samples. If the whole population is still drifting — settling
    after induction, or relaxing towards a steady state it has not reached —
    every gene drifts with it, and a naive correlation reports that shared
    trend as though the genes were coupled. Subtracting the ensemble mean at
    each moment leaves only how a cell differs from its neighbours *at that
    moment*, which is the quantity the question is actually about.
    """
    values: list[float] = []
    if not runs:
        return values

    cells = len(runs)
    count = len(runs[0].protein[gene])
    for sample in range(start, count):
        column = [run.protein[gene][sample] for run in runs]
        mean = sum(column) / cells
        values.extend(value - mean for value in column)

    return values


def cell_factors(runs: list[CellRun], start: int, count: int) -> list[float]:
    """Each cell's own translation capacity, laid out to match ``deviations``."""
    if not runs:
        return []

    average = sum(run.efficiency for run in runs) / len(runs)
    values: list[float] = []
    for _ in range(start, count):
        values.extend(run.efficiency - average for run in runs)
    return values


def residuals(values: Sequence[float], control: Sequence[float]) -> list[float]:
    """What is left of ``values`` once the linear effect of ``control`` is removed."""
    mean_value, _ = moments(values)
    mean_control, var_control = moments(control)
    if var_control <= 0.0:
        return list(values)

    covariance = 0.0
    for index in range(len(values)):
        covariance += (values[index] - mean_value) * (control[index] - mean_control)
    covariance /= len(values)

    slope = covariance / var_control
    return [values[index] - slope * (control[index] - mean_control) for index in range(len(values))]


def effective_samples(runs: list[CellRun], gene_index: int, seconds: float, retained: float,
                      decay: float) -> float:
    """How many genuinely independent measurements the run produced.

    Not the number of samples taken. A protein with a half-life of half an hour
    forgets its own value only over roughly that long, so sampling it every ten
    seconds produces a great many numbers and very little extra information.
    Independent observations accumulate at one per two protein lifetimes per
    cell, and never fewer than one per cell — the cells themselves are
    independent. This is the number that governs how far the reported noise can
    be trusted, so it is computed and shown rather than left implied.
    """
    lifetime = 1.0 / decay if decay > 0 else float("inf")
    window = seconds * retained
    per_cell = max(1.0, window / (2.0 * lifetime))
    return len(runs) * per_cell


# --------------------------------------------------------------------------
# Per-gene statistics
# --------------------------------------------------------------------------

def analytic_fano(net: Network, gene_index: int) -> float:
    """Fano factor predicted for this gene on its own, from theory.

    Thattai and van Oudenaarden's two-stage result: a protein made from mRNA
    that is itself Poisson has Fano = 1 + b/(1 + d_p/d_m), where b is the burst
    size. It is printed next to the measured value because the gap between them
    is informative — it is everything the two-stage model leaves out, which is
    to say promoter switching and every coupling in the network.
    """
    gene = net.genes[gene_index]
    return 1.0 + gene.burst_size / (1.0 + gene.d_p / gene.d_m)


def gene_statistics(
    net: Network,
    runs: list[CellRun],
    control: list[CellRun] | None,
    start: int,
    settings: RunSettings,
    retained: float,
) -> dict[str, dict[str, Any]]:
    result: dict[str, dict[str, Any]] = {}

    for index, gene in enumerate(net.genes):
        protein = pooled(runs, index, start)
        mrna = pooled(runs, index, start, "mrna")

        mean_p, var_p = moments(protein)
        mean_m, var_m = moments(mrna)

        cv_squared = var_p / (mean_p * mean_p) if mean_p > 0 else 0.0
        fano = var_p / mean_p if mean_p > 0 else 0.0

        independent = effective_samples(runs, index, settings.seconds, retained, gene.d_p)

        # Steady state check: the retained window is split in half and the two
        # halves compared. A gene still climbing towards its steady state would
        # report a variance that is really a trend, and that is worth saying out
        # loud rather than quietly averaging away.
        #
        # What counts as "drifting" cannot be a fixed percentage. Two halves of a
        # stationary run differ by about 2*CV/sqrt(n) from sampling alone, so a
        # noisy gene measured in few cells looks like it is drifting when it is
        # not. The threshold is therefore derived from this gene's own noise and
        # its own effective sample size.
        half = len(protein) // 2
        first_half, _ = moments(protein[:half])
        second_half, _ = moments(protein[half:])
        drift = abs(second_half - first_half) / mean_p if mean_p > 0 else 0.0
        expected_drift = (
            2.0 * math.sqrt(cv_squared) / math.sqrt(independent) if independent > 0 else 0.0
        )

        entry: dict[str, Any] = {
            "id": gene.id,
            "label": gene.label,
            "mean_protein": round(mean_p, 2),
            "sd_protein": round(math.sqrt(var_p), 2),
            "cv": round(math.sqrt(cv_squared), 4),
            "cv_squared": round(cv_squared, 5),
            "fano": round(fano, 2),
            "mean_mrna": round(mean_m, 3),
            "fano_mrna": round(var_m / mean_m, 2) if mean_m > 0 else 0.0,
            "burst_size": round(gene.burst_size, 2),
            "analytic_fano": round(analytic_fano(net, index), 2),
            "drift": round(drift, 4),
            "drift_threshold": round(max(0.05, 2.5 * expected_drift), 4),
            "samples": len(protein),
            "effective_samples": round(independent, 1),
            # Relative standard error of a variance estimated from n independent
            # observations. Printing the noise without it invites reading a
            # difference between two runs that is only the dice.
            "precision": round(math.sqrt(2.0 / independent), 4) if independent > 0 else 0.0,
        }

        entry["noise_budget"] = noise_budget(
            net, index, cv_squared, mean_p, control, start, settings.extrinsic_cv
        )
        result[gene.id] = entry

    return result


def noise_budget(
    net: Network,
    index: int,
    cv_squared: float,
    mean_protein: float,
    control: list[CellRun] | None,
    start: int,
    extrinsic_cv: float,
) -> dict[str, float]:
    """Split measured noise into the sources that produced it.

    Noise expressed as CV squared is additive over independent sources, which
    is the only reason a budget like this can be written down at all. Some
    terms are theory and some are measurement, and they are labelled that way
    because the distinction matters when they disagree:

      floor      1/<p>, the Poisson limit. No mechanism gets below this.
      bursting   the analytic two-stage excess, b/(1 + d_p/d_m) over <p>.
      extrinsic  cell-to-cell spread in translation capacity, which enters
                 multiplicatively and so contributes its CV squared directly.
      promoter   whatever the isolated gene still shows beyond those three. In
                 this model that is the promoter switching OFF and ON.
      coupling   what the couplings add on top, measured by re-running the same
                 cells with the crosstalk and the shared ribosome pool removed.

    ``coupling`` is signed on purpose. Coupling that happens to act as negative
    feedback *reduces* noise, and clamping that to zero would hide one of the
    more useful things a student can see here.
    """
    empty = {"floor": 0.0, "bursting": 0.0, "extrinsic": 0.0, "promoter": 0.0,
             "coupling": 0.0, "total": 0.0}
    if mean_protein <= 0:
        return empty

    floor = 1.0 / mean_protein
    gene = net.genes[index]
    bursting = (gene.burst_size / (1.0 + gene.d_p / gene.d_m)) / mean_protein
    extrinsic = extrinsic_cv * extrinsic_cv

    if control is None:
        isolated_cv_squared = cv_squared
    else:
        isolated = pooled(control, index, start)
        mean_isolated, var_isolated = moments(isolated)
        isolated_cv_squared = (
            var_isolated / (mean_isolated * mean_isolated) if mean_isolated > 0 else 0.0
        )

    promoter = max(0.0, isolated_cv_squared - floor - bursting - extrinsic)
    coupling = cv_squared - isolated_cv_squared

    return {
        "floor": round(floor, 5),
        "bursting": round(bursting, 5),
        "extrinsic": round(extrinsic, 5),
        "promoter": round(promoter, 5),
        "coupling": round(coupling, 5),
        "total": round(cv_squared, 5),
    }


# --------------------------------------------------------------------------
# Crosstalk
# --------------------------------------------------------------------------

def crosstalk_report(net: Network, runs: list[CellRun], start: int) -> dict[str, Any]:
    """Where each gene's transcripts came from, and how the genes move together.

    The attribution is exact inside the model rather than inferred from the
    output: every time a promoter opened, the simulator recorded what share of
    the drive opening it was foreign, and the transcripts made during that open
    interval carry that share. It answers "how much of this gene's expression
    should not be happening" without having to guess from a correlation.

    Two correlation matrices are returned, and the pair is the point. The
    measured one is what a microscope sees, and on any real pair of genes it is
    dominated by cells simply differing from one another: two genes with no
    connection whatsoever still correlate strongly, because a cell rich in
    ribosomes makes more of both. The partial matrix removes that shared factor
    and leaves what the wiring and the competition actually did. Reading the
    first as evidence of a regulatory link is one of the easiest mistakes to
    make with single-cell data, so both numbers are shown side by side.
    """
    ids = [gene.id for gene in net.genes]
    attribution: dict[str, dict[str, float]] = {}

    for index, gene in enumerate(net.genes):
        total = sum(run.transcripts[index] for run in runs)
        foreign = sum(run.crosstalk_transcripts[index] for run in runs)
        leaked = sum(run.leak_transcripts[index] for run in runs)
        cognate = max(0.0, total - foreign - leaked)

        attribution[gene.id] = {
            "transcripts": round(total, 1),
            "cognate": round(cognate / total, 4) if total else 0.0,
            "crosstalk": round(foreign / total, 4) if total else 0.0,
            "leak": round(leaked / total, 4) if total else 0.0,
        }

    spread = [deviations(runs, index, start) for index in range(len(net.genes))]
    matrix = [
        [round(correlation(spread[row], spread[column]), 3) for column in range(len(ids))]
        for row in range(len(ids))
    ]

    samples = len(runs[0].protein[0]) if runs else 0
    factors = cell_factors(runs, start, samples)
    cleaned = [residuals(series, factors) for series in spread] if factors else spread
    partial = [
        [round(correlation(cleaned[row], cleaned[column]), 3) for column in range(len(ids))]
        for row in range(len(ids))
    ]

    return {
        "genes": ids,
        "attribution": attribution,
        "correlation": matrix,
        "partial": partial,
        "samples": len(spread[0]) if spread else 0,
    }


# --------------------------------------------------------------------------
# Intrinsic and extrinsic noise
# --------------------------------------------------------------------------

def decomposition(net: Network, runs: list[CellRun], start: int) -> dict[str, Any] | None:
    """The Elowitz-Swain split, computed only where it means something.

    Two identical reporters in one cell share every cell-wide fluctuation —
    ribosome availability, cell size, growth rate. So whatever makes the two
    differ *within* a cell can only have come from the randomness of their own
    reactions (intrinsic), and whatever moves them together must have come from
    outside both (extrinsic).

        eta_int^2 = <(p1 - p2)^2> / (2 <p1><p2>)
        eta_ext^2 = (<p1 p2> - <p1><p2>) / (<p1><p2>)

    Reported only when the network declares a matched pair, because on two
    genes that are not identical the same arithmetic produces a number with no
    meaning.
    """
    if net.dual_reporters is None:
        return None

    left_index = net.index(net.dual_reporters[0])
    right_index = net.index(net.dual_reporters[1])

    mean_left, _ = moments(pooled(runs, left_index, start))
    mean_right, _ = moments(pooled(runs, right_index, start))
    if mean_left <= 0 or mean_right <= 0:
        return None

    # Both terms are taken from deviations about the ensemble mean at each
    # moment, for the same reason the correlation matrix is: a population still
    # settling would otherwise have its shared drift counted as extrinsic noise,
    # which is exactly the quantity being measured.
    left = deviations(runs, left_index, start)
    right = deviations(runs, right_index, start)
    if not left or not right:
        return None

    count = len(left)
    squared_difference = 0.0
    product = 0.0
    for index in range(count):
        difference = left[index] - right[index]
        squared_difference += difference * difference
        product += left[index] * right[index]

    # Removing a mean estimated from the same cells costs one degree of
    # freedom per time point; without this the split is biased low by 1/cells.
    cells = len(runs)
    correction = cells / (cells - 1) if cells > 1 else 1.0
    squared_difference *= correction / count
    product *= correction / count

    intrinsic = squared_difference / (2.0 * mean_left * mean_right)
    extrinsic = product / (mean_left * mean_right)

    return {
        "pair": [net.dual_reporters[0], net.dual_reporters[1]],
        "intrinsic": round(max(0.0, intrinsic), 5),
        "extrinsic": round(extrinsic, 5),
        "total": round(max(0.0, intrinsic) + extrinsic, 5),
        "intrinsic_share": (
            round(max(0.0, intrinsic) / (max(0.0, intrinsic) + extrinsic), 3)
            if (max(0.0, intrinsic) + extrinsic) > 0 else 0.0
        ),
    }


# --------------------------------------------------------------------------
# Bistability
# --------------------------------------------------------------------------

def switching(net: Network, runs: list[CellRun], settings: RunSettings) -> dict[str, Any] | None:
    """How often noise flipped a memory that should have held.

    A toggle switch is stable in the differential equations and only in the
    differential equations. Counting the flips gives the memory a half-life,
    which is the single most useful number for anyone designing a circuit that
    has to remember something.
    """
    if net.bistable_pair is None:
        return None

    total = sum(run.switches for run in runs)
    hours = settings.seconds / 3600.0
    cell_hours = hours * len(runs)
    flipped = sum(1 for run in runs if run.switches > 0)

    return {
        "pair": [net.bistable_pair[0], net.bistable_pair[1]],
        "switches": total,
        "cells_that_switched": flipped,
        "cells": len(runs),
        "per_cell_per_hour": round(total / cell_hours, 4) if cell_hours > 0 else 0.0,
        "mean_dwell_minutes": round(cell_hours * 60.0 / total, 1) if total else None,
    }


# --------------------------------------------------------------------------
# Trajectories for plotting
# --------------------------------------------------------------------------

def trajectories(net: Network, runs: list[CellRun], count: int) -> dict[str, Any]:
    """Ensemble mean, spread, and a few individual cells.

    The individual traces are not decoration. A mean with an error band looks
    like a smooth line with fuzz around it; a single cell looks like a
    staircase of bursts followed by decay, and those two pictures lead to
    completely different intuitions about what the cell is doing. Both are
    shown for that reason.
    """
    series: dict[str, Any] = {}
    cells = len(runs)
    picks = sorted({0, cells // 2, cells - 1})[:EXAMPLE_TRACES] if cells else []

    for index, gene in enumerate(net.genes):
        means: list[float] = []
        deviations: list[float] = []

        for sample in range(count):
            column = [run.protein[index][sample] for run in runs]
            mean, variance = moments(column)
            means.append(round(mean, 2))
            deviations.append(round(math.sqrt(variance), 2))

        series[gene.id] = {
            "mean": means,
            "sd": deviations,
            "examples": [runs[pick].protein[index] for pick in picks],
            "mrna_mean": [
                round(moments([run.mrna[index][sample] for run in runs])[0], 3)
                for sample in range(count)
            ],
        }

    return series


def distributions(net: Network, runs: list[CellRun], start: int) -> dict[str, Any]:
    return {
        gene.id: histogram(pooled(runs, index, start))
        for index, gene in enumerate(net.genes)
    }
