"""Restriction analysis and primer design.

The restriction half is checked against enzymes whose sites and cut offsets are
published on every supplier's datasheet, so a failure here means this module has
misread REBASE rather than that biology has changed.

The primer half is checked against properties rather than against remembered
output: a primer pair is correct if the sequences it returns actually flank the
region it says they flank, whatever scoring produced them. Scoring is then
checked separately, by constructing a template where the right answer is forced.
"""

from __future__ import annotations

import random

import pytest
from app.services import cloning
from app.services.cloning import primers as P
from app.services.cloning import restriction as R
from app.services.cloning.diagnostics import Code, Report


def codes(result: dict) -> set[str]:
    return {item["code"] for item in result["diagnostics"]}


def random_dna(length: int, seed: int) -> str:
    rng = random.Random(seed)
    return "".join(rng.choice("ATCG") for _ in range(length))


# --------------------------------------------------------------------------
# Restriction: sites and coordinates
# --------------------------------------------------------------------------

def test_a_single_site_is_found_at_the_right_coordinate():
    # GAATTC begins at base 5 (1-based).
    sequence = "TTTT" + "GAATTC" + "AAAA"
    result = R.digest(sequence, Report(), enzymes=["EcoRI"])

    (enzyme,) = result["enzymes"]
    assert enzyme["enzyme"] == "EcoRI"
    assert enzyme["cut_count"] == 1
    assert enzyme["sites"][0]["site_start"] == 5


@pytest.mark.parametrize(
    ("enzyme", "site", "site_start"),
    [
        ("EcoRI", "GAATTC", 5),     # G^AATTC   — cuts one base in
        ("KpnI", "GGTACC", 5),      # GGTAC^C   — cuts five bases in
        ("SmaI", "CCCGGG", 5),      # CCC^GGG   — cuts in the middle, blunt
        ("NotI", "GCGGCCGC", 5),    # eight-cutter
        ("BsaI", "GGTCTC", 5),      # type IIS — cuts outside its own site
    ],
)
def test_site_start_is_reported_independently_of_where_the_enzyme_cuts(enzyme, site, site_start):
    """The recognition site and the cut are different coordinates for every
    enzyme that does not cut in the middle. Conflating them is a one-base error
    in a construct that nothing downstream will catch."""
    sequence = "TTTT" + site + "A" * 20
    result = R.digest(sequence, Report(), enzymes=[enzyme])

    assert result["enzymes"][0]["sites"][0]["site_start"] == site_start


def test_a_type_iis_enzyme_is_marked_as_cutting_outside_its_site():
    result = R.digest("TTTT" + "GGTCTC" + "A" * 20, Report(), enzymes=["BsaI"])

    assert result["enzymes"][0]["cuts_outside_site"] is True


def test_overhangs_are_reported_by_kind():
    kinds = {}
    for name in ("EcoRI", "KpnI", "SmaI"):
        site = {"EcoRI": "GAATTC", "KpnI": "GGTACC", "SmaI": "CCCGGG"}[name]
        result = R.digest("TTTT" + site + "A" * 20, Report(), enzymes=[name])
        kinds[name] = result["enzymes"][0]["overhang"]

    assert kinds["EcoRI"]["kind"] == "five_prime"
    assert kinds["EcoRI"]["sequence"] == "AATT"
    assert kinds["KpnI"]["kind"] == "three_prime"
    assert kinds["SmaI"]["kind"] == "blunt"
    assert kinds["SmaI"]["length"] == 0


# --------------------------------------------------------------------------
# Restriction: fragments
# --------------------------------------------------------------------------

def test_a_linear_molecule_with_one_cut_gives_two_fragments_summing_to_its_length():
    sequence = "T" * 40 + "GAATTC" + "A" * 60
    result = R.digest(sequence, Report(), enzymes=["EcoRI"])

    fragments = result["enzymes"][0]["fragments"]
    assert len(fragments) == 2
    assert sum(fragments) == len(sequence)


def test_a_circular_molecule_with_one_cut_gives_one_fragment():
    """Cutting a circle once linearises it; cutting a line once halves it. The
    classic off-by-one is producing two fragments from a single-cut plasmid."""
    sequence = "T" * 40 + "GAATTC" + "A" * 60
    result = R.digest(sequence, Report(), enzymes=["EcoRI"], circular=True)

    fragments = result["enzymes"][0]["fragments"]
    assert fragments == [len(sequence)]


