"""Tests for the stochastic simulator.

A simulator is harder to test than a parser: there is no expected output to
compare against, and every run is different on purpose. So the suite is built
on the three things that *can* be pinned down.

First, theory. For a constitutive gene the chemical master equation has a
closed-form steady state — mRNA is exactly Poisson, and the protein Fano factor
is exactly 1 + b/(1 + d_p/d_m). If the engine drifts away from those, it is
wrong, and no amount of plausible-looking output would reveal it.

Second, invariance. The same seed must give the same answer, a knob set to zero
must have no effect, and turning a knob up must move the result in the
direction it claims to move it.

Third, refusal. Bad input must produce a diagnostic rather than a plausible
number, and the standing limits of the model must be attached to every result.
"""

from __future__ import annotations

import random

import pytest

from app.services import bionoise
from app.services.bionoise import gillespie, network, statistics
from app.services.bionoise.diagnostics import Code
from app.services.bionoise.gillespie import RunSettings, run_ensemble


def codes(result) -> set[str]:
    return {item["code"] for item in result["diagnostics"]}


def settings_for(seconds: float, **overrides) -> RunSettings:
    defaults = {
        "seconds": seconds, "samples": 120, "induction": 1.0, "crosstalk": 0.0,
        "resource_coupling": False, "max_steps_per_cell": 5_000_000, "extrinsic_cv": 0.0,
    }
    defaults.update(overrides)
    return RunSettings(**defaults)


# --------------------------------------------------------------------------
# Agreement with the analytic steady state
#
# leak = 1.0 makes transcription independent of the promoter state, which
# reduces the three-stage model to the two-stage one that has been solved
# exactly. That is the only configuration where the engine can be checked
# against arithmetic rather than against itself.
# --------------------------------------------------------------------------

CONSTITUTIVE = network.Gene(
    "A", "reporter_green", k_on=0.02, k_off=0.02, k_tx=0.05, k_tl=0.05, leak=1.0,
)


@pytest.fixture(scope="module")
def constitutive_run():
    net = network.Network(preset="test", genes=[CONSTITUTIVE])
    runs = run_ensemble(net, settings_for(240 * 60), seed=4242, cells=60)
    start = 48
    return {
        "protein": statistics.pooled(runs, 0, start),
        "mrna": statistics.pooled(runs, 0, start, "mrna"),
    }


def test_mrna_of_a_constitutive_gene_is_poisson(constitutive_run):
    mean, variance = statistics.moments(constitutive_run["mrna"])

    assert mean == pytest.approx(CONSTITUTIVE.k_tx / CONSTITUTIVE.d_m, rel=0.05)
    # Fano exactly 1 is the signature of a Poisson process. Anything else means
    # the birth-death loop is not sampling the right distribution.
    assert variance / mean == pytest.approx(1.0, abs=0.12)


def test_protein_mean_matches_the_rate_equations(constitutive_run):
    gene = CONSTITUTIVE
    predicted = gene.k_tx * gene.k_tl / (gene.d_m * gene.d_p)
    mean, _ = statistics.moments(constitutive_run["protein"])

    assert mean == pytest.approx(predicted, rel=0.05)


def test_protein_fano_matches_the_two_stage_theory(constitutive_run):
    """Fano = 1 + b/(1 + d_p/d_m), the Thattai-van Oudenaarden result.

    This is the single most important assertion in the suite. The burst size
    is the whole reason protein noise exceeds mRNA noise, and reproducing the
    coefficient means the translation and decay steps are coupled correctly.
    """
    mean, variance = statistics.moments(constitutive_run["protein"])
    predicted = 1.0 + CONSTITUTIVE.burst_size / (1.0 + CONSTITUTIVE.d_p / CONSTITUTIVE.d_m)

    assert variance / mean == pytest.approx(predicted, rel=0.15)


