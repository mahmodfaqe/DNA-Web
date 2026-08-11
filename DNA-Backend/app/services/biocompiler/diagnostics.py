"""Compiler diagnostics.

A compiler's most useful output is often not the artefact but the diagnostics:
"this clause maps cleanly onto biology, this one does not, and here is why."
That is the difference between a tool that produces a sequence and a tool that
teaches what the sequence can and cannot do.

Like the analysis errors, diagnostics carry a code and parameters and never
prose, so the same compile result reads correctly in Kurdish, Arabic or English.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any, Literal

Severity = Literal["error", "warning", "info"]


class Code:
    # --- parse failures (error) -------------------------------------------
    EMPTY_INPUT = "empty_input"
    NO_CONDITION = "no_condition_found"
    NO_OUTPUT = "no_output_found"
    UNKNOWN_SENSOR = "unknown_sensor"
    UNKNOWN_ACTUATOR = "unknown_actuator"
    MISSING_THRESHOLD = "missing_threshold"
    TOO_MANY_CONDITIONS = "too_many_conditions"

    # --- design warnings (warning) ----------------------------------------
    MIXED_CONNECTIVES = "mixed_connectives"
    HYBRID_PROMOTER_UNCHARACTERISED = "hybrid_promoter_uncharacterised"
    DURATION_NOT_ENCODABLE = "duration_not_encodable"
    TEMPERATURE_THRESHOLD_FIXED = "temperature_threshold_fixed"
    KILL_SWITCH_PLACEHOLDER = "kill_switch_placeholder"
    INVERTER_ADDS_DELAY = "inverter_adds_delay"
    METABOLIC_BURDEN = "metabolic_burden"

    # --- provenance (info) -------------------------------------------------
    CDS_PLACEHOLDER = "cds_placeholder"
    SEQUENCE_PROVENANCE = "sequence_provenance"
    LANGUAGE_DETECTED = "language_detected"
    NOT_FOR_SYNTHESIS = "not_for_synthesis"


@dataclass
class Diagnostic:
    code: str
    severity: Severity
    params: dict[str, Any] = field(default_factory=dict)
    span: str | None = None  # the phrase that triggered it, for highlighting

    def as_dict(self) -> dict[str, Any]:
        return {
            "code": self.code,
            "severity": self.severity,
            "params": self.params,
            "span": self.span,
        }


class Report:
    """Collects diagnostics during a compile pass."""

    def __init__(self) -> None:
        self._items: list[Diagnostic] = []

    def error(self, code: str, span: str | None = None, **params: Any) -> None:
        self._items.append(Diagnostic(code, "error", params, span))

    def warn(self, code: str, span: str | None = None, **params: Any) -> None:
        self._items.append(Diagnostic(code, "warning", params, span))

    def info(self, code: str, span: str | None = None, **params: Any) -> None:
        self._items.append(Diagnostic(code, "info", params, span))

    @property
    def failed(self) -> bool:
        return any(item.severity == "error" for item in self._items)

    def counts(self) -> dict[str, int]:
        return {
            severity: sum(1 for item in self._items if item.severity == severity)
            for severity in ("error", "warning", "info")
        }

    def as_list(self) -> list[dict[str, Any]]:
        order = {"error": 0, "warning": 1, "info": 2}
        return [item.as_dict() for item in sorted(self._items, key=lambda d: order[d.severity])]
