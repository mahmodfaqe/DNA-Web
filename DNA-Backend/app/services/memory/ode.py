"""The deterministic model, and the two questions it can answer.

Every run has two phases, because a memory has two jobs and they are measured
differently:

    write      the signal is present. Does the circuit record it, and how fast?
    retention  the signal is gone. Is the record still there an hour later,
               a day later, after twenty divisions?

Most circuit descriptions only show the first. The second is where the two
architectures separate, and it is the one the user is actually asking about.

Integration is fourth-order Runge-Kutta at a fixed step. The systems here are
two- and three-dimensional and not stiff over the timescales involved, so a
fixed-step RK4 is accurate enough and brings no dependency; SciPy would be a
40 MB addition to a container for one solver call.

What a deterministic model cannot do is decide a bistable system's fate. Its
answer to "how long does a toggle hold?" is "for ever", which is wrong for the
same reason the third tab exists — the bit is held by a few dozen molecules,
and a large enough expression burst crosses the barrier. That number is
estimated separately, in `escape_time`, and the estimate says out loud that the
simulator measures it directly.
"""

from __future__ import annotations

import math
from collections.abc import Callable, Sequence
from dataclasses import dataclass, field
from typing import Any

from .library import Architecture, Chassis, Recombinase, Signal

STEP_MINUTES = 0.25
MAX_SAMPLES = 160


# --------------------------------------------------------------------------
# Integration
# --------------------------------------------------------------------------

Derivative = Callable[[float, list[float]], list[float]]


def integrate(
    derivative: Derivative,
    state: list[float],
    start: float,
    end: float,
    step: float = STEP_MINUTES,
) -> tuple[float, list[float], list[tuple[float, list[float]]]]:
    """Classic RK4 from `start` to `end`, returning the path as well as the end."""
    time = start
    current = list(state)
    path: list[tuple[float, list[float]]] = [(time, list(current))]

    steps = max(1, int(round((end - start) / step)))
    step = (end - start) / steps

    for _ in range(steps):
        k1 = derivative(time, current)
        k2 = derivative(time + step / 2, [current[i] + step / 2 * k1[i] for i in range(len(current))])
        k3 = derivative(time + step / 2, [current[i] + step / 2 * k2[i] for i in range(len(current))])
        k4 = derivative(time + step, [current[i] + step * k3[i] for i in range(len(current))])

        current = [
            current[i] + step / 6 * (k1[i] + 2 * k2[i] + 2 * k3[i] + k4[i])
            for i in range(len(current))
        ]
        # Copy numbers cannot be negative; a fraction cannot exceed one. Without
        # this a stiff transient can push the solver into states the chemistry
        # has no meaning for, and the trajectory afterwards is fiction.
        current = [value if value > 0.0 else 0.0 for value in current]
        time += step
        path.append((time, list(current)))

    return time, current, path


def thin(
    path: Sequence[tuple[float, list[float]]],
    count: int = MAX_SAMPLES,
) -> list[tuple[float, list[float]]]:
    """Reduce a dense solver path to something a chart and a JSON column can hold."""
    if len(path) <= count:
        return list(path)
    stride = len(path) / count
    picked = [path[min(len(path) - 1, int(index * stride))] for index in range(count)]
    picked[-1] = path[-1]
    return picked


def hill(value: float, half: float, coefficient: int) -> float:
    if value <= 0.0:
        return 0.0
    powered = value ** coefficient
    return powered / (half ** coefficient + powered)


# --------------------------------------------------------------------------
# Model results
# --------------------------------------------------------------------------

@dataclass
class Phase:
    """One leg of the experiment, sampled for plotting."""

    name: str
    minutes: list[float] = field(default_factory=list)
    series: dict[str, list[float]] = field(default_factory=dict)

    def as_dict(self) -> dict[str, Any]:
        return {"name": self.name, "minutes": self.minutes, "series": self.series}


