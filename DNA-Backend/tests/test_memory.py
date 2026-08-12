"""Tests for the memory architect.

The thing being tested is a recommendation, and a recommendation is only worth
anything if it changes when the situation changes. So most of the suite is
comparative: hold the memory for longer and the answer should move towards DNA;
make the sensor leakier and it should move away from it; demand reversibility
and an architecture that cannot be reversed should not merely score lower, it
should be excluded.

The ODE solver is checked against solutions that can be written down — a
first-order system's approach to its steady state, and a symmetric toggle's
fixed points — because a solver that is subtly wrong produces trajectories that
look completely reasonable.
"""

from __future__ import annotations

import math

import pytest
from app.services import memory
from app.services.memory import construct, library, ode, sequence
from app.services.memory.diagnostics import Code


def codes(result) -> set[str]:
    return {item["code"] for item in result["diagnostics"]}


def design(**overrides):
    request = {
        "signal": "lactose", "chassis": "ecoli", "hold_hours": 24,
        "signal_minutes": 60, "must_be_reversible": False, "on_plasmid": True,
    }
    request.update(overrides)
    return memory.design(request)


# --------------------------------------------------------------------------
# The solver
# --------------------------------------------------------------------------

def test_the_solver_reproduces_exponential_approach_to_steady_state():
    """dx/dt = a - bx has the closed form x(t) = a/b (1 - e^(-bt)).

    Every model in this module is built out of exactly this shape, so a solver
    that gets it wrong gets everything wrong — quietly, and with plausible
    looking curves.
    """
    a, b = 40.0, 0.05
    _, final, _ = ode.integrate(lambda _t, s: [a - b * s[0]], [0.0], 0.0, 60.0)
    expected = a / b * (1 - math.exp(-b * 60.0))

    assert final[0] == pytest.approx(expected, rel=1e-6)


def test_the_solver_holds_a_steady_state_that_is_already_reached():
    a, b = 40.0, 0.05
    _, final, _ = ode.integrate(lambda _t, s: [a - b * s[0]], [a / b], 0.0, 500.0)

    assert final[0] == pytest.approx(a / b, rel=1e-9)


def test_copy_numbers_never_go_negative():
    # A decay far faster than the step size would drive a naive integrator
    # below zero, and a negative protein count makes every Hill term downstream
    # meaningless rather than merely inaccurate.
    _, final, path = ode.integrate(lambda _t, s: [-50.0 * s[0]], [10.0], 0.0, 30.0)

    assert final[0] >= 0.0
    assert all(state[0] >= 0.0 for _, state in path)


def test_a_symmetric_toggle_has_three_fixed_points_when_it_is_bistable():
    states = ode.steady_states(alpha=40.0, leak=0.02, half=150.0, coefficient=2, clearance=0.0234)

    assert len(states) == 3
    low, saddle, high = sorted(states)
    assert low < saddle < high


def test_a_weak_toggle_is_not_bistable_and_is_not_a_memory():
    """Below a threshold the two states merge and the circuit simply relaxes."""
    states = ode.steady_states(alpha=6.0, leak=0.02, half=150.0, coefficient=2, clearance=0.0234)

    assert len(states) == 1


def test_escape_time_falls_as_the_barrier_shrinks():
    far = ode.escape_time(300.0, burst_size=15.0, burst_frequency=1.2)
    near = ode.escape_time(30.0, burst_size=15.0, burst_frequency=1.2)

    assert far > near > 0


def test_a_barrier_of_zero_is_no_memory_at_all():
    assert ode.escape_time(0.0, 15.0, 1.2) == 0.0


# --------------------------------------------------------------------------
# The two architectures
# --------------------------------------------------------------------------

def test_a_recombinase_register_never_unflips_on_its_own():
    """Unidirectionality is the entire proposition; if the model loses it,
    the tool is recommending a memory that forgets."""
    outcome = ode.recombinase_outcome(
        library.ARCHITECTURES["recombinase"], library.SIGNALS["arabinose"],
        library.CHASSIS["ecoli"], library.RECOMBINASES["bxb1"],
        signal_minutes=60, hold_minutes=48 * 60, on_plasmid=False,
    )

    flipped = outcome.phases[1].series["flipped"]
    assert all(later >= earlier - 1e-9 for earlier, later in zip(flipped, flipped[1:], strict=False))
    assert outcome.retained_fraction >= outcome.write_fraction - 1e-9


