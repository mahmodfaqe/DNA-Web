"""Compiler diagnostic codes.

The collection machinery (``Diagnostic``, ``Report``, severity ordering) is
shared with the simulator and lives in ``app.services.diagnostics``. Only the
code table below is specific to the compiler, and it is re-exported here so the
rest of the package keeps importing from one place.
"""

from __future__ import annotations

from ..diagnostics import Diagnostic, Report, Severity

__all__ = ["Code", "Diagnostic", "Report", "Severity"]


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
