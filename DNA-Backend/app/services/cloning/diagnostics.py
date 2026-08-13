"""Cloning diagnostic codes.

Same contract as every other tool: a code and structured parameters, never
prose, so the same finding reads correctly in Kurdish, Arabic or English.

The codes here divide into three kinds, and the division is the point of the
tool. An *error* means the request cannot be honoured at all. A *warning* means
the design will be built and is likely to disappoint at the bench — a site that
cuts the insert, a primer pair that will never anneal at one temperature. An
*info* names a choice the tool made on the user's behalf, so a result is
arguable rather than oracular.
"""

from __future__ import annotations

from ..diagnostics import Diagnostic, Report, Severity

__all__ = ["Code", "Diagnostic", "Report", "Severity"]


class Code:
    # --- request failures (error) -----------------------------------------
    EMPTY_SEQUENCE = "empty_sequence"
    SEQUENCE_TOO_SHORT = "sequence_too_short"
    UNKNOWN_ENZYME = "unknown_enzyme"
    TARGET_OUT_OF_RANGE = "target_out_of_range"
    TARGET_TOO_SHORT = "target_too_short"
    NO_PRIMER_FOUND = "no_primer_found"

    # --- cloning warnings (warning) ---------------------------------------
    # The one that ruins a cloning week: the site added to a primer tail also
    # occurs inside the fragment being cloned, so the digest cuts the insert.
    TAIL_SITE_CUTS_AMPLICON = "tail_site_cuts_amplicon"
    TAIL_SITES_INCOMPATIBLE = "tail_sites_incompatible"
    NO_UNIQUE_CUTTER = "no_unique_cutter"
    FRAGMENTS_UNRESOLVABLE = "fragments_unresolvable"
    PRIMER_TM_MISMATCH = "primer_tm_mismatch"
    PRIMER_SELF_DIMER = "primer_self_dimer"
    PRIMER_PAIR_DIMER = "primer_pair_dimer"
    PRIMER_HAIRPIN = "primer_hairpin"
    PRIMER_NOT_UNIQUE = "primer_not_unique"
    PRIMER_GC_OUT_OF_RANGE = "primer_gc_out_of_range"
    PRIMER_RUN_OF_BASES = "primer_run_of_bases"
    AMBIGUOUS_BASES_IN_TEMPLATE = "ambiguous_bases_in_template"

    # --- choices the tool made (info) --------------------------------------
    PANEL_SELECTED = "panel_selected"
    TAIL_TM_EXCLUDES_TAIL = "tail_tm_excludes_tail"
    METHYLATION_UNCHECKED = "methylation_unchecked"
    STAR_ACTIVITY_UNCHECKED = "star_activity_unchecked"
    NOT_A_SPECIFICITY_CHECK = "not_a_specificity_check"
