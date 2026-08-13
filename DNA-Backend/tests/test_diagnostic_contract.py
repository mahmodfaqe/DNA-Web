"""The diagnostic parameter contract.

Every tool here reports findings the same way: a language-neutral code plus
parameters, which the frontend substitutes into a translated sentence. That
makes a parameter a **message value**, not a data structure — something a
sentence can hold.

Nothing enforced that until a warning shipped carrying a list of dictionaries.
The renderer is shared by all five tools and joins arrays into text, so it hit
an array-to-string conversion and returned a 500. The reader lost the entire
cloning plan — the primers, the enzyme table, all of it — over a note about two
gel bands being hard to tell apart, which was the least important thing on the
page.

So the contract is checked here, at the boundary that produces it, across every
tool and against inputs chosen to make each tool complain.
"""

from __future__ import annotations

import random

import pytest
from app.services import biocompiler, bionoise, cloning, memory


def diagnostics_from(result: dict) -> list[dict]:
    return result.get("diagnostics", [])


def random_dna(length: int, seed: int) -> str:
    rng = random.Random(seed)
    return "".join(rng.choice("ATCG") for _ in range(length))


# Inputs chosen to make each tool emit as many different codes as possible.
# A contract test that only sees happy paths checks nothing.
def all_results() -> list[tuple[str, dict]]:
    unresolvable = ("A" * 97 + "GAATTC") * 3
    with_trap = random_dna(300, 1) + "GAATTC" + random_dna(300, 2)

    return [
        ("cloning/unresolvable_bands", cloning.plan({
            "sequence": unresolvable, "enzymes": ["EcoRI"],
            "circular": True, "design_primers": False,
        })),
        ("cloning/tail_cuts_insert", cloning.plan({
            "sequence": with_trap, "target": {"start": 20, "end": 580},
            "tails": {"forward_enzyme": "EcoRI", "reverse_enzyme": "XhoI"},
        })),
        ("cloning/ambiguous_template", cloning.plan({
            "sequence": "ATCGN" * 60, "design_primers": False,
        })),
        ("cloning/unknown_enzyme", cloning.plan({
            "sequence": random_dna(400, 3), "enzymes": ["EccRI"], "design_primers": False,
        })),
        ("cloning/target_out_of_range", cloning.plan({
            "sequence": random_dna(300, 4), "target": {"start": 1, "end": 9000},
        })),
        ("cloning/same_overhang_tails", cloning.plan({
            "sequence": random_dna(400, 5).replace("GAATTC", "GAATTA"),
            "target": {"start": 20, "end": 380},
            "tails": {"forward_enzyme": "EcoRI", "reverse_enzyme": "MfeI"},
        })),
        ("compiler/ok", biocompiler.compile_text("if lactose then produce green protein")),
        ("compiler/unparsable", biocompiler.compile_text("hello there how are you today")),
        ("compiler/partial", biocompiler.compile_text("if something unknown then glow")),
        ("memory/ok", memory.design({
            "signal": "lactose", "chassis": "ecoli", "hold_hours": 24,
            "signal_minutes": 60, "strength": 0.8,
        })),
        ("memory/mismatched_host", memory.design({
            "signal": "galactose", "chassis": "ecoli", "hold_hours": 24,
            "signal_minutes": 60, "strength": 0.8,
        })),
        # Small and short on purpose: this asks about the shape of the
        # diagnostics, not about the physics, and a full ensemble would make
        # the contract check the slowest thing in the suite.
        ("simulator/crosstalk", bionoise.simulate({
            "preset": "crosstalk_pair", "cells": 25, "minutes": 20, "seed": 1,
        })),
        ("simulator/resource_competition", bionoise.simulate({
            "preset": "resource_competition", "cells": 25, "minutes": 20, "seed": 2,
        })),
        ("simulator/unknown_preset", bionoise.simulate({
            "preset": "nonsense_preset", "cells": 25, "minutes": 20,
        })),
    ]


CASES = all_results()


@pytest.mark.parametrize(("label", "result"), CASES, ids=[label for label, _ in CASES])
def test_every_diagnostic_parameter_is_something_a_sentence_can_hold(label, result):
    """The regression. A parameter may be a scalar, or a flat list of scalars —
    never a nested structure."""
    for diagnostic in diagnostics_from(result):
        for name, value in (diagnostic.get("params") or {}).items():
            where = f"{label}: {diagnostic['code']}.{name}"

            if isinstance(value, (list, tuple)):
                for element in value:
                    assert isinstance(element, (str, int, float, bool)) or element is None, (
                        f"{where} is a list containing {type(element).__name__}; "
                        "the shared renderer joins lists into text and cannot hold this"
                    )
            else:
                assert isinstance(value, (str, int, float, bool)) or value is None, (
                    f"{where} is a {type(value).__name__}, which no translated "
                    "sentence can substitute"
                )


@pytest.mark.parametrize(("label", "result"), CASES, ids=[label for label, _ in CASES])
def test_every_diagnostic_has_a_code_and_a_known_severity(label, result):
    for diagnostic in diagnostics_from(result):
        assert diagnostic.get("code"), f"{label}: a diagnostic with no code"
        assert diagnostic.get("severity") in {"error", "warning", "info"}, (
            f"{label}: {diagnostic['code']} has severity {diagnostic.get('severity')!r}"
        )


def test_the_band_warning_names_the_two_sizes():
    """It used to pass the whole list of pairs and the sentence used none of it,
    so the reader was told bands were "too close" without being told which."""
    result = cloning.plan({
        "sequence": ("A" * 97 + "GAATTC") * 3,
        "enzymes": ["EcoRI"], "circular": True, "design_primers": False,
    })

    warnings = [
        item for item in result["diagnostics"]
        if item["code"] == cloning.Code.FRAGMENTS_UNRESOLVABLE
    ]

    assert warnings, "the fixture should produce unresolvable bands"
    params = warnings[0]["params"]
    assert isinstance(params["larger"], int)
    assert isinstance(params["smaller"], int)
    assert params["larger"] >= params["smaller"]