@dataclass
class Outcome:
    """Everything the model has to say about one architecture."""

    architecture: str
    written: bool
    write_fraction: float          # state reached by the end of the write phase
    retained_fraction: float       # state still held at the end of retention
    write_minutes_to_half: float | None
    retention_half_life_hours: float | None
    false_write_per_hour: float
    generations_held: float
    burden_units: int
    reversible: bool
    stores_in_dna: bool
    phases: list[Phase] = field(default_factory=list)
    detail: dict[str, Any] = field(default_factory=dict)

    def as_dict(self) -> dict[str, Any]:
        return {
            "architecture": self.architecture,
            "written": self.written,
            "write_fraction": round(self.write_fraction, 4),
            "retained_fraction": round(self.retained_fraction, 4),
            "write_minutes_to_half": (
                round(self.write_minutes_to_half, 1) if self.write_minutes_to_half else None
            ),
            "retention_half_life_hours": (
                round(self.retention_half_life_hours, 2)
                if self.retention_half_life_hours is not None else None
            ),
            "false_write_per_hour": round(self.false_write_per_hour, 6),
            "generations_held": round(self.generations_held, 2),
            "burden_units": self.burden_units,
            "reversible": self.reversible,
            "stores_in_dna": self.stores_in_dna,
            "phases": [phase.as_dict() for phase in self.phases],
            "detail": self.detail,
        }


# --------------------------------------------------------------------------
# Recombinase memory
# --------------------------------------------------------------------------

def recombinase_outcome(
    architecture: Architecture,
    signal: Signal,
    chassis: Chassis,
    enzyme: Recombinase,
    signal_minutes: float,
    hold_minutes: float,
    on_plasmid: bool,
    strength: float = 1.0,
) -> Outcome:
    """Integrase accumulates, flips the register, and then stops mattering.

    Two state variables: integrase copies, and the fraction of the population
    whose register has been inverted. The second never decreases on its own —
    that is the point of a unidirectional integrase — so retention is limited
    not by the memory decaying but by two other things entirely: leaky integrase
    slowly flipping cells that were never induced, and, on a plasmid, the whole
    construct being lost at division.
    """
    growth = chassis.growth_rate
    decay = math.log(2) / chassis.protein_half_life_minutes
    clearance = decay + growth

    induced = architecture.alpha * strength
    basal = induced * signal.leak

    def derivative(present: bool) -> Derivative:
        production = induced if present else basal

        def inner(_time: float, state: list[float]) -> list[float]:
            integrase, flipped = state
            recombination = enzyme.k_recombination * hill(integrase, enzyme.k_half, enzyme.hill)
            return [
                production - clearance * integrase,
                recombination * (1.0 - flipped),
            ]

        return inner

    # --- write ----------------------------------------------------------
    _, state, write_path = integrate(derivative(True), [0.0, 0.0], 0.0, signal_minutes)
    write_fraction = state[1]

    # --- retention ------------------------------------------------------
    # The signal is gone but the promoter is not silent: it still leaks, and
    # integrase that is already present has to be diluted away before the
    # register stops flipping.
    _, final, hold_path = integrate(derivative(False), state, 0.0, hold_minutes)

    # Leak is the failure mode that matters. At the steady state the uninduced
    # promoter reaches, the register keeps converting — permanently, because the
    # reaction is one-way. A cell that was never signalled eventually reads as
    # though it was.
    leak_steady = basal / clearance if clearance > 0 else 0.0
    false_rate = enzyme.k_recombination * hill(leak_steady, enzyme.k_half, enzyme.hill)
    false_per_hour = false_rate * 60.0

    # --- plasmid loss ----------------------------------------------------
    # Random segregation of n copies loses the construct from roughly 2^(1-n)
    # of daughters per division. It is small at high copy number and not zero,
    # and unlike everything else here it takes the memory with it.
    if on_plasmid:
        loss_per_division = 2.0 ** (1 - chassis.plasmid_copy_number)
        divisions_per_hour = 60.0 / chassis.doubling_minutes
        loss_rate = loss_per_division * divisions_per_hour
        retention_half_life = math.log(2) / loss_rate if loss_rate > 0 else None
    else:
        loss_rate = 0.0
        retention_half_life = None  # DNA in the genome; nothing erases it

    generations = hold_minutes / chassis.doubling_minutes

    return Outcome(
        architecture=architecture.id,
        written=write_fraction >= 0.5,
        write_fraction=write_fraction,
        retained_fraction=final[1] * math.exp(-loss_rate * hold_minutes / 60.0),
        write_minutes_to_half=_crossing(write_path, index=1, level=0.5),
        retention_half_life_hours=retention_half_life,
        false_write_per_hour=false_per_hour,
        generations_held=generations,
        burden_units=architecture.units,
        reversible=architecture.reversible,
        stores_in_dna=True,
        phases=[
            _phase("write", write_path, ["integrase", "flipped"]),
            _phase("hold", hold_path, ["integrase", "flipped"]),
        ],
        detail={
            "leak_steady_integrase": round(leak_steady, 2),
            "integrase_peak": round(max(point[1][0] for point in write_path), 1),
            "plasmid_loss_per_hour": round(loss_rate, 6),
            "recombinase": enzyme.id,
        },
    )