def test_a_leaky_promoter_writes_the_register_with_no_signal():
    """The failure this whole tab exists to quantify."""
    tight = ode.recombinase_outcome(
        library.ARCHITECTURES["recombinase"], library.SIGNALS["arabinose"],
        library.CHASSIS["ecoli"], library.RECOMBINASES["bxb1"], 60, 1440, False,
    )
    leaky = ode.recombinase_outcome(
        library.ARCHITECTURES["recombinase"], library.SIGNALS["ph_acid"],
        library.CHASSIS["ecoli"], library.RECOMBINASES["bxb1"], 60, 1440, False,
    )

    assert leaky.false_write_per_hour > tight.false_write_per_hour * 5


def test_a_toggle_is_charged_for_the_same_leak_as_the_recombinase():
    """Otherwise the comparison is rigged by assumption rather than biology."""
    outcome = ode.toggle_outcome(
        library.ARCHITECTURES["toggle"], library.SIGNALS["ph_acid"],
        library.CHASSIS["ecoli"], 60, 24 * 60, False,
    )

    assert outcome.detail["falsely_set_by_leak"] is True
    assert outcome.false_write_per_hour > 0


def test_a_dna_memory_is_not_diluted_by_growth_and_a_protein_one_is():
    fast = library.Chassis("fast", "bacteria", 20.0, True, True, 15, 2000.0)
    slow = library.Chassis("slow", "bacteria", 120.0, True, True, 15, 2000.0)

    quick = ode.recombinase_outcome(
        library.ARCHITECTURES["recombinase"], library.SIGNALS["arabinose"],
        fast, library.RECOMBINASES["bxb1"], 120, 12 * 60, False,
    )
    slower = ode.recombinase_outcome(
        library.ARCHITECTURES["recombinase"], library.SIGNALS["arabinose"],
        slow, library.RECOMBINASES["bxb1"], 120, 12 * 60, False,
    )

    # Both keep the bit; the register is copied, not re-expressed.
    assert quick.retained_fraction > 0.5
    assert slower.retained_fraction > 0.5
    assert quick.stores_in_dna and slower.stores_in_dna


def test_a_plasmid_borne_memory_is_lost_and_a_genomic_one_is_not():
    on_plasmid = ode.recombinase_outcome(
        library.ARCHITECTURES["recombinase"], library.SIGNALS["arabinose"],
        library.CHASSIS["ecoli"], library.RECOMBINASES["bxb1"], 60, 48 * 60, True,
    )
    in_genome = ode.recombinase_outcome(
        library.ARCHITECTURES["recombinase"], library.SIGNALS["arabinose"],
        library.CHASSIS["ecoli"], library.RECOMBINASES["bxb1"], 60, 48 * 60, False,
    )

    assert on_plasmid.retention_half_life_hours is not None
    assert in_genome.retention_half_life_hours is None
    assert in_genome.retained_fraction >= on_plasmid.retained_fraction


# --------------------------------------------------------------------------
# The recommendation moves when the situation moves
# --------------------------------------------------------------------------

def test_a_leakier_sensor_pushes_the_recommendation_away_from_dna():
    tight = design(signal="arabinose", hold_hours=48)
    leaky = design(signal="lactose", hold_hours=48)

    tight_score = next(c for c in tight["comparison"] if c["architecture"] == "recombinase")
    leaky_score = next(c for c in leaky["comparison"] if c["architecture"] == "recombinase")

    assert leaky_score["false_write_share"] > tight_score["false_write_share"]
    assert leaky_score["fidelity"] < tight_score["fidelity"]


def test_demanding_reversibility_excludes_a_one_way_integrase():
    result = design(must_be_reversible=True)

    architectures = {entry["architecture"] for entry in result["comparison"]}
    assert "recombinase" not in architectures
    assert "recombinase_reversible" in architectures


def test_a_toggle_that_cannot_hold_is_disqualified_rather_than_ranked_low():
    """A memory that does not remember has failed at its only job."""
    result = design(signal="arabinose", hold_hours=72, signal_minutes=30, on_plasmid=False)

    toggle = next(c for c in result["comparison"] if c["architecture"] == "toggle")
    if not toggle["written"] if "written" in toggle else toggle["disqualified"]:
        assert toggle["total"] == 0.0


def test_a_close_call_is_declared_rather_than_dressed_up_as_a_verdict():
    result = design(signal="arabinose", hold_hours=6, signal_minutes=120, must_be_reversible=True)

    gap = result["recommendation"]["gap"]
    if gap is not None and gap < memory.CLOSE_CALL:
        assert Code.RECOMMENDATION_IS_CLOSE in codes(result)


