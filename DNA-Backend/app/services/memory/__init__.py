"""DeepBio-Memory Architect: choosing where to put a bit, and building it.

    design({"signal": "lactose", "chassis": "ecoli", "hold_hours": 24,
            "signal_minutes": 60, "must_be_reversible": False})

Models both ways of storing a bit in a living cell, compares them on the terms
the user actually cares about — does it survive the signal going away, does it
survive division, can it be erased, what does it cost — recommends one, and
emits the DNA.

The recommendation is the point, and so is the reasoning behind it. Every
comparison the engine makes is returned alongside the verdict, because "use a
recombinase" is worth nothing to someone who cannot see that it was chosen for
retention across twelve divisions and chosen *despite* a leak that will falsely
write four percent of the population in the same window.
"""

from __future__ import annotations

import math
import time
from typing import Any

from ..diagnostics import Report
from . import construct, library, ode, sequence
from .diagnostics import Code

__all__ = ["design", "Code", "SIGNALS", "CHASSIS", "RECOMBINASES"]

SIGNALS = tuple(library.SIGNALS)
CHASSIS = tuple(library.CHASSIS)
RECOMBINASES = tuple(library.RECOMBINASES)

MAX_PAYLOAD_BP = 20_000
MIN_HOLD_HOURS, MAX_HOLD_HOURS = 0.5, 168.0
MIN_SIGNAL_MINUTES, MAX_SIGNAL_MINUTES = 1.0, 720.0

# A leak that writes this share of an uninduced population over the requested
# holding window stops being a nuisance and becomes the dominant failure.
FALSE_WRITE_ALARM = 0.05
# Two architectures whose scores sit within this of each other are not
# meaningfully different, and saying otherwise would be false precision.
CLOSE_CALL = 0.12
BURDEN_PER_UNIT = 0.04


def _clamp(value: float, low: float, high: float) -> float:
    return low if value < low else (high if value > high else value)


def _read_request(payload: dict[str, Any], report: Report) -> library.Requirements | None:
    signal = str(payload.get("signal") or "lactose")
    if signal not in library.SIGNALS:
        report.error(Code.UNKNOWN_SIGNAL, signal=signal, available=", ".join(library.SIGNALS))
        return None

    chassis = str(payload.get("chassis") or "ecoli")
    if chassis not in library.CHASSIS:
        report.error(Code.UNKNOWN_CHASSIS, chassis=chassis, available=", ".join(library.CHASSIS))
        return None

    host = library.CHASSIS[chassis]
    if not host.parts_available:
        # Each domain is served from its own kit — bacterial parts for a
        # bacterium, polymerase II parts for a nucleus. A host with no kit at
        # all is refused rather than dressed in another domain's parts, because
        # a sequence that looks buildable and cannot work is worse than none.
        report.error(Code.CHASSIS_PARTS_UNAVAILABLE, chassis=chassis)
        return None

    sensor = library.SIGNALS[signal]
    if chassis not in sensor.chassis:
        report.error(Code.SIGNAL_NOT_IN_CHASSIS, signal=signal, chassis=chassis,
                     available=", ".join(sensor.chassis))
        return None

    recombinase = str(payload.get("recombinase") or "bxb1")
    if recombinase not in library.RECOMBINASES:
        recombinase = "bxb1"

    return library.Requirements(
        signal=signal,
        chassis=chassis,
        hold_hours=_clamp(float(payload.get("hold_hours") or 24), MIN_HOLD_HOURS, MAX_HOLD_HOURS),
        signal_minutes=_clamp(
            float(payload.get("signal_minutes") or 60), MIN_SIGNAL_MINUTES, MAX_SIGNAL_MINUTES
        ),
        must_be_reversible=bool(payload.get("must_be_reversible", False)),
        on_plasmid=bool(payload.get("on_plasmid", True)),
        recombinase=recombinase,
        extras={"strength": _clamp(float(payload.get("strength", 0.7)), 0.1, 1.0)},
    )


def eukaryote_nls() -> str:
    """The peptide the integrase has to carry, named in the diagnostic itself."""
    from .eukaryote import NLS_PEPTIDE

    return NLS_PEPTIDE