# --------------------------------------------------------------------------
# Toggle switch
# --------------------------------------------------------------------------

def toggle_outcome(
    architecture: Architecture,
    signal: Signal,
    chassis: Chassis,
    signal_minutes: float,
    hold_minutes: float,
    on_plasmid: bool,
    strength: float = 1.0,
) -> Outcome:
    """Two repressors holding each other down, and growth pulling both apart.

    The bit is which of the two is winning. Nothing about that is written down:
    it survives only because each protein keeps suppressing the other faster
    than growth dilutes it, which is why this architecture has to keep spending
    energy for as long as it remembers.
    """
    growth = chassis.growth_rate
    decay = math.log(2) / chassis.protein_half_life_minutes
    clearance = decay + growth

    alpha = architecture.alpha * strength
    leak = signal.leak
    half = architecture.k_half
    coefficient = architecture.hill

    def derivative(present: bool) -> Derivative:
        # The signal relieves repression of A — the standard way a toggle is
        # set, and the reason the write is fast: it does not have to build a
        # protein from nothing, it has to stop one being destroyed.
        #
        # With the signal gone the input does not fall silent, it falls to the
        # promoter's leak. That has to be modelled here or the comparison is
        # rigged: the recombinase is charged for the same leak, and a toggle
        # that is assumed to receive exactly zero input when uninduced would
        # win on fidelity by assumption rather than by biology.
        push = alpha if present else alpha * leak

        def inner(_time: float, state: list[float]) -> list[float]:
            a, b = state
            return [
                alpha * (leak + (1 - leak) * (1 - hill(b, half, coefficient))) + push - clearance * a,
                alpha * (leak + (1 - leak) * (1 - hill(a, half, coefficient))) - clearance * b,
            ]

        return inner

    # Start from the B-dominant state: the memory has not been written yet.
    resting = alpha / clearance if clearance > 0 else 0.0
    _, state, write_path = integrate(derivative(True), [0.0, resting], 0.0, signal_minutes)
    _, final, hold_path = integrate(derivative(False), state, 0.0, hold_minutes)

    written = state[0] > state[1]
    retained = final[0] > final[1]

    # Bistability. If the system has only one steady state the circuit is not a
    # memory at all — it relaxes back the moment the signal stops, however
    # convincing the write phase looked.
    states = steady_states(alpha, leak, half, coefficient, clearance)
    bistable = len(states) >= 3

    # The barrier is not the gap between the two stable states. Flipping means
    # the *losing* repressor climbing to the unstable middle fixed point, from
    # which the system falls into the other well on its own — so the distance
    # that matters is from the low state up to the saddle, and it is a great
    # deal shorter than the distance between the two answers.
    if bistable:
        low, saddle = sorted(states)[0], sorted(states)[1]
        barrier = max(0.0, saddle - low)
    else:
        barrier = 0.0

    escape_hours = escape_time(
        barrier, architecture.burst_size, architecture.burst_frequency
    ) if bistable and retained else 0.0

    # False writing, measured the same way it is for the recombinase: start a
    # population that was never signalled, give it nothing but the promoter's
    # leak, and see whether it sets itself inside the holding window. A toggle
    # driven hard enough by leak alone crosses the barrier and latches — and
    # once latched it stays, which is exactly the failure the DNA-based memory
    # is accused of.
    _, unsignalled, _ = integrate(derivative(False), [0.0, resting], 0.0, hold_minutes)
    falsely_set = unsignalled[0] > unsignalled[1]
    false_rate = (math.log(2) / (hold_minutes / 60.0)) if falsely_set else 0.0

    return Outcome(
        architecture=architecture.id,
        written=written,
        write_fraction=1.0 if written else 0.0,
        retained_fraction=1.0 if retained else 0.0,
        write_minutes_to_half=_crossover(write_path),
        retention_half_life_hours=escape_hours if bistable else 0.0,
        false_write_per_hour=false_rate if bistable else 1.0,
        generations_held=hold_minutes / chassis.doubling_minutes,
        burden_units=architecture.units,
        reversible=True,
        stores_in_dna=False,
        phases=[
            _phase("write", write_path, ["set", "reset"]),
            _phase("hold", hold_path, ["set", "reset"]),
        ],
        detail={
            "bistable": bistable,
            "steady_states": [round(value, 1) for value in states],
            "barrier": round(barrier, 1),
            "burst_size": architecture.burst_size,
            "final_set": round(final[0], 1),
            "final_reset": round(final[1], 1),
            "falsely_set_by_leak": falsely_set,
        },
    )


