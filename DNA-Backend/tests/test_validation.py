"""Validation against independent implementations.

Every other test in this suite asks whether the code does what this project
intended. These ask something harder: whether what this project intended agrees
with tools written by people who had never seen it.

The references are `primer3` (oligotm, ntthal) and `EMBOSS` (getorf). They are
system packages, not Python ones, so each test skips when its binary is absent
and the suite still passes on a laptop without them. CI installs both, which is
where these actually run — a validation that only works on one machine is a
claim, not a check.

The recorded numbers are in docs/VALIDATION.md, including the disagreement these
tests found: passing a stated primer concentration to Biopython as `dnac1` alone
reads 1.3 °C hotter than primer3 at the same nominal figure, which lands
directly on a thermocycler when the annealing step is set five degrees below.
"""

from __future__ import annotations

import random
import shutil
import statistics
import subprocess
import tempfile
from pathlib import Path

import pytest
from app.services.cloning import primers as P
from app.services.sequence import find_open_reading_frames

OLIGOTM = shutil.which("oligotm")
NTTHAL = shutil.which("ntthal")
GETORF = shutil.which("getorf")

needs_oligotm = pytest.mark.skipif(OLIGOTM is None, reason="primer3's oligotm is not installed")
needs_ntthal = pytest.mark.skipif(NTTHAL is None, reason="primer3's ntthal is not installed")
needs_getorf = pytest.mark.skipif(GETORF is None, reason="EMBOSS getorf is not installed")

# Not a tolerance for two implementations that roughly agree: under these
# conditions the two are the same calculation, and the only difference left is
# this project rounding to two decimal places. Anything above this means a
# parameter changed.
TM_TOLERANCE = 0.01


def random_oligos(count: int, seed: int, low: int = 18, high: int = 32) -> list[str]:
    rng = random.Random(seed)
    return [
        "".join(rng.choice("ATCG") for _ in range(rng.randint(low, high)))
        for _ in range(count)
    ]


def oligotm(sequence: str) -> float:
    """primer3's melting temperature under the same stated conditions.

    `-tp 1` selects the SantaLucia 1998 table and `-sc 1` its salt correction;
    `-d` is a total oligo concentration, which is why this project splits the
    same figure across Biopython's two strand parameters.
    """
    result = subprocess.run(
        [
            str(OLIGOTM),
            "-tp", "1", "-sc", "1",
            "-mv", str(P.PCR_CONDITIONS["na_mM"]),
            "-dv", str(P.PCR_CONDITIONS["mg_mM"]),
            "-n", str(P.PCR_CONDITIONS["dntp_mM"]),
            "-d", str(P.PCR_CONDITIONS["primer_nM"]),
            sequence,
        ],
        capture_output=True, text=True, check=True,
    )
    return float(result.stdout.strip())


def ntthal(sequence: str, mode: str) -> float:
    """Free energy of the best secondary structure, in cal/mol. Zero if none."""
    result = subprocess.run(
        [
            str(NTTHAL), "-a", mode,
            "-mv", str(P.PCR_CONDITIONS["na_mM"]),
            "-dv", str(P.PCR_CONDITIONS["mg_mM"]),
            "-n", str(P.PCR_CONDITIONS["dntp_mM"]),
            "-d", str(P.PCR_CONDITIONS["primer_nM"]),
            "-s1", sequence,
        ],
        capture_output=True, text=True, check=True,
    )

    for line in result.stdout.splitlines():
        if "dG =" in line:
            return float(line.split("dG =")[1].split()[0])
    return 0.0


# --------------------------------------------------------------------------
# Melting temperature
# --------------------------------------------------------------------------

@needs_oligotm
def test_melting_temperatures_agree_with_primer3():
    """The number a student compares against IDT, NEB or a supplier's form."""
    oligos = random_oligos(200, seed=21)
    differences = [P.tm(oligo) - oligotm(oligo) for oligo in oligos]
    worst = max(abs(difference) for difference in differences)

    assert worst < TM_TOLERANCE, f"worst case {worst:.3f} °C against primer3"


@needs_oligotm
def test_the_offset_against_primer3_has_no_composition_bias():
    """A mean that cancels out can still hide a salt model that is wrong at the
    ends. The first attempt here used Owczarzy 2008, whose mean against primer3
    was a tolerable -0.4 C and which was nonetheless +0.6 on GC-rich oligos and
    -1.4 on AT-rich ones. Composition is checked separately for that reason."""
    for label, alphabet in (("gc_rich", "GCGCGCAT"), ("at_rich", "ATATATGC")):
        rng = random.Random(hash(label) % 1000)
        oligos = ["".join(rng.choice(alphabet) for _ in range(24)) for _ in range(30)]
        worst = max(abs(P.tm(oligo) - oligotm(oligo)) for oligo in oligos)

        assert worst < TM_TOLERANCE, f"{label} oligos differ by up to {worst:.3f} °C"