def test_promoter_switching_makes_a_gene_noisier_than_the_two_stage_limit():
    """A bursty promoter must exceed the constitutive prediction, not fall below it.

    The two-stage formula assumes Poisson mRNA. Switching the promoter on and
    off adds variance on top, so the measured Fano has to sit above it — a
    result below would mean the promoter states were being averaged away
    instead of simulated.
    """
    net = network.build("independent")
    runs = run_ensemble(net, settings_for(180 * 60), seed=99, cells=60)
    protein = statistics.pooled(runs, 0, 48)

    mean, variance = statistics.moments(protein)
    assert variance / mean > statistics.analytic_fano(net, 0)


def test_mrna_of_a_switching_promoter_is_over_dispersed():
    net = network.build("independent")
    runs = run_ensemble(net, settings_for(120 * 60), seed=17, cells=50)
    mean, variance = statistics.moments(statistics.pooled(runs, 0, 48, "mrna"))

    assert variance / mean > 1.15


# --------------------------------------------------------------------------
# Reproducibility
# --------------------------------------------------------------------------

def test_the_same_seed_reproduces_the_run_exactly():
    first = bionoise.simulate({"preset": "crosstalk_pair", "cells": 8, "minutes": 15, "seed": 777})
    second = bionoise.simulate({"preset": "crosstalk_pair", "cells": 8, "minutes": 15, "seed": 777})

    assert first["statistics"] == second["statistics"]
    assert first["trajectories"] == second["trajectories"]


def test_a_different_seed_gives_a_different_run():
    first = bionoise.simulate({"preset": "independent", "cells": 8, "minutes": 15, "seed": 1})
    second = bionoise.simulate({"preset": "independent", "cells": 8, "minutes": 15, "seed": 2})

    assert first["trajectories"] != second["trajectories"]


def test_a_run_without_a_seed_still_records_the_one_it_used():
    result = bionoise.simulate({"preset": "independent", "cells": 6, "minutes": 10})

    assert isinstance(result["request"]["seed"], int)
    assert Code.SEED_RECORDED in codes(result)


# --------------------------------------------------------------------------
# Crosstalk
# --------------------------------------------------------------------------

def test_crosstalk_at_zero_leaves_every_transcript_accounted_to_its_own_promoter():
    result = bionoise.simulate({
        "preset": "crosstalk_pair", "cells": 20, "minutes": 20, "seed": 3, "crosstalk": 0.0,
    })

    for share in result["crosstalk"]["attribution"].values():
        assert share["crosstalk"] == 0.0


def test_turning_crosstalk_up_moves_transcripts_onto_the_wrong_gene():
    quiet = bionoise.simulate({
        "preset": "crosstalk_pair", "cells": 20, "minutes": 20, "seed": 3, "crosstalk": 0.1,
    })
    loud = bionoise.simulate({
        "preset": "crosstalk_pair", "cells": 20, "minutes": 20, "seed": 3, "crosstalk": 0.9,
    })

    assert (loud["crosstalk"]["attribution"]["B"]["crosstalk"]
            > quiet["crosstalk"]["attribution"]["B"]["crosstalk"])
    # The bystander is expressed harder when crosstalk is stronger.
    assert loud["statistics"]["B"]["mean_protein"] > quiet["statistics"]["B"]["mean_protein"]


def test_the_intended_gene_is_not_blamed_for_crosstalk():
    result = bionoise.simulate({
        "preset": "crosstalk_pair", "cells": 20, "minutes": 20, "seed": 8, "crosstalk": 0.8,
    })

    assert result["crosstalk"]["attribution"]["A"]["crosstalk"] == 0.0
    assert result["crosstalk"]["attribution"]["B"]["crosstalk"] > 0.2


def test_heavy_crosstalk_is_reported_as_a_warning():
    result = bionoise.simulate({
        "preset": "crosstalk_pair", "cells": 20, "minutes": 20, "seed": 8, "crosstalk": 0.9,
    })

    assert Code.CROSSTALK_DOMINATES in codes(result)