def _score(outcome: ode.Outcome, need: library.Requirements, hold_minutes: float) -> dict[str, Any]:
    """Turn a modelled outcome into one comparable number, and show the working.

    Four things are being traded, and they are not in the same units, so each is
    mapped onto 0..1 before being weighted. Retention carries the most weight
    because it is the property the user came for: a memory that does not
    remember has failed at its only job, however cheap or fast it is.
    """
    # Retention: did the bit survive the holding window, and is it still there
    # by a margin rather than by luck?
    if outcome.retention_half_life_hours is None:
        retention = 1.0                       # DNA in the genome; nothing erases it
    elif outcome.retention_half_life_hours <= 0:
        retention = 0.0
    else:
        retention = _clamp(outcome.retention_half_life_hours / max(need.hold_hours, 0.01), 0.0, 1.0)

    retention *= outcome.retained_fraction if outcome.stores_in_dna else (
        1.0 if outcome.retained_fraction >= 0.5 else 0.0
    )

    # Fidelity: how much of an uninduced population writes itself by accident
    # over the same window.
    false_share = 1.0 - math.exp(-outcome.false_write_per_hour * need.hold_hours)
    fidelity = _clamp(1.0 - false_share, 0.0, 1.0)

    # Speed: did it finish writing inside the time the signal was present?
    if outcome.write_minutes_to_half is None:
        speed = 0.0
    else:
        speed = _clamp(1.0 - outcome.write_minutes_to_half / max(need.signal_minutes, 1.0), 0.0, 1.0)

    cost = _clamp(1.0 - outcome.burden_units * BURDEN_PER_UNIT, 0.0, 1.0)

    total = 0.45 * retention + 0.30 * fidelity + 0.15 * speed + 0.10 * cost

    # A memory that has to be erasable and cannot be is not a lower-scoring
    # option, it is the wrong answer. Same for one that never wrote at all.
    disqualified = (need.must_be_reversible and not outcome.reversible) or not outcome.written

    return {
        "architecture": outcome.architecture,
        "retention": round(retention, 4),
        "fidelity": round(fidelity, 4),
        "speed": round(speed, 4),
        "cost": round(cost, 4),
        "total": round(0.0 if disqualified else total, 4),
        "false_write_share": round(false_share, 4),
        "disqualified": disqualified,
        "disqualified_reason": (
            "not_reversible" if (need.must_be_reversible and not outcome.reversible)
            else ("never_written" if not outcome.written else None)
        ),
    }


