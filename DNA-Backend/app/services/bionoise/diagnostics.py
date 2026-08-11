"""Simulator diagnostic codes.

A stochastic simulation is very good at producing numbers that look
authoritative and mean nothing: a coefficient of variation computed from eight
cells, a steady-state average taken while the protein was still climbing, a
correlation read off a run that stopped early. Every one of those has a code
here, so the result page can say which numbers to trust.

The last three are not about this run at all. They are the standing limits of
the model, and they are attached to every result on purpose — a simulator that
only mentions its assumptions in the documentation is a simulator whose
assumptions nobody reads.
"""

from __future__ import annotations

from ..diagnostics import Diagnostic, Report, Severity

__all__ = ["Code", "Diagnostic", "Report", "Severity"]


class Code:
    # --- refused (error) ---------------------------------------------------
    UNKNOWN_PRESET = "unknown_preset"

    # --- the run itself (warning) ------------------------------------------
    CELLS_CLAMPED = "cells_clamped"
    DURATION_CLAMPED = "duration_clamped"
    RUN_TRUNCATED = "run_truncated"
    NOT_AT_STEADY_STATE = "not_at_steady_state"
    IMPRECISE = "imprecise"

    # --- what the numbers show (warning) -----------------------------------
    LOW_COPY_NUMBER = "low_copy_number"
    CROSSTALK_DOMINATES = "crosstalk_dominates"
    LEAK_DOMINATES = "leak_dominates"
    RESOURCES_LIMITING = "resources_limiting"
    NO_SWITCHING_OBSERVED = "no_switching_observed"

    # --- readings worth pointing at (info) ---------------------------------
    SWITCHING_OBSERVED = "switching_observed"
    NOISE_EXCEEDS_THEORY = "noise_exceeds_theory"
    CONTROL_ENSEMBLE = "control_ensemble"
    PRECISION = "precision"
    SEED_RECORDED = "seed_recorded"

    # --- standing limits of the model (info) -------------------------------
    WELL_MIXED_ASSUMPTION = "well_mixed_assumption"
    PARAMETERS_ILLUSTRATIVE = "parameters_illustrative"
    NO_CELL_DIVISION = "no_cell_division"
