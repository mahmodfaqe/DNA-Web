"""Memory-architect diagnostic codes.

This tool answers a question with two defensible answers — recombinase or
toggle — and the answer depends on things the user did not type: how long the
memory has to last, whether it must be erasable, and whether the cell divides
in between. Every code here exists to make the reasoning visible rather than
leaving a recommendation the user has to take on trust.

The refusals matter as much as the recommendations. A parts library assembled
for E. coli cannot build a circuit for yeast, and producing a plausible-looking
sequence anyway would be the most expensive kind of helpfulness.
"""

from __future__ import annotations

from ..diagnostics import Diagnostic, Report, Severity

__all__ = ["Code", "Diagnostic", "Report", "Severity"]


class Code:
    # --- refused (error) ---------------------------------------------------
    UNKNOWN_SIGNAL = "unknown_signal"
    UNKNOWN_CHASSIS = "unknown_chassis"
    CHASSIS_PARTS_UNAVAILABLE = "chassis_parts_unavailable"
    SIGNAL_NOT_IN_CHASSIS = "signal_not_in_chassis"
    NO_ARCHITECTURE_MEETS_REQUIREMENTS = "no_architecture_meets_requirements"

    # --- the recommendation (warning) --------------------------------------
    RECOMMENDATION_IS_CLOSE = "recommendation_is_close"
    REVERSIBILITY_COSTS_RETENTION = "reversibility_costs_retention"
    TOGGLE_NOT_BISTABLE = "toggle_not_bistable"
    MEMORY_LOST_TO_DILUTION = "memory_lost_to_dilution"
    LEAK_WRITES_WITHOUT_SIGNAL = "leak_writes_without_signal"
    PLASMID_SEGREGATION_LOSS = "plasmid_segregation_loss"
    WRITE_TOO_SLOW = "write_too_slow"
    INTEGRASE_BURDEN = "integrase_burden"

    # --- the sequence (warning) --------------------------------------------
    CRYPTIC_PROMOTER_IN_REGISTER = "cryptic_promoter_in_register"
    TERMINATOR_IN_REGISTER = "terminator_in_register"
    ORIENTATION_ASYMMETRY = "orientation_asymmetry"
    HOMOPOLYMER_RUN = "homopolymer_run"
    REPEATED_ATT_CORE = "repeated_att_core"
    SYNTHESIS_DIFFICULT = "synthesis_difficult"

    # --- readings worth pointing at (info) ---------------------------------
    CARGO_NOT_SUPPLIED = "cargo_not_supplied"
    NUCLEAR_LOCALISATION_REQUIRED = "nuclear_localisation_required"
    EUKARYOTIC_PARTS_UNRESOLVED = "eukaryotic_parts_unresolved"
    ORIENTATION_CHOSEN = "orientation_chosen"
    RETENTION_ESTIMATE = "retention_estimate"
    NOISE_ESTIMATE_IS_ANALYTIC = "noise_estimate_is_analytic"
    SIMULATE_THIS = "simulate_this"

    # --- standing limits of the model (info) -------------------------------
    ATT_SITES_MUST_BE_VERIFIED = "att_sites_must_be_verified"
    RECOMBINASE_CDS_PLACEHOLDER = "recombinase_cds_placeholder"
    DETERMINISTIC_MODEL = "deterministic_model"
    PARAMETERS_ILLUSTRATIVE = "parameters_illustrative"
    NOT_FOR_SYNTHESIS = "not_for_synthesis"