@needs_oligotm
def test_passing_the_whole_concentration_as_one_strand_is_the_error_this_avoids():
    """The regression this test exists for.

    `dnac1=250, dnac2=0` models primer-in-excess, which is what a PCR is, and it
    reads over a degree hotter than every calculator a reader will check against.
    If someone restores it, this fails.
    """
    from Bio.SeqUtils import MeltingTemp as mt

    oligos = random_oligos(40, seed=99)
    naive = [
        float(mt.Tm_NN(
            oligo, dnac1=P.PCR_CONDITIONS["primer_nM"], dnac2=0.0,
            Na=P.PCR_CONDITIONS["na_mM"], Mg=P.PCR_CONDITIONS["mg_mM"],
            dNTPs=P.PCR_CONDITIONS["dntp_mM"], saltcorr=5,
        )) - oligotm(oligo)
        for oligo in oligos
    ]

    assert statistics.mean(naive) > 1.0, "the convention difference has changed; re-check the mapping"


# --------------------------------------------------------------------------
# Secondary structure
# --------------------------------------------------------------------------

@needs_ntthal
def test_flagged_hairpins_are_real_ones():
    """The screen here counts complementary bases rather than folding anything,
    so the claim it has to earn is one-directional: what it flags should be
    structure that a thermodynamic model also finds. Missing marginal ones is
    documented; inventing them is not acceptable."""
    flagged = [
        oligo for oligo in random_oligos(150, seed=31)
        if P.hairpin_stem(oligo) >= P.HAIRPIN_STEM_MIN
    ]

    if not flagged:
        pytest.skip("no hairpins in this sample")

    # ntthal reports dG in cal/mol; more negative is more stable. A structure
    # this weak would not survive an annealing step, so anything at or below it
    # counts as agreement.
    confirmed = sum(1 for oligo in flagged if ntthal(oligo, "HAIRPIN") < -500)

    assert confirmed / len(flagged) > 0.75, (
        f"only {confirmed}/{len(flagged)} flagged hairpins are structures ntthal also finds"
    )


@needs_ntthal
def test_the_structure_screen_is_admitted_to_be_incomplete():
    """The honest half of the claim. A counting screen misses marginal hairpins
    that a free-energy model finds, and the documentation says so. This asserts
    the documentation is telling the truth rather than being modest."""
    oligos = random_oligos(150, seed=41)
    missed = [
        oligo for oligo in oligos
        if P.hairpin_stem(oligo) < P.HAIRPIN_STEM_MIN and ntthal(oligo, "HAIRPIN") < -2000
    ]

    assert missed, (
        "the screen caught every stable hairpin in this sample — if that holds, "
        "the documented limitation is understated and should be rewritten"
    )


# --------------------------------------------------------------------------
# Open reading frames
# --------------------------------------------------------------------------

@needs_getorf
def test_open_reading_frames_agree_with_emboss():
    """ORF finding is the oldest code in this service and the easiest to get
    subtly wrong at the frame boundaries. EMBOSS has been finding them since
    1998."""
    rng = random.Random(7)
    sequence = "ATG" + "".join(rng.choice("ATCG") for _ in range(1200)) + "TAA"

    with tempfile.TemporaryDirectory() as directory:
        path = Path(directory) / "input.fasta"
        path.write_text(f">validation\n{sequence}\n", encoding="utf-8")

        result = subprocess.run(
            [
                str(GETORF), "-sequence", str(path),
                # `-find 3` reports nucleotide regions between stop codons in
                # all six frames, which is the same question this service asks.
                "-find", "3", "-minsize", "150",
                "-outseq", "stdout", "-auto",
            ],
            capture_output=True, text=True, check=True,
        )

    reference = {
        len(line.strip())
        for block in result.stdout.split(">")[1:]
        for line in ["".join(block.splitlines()[1:])]
    }

    ours = find_open_reading_frames(sequence, top=50)
    lengths = {orf["length_bp"] for orf in ours["top"]}

    # Not set equality: the two tools differ in whether the stop codon is
    # included and in how they treat frames that run off the end. What must
    # hold is that the longest thing each finds is the same feature.
    assert reference, "EMBOSS found no ORFs in a sequence that contains them"
    assert lengths, "this service found no ORFs where EMBOSS did"
    assert abs(max(lengths) - max(reference)) <= 3, (
        f"longest ORF differs: ours {max(lengths)} bp, EMBOSS {max(reference)} bp"
    )