def test_a_circular_molecule_with_two_cuts_gives_two_fragments_summing_to_its_length():
    sequence = "T" * 40 + "GAATTC" + "A" * 60 + "GAATTC" + "C" * 30
    result = R.digest(sequence, Report(), enzymes=["EcoRI"], circular=True)

    fragments = result["enzymes"][0]["fragments"]
    assert len(fragments) == 2
    assert sum(fragments) == len(sequence)


def test_an_enzyme_that_does_not_cut_reports_the_whole_molecule():
    result = R.digest("A" * 100, Report(), enzymes=["EcoRI"])

    assert result["enzymes"][0]["cut_count"] == 0
    assert result["enzymes"][0]["fragments"] == [100]


def test_bands_too_close_to_separate_are_named():
    """Two fragments within 10% of each other are one band on agarose. A digest
    that is right on paper and unreadable on a gel has not answered the
    question."""
    # Cuts at ~1/3 and ~2/3 of a circle give three near-equal fragments.
    unit = "A" * 97 + "GAATTC"
    result = R.digest(unit * 3, Report(), enzymes=["EcoRI"], circular=True)

    assert result["enzymes"][0]["unresolvable_pairs"] != []


# --------------------------------------------------------------------------
# Restriction: the two lists that decide a cloning strategy
# --------------------------------------------------------------------------

def test_unique_cutters_and_non_cutters_are_separated():
    sequence = "TTTT" + "GAATTC" + "A" * 30 + "GGATCC" + "C" * 30 + "GGATCC" + "TTTT"
    result = R.digest(sequence, Report(), enzymes=["EcoRI", "BamHI", "NotI"])

    assert result["unique_cutters"] == ["EcoRI"]      # once
    assert "BamHI" not in result["unique_cutters"]     # twice
    assert result["non_cutters"] == ["NotI"]           # never


def test_no_unique_cutter_is_a_warning_not_a_silence():
    report = Report()
    R.digest("A" * 200, report, enzymes=["EcoRI", "BamHI"])

    assert Code.NO_UNIQUE_CUTTER in {item["code"] for item in report.as_list()}


def test_an_unknown_enzyme_is_a_diagnostic_not_a_crash():
    report = Report()
    result = R.digest("A" * 100 + "GAATTC", report, enzymes=["EcoRI", "EccRI"])

    assert result["searched"] == 1
    assert Code.UNKNOWN_ENZYME in {item["code"] for item in report.as_list()}


def test_the_teaching_panel_is_searched_by_default():
    result = R.digest(random_dna(500, seed=1), Report())

    assert result["searched"] == len(R.TEACHING_PANEL)


def test_methylation_and_star_activity_are_declared_unmodelled():
    report = Report()
    R.digest("A" * 100, report, enzymes=["EcoRI"])
    found = {item["code"] for item in report.as_list()}

    assert Code.METHYLATION_UNCHECKED in found
    assert Code.STAR_ACTIVITY_UNCHECKED in found


# --------------------------------------------------------------------------
# Primer helpers
# --------------------------------------------------------------------------

def test_melting_temperature_rises_with_gc_content():
    at_rich = P.tm("ATATATATATATATATATAT")
    gc_rich = P.tm("GCGCGCGCGCGCGCGCGCGC")

    assert at_rich is not None and gc_rich is not None
    assert gc_rich > at_rich + 20


def test_melting_temperature_is_none_for_something_too_short_to_model():
    assert P.tm("ATCG") is None


def test_self_complementarity_finds_a_palindrome():
    # A palindrome is its own reverse complement, so it pairs with itself along
    # its whole length — the worst possible primer.
    assert P.longest_complement("GAATTC", "GAATTC") == 6
    assert P.longest_complement("AAAAAA", "AAAAAA") == 0


def test_three_prime_complementarity_only_asks_about_the_extendable_end():
    # The 3' end of the first pairs with the second; a polymerase can extend it.
    assert P.three_prime_complement("AAAAAAGGGCCC", "AAAAAAGGGCCC") >= 3
    # A run of A at the 3' end against a partner with no T anywhere.
    assert P.three_prime_complement("GGGCCCAAAAAA", "GGGGGGGGGGGG") == 0