def test_transcript_shares_sum_to_one():
    result = bionoise.simulate({
        "preset": "crosstalk_pair", "cells": 20, "minutes": 20, "seed": 12, "crosstalk": 0.5,
    })

    for share in result["crosstalk"]["attribution"].values():
        assert share["cognate"] + share["crosstalk"] + share["leak"] == pytest.approx(1.0, abs=0.01)


# --------------------------------------------------------------------------
# Cell-to-cell variability and the correlation it manufactures
# --------------------------------------------------------------------------

def test_unconnected_genes_correlate_only_because_cells_differ():
    """The trap this tool exists to show.

    Two genes with no link between them and no shared inducer still correlate
    strongly, because a cell with more ribosomes makes more of both. Removing
    the shared factor has to take that correlation back to zero — otherwise the
    partial matrix is not doing the job it is displayed for.
    """
    result = bionoise.simulate({
        "preset": "independent", "cells": 60, "minutes": 60, "seed": 21, "variability": 0.3,
    })

    measured = result["crosstalk"]["correlation"][0][1]
    partial = result["crosstalk"]["partial"][0][1]

    assert measured > 0.4
    # What is left has to be a small fraction of what was measured. An exact
    # zero is not available: a correlation estimated from sixty cells carries a
    # standard error of about 1/sqrt(60), so the assertion is scaled to the
    # effect it is meant to detect rather than to an arbitrary tolerance.
    assert abs(partial) < measured / 2
    assert abs(partial) < 0.3


def test_without_cell_to_cell_variability_there_is_nothing_to_remove():
    result = bionoise.simulate({
        "preset": "independent", "cells": 40, "minutes": 40, "seed": 22, "variability": 0.0,
    })

    assert abs(result["crosstalk"]["correlation"][0][1]) < 0.25


def test_competition_for_ribosomes_makes_genes_anticorrelate():
    """Coupling with no wire between the genes.

    Nothing regulates anything in this preset. Once the shared cell-to-cell
    factor is removed, what is left has to be negative: every protein the
    burden gene makes is a ribosome the reporters did not get.
    """
    result = bionoise.simulate({
        "preset": "resource_competition", "cells": 50, "minutes": 45, "seed": 31,
        "resource_coupling": True,
    })

    genes = result["crosstalk"]["genes"]
    reporter = genes.index("B")
    burden = genes.index("L")

    assert result["crosstalk"]["partial"][reporter][burden] < -0.1
    assert result["performance"]["availability"] < 0.85
    assert Code.RESOURCES_LIMITING in codes(result)


def test_switching_resource_coupling_off_removes_the_competition():
    result = bionoise.simulate({
        "preset": "resource_competition", "cells": 30, "minutes": 30, "seed": 31,
        "resource_coupling": False,
    })

    assert result["performance"]["availability"] == 1.0
    assert Code.RESOURCES_LIMITING not in codes(result)


# --------------------------------------------------------------------------
# Intrinsic and extrinsic noise
# --------------------------------------------------------------------------

def test_the_dual_reporter_split_is_only_offered_where_it_is_valid():
    paired = bionoise.simulate({"preset": "dual_reporter", "cells": 20, "minutes": 20, "seed": 5})
    unpaired = bionoise.simulate({"preset": "toggle_switch", "cells": 20, "minutes": 20, "seed": 5})

    assert paired["decomposition"] is not None
    assert unpaired["decomposition"] is None


def test_cell_to_cell_variability_shows_up_as_extrinsic_noise():
    """Turning the knob has to move the half of the split that names it.

    If cell-to-cell spread appeared as intrinsic noise, the decomposition would
    be mislabelling its own inputs.
    """
    uniform = bionoise.simulate({
        "preset": "dual_reporter", "cells": 60, "minutes": 60, "seed": 6, "variability": 0.0,
    })
    varied = bionoise.simulate({
        "preset": "dual_reporter", "cells": 60, "minutes": 60, "seed": 6, "variability": 0.4,
    })

    assert varied["decomposition"]["extrinsic"] > uniform["decomposition"]["extrinsic"] + 0.05
    assert varied["decomposition"]["intrinsic"] == pytest.approx(
        uniform["decomposition"]["intrinsic"], rel=0.6
    )