def steady_states(
    alpha: float, leak: float, half: float, coefficient: int, clearance: float,
) -> list[float]:
    """Where the symmetric toggle can sit, found by scanning its own map.

    At a symmetric steady state each repressor's production balances its
    clearance given the other's level, so the fixed points are the roots of
    f(x) - x where f is one gene's response to the other. Scanned numerically
    for sign changes rather than solved: the Hill exponent is an integer chosen
    by the user, and a scan is honest for any of them.
    """
    if clearance <= 0:
        return []

    def response(value: float) -> float:
        return alpha * (leak + (1 - leak) * (1 - hill(value, half, coefficient))) / clearance

    ceiling = alpha / clearance
    points = 400
    roots: list[float] = []
    previous_x = 0.0
    previous_y = response(response(0.0)) - 0.0

    for index in range(1, points + 1):
        x = ceiling * index / points
        y = response(response(x)) - x
        if previous_y == 0.0:
            roots.append(previous_x)
        elif previous_y * y < 0:
            # Linear interpolation is enough; the scan is fine-grained relative
            # to the curvature at this scale.
            roots.append(previous_x + (x - previous_x) * previous_y / (previous_y - y))
        previous_x, previous_y = x, y

    # Fixed points closer together than one molecule are the same point.
    distinct: list[float] = []
    for root in roots:
        if not any(abs(root - kept) < 1.0 for kept in distinct):
            distinct.append(root)
    return distinct


def escape_time(separation: float, burst_size: float, burst_frequency: float) -> float:
    """Order-of-magnitude estimate of how long noise leaves the bit alone.

    Flipping a toggle means the losing repressor accumulating enough copies, in
    one go, to cross the barrier between the two states — waiting for it to
    happen gradually does not work, because the winning repressor suppresses it
    faster than that. Expression arrives in bursts whose sizes are roughly
    geometric with mean b, so the chance any one burst is large enough is about
    exp(-separation / b), and the waiting time follows.

    This is a first-order argument, not a computed rate. The stochastic
    simulator in the third tab measures the same quantity directly by counting
    flips, and the result says so rather than presenting this as a measurement.
    """
    if separation <= 0 or burst_size <= 0 or burst_frequency <= 0:
        return 0.0

    exponent = separation / burst_size
    if exponent > 700:  # exp() would overflow; the memory is stable for ever
        return float("inf")

    rate_per_minute = burst_frequency * math.exp(-exponent)
    if rate_per_minute <= 0:
        return float("inf")

    return math.log(2) / rate_per_minute / 60.0


# --------------------------------------------------------------------------
# Reading a trajectory
# --------------------------------------------------------------------------

def _phase(name: str, path: Sequence[tuple[float, list[float]]], labels: list[str]) -> Phase:
    sampled = thin(path)
    phase = Phase(name=name, minutes=[round(point[0], 3) for point in sampled])
    for index, label in enumerate(labels):
        phase.series[label] = [round(point[1][index], 3) for point in sampled]
    return phase


def _crossing(path: Sequence[tuple[float, list[float]]], index: int, level: float) -> float | None:
    """When a variable first reaches a level, interpolated between steps."""
    previous = path[0]
    for point in path[1:]:
        if point[1][index] >= level:
            before, after = previous[1][index], point[1][index]
            if after == before:
                return point[0]
            fraction = (level - before) / (after - before)
            return previous[0] + (point[0] - previous[0]) * fraction
        previous = point
    return None


def _crossover(path: Sequence[tuple[float, list[float]]]) -> float | None:
    """When the set repressor overtakes the reset one — the moment of writing."""
    for time, state in path:
        if state[0] > state[1]:
            return time
    return None