def test_a_hairpin_needs_a_loop_as_well_as_a_stem():
    assert P.hairpin_stem("GCGC" + "AAA" + "GCGC") >= P.HAIRPIN_STEM_MIN
    # The same self-complementary bases with no room to turn around cannot fold.
    # A stem may still be counted, but never one long enough to be reported.
    assert P.hairpin_stem("GCGCGCGC") < P.HAIRPIN_STEM_MIN


def test_a_hairpin_never_claims_a_loop_shorter_than_a_strand_can_turn_in():
    """Pairing one base too far leaves a two-base loop, which is not a shape
    DNA makes. The stem has to stop while a real loop is still left."""
    for length in range(8, 26):
        sequence = ("GC" * 20)[:length]
        stem = P.hairpin_stem(sequence)
        loop = length - 2 * stem
        assert stem == 0 or loop >= P.HAIRPIN_LOOP_MIN


def test_gc_clamp_looks_at_the_last_two_bases():
    assert P.has_gc_clamp("AAAAAAAAAG") is True
    assert P.has_gc_clamp("GGGGGGGGAT") is False


def test_longest_run_finds_the_longest_homopolymer():
    assert P.longest_run("ATTTTGC") == ("T", 4)
    assert P.longest_run("ATCG") == ("A", 1)


# --------------------------------------------------------------------------
# Primer design: the property that has to hold
# --------------------------------------------------------------------------

def template_and_plan(seed: int = 7, **overrides):
    template = "ATGGCTAGCAAAGGAGAAGAACTTTTCACT" + random_dna(600, seed) + "TTAGGTACCGCTAGCTAGC"
    request = {"sequence": template, "target": {"start": 20, "end": 560}}
    request.update(overrides)
    return template, cloning.plan(request)


def test_the_designed_primers_actually_flank_the_amplicon_they_report():
    """The one property that must hold regardless of how scoring changes: the
    forward primer is the start of the amplicon, and the reverse primer is the
    reverse complement of its end."""
    _, result = template_and_plan()
    primers = result["primers"]
    amplicon = primers["amplicon"]["sequence"]
    forward = primers["forward"]["sequence"]
    reverse = primers["reverse"]["sequence"]

    assert amplicon.startswith(forward)
    assert P._revcomp(amplicon[-len(reverse):]) == reverse


def test_reported_coordinates_agree_with_the_template():
    template, result = template_and_plan()
    forward = result["primers"]["forward"]
    reverse = result["primers"]["reverse"]

    assert template[forward["start"] - 1:forward["end"]] == forward["sequence"]
    assert P._revcomp(template[reverse["start"] - 1:reverse["end"]]) == reverse["sequence"]


def test_the_amplicon_length_agrees_with_its_coordinates():
    _, result = template_and_plan()
    amplicon = result["primers"]["amplicon"]

    assert amplicon["length"] == amplicon["end"] - amplicon["start"] + 1
    assert len(amplicon["sequence"]) == amplicon["length"]


def test_primers_stay_inside_the_requested_length_range():
    _, result = template_and_plan(min_length=20, max_length=24)

    for role in ("forward", "reverse"):
        assert 20 <= result["primers"][role]["length"] <= 24


def test_the_conditions_behind_every_tm_are_reported():
    """A Tm with no stated salt and primer concentration is not reproducible."""
    _, result = template_and_plan()
    conditions = result["primers"]["conditions"]

    assert conditions["primer_nM"] == 250.0
    assert conditions["na_mM"] == 50.0
    assert "nearest_neighbour" in conditions["model"]


def test_the_designer_says_it_has_not_checked_specificity():
    _, result = template_and_plan()

    assert Code.NOT_A_SPECIFICITY_CHECK in codes(result)


def test_a_mismatched_pair_is_reported_rather_than_hidden():
    """One AT-rich flank and one GC-rich flank cannot share an annealing step.
    The tool should still return the pair, and say so."""
    template = ("A" * 40 + "T" * 40) + random_dna(300, seed=3) + ("GCGCGC" * 12)
    result = cloning.plan({
        "sequence": template,
        "target": {"start": 5, "end": len(template) - 5},
        "design_primers": True,
    })

    assert result["primers"] is not None
    assert result["ok"] is True


# --------------------------------------------------------------------------
# Primer design: request handling
# --------------------------------------------------------------------------

def test_an_empty_sequence_is_an_error_not_an_exception():
    result = cloning.plan({"sequence": ""})

    assert result["ok"] is False
    assert Code.EMPTY_SEQUENCE in codes(result)