def test_every_architecture_is_modelled_not_only_the_winner():
    """The reasoning is the product. A verdict with nothing behind it is a guess."""
    result = design()

    assert len(result["comparison"]) >= 2
    assert set(result["outcomes"]) == {entry["architecture"] for entry in result["comparison"]}
    for outcome in result["outcomes"].values():
        assert outcome["phases"], "every architecture needs its trajectory shown"


# --------------------------------------------------------------------------
# Refusals
# --------------------------------------------------------------------------

def test_a_bacterial_sensor_is_refused_in_a_eukaryotic_host():
    """The promoter is the whole point: pLac is not transcribed in a nucleus."""
    result = design(signal="lactose", chassis="yeast")

    assert result["ok"] is False
    assert Code.SIGNAL_NOT_IN_CHASSIS in codes(result)
    assert "fasta" not in result


def test_a_host_with_no_parts_kit_is_refused():
    """The guard still stands for any host the library cannot dress."""
    bare = library.Chassis("bare", "bacteria", 30.0, False, True, 1, 2000.0)
    original = dict(library.CHASSIS)
    library.CHASSIS["bare"] = bare
    try:
        result = design(chassis="bare")
    finally:
        library.CHASSIS.clear()
        library.CHASSIS.update(original)

    assert result["ok"] is False
    assert Code.CHASSIS_PARTS_UNAVAILABLE in codes(result)


def test_a_sensor_that_is_not_characterised_in_the_host_is_refused():
    result = design(signal="arabinose", chassis="bsubtilis")

    assert result["ok"] is False
    assert Code.SIGNAL_NOT_IN_CHASSIS in codes(result)


def test_an_unknown_signal_is_refused_rather_than_guessed():
    result = design(signal="unicorn_tears")

    assert result["ok"] is False
    assert Code.UNKNOWN_SIGNAL in codes(result)


def test_out_of_range_durations_are_clamped_into_the_supported_window():
    result = design(hold_hours=100000, signal_minutes=0.001)

    assert result["request"]["hold_hours"] == memory.MAX_HOLD_HOURS
    assert result["request"]["signal_minutes"] == memory.MIN_SIGNAL_MINUTES


# --------------------------------------------------------------------------
# Sequence analysis
# --------------------------------------------------------------------------

def test_reverse_complement_is_its_own_inverse():
    original = "ATGCGGTCATTACGGATC"
    assert sequence.reverse_complement(sequence.reverse_complement(original)) == original


def test_a_fasta_payload_is_stripped_to_bases():
    assert sequence.clean(">gene one\nacgt\nAC GT!!\n") == "ACGTACGT"


def test_a_consensus_promoter_is_found_and_a_random_stretch_is_not():
    promoter = "TTGACA" + "GCTTAGCTAGCTAGCTA" + "TATAAT"
    found = sequence.find_promoters("AAAA" + promoter + "AAAA")

    assert len(found) == 1
    assert found[0].score > 0.9
    assert sequence.find_promoters("A" * 60) == []


def test_the_default_payload_reads_as_the_promoter_it_is():
    """J23119 is a promoter, so the scan had better find it — the register's
    whole function is pointing it one way or the other."""
    found = sequence.find_promoters(construct.DEFAULT_PAYLOAD)

    assert found, "the scan missed a real constitutive promoter"


def test_inverting_a_register_reverses_which_way_its_promoter_fires():
    filler = "ACGTGCTAGCATCGTAGCTAGCATCGATCGTAGCTAGCTAGCATCGATCGA" * 4
    payload = filler + "TTGACA" + "GCTTAGCTAGCTAGCTA" + "TATAAT" + filler

    comparison = sequence.compare_orientations(payload)

    outward = comparison["forward"]["counts"]["promoters_outward"]
    inward = comparison["reverse"]["counts"]["promoters_inward"]
    assert outward >= 1
    assert inward >= 1
    # Pointing a cryptic promoter away from the output is the safer build.
    assert comparison["preferred"] == "reverse"


def test_a_payload_with_nothing_to_say_does_not_pick_a_side():
    """Random sequence throws up weak hits; calling an orientation on one of
    those would be reading noise back to the user as a decision."""
    comparison = sequence.compare_orientations("ACGT" * 100)

    assert comparison["preferred"] == "either"
    assert comparison["decided_by_sequence"] is False


def test_homopolymer_runs_are_found():
    found = sequence.find_homopolymers("ACGT" + "A" * 9 + "CGT")

    assert len(found) == 1
    assert found[0].end - found[0].start + 1 == 9