def test_the_two_halves_of_the_split_add_up_to_the_measured_noise():
    result = bionoise.simulate({
        "preset": "dual_reporter", "cells": 60, "minutes": 60, "seed": 7, "variability": 0.25,
    })

    split = result["decomposition"]
    measured = result["statistics"]["A"]["cv_squared"]

    assert split["intrinsic"] + split["extrinsic"] == pytest.approx(split["total"], abs=1e-6)
    assert split["total"] == pytest.approx(measured, rel=0.35)


# --------------------------------------------------------------------------
# The noise budget
# --------------------------------------------------------------------------

def test_the_noise_budget_adds_up_to_the_measured_noise():
    result = bionoise.simulate({
        "preset": "crosstalk_pair", "cells": 40, "minutes": 40, "seed": 9, "variability": 0.2,
    })

    for entry in result["statistics"].values():
        budget = entry["noise_budget"]
        parts = budget["floor"] + budget["bursting"] + budget["extrinsic"] \
            + budget["promoter"] + budget["coupling"]
        assert parts == pytest.approx(budget["total"], abs=0.02)


def test_no_gene_can_be_quieter_than_the_poisson_floor():
    result = bionoise.simulate({"preset": "independent", "cells": 40, "minutes": 60, "seed": 10})

    for entry in result["statistics"].values():
        assert entry["cv_squared"] >= entry["noise_budget"]["floor"] * 0.9


def test_a_control_ensemble_is_only_run_when_something_is_coupled():
    coupled = bionoise.simulate({
        "preset": "crosstalk_pair", "cells": 10, "minutes": 15, "seed": 11,
        "resource_coupling": True,
    })
    alone = bionoise.simulate({
        "preset": "independent", "cells": 10, "minutes": 15, "seed": 11,
        "resource_coupling": False,
    })

    assert coupled["performance"]["control_ensemble"] is True
    assert alone["performance"]["control_ensemble"] is False
    assert alone["statistics"]["A"]["noise_budget"]["coupling"] == 0.0


# --------------------------------------------------------------------------
# Bistability
# --------------------------------------------------------------------------

def test_a_toggle_switch_flips_when_nothing_deterministic_would():
    """The circuit is stable in the rate equations and not in the cell."""
    result = bionoise.simulate({
        "preset": "toggle_switch", "cells": 60, "minutes": 120, "seed": 13,
    })

    assert result["switching"]["switches"] > 0
    assert result["switching"]["mean_dwell_minutes"] is not None
    assert Code.SWITCHING_OBSERVED in codes(result)


def test_the_two_repressors_are_anticorrelated():
    result = bionoise.simulate({"preset": "toggle_switch", "cells": 50, "minutes": 60, "seed": 14})

    assert result["crosstalk"]["partial"][0][1] < -0.3


def test_low_copy_numbers_are_flagged():
    """A toggle holding its state with a few dozen molecules is the reason it flips."""
    result = bionoise.simulate({"preset": "toggle_switch", "cells": 30, "minutes": 30, "seed": 15})

    assert result["statistics"]["A"]["mean_protein"] < bionoise.LOW_COPY_THRESHOLD
    assert Code.LOW_COPY_NUMBER in codes(result)


# --------------------------------------------------------------------------
# Induction
# --------------------------------------------------------------------------

def test_induction_controls_expression():
    off = bionoise.simulate({
        "preset": "independent", "cells": 25, "minutes": 30, "seed": 16, "induction": 0.0,
    })
    on = bionoise.simulate({
        "preset": "independent", "cells": 25, "minutes": 30, "seed": 16, "induction": 1.0,
    })

    assert on["statistics"]["A"]["mean_protein"] > off["statistics"]["A"]["mean_protein"] * 5


def test_an_uninduced_promoter_still_leaks():
    result = bionoise.simulate({
        "preset": "independent", "cells": 25, "minutes": 30, "seed": 18, "induction": 0.0,
    })

    assert result["statistics"]["A"]["mean_protein"] > 0
    assert result["crosstalk"]["attribution"]["A"]["leak"] > 0.5
    assert Code.LEAK_DOMINATES in codes(result)