def test_a_target_outside_the_template_is_rejected():
    result = cloning.plan({"sequence": random_dna(300, 4), "target": {"start": 1, "end": 5000}})

    assert Code.TARGET_OUT_OF_RANGE in codes(result)
    assert result["primers"] is None


def test_a_target_too_short_to_amplify_is_rejected():
    result = cloning.plan({"sequence": random_dna(300, 4), "target": {"start": 10, "end": 30}})

    assert Code.TARGET_TOO_SHORT in codes(result)


def test_the_digest_still_runs_when_primer_design_is_declined():
    result = cloning.plan({"sequence": random_dna(400, 5), "design_primers": False})

    assert result["digest"] is not None
    assert result["primers"] is None


def test_ambiguous_bases_in_the_template_are_flagged():
    result = cloning.plan({"sequence": "ATCGN" * 60, "design_primers": False})

    assert Code.AMBIGUOUS_BASES_IN_TEMPLATE in codes(result)


# --------------------------------------------------------------------------
# Tails: the reason the two halves share a module
# --------------------------------------------------------------------------

def test_a_tail_site_that_also_occurs_inside_the_amplicon_is_a_warning():
    """The mistake this whole feature exists to catch: the enzyme used to open
    the ends also cuts the insert in half, and nothing in the protocol says so."""
    template = random_dna(200, 11) + "GAATTC" + random_dna(200, 12)
    result = cloning.plan({
        "sequence": template,
        "target": {"start": 20, "end": 380},
        "tails": {"forward_enzyme": "EcoRI", "reverse_enzyme": "XhoI"},
    })

    assert Code.TAIL_SITE_CUTS_AMPLICON in codes(result)
    assert result["tails"]["ends"]["forward"]["cuts_inside_amplicon"] >= 1


def test_a_tail_site_absent_from_the_amplicon_raises_nothing():
    template = random_dna(400, 13).replace("GAATTC", "GAATTA").replace("CTCGAG", "CTCGAA")
    result = cloning.plan({
        "sequence": template,
        "target": {"start": 20, "end": 380},
        "tails": {"forward_enzyme": "EcoRI", "reverse_enzyme": "XhoI"},
    })

    assert Code.TAIL_SITE_CUTS_AMPLICON not in codes(result)


def test_the_tail_is_prepended_and_the_binding_region_kept_separate():
    template = random_dna(400, 14).replace("GAATTC", "GAATTA")
    result = cloning.plan({
        "sequence": template,
        "target": {"start": 20, "end": 380},
        "tails": {"forward_enzyme": "EcoRI"},
    })

    end = result["tails"]["ends"]["forward"]
    assert end["sequence"].startswith(result["tails"]["clamp"] + "GAATTC")
    assert end["sequence"].endswith(end["binding_region"])


def test_two_temperatures_are_given_because_the_tail_does_not_anneal_first():
    """In cycle one only the binding region pairs with anything, so annealing
    temperature comes from that region alone. Reporting only the full-length Tm
    is how a PCR gets run five degrees too hot."""
    template = random_dna(400, 15).replace("GAATTC", "GAATTA")
    result = cloning.plan({
        "sequence": template,
        "target": {"start": 20, "end": 380},
        "tails": {"forward_enzyme": "EcoRI"},
    })

    end = result["tails"]["ends"]["forward"]
    assert end["binding_tm"] < end["full_length_tm"]
    assert Code.TAIL_TM_EXCLUDES_TAIL in codes(result)


def test_two_ends_with_the_same_overhang_cannot_clone_directionally():
    """Identical overhangs religate to each other and the insert goes in either
    way round."""
    template = random_dna(400, 16).replace("GAATTC", "GAATTA")
    result = cloning.plan({
        "sequence": template,
        "target": {"start": 20, "end": 380},
        "tails": {"forward_enzyme": "EcoRI", "reverse_enzyme": "MfeI"},  # both leave AATT
    })

    assert Code.TAIL_SITES_INCOMPATIBLE in codes(result)


def test_an_unknown_tail_enzyme_fails_the_request_rather_than_guessing():
    template = random_dna(400, 17)
    result = cloning.plan({
        "sequence": template,
        "target": {"start": 20, "end": 380},
        "tails": {"forward_enzyme": "EccRI"},
    })

    assert result["ok"] is False
    assert Code.UNKNOWN_ENZYME in codes(result)


def test_no_tails_requested_means_no_tails_section():
    _, result = template_and_plan()

    assert result["tails"] is None