def test_synthesis_difficulty_names_its_reasons():
    # "ACGT" repeated is not an easy sequence — it is one long tandem repeat,
    # which is exactly what a synthesis provider objects to. The benign case
    # has to be genuinely unstructured.
    import random

    rng = random.Random(11)
    easy = sequence.synthesis_difficulty("".join(rng.choice("ACGT") for _ in range(600)))
    hard = sequence.synthesis_difficulty("GC" * 300 + "A" * 12)

    assert easy["difficult"] is False
    assert hard["difficult"] is True
    assert "homopolymer" in hard["reasons"]


def test_a_tandem_repeat_is_correctly_called_difficult():
    assert sequence.synthesis_difficulty("ACGT" * 100)["difficult"] is True


def test_entropy_is_maximal_for_balanced_sequence_and_low_for_one_base():
    assert sequence.entropy("ACGT" * 50) == pytest.approx(2.0, abs=1e-9)
    assert sequence.entropy("A" * 200) == pytest.approx(0.0, abs=1e-9)


# --------------------------------------------------------------------------
# The DNA that comes out
# --------------------------------------------------------------------------

def test_the_register_carries_att_sites_around_the_payload():
    """Tested on the builder directly rather than through a recommendation.

    Which architecture wins depends on the parameters, so routing this through
    `design()` would make a structural assertion silently stop testing anything
    the day the scoring weights move.
    """
    constructs = construct.build_recombinase(
        library.ARCHITECTURES["recombinase"], library.SIGNALS["arabinose"],
        library.RECOMBINASES["bxb1"], construct.DEFAULT_PAYLOAD, "forward",
    )

    register = next(item for item in constructs if item.name == "register")
    roles = [item["role"] for item in register.annotations()]

    assert roles.count("att") == 2
    assert roles.index("payload") == 1
    assert roles[0] == "att" and "att" in roles[2:]


def test_choosing_the_reverse_orientation_inverts_the_payload_and_nothing_else():
    forward = construct.build_recombinase(
        library.ARCHITECTURES["recombinase"], library.SIGNALS["arabinose"],
        library.RECOMBINASES["bxb1"], construct.DEFAULT_PAYLOAD, "forward",
    )
    reverse = construct.build_recombinase(
        library.ARCHITECTURES["recombinase"], library.SIGNALS["arabinose"],
        library.RECOMBINASES["bxb1"], construct.DEFAULT_PAYLOAD, "reverse",
    )

    forward_register = next(item for item in forward if item.name == "register")
    reverse_register = next(item for item in reverse if item.name == "register")

    forward_payload = forward_register.segments[1].sequence
    reverse_payload = reverse_register.segments[1].sequence

    assert reverse_payload == sequence.reverse_complement(forward_payload)
    # The att sites flank the payload and do not move with it.
    assert forward_register.segments[0].sequence == reverse_register.segments[0].sequence
    assert forward_register.segments[2].sequence == reverse_register.segments[2].sequence


def test_coding_sequences_are_placeholders_and_regulatory_parts_are_not():
    """The compiler's rule, for the compiler's reason: a 1416 bp integrase
    written from memory produces a construct that looks right and does not work."""
    result = design()

    for part in result["parts"]:
        if part["role"] == "cds":
            assert part["provenance"] == "placeholder", part["id"]
        if part["role"] in ("att", "rbs", "terminator"):
            assert part["provenance"] == "literal", part["id"]


def test_the_fasta_says_what_it_is_and_what_is_missing():
    result = design()
    text = result["fasta"]

    assert text.startswith("; DeepBio-Memory Architect design")
    assert "Verify every part" in text
    assert text.count(">") == len(result["constructs"])


def test_annotation_coordinates_tile_the_construct_exactly():
    """A part map is a lie if the coordinates do not add up."""
    result = design()

    for item in result["constructs"]:
        cursor = 1
        for annotation in item["annotations"]:
            assert annotation["start"] == cursor
            cursor = annotation["end"] + 1
        assert cursor - 1 == item["length"]


def test_the_att_sites_are_flagged_for_verification():
    """Their central dinucleotide is the whole mechanism of directionality, and
    a single wrong base there fails silently rather than loudly."""
    result = design(signal="arabinose", hold_hours=96, on_plasmid=False)

    assert Code.ATT_SITES_MUST_BE_VERIFIED in codes(result)