# --------------------------------------------------------------------------
# Input handling
# --------------------------------------------------------------------------

def test_an_unknown_preset_is_refused_rather_than_guessed():
    result = bionoise.simulate({"preset": "does_not_exist"})

    assert result["ok"] is False
    assert Code.UNKNOWN_PRESET in codes(result)
    assert "statistics" not in result


def test_every_preset_runs_and_returns_a_complete_result():
    for preset in bionoise.PRESETS:
        result = bionoise.simulate({"preset": preset, "cells": 6, "minutes": 10, "seed": 1})

        assert result["ok"] is True, preset
        for key in ("network", "trajectories", "distributions", "statistics",
                    "crosstalk", "performance", "time"):
            assert key in result, f"{preset} is missing {key}"

        for gene in result["network"]["genes"]:
            assert gene["id"] in result["statistics"]
            assert gene["id"] in result["trajectories"]


def test_an_oversized_request_is_clamped_and_says_so():
    result = bionoise.simulate({"preset": "independent", "cells": 5000, "minutes": 10, "seed": 1})

    assert result["request"]["cells"] == bionoise.MAX_CELLS
    assert Code.CELLS_CLAMPED in codes(result)


def test_an_impossibly_short_run_is_clamped_and_says_so():
    result = bionoise.simulate({"preset": "independent", "cells": 6, "minutes": 0.1, "seed": 1})

    assert result["request"]["minutes"] == bionoise.MIN_MINUTES
    assert Code.DURATION_CLAMPED in codes(result)


def test_missing_parameters_fall_back_to_defaults():
    result = bionoise.simulate({})

    assert result["ok"] is True
    assert result["request"]["preset"] == "independent"


# --------------------------------------------------------------------------
# Shape of the output
# --------------------------------------------------------------------------

def test_every_series_is_the_length_the_time_grid_claims():
    result = bionoise.simulate({"preset": "independent", "cells": 8, "minutes": 20, "seed": 19})
    points = len(result["time"]["grid_minutes"])

    for series in result["trajectories"].values():
        assert len(series["mean"]) == points
        assert len(series["sd"]) == points
        for trace in series["examples"]:
            assert len(trace) == points


def test_the_burn_in_window_is_discarded_from_the_statistics_but_kept_for_the_plot():
    result = bionoise.simulate({"preset": "independent", "cells": 10, "minutes": 20, "seed": 20})

    burn_in = result["time"]["burn_in_index"]
    points = len(result["time"]["grid_minutes"])

    assert 0 < burn_in < points
    assert result["statistics"]["A"]["samples"] == (points - burn_in) * 10


def test_the_standing_limits_of_the_model_are_attached_to_every_result():
    """These are not findings about a run; they are true of all of them.

    A simulator that mentions its assumptions only in the documentation is a
    simulator whose assumptions nobody reads.
    """
    for preset in bionoise.PRESETS:
        found = codes(bionoise.simulate({"preset": preset, "cells": 5, "minutes": 10, "seed": 2}))

        assert Code.WELL_MIXED_ASSUMPTION in found
        assert Code.PARAMETERS_ILLUSTRATIVE in found
        assert Code.NO_CELL_DIVISION in found


def test_precision_is_reported_and_improves_with_more_cells():
    few = bionoise.simulate({"preset": "independent", "cells": 8, "minutes": 30, "seed": 23})
    many = bionoise.simulate({"preset": "independent", "cells": 120, "minutes": 30, "seed": 23})

    assert few["statistics"]["A"]["precision"] > many["statistics"]["A"]["precision"]
    assert Code.IMPRECISE in codes(few)