def _interpret(
    need: library.Requirements,
    outcomes: dict[str, ode.Outcome],
    scores: list[dict[str, Any]],
    winner: str,
    orientation: dict[str, Any],
    difficulty: dict[str, Any],
    report: Report,
) -> None:
    host = library.CHASSIS[need.chassis]
    best = outcomes[winner]
    ranked = sorted(scores, key=lambda item: item["total"], reverse=True)

    if len(ranked) > 1 and not ranked[1]["disqualified"]:
        gap = ranked[0]["total"] - ranked[1]["total"]
        if gap < CLOSE_CALL:
            report.warn(Code.RECOMMENDATION_IS_CLOSE, first=ranked[0]["architecture"],
                        second=ranked[1]["architecture"], gap=round(gap, 3))

    for entry in scores:
        outcome = outcomes[entry["architecture"]]

        if entry["false_write_share"] > FALSE_WRITE_ALARM and outcome.stores_in_dna:
            report.warn(Code.LEAK_WRITES_WITHOUT_SIGNAL, span=entry["architecture"],
                        architecture=entry["architecture"],
                        percent=round(entry["false_write_share"] * 100, 1),
                        hours=round(need.hold_hours, 1),
                        leak=round(library.SIGNALS[need.signal].leak * 100, 1))

        if not outcome.stores_in_dna and not outcome.detail.get("bistable", True):
            report.warn(Code.TOGGLE_NOT_BISTABLE, span=entry["architecture"],
                        states=len(outcome.detail.get("steady_states", [])))

        if outcome.written and outcome.write_minutes_to_half and \
                outcome.write_minutes_to_half > need.signal_minutes:
            report.warn(Code.WRITE_TOO_SLOW, span=entry["architecture"],
                        architecture=entry["architecture"],
                        needed=round(outcome.write_minutes_to_half),
                        available=round(need.signal_minutes))

    if not best.stores_in_dna:
        generations = need.hold_hours * 60 / host.doubling_minutes
        report.warn(Code.MEMORY_LOST_TO_DILUTION, generations=round(generations, 1),
                    doubling=round(host.doubling_minutes))

    if need.on_plasmid and best.stores_in_dna:
        loss = best.detail.get("plasmid_loss_per_hour", 0.0)
        if loss > 0:
            share = 1.0 - math.exp(-loss * need.hold_hours)
            report.warn(Code.PLASMID_SEGREGATION_LOSS,
                        percent=round(share * 100, 2),
                        copies=host.plasmid_copy_number,
                        hours=round(need.hold_hours, 1))

    if need.must_be_reversible and best.stores_in_dna:
        report.warn(Code.REVERSIBILITY_COSTS_RETENTION)

    if best.burden_units >= 3:
        report.warn(Code.INTEGRASE_BURDEN, units=best.burden_units)

    # --- the sequence ----------------------------------------------------
    forward = orientation["forward"]
    reverse = orientation["reverse"]
    outward = max(forward["counts"]["promoters_outward"], reverse["counts"]["promoters_outward"])

    if outward:
        report.warn(Code.CRYPTIC_PROMOTER_IN_REGISTER, count=outward,
                    score=round(max(forward["strongest_outward"], reverse["strongest_outward"]) * 100))

    if forward["counts"]["terminators"] or reverse["counts"]["terminators"]:
        report.warn(Code.TERMINATOR_IN_REGISTER,
                    count=max(forward["counts"]["terminators"], reverse["counts"]["terminators"]))

    if orientation["decided_by_sequence"]:
        report.info(Code.ORIENTATION_CHOSEN, orientation=orientation["preferred"],
                    difference=orientation["difference"])
    else:
        report.warn(Code.ORIENTATION_ASYMMETRY, difference=orientation["difference"])

    for run in forward["homopolymers"][:1]:
        report.warn(Code.HOMOPOLYMER_RUN, run=run["detail"], position=run["start"])

    if difficulty["difficult"]:
        report.warn(Code.SYNTHESIS_DIFFICULT, reasons=", ".join(difficulty["reasons"]),
                    gc=difficulty["gc_percent"], longest=difficulty["longest_homopolymer"])

    # --- what the numbers mean -------------------------------------------
    if best.retention_half_life_hours is None:
        report.info(Code.RETENTION_ESTIMATE, architecture=winner, verdict="dna")
    else:
        report.info(Code.RETENTION_ESTIMATE, architecture=winner,
                    hours=round(best.retention_half_life_hours, 1),
                    generations=round(
                        best.retention_half_life_hours * 60 / host.doubling_minutes, 1
                    ), verdict="estimated")

    if not best.stores_in_dna:
        report.info(Code.NOISE_ESTIMATE_IS_ANALYTIC, barrier=best.detail.get("barrier", 0),
                    burst=best.detail.get("burst_size", 0))
        report.info(Code.SIMULATE_THIS)

    report.info(Code.ATT_SITES_MUST_BE_VERIFIED, recombinase=need.recombinase,
                core=library.RECOMBINASES[need.recombinase].core)
    report.info(Code.RECOMBINASE_CDS_PLACEHOLDER,
                length=library.RECOMBINASES[need.recombinase].cds_length)
    report.info(Code.DETERMINISTIC_MODEL)
    report.info(Code.PARAMETERS_ILLUSTRATIVE)
    report.info(Code.NOT_FOR_SYNTHESIS)


