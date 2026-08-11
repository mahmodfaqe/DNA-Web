"""Specification to DNA.

Two stages, kept separate on purpose:

  synthesise()  spec -> gate netlist        (what the circuit does)
  assemble()    netlist -> transcriptional units -> sequence   (how it is built)

The netlist is the artefact worth showing a student: it is the point where
"if temperature and lactose" becomes "AND gate", and where the compiler has to
admit that some clauses have no clean biological realisation.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

from . import parts as lib
from .diagnostics import Code, Report
from .parser import Specification

LINE_WIDTH = 70


@dataclass
class Gate:
    id: str
    type: str                      # SENSOR | AND | OR | NOT | OUTPUT | TERMINAL
    label: str
    inputs: list[str] = field(default_factory=list)
    part_id: str | None = None

    def as_dict(self) -> dict[str, Any]:
        return {
            "id": self.id, "type": self.type, "label": self.label,
            "inputs": self.inputs, "part_id": self.part_id,
        }


@dataclass
class Annotation:
    part_id: str
    name: str
    role: str
    provenance: str
    start: int
    end: int

    def as_dict(self) -> dict[str, Any]:
        return {
            "part_id": self.part_id, "name": self.name, "role": self.role,
            "provenance": self.provenance, "start": self.start, "end": self.end,
        }


@dataclass
class TranscriptionalUnit:
    name: str
    purpose: str
    sequence: str
    annotations: list[Annotation]

    @property
    def length(self) -> int:
        return len(self.sequence)

    @property
    def known_fraction(self) -> float:
        if not self.sequence:
            return 0.0
        return round((self.length - self.sequence.count("N")) / self.length * 100, 1)

    def as_dict(self) -> dict[str, Any]:
        return {
            "name": self.name,
            "purpose": self.purpose,
            "length": self.length,
            "known_fraction": self.known_fraction,
            "sequence": self.sequence,
            "annotations": [a.as_dict() for a in self.annotations],
        }


# --------------------------------------------------------------------------
# Stage 1 - logic synthesis
# --------------------------------------------------------------------------

def synthesise(spec: Specification, report: Report) -> list[Gate]:
    gates: list[Gate] = []

    sensor_ids: list[str] = []
    for index, condition in enumerate(spec.conditions):
        promoter = lib.PROMOTERS.get(condition.sensor)
        if promoter is None:
            report.error(Code.UNKNOWN_SENSOR, span=condition.span, sensor=condition.sensor)
            continue

        gate_id = f"s{index + 1}"
        gates.append(Gate(gate_id, "SENSOR", condition.sensor, part_id=promoter.id))
        sensor_ids.append(gate_id)

        if condition.sensor == "temperature" and condition.value is not None:
            # A thermosensitive repressor switches at the temperature its
            # mutations give it. The compiler cannot dial in an arbitrary number.
            report.warn(
                Code.TEMPERATURE_THRESHOLD_FIXED,
                span=condition.span,
                requested=condition.value,
                actual="32-37",
                part=promoter.id,
            )

        if condition.negated:
            inverter_id = f"n{index + 1}"
            gates.append(Gate(inverter_id, "NOT", condition.sensor, [gate_id], "BBa_C0040"))
            sensor_ids[-1] = inverter_id
            report.warn(Code.INVERTER_ADDS_DELAY, span=condition.span, sensor=condition.sensor)

    if not sensor_ids:
        return gates

    if len(sensor_ids) == 1:
        driver = sensor_ids[0]
    else:
        logic_type = "AND" if spec.connective == "and" else "OR"
        driver = "g1"
        gates.append(Gate(driver, logic_type, logic_type, list(sensor_ids)))

        if logic_type == "AND":
            report.warn(Code.HYBRID_PROMOTER_UNCHARACTERISED, inputs=len(sensor_ids))

    for index, output in enumerate(spec.outputs):
        cds = lib.ACTUATOR_CDS.get(output.actuator)
        if cds is None:
            report.error(Code.UNKNOWN_ACTUATOR, actuator=output.actuator)
            continue
        gates.append(Gate(f"o{index + 1}", "OUTPUT", output.actuator, [driver], cds.id))

    if spec.terminal == "self_destruct":
        gates.append(Gate("t1", "TERMINAL", "self_destruct", [driver], lib.KILL_SWITCH.id))

    return gates


# --------------------------------------------------------------------------
# Stage 2 - physical assembly
# --------------------------------------------------------------------------

def _build_unit(name: str, purpose: str, chain: list[lib.Part]) -> TranscriptionalUnit:
    """Join parts with assembly scars, tracking coordinates as we go."""
    pieces: list[str] = []
    annotations: list[Annotation] = []
    cursor = 0

    def add(part: lib.Part) -> None:
        nonlocal cursor
        sequence = part.resolved()
        if not sequence:
            return
        pieces.append(sequence)
        annotations.append(Annotation(
            part.id, part.name, part.role, part.provenance,
            cursor + 1, cursor + len(sequence),
        ))
        cursor += len(sequence)

    add(lib.PREFIX)
    for position, part in enumerate(chain):
        if position:
            add(lib.SCAR)
        add(part)
    add(lib.SUFFIX)

    return TranscriptionalUnit(name, purpose, "".join(pieces), annotations)


def _duration_tag(seconds: int | None) -> lib.Part | None:
    if seconds is None:
        return None
    if seconds <= 1800:
        return lib.DEGRADATION_TAGS["fast"]
    if seconds <= 21600:
        return lib.DEGRADATION_TAGS["medium"]
    return lib.DEGRADATION_TAGS["slow"]


def assemble(spec: Specification, gates: list[Gate], report: Report) -> list[TranscriptionalUnit]:
    units: list[TranscriptionalUnit] = []

    active = [c for c in spec.conditions if c.sensor in lib.PROMOTERS]
    if not active:
        return units

    # --- driver promoter -------------------------------------------------
    positive = [c for c in active if not c.negated]
    negative = [c for c in active if c.negated]

    for condition in negative:
        # NOT gate: the sensor drives a repressor, which shuts off the promoter
        # that carries the output.
        sensor_promoter = lib.PROMOTERS[condition.sensor]
        repressor_promoter, repressor_cds = lib.INVERTING_PROMOTERS["tetr"]
        units.append(_build_unit(
            f"inverter_{condition.sensor}",
            "NOT",
            [sensor_promoter, lib.RBS_STRONG, repressor_cds, lib.TERMINATOR],
        ))
        driver = repressor_promoter
        break
    else:
        driver = None

    promoters = [lib.PROMOTERS[c.sensor] for c in positive]

    if driver is None:
        if len(promoters) == 1:
            driver = promoters[0]
        elif spec.connective == "and" and len(promoters) >= 2:
            driver = lib.hybrid_promoter(promoters[0], promoters[1])
            for extra in promoters[2:]:
                driver = lib.hybrid_promoter(driver, extra)
        else:
            driver = promoters[0]  # OR is built from parallel units below

    # --- output units ----------------------------------------------------
    for output in spec.outputs:
        cds = lib.ACTUATOR_CDS.get(output.actuator)
        if cds is None:
            continue

        tag = _duration_tag(output.duration_seconds)
        chain: list[lib.Part] = [driver, lib.RBS_STRONG, cds]
        if tag:
            chain.append(tag)
        chain.append(lib.TERMINATOR_DOUBLE)

        units.append(_build_unit(f"output_{output.actuator}", "OUTPUT", chain))

        # OR: one transcriptional unit per input promoter, all making the same
        # protein. Simpler and far more predictable than a hybrid promoter.
        if spec.connective == "or" and len(promoters) > 1 and not negative:
            for extra in promoters[1:]:
                units.append(_build_unit(
                    f"output_{output.actuator}_{extra.id}",
                    "OUTPUT",
                    [extra, lib.RBS_STRONG, cds, lib.TERMINATOR_DOUBLE],
                ))

        if output.duration_seconds and output.duration_seconds > 3600:
            report.warn(
                Code.DURATION_NOT_ENCODABLE,
                requested_seconds=output.duration_seconds,
                requested_hours=round(output.duration_seconds / 3600, 1),
                tag=tag.id if tag else None,
            )

    # --- terminal action -------------------------------------------------
    if spec.terminal == "self_destruct":
        units.append(_build_unit(
            "biocontainment",
            "TERMINAL",
            [driver, lib.RBS_MEDIUM, lib.KILL_SWITCH, lib.TERMINATOR_DOUBLE],
        ))
        report.warn(Code.KILL_SWITCH_PLACEHOLDER, part=lib.KILL_SWITCH.id)

    if len(units) >= 3:
        report.warn(Code.METABOLIC_BURDEN, units=len(units))

    return units


# --------------------------------------------------------------------------
# Output formatting
# --------------------------------------------------------------------------

def _wrap(sequence: str) -> str:
    return "\n".join(sequence[i:i + LINE_WIDTH] for i in range(0, len(sequence), LINE_WIDTH))


def to_fasta(spec: Specification, units: list[TranscriptionalUnit]) -> str:
    """FASTA with the design intent in the header of every record.

    Anyone who opens the file six months later can see which sentence produced
    it and which regions still need a coding sequence, without the web page.
    """
    lines = [
        f"; BioCompiler design - source: {spec.source}",
        f"; logic: {spec.boolean_expression()}",
        "; N runs are coding sequences referenced by registry ID, not yet resolved.",
        "; Verify every part against parts.igem.org before ordering synthesis.",
    ]

    for unit in units:
        composition = " + ".join(
            a.part_id for a in unit.annotations if a.role not in ("scar",)
        )
        lines.append(
            f">{unit.name} purpose={unit.purpose} length={unit.length}bp "
            f"resolved={unit.known_fraction}% parts=[{composition}]"
        )
        lines.append(_wrap(unit.sequence))

    return "\n".join(lines) + "\n"


def compile_specification(spec: Specification, report: Report) -> dict[str, Any]:
    gates = synthesise(spec, report)
    units = assemble(spec, gates, report) if not report.failed else []

    total_length = sum(unit.length for unit in units)
    unresolved = sum(unit.sequence.count("N") for unit in units)

    used_parts: dict[str, dict[str, Any]] = {}
    for unit in units:
        for annotation in unit.annotations:
            used_parts.setdefault(annotation.part_id, {
                "id": annotation.part_id,
                "name": annotation.name,
                "role": annotation.role,
                "provenance": annotation.provenance,
                "length": annotation.end - annotation.start + 1,
                "registry_url": (
                    lib.REGISTRY_URL + annotation.part_id
                    if annotation.part_id.startswith("BBa_") else None
                ),
            })

    if units:
        report.info(Code.SEQUENCE_PROVENANCE)
        report.info(Code.NOT_FOR_SYNTHESIS)
        if unresolved:
            report.info(
                Code.CDS_PLACEHOLDER,
                bases=unresolved,
                percent=round(unresolved / total_length * 100, 1) if total_length else 0,
            )

    return {
        "specification": spec.as_dict(),
        "expression": spec.boolean_expression(),
        "gates": [gate.as_dict() for gate in gates],
        "units": [unit.as_dict() for unit in units],
        "parts": sorted(used_parts.values(), key=lambda p: p["role"]),
        "totals": {
            "units": len(units),
            "length": total_length,
            "unresolved_bases": unresolved,
            "resolved_percent": (
                round((total_length - unresolved) / total_length * 100, 1) if total_length else 0.0
            ),
        },
        "fasta": to_fasta(spec, units) if units else "",
    }