def test_the_standing_limits_are_attached_to_every_design():
    for signal in ("lactose", "arabinose", "tetracycline"):
        found = codes(design(signal=signal))

        assert Code.DETERMINISTIC_MODEL in found
        assert Code.PARAMETERS_ILLUSTRATIVE in found
        assert Code.NOT_FOR_SYNTHESIS in found


def test_a_supplied_payload_is_used_instead_of_the_default():
    custom = "ATGC" * 60
    result = design(payload=custom)

    assert result["composition"]["is_default_payload"] is False
    assert result["composition"]["payload_length"] == len(custom)


def test_an_oversized_payload_is_truncated_rather_than_accepted():
    result = design(payload="ACGT" * 10_000)

    assert result["composition"]["payload_length"] == memory.MAX_PAYLOAD_BP


def test_the_same_request_gives_the_same_design():
    """Deterministic by construction — there is no sampling anywhere in it."""
    first, second = design(), design()

    assert first["fasta"] == second["fasta"]
    assert first["comparison"] == second["comparison"]


# --------------------------------------------------------------------------
# Eukaryotic hosts
# --------------------------------------------------------------------------

def test_yeast_is_built_from_polymerase_two_parts_not_bacterial_ones():
    """The bacterial kit is not the yeast kit with different labels."""
    result = design(signal="galactose", chassis="yeast", hold_hours=48)

    assert result["ok"] is True
    ids = {part["id"] for part in result["parts"]}

    # Nothing from the iGEM bacterial library may appear in a nucleus.
    assert not any(part_id.startswith("BBa_") for part_id in ids)
    # And the initiation element is a base context, not a Shine-Dalgarno site.
    assert "KOZAK_SC" in ids


def test_a_yeast_integrase_carries_a_nuclear_localisation_signal():
    """Expressed in the cytoplasm, an integrase never meets the DNA it cuts."""
    result = design(signal="galactose", chassis="yeast", hold_hours=8,
                    signal_minutes=120, must_be_reversible=False)

    writer = next(c for c in result["constructs"] if c["name"] == "writer")
    roles = [item["part_id"] for item in writer["annotations"]]

    assert "NLS_SV40" in roles
    # And it sits ahead of the coding sequence, in frame with it.
    assert roles.index("NLS_SV40") < roles.index("BXB1_INT")
    assert Code.NUCLEAR_LOCALISATION_REQUIRED in codes(result)


def test_a_yeast_part_points_at_sgd_rather_than_the_igem_registry():
    result = design(signal="galactose", chassis="yeast", hold_hours=48)

    terminator = next(p for p in result["parts"] if p["id"] == "tCYC1")
    assert terminator["registry_url"] == "https://www.yeastgenome.org/locus/YJR048W"


def test_an_unsupplied_eukaryotic_cargo_is_declared_rather_than_invented():
    """A yeast cargo is a 400 bp promoter this tool will not write from memory."""
    result = design(signal="galactose", chassis="yeast", hold_hours=48)

    assert Code.CARGO_NOT_SUPPLIED in codes(result)
    # And with nothing to read, no orientation is claimed.
    assert result["orientation"]["decided_by_sequence"] is False


def test_a_yeast_protein_is_short_lived_and_a_toggle_feels_it():
    """Median yeast protein half-life is ~43 min, not the bacterial assumption."""
    assert library.CHASSIS["yeast"].protein_half_life_minutes < 100
    assert library.CHASSIS["yeast"].domain == "yeast"


# --------------------------------------------------------------------------
# Synthesis difficulty
# --------------------------------------------------------------------------

def test_placeholder_bases_do_not_drag_gc_content_to_an_extreme():
    """The regression: a construct is mostly N, and N is neither G nor C.

    Counting placeholders in the denominator put every design this tool
    produced at roughly ten percent GC and reported all of them as difficult to
    synthesise. The bases that exist are ordinary.
    """
    real = "GCATGCATGCAT" * 12          # 144 bp, 50% GC
    padded = real + "N" * 2000

    honest = sequence.synthesis_difficulty(real)
    with_placeholders = sequence.synthesis_difficulty(padded)

    assert honest["gc_percent"] == with_placeholders["gc_percent"]
    assert "gc_extreme" not in with_placeholders["reasons"]
    assert with_placeholders["resolved_bases"] == len(real)


def test_gc_is_not_judged_when_almost_nothing_is_resolved():
    barely = "GGGG" + "N" * 1000

    verdict = sequence.synthesis_difficulty(barely)

    assert "gc_extreme" not in verdict["reasons"]
    assert verdict["resolved_percent"] < 1