def test_a_run_that_exhausts_its_budget_says_so_instead_of_pretending():
    net = network.build("independent")
    runs = run_ensemble(net, settings_for(60 * 60, max_steps_per_cell=500), seed=1, cells=4)

    assert all(run.truncated for run in runs)
    # Truncated or not, every sample point is filled, so the ensemble average is
    # taken over the same number of cells at every time.
    for run in runs:
        assert len(run.protein[0]) == 120


def test_histograms_cover_the_data_they_summarise():
    result = bionoise.simulate({"preset": "independent", "cells": 20, "minutes": 30, "seed": 24})

    for gene_id, shape in result["distributions"].items():
        assert sum(shape["counts"]) == result["statistics"][gene_id]["samples"]
        assert shape["edges"][0] <= shape["min"]
        assert shape["edges"][-1] >= shape["max"]


# --------------------------------------------------------------------------
# Numeric helpers
# --------------------------------------------------------------------------

def test_correlation_of_a_series_with_itself_is_one():
    values = [3, 1, 4, 1, 5, 9, 2, 6]
    assert statistics.correlation(values, values) == pytest.approx(1.0)


def test_correlation_of_a_series_with_its_negation_is_minus_one():
    values = [3, 1, 4, 1, 5, 9, 2, 6]
    assert statistics.correlation(values, [-v for v in values]) == pytest.approx(-1.0)


def test_a_constant_series_has_no_correlation_rather_than_a_division_by_zero():
    assert statistics.correlation([2, 2, 2, 2], [1, 2, 3, 4]) == 0.0


def test_removing_a_control_variable_removes_the_correlation_it_caused():
    shared = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0]
    left = [value * 2 for value in shared]
    right = [value * 3 for value in shared]

    assert statistics.correlation(left, right) == pytest.approx(1.0)
    assert statistics.correlation(
        statistics.residuals(left, shared), statistics.residuals(right, shared)
    ) == pytest.approx(0.0, abs=1e-9)


def test_effective_samples_never_exceeds_what_independence_allows():
    net = network.build("independent")
    runs = run_ensemble(net, settings_for(10 * 60), seed=1, cells=10)

    # A run far shorter than one protein lifetime yields one observation per
    # cell, however densely it was sampled.
    independent = statistics.effective_samples(runs, 0, 600, 0.6, net.genes[0].d_p)
    assert independent == pytest.approx(10.0)


def test_analytic_fano_grows_with_burst_size():
    small = network.Gene("A", "x", k_on=0.02, k_off=0.02, k_tx=0.05, k_tl=0.01)
    large = network.Gene("A", "x", k_on=0.02, k_off=0.02, k_tx=0.05, k_tl=0.20)

    quiet = network.Network(preset="t", genes=[small])
    loud = network.Network(preset="t", genes=[large])

    assert statistics.analytic_fano(loud, 0) > statistics.analytic_fano(quiet, 0)
    assert statistics.analytic_fano(quiet, 0) > 1.0


def test_isolating_a_network_removes_crosstalk_but_keeps_the_design():
    full = network.build("crosstalk_pair")
    stripped = network.isolate(full)

    assert full.has_crosstalk is True
    assert stripped.has_crosstalk is False
    assert len(stripped.genes) == len(full.genes)


def test_the_gamma_seed_has_the_mean_and_spread_it_was_asked_for():
    """The ensemble is seeded from this, so a wrong draw biases every run."""
    rng = random.Random(1)
    draws = [gillespie._gamma_counts(rng, 20.0, 5.0) for _ in range(4000)]

    mean, variance = statistics.moments(draws)
    assert mean == pytest.approx(100.0, rel=0.05)
    # A gamma has variance shape * scale^2, so its Fano factor is the scale —
    # which is exactly the burst size the seed is meant to reproduce.
    assert variance / mean == pytest.approx(5.0, rel=0.15)


def test_the_poisson_seed_is_poisson():
    rng = random.Random(2)
    draws = [gillespie._poisson(rng, 4.0) for _ in range(4000)]

    mean, variance = statistics.moments(draws)
    assert mean == pytest.approx(4.0, rel=0.06)
    assert variance / mean == pytest.approx(1.0, abs=0.1)