def design(payload: dict[str, Any]) -> dict[str, Any]:
    started = time.perf_counter()
    report = Report()
    need = _read_request(payload, report)

    if need is None:
        return {
            "ok": False,
            "request": payload,
            "diagnostics": report.as_list(),
            "diagnostic_counts": report.counts(),
        }

    host = library.CHASSIS[need.chassis]
    signal = library.SIGNALS[need.signal]
    enzyme = library.RECOMBINASES[need.recombinase]
    strength = float(need.extras["strength"])
    hold_minutes = need.hold_hours * 60.0

    # --- model every architecture, not only the one that wins -------------
    candidates = ["recombinase", "toggle"]
    if need.must_be_reversible:
        candidates = ["recombinase_reversible", "toggle"]

    outcomes: dict[str, ode.Outcome] = {}
    for name in candidates:
        architecture = library.ARCHITECTURES[name]
        if architecture.stores_in_dna:
            outcomes[name] = ode.recombinase_outcome(
                architecture, signal, host, enzyme,
                need.signal_minutes, hold_minutes, need.on_plasmid, strength,
            )
        else:
            outcomes[name] = ode.toggle_outcome(
                architecture, signal, host,
                need.signal_minutes, hold_minutes, need.on_plasmid, strength,
            )

    scores = [_score(outcomes[name], need, hold_minutes) for name in candidates]
    ranked = sorted(scores, key=lambda item: item["total"], reverse=True)

    if ranked[0]["total"] <= 0:
        report.error(Code.NO_ARCHITECTURE_MEETS_REQUIREMENTS,
                     reason=ranked[0]["disqualified_reason"] or "unwritten")
        return {
            "ok": False,
            "request": need.as_dict(),
            "comparison": scores,
            "outcomes": {name: outcome.as_dict() for name, outcome in outcomes.items()},
            "diagnostics": report.as_list(),
            "diagnostic_counts": report.counts(),
        }

    winner = ranked[0]["architecture"]

    # --- the sequence -----------------------------------------------------
    kit = construct.KITS[host.domain]

    supplied = sequence.clean(str(payload.get("payload") or ""))
    cargo = supplied or kit.default_payload
    if len(cargo) > MAX_PAYLOAD_BP:
        cargo = cargo[:MAX_PAYLOAD_BP]

    if cargo:
        orientation = sequence.compare_orientations(cargo, host.sigma70)
        chosen = orientation["preferred"] if orientation["decided_by_sequence"] else "forward"
    else:
        # A eukaryotic cargo is a polymerase II promoter — hundreds of bases of
        # native sequence this tool will not invent. Without those bases there
        # is nothing to scan, and claiming an orientation would be inventing the
        # one answer the reader came for.
        cargo = "N" * kit.default_payload_bp
        orientation = sequence.compare_orientations(cargo, host.sigma70)
        chosen = "forward"
        report.info(Code.CARGO_NOT_SUPPLIED, bases=kit.default_payload_bp)

    architecture = library.ARCHITECTURES[winner]
    if architecture.stores_in_dna:
        constructs = construct.build_recombinase(architecture, signal, enzyme, cargo, chosen, kit)
    else:
        constructs = construct.build_toggle(signal, cargo, kit)

    if host.domain != "bacteria":
        report.info(Code.NUCLEAR_LOCALISATION_REQUIRED, signal=eukaryote_nls())
        report.info(Code.EUKARYOTIC_PARTS_UNRESOLVED, chassis=host.id)

    difficulty = sequence.synthesis_difficulty("".join(item.sequence for item in constructs))

    _interpret(need, outcomes, scores, winner, orientation, difficulty, report)

    notes = [
        "DeepBio-Memory Architect design",
        f"signal={need.signal} chassis={need.chassis} architecture={winner}",
        f"register orientation={chosen}",
        "N runs are coding sequences referenced by ID, not yet resolved.",
        "Verify every part, and every att site, before ordering synthesis.",
    ]

    return {
        "ok": True,
        "request": need.as_dict(),
        "recommendation": {
            "architecture": winner,
            "score": ranked[0]["total"],
            "runner_up": ranked[1]["architecture"] if len(ranked) > 1 else None,
            "gap": round(ranked[0]["total"] - ranked[1]["total"], 4) if len(ranked) > 1 else None,
            "orientation": chosen,
        },
        "comparison": scores,
        "outcomes": {name: outcome.as_dict() for name, outcome in outcomes.items()},
        "orientation": orientation,
        "composition": {
            "payload_length": len(cargo),
            "gc_ramp": sequence.gc_ramp(cargo),
            "entropy": round(sequence.entropy(cargo), 4),
            "is_default_payload": cargo == construct.DEFAULT_PAYLOAD,
        },
        "constructs": [item.as_dict() for item in constructs],
        "parts": construct.parts_manifest(constructs),
        "totals": {
            "constructs": len(constructs),
            "length": sum(item.length for item in constructs),
            "unresolved_bases": sum(item.sequence.count("N") for item in constructs),
            "resolved_percent": round(
                sum(item.length - item.sequence.count("N") for item in constructs)
                / max(1, sum(item.length for item in constructs)) * 100, 1
            ),
        },
        "synthesis": difficulty,
        "fasta": construct.to_fasta(constructs, notes),
        "performance": {"wall_ms": round((time.perf_counter() - started) * 1000, 1)},
        "diagnostics": report.as_list(),
        "diagnostic_counts": report.counts(),
    }
