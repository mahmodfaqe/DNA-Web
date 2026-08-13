"""Primer design for a PCR that a student will actually run.

Scope, stated plainly: this is a **teaching-grade designer**, not Primer3. It
scores candidates on the properties that decide whether a reaction works, it
reports every criterion it used, and it never returns a pair without also
returning what was wrong with it. It does not do genome-wide specificity — the
uniqueness check here is against the submitted template only, which is a much
weaker claim and is labelled as one.

Where the numbers come from
---------------------------
Melting temperature is nearest-neighbour (SantaLucia 1998 via Biopython) under a
single, named set of PCR conditions: 250 nM total oligo, 50 mM Na+, 1.5 mM Mg2+,
0.2 mM dNTPs.

The concentration is split evenly across `dnac1` and `dnac2` rather than passed
whole as `dnac1` with `dnac2=0`. Both are defensible models — the second is
primer-in-excess, which is physically what a PCR is — but they are not the same
number, and the first is what every calculator a student will check against uses.
Passing 250 as `dnac1` alone reads **1.3 °C hotter** than primer3 at the same
nominal concentration, and since the suggested annealing temperature is derived
by subtracting five, that difference lands directly on the thermocycler. Being
"more physically apt" and 1.3 °C away from IDT, NEB and primer3 is the wrong
trade for a tool whose output gets typed into a machine. See docs/VALIDATION.md.

Secondary structure is **not** thermodynamic here. A real hairpin or dimer
prediction folds the sequence and reports ΔG; this counts the longest
complementary stretch, and weights complementarity at the 3' end heavily because
that is the end a polymerase extends from. It catches the primers that obviously
cannot work and will miss marginal ones. Anything it flags is worth looking at
in a real folding tool before ordering.
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any

from Bio.SeqUtils import MeltingTemp as mt

# --------------------------------------------------------------------------
# Conditions and defaults
# --------------------------------------------------------------------------

# The conditions themselves, as numbers. Named separately from the payload
# below because they are arithmetic, not description — a mixed dict of floats
# and model names types as `object` and cannot be divided.
PRIMER_NM = 250.0
NA_MM = 50.0
MG_MM = 1.5
DNTP_MM = 0.2

# A named condition set, reported with every result so a Tm printed by this tool
# can be reproduced rather than trusted. The two convention fields are here
# because they are the difference between this tool's number and a supplier's,
# and are invisible in the result otherwise.
PCR_CONDITIONS: dict[str, Any] = {
    "primer_nM": PRIMER_NM,
    "na_mM": NA_MM,
    "mg_mM": MG_MM,
    "dntp_mM": DNTP_MM,
    "model": "santalucia_1998_nearest_neighbour",
    "salt_correction": "santalucia_1998",
    "strand_convention": "total_oligo_split_evenly",
}

MIN_LENGTH = 18
MAX_LENGTH = 30
DEFAULT_TARGET_TM = 60.0

# A pair whose members anneal more than this far apart cannot share one
# annealing step: the colder primer is either unbound or the hotter one is
# priming non-specifically.
MAX_PAIR_TM_DELTA = 3.0

GC_MIN_PERCENT = 40.0
GC_MAX_PERCENT = 60.0

# Four or more of the same base in a row slips during synthesis and during
# extension; runs of G are the worst because they stack.
MAX_HOMOPOLYMER_RUN = 4

# Complementary stretches at or above these lengths are reported. The 3' number
# is lower because three complementary bases at the extendable end are enough to
# prime a dimer, while the same three in the middle are harmless.
SELF_DIMER_MIN = 5
THREE_PRIME_DIMER_MIN = 3
HAIRPIN_STEM_MIN = 4
HAIRPIN_LOOP_MIN = 3

_COMPLEMENT = str.maketrans("ATCG", "TAGC")


def _revcomp(sequence: str) -> str:
    return sequence.translate(_COMPLEMENT)[::-1]


def tm(sequence: str) -> float | None:
    """Nearest-neighbour Tm under PCR_CONDITIONS, or None if not computable."""
    clean = "".join(base for base in sequence.upper() if base in "ATCG")
    if len(clean) < 8:
        return None
    try:
        # The stated concentration is the total oligo, split evenly — the
        # mapping Biopython's own documentation gives for a primer3 `-d` value.
        half = PRIMER_NM / 2
        return round(float(mt.Tm_NN(
            clean,
            dnac1=half,
            dnac2=half,
            Na=NA_MM,
            Mg=MG_MM,
            dNTPs=DNTP_MM,
            # saltcorr=5 rather than the newer 7. Under these conditions it is
            # bit-identical to primer3's default across every composition
            # tested, which means any reader can verify a temperature this tool
            # prints with a one-line command instead of trusting it. Owczarzy
            # 2008 is the more modern model and reads up to 1.4 C differently
            # depending on GC content; exact reproducibility is worth more here
            # than a degree of contested accuracy. See docs/VALIDATION.md.
            saltcorr=5,
        )), 2)
    except Exception:  # pragma: no cover - Biopython edge cases
        return None


def gc_percent(sequence: str) -> float:
    if not sequence:
        return 0.0
    gc = sum(1 for base in sequence.upper() if base in "GC")
    return round(gc / len(sequence) * 100, 2)


# --------------------------------------------------------------------------
# Structural screens
# --------------------------------------------------------------------------

def longest_complement(left: str, right: str) -> int:
    """Longest run where `left` pairs with `right` read antiparallel.

    Implemented as the longest common substring between `left` and the reverse
    complement of `right`, which is the same question asked the easy way.
    """
    other = _revcomp(right.upper())
    a, b = left.upper(), other
    best = 0
    previous = [0] * (len(b) + 1)
    for i in range(1, len(a) + 1):
        current = [0] * (len(b) + 1)
        for j in range(1, len(b) + 1):
            if a[i - 1] == b[j - 1]:
                current[j] = previous[j - 1] + 1
                if current[j] > best:
                    best = current[j]
        previous = current
    return best


def three_prime_complement(left: str, right: str, window: int = 6) -> int:
    """Complementarity involving the extendable 3' end of `left`.

    A dimer only matters if a polymerase can extend from it, so only the last
    few bases are asked about.
    """
    tail = left.upper()[-window:]
    if not tail:
        return 0
    other = _revcomp(right.upper())
    best = 0
    for size in range(len(tail), 0, -1):
        if tail[-size:] in other:
            best = size
            break
    return best


def hairpin_stem(sequence: str) -> int:
    """Longest stem a primer can fold back on itself to form.

    A stem is only a hairpin if the strand can turn around between its two
    halves, so pairing stops as soon as fewer than HAIRPIN_LOOP_MIN bases would
    be left unpaired in the middle. The check is written as "after pairing this
    base, is there still a loop?" — asking it the other way round quietly allows
    a two-base loop, which no strand can actually make.
    """
    upper = sequence.upper()
    n = len(upper)
    best = 0

    for start in range(n):
        for end in range(start + HAIRPIN_LOOP_MIN + 2, n + 1):
            stem = 0
            while (
                start + stem + 1 + HAIRPIN_LOOP_MIN <= end - stem - 1
                and upper[start + stem] == _revcomp(upper[end - stem - 1])
            ):
                stem += 1
            best = max(best, stem)

    return best


def longest_run(sequence: str) -> tuple[str, int]:
    """The longest single-base run, as (base, length)."""
    if not sequence:
        return ("", 0)
    best_base, best = sequence[0], 1
    current, run = sequence[0], 1
    for base in sequence[1:]:
        if base == current:
            run += 1
        else:
            current, run = base, 1
        if run > best:
            best_base, best = current, run
    return (best_base, best)


def has_gc_clamp(sequence: str) -> bool:
    """A G or C in the last two bases, anchoring the extendable end."""
    return any(base in "GC" for base in sequence.upper()[-2:])


def gc_clamp_overloaded(sequence: str) -> bool:
    """More than three G/C in the last five: binds too tightly, mispriming."""
    return sum(1 for base in sequence.upper()[-5:] if base in "GC") > 3


# --------------------------------------------------------------------------
# Candidate scoring
# --------------------------------------------------------------------------

@dataclass
class Candidate:
    sequence: str
    start: int          # 1-based on the plus strand
    end: int            # 1-based inclusive
    direction: str      # "forward" | "reverse"
    tm: float
    gc: float
    penalty: float
    flags: list[str]


def _score(sequence: str, target_tm: float) -> tuple[float, list[str]]:
    """Lower is better. Every contribution is a property, not a magic weight."""
    flags: list[str] = []
    value = tm(sequence)
    if value is None:
        return (1e9, ["no_tm"])

    penalty = abs(value - target_tm) * 2.0

    gc = gc_percent(sequence)
    if gc < GC_MIN_PERCENT or gc > GC_MAX_PERCENT:
        penalty += min(abs(gc - GC_MIN_PERCENT), abs(gc - GC_MAX_PERCENT))
        flags.append("gc_out_of_range")

    if not has_gc_clamp(sequence):
        penalty += 4.0
        flags.append("no_gc_clamp")

    if gc_clamp_overloaded(sequence):
        penalty += 3.0
        flags.append("gc_clamp_overloaded")

    _, run = longest_run(sequence)
    if run >= MAX_HOMOPOLYMER_RUN:
        penalty += (run - MAX_HOMOPOLYMER_RUN + 1) * 3.0
        flags.append("homopolymer_run")

    self_dimer = longest_complement(sequence, sequence)
    if self_dimer >= SELF_DIMER_MIN:
        penalty += (self_dimer - SELF_DIMER_MIN + 1) * 2.0
        flags.append("self_dimer")

    if three_prime_complement(sequence, sequence) >= THREE_PRIME_DIMER_MIN:
        penalty += 5.0
        flags.append("three_prime_self_dimer")

    if hairpin_stem(sequence) >= HAIRPIN_STEM_MIN:
        penalty += 4.0
        flags.append("hairpin")

    return (penalty, flags)


def _candidates(
    template: str,
    anchor: int,
    direction: str,
    target_tm: float,
    min_length: int,
    max_length: int,
) -> list[Candidate]:
    """Primers that begin (forward) or end (reverse) at or near `anchor`.

    `anchor` is a 0-based index into the plus strand. A forward primer starts
    there and runs right; a reverse primer covers the region ending there and is
    reported as the reverse complement, which is what gets ordered.
    """
    found: list[Candidate] = []
    length_range = range(min_length, max_length + 1)

    # Allowing the anchor to drift a few bases keeps a good primer reachable
    # when the exact boundary happens to sit in an AT-rich stretch.
    for shift in range(0, 6):
        for size in length_range:
            if direction == "forward":
                start = anchor + shift
                stop = start + size
                if stop > len(template):
                    continue
                binding = template[start:stop]
                first, last = start + 1, stop
            else:
                stop = anchor - shift + 1
                start = stop - size
                if start < 0:
                    continue
                binding = _revcomp(template[start:stop])
                first, last = start + 1, stop

            if any(base not in "ATCG" for base in binding):
                continue

            value = tm(binding)
            if value is None:
                continue

            penalty, flags = _score(binding, target_tm)
            # A primer whose boundary drifted is slightly worse than one that
            # sits exactly where the user asked for.
            penalty += shift * 0.5

            found.append(Candidate(
                sequence=binding,
                start=first,
                end=last,
                direction=direction,
                tm=value,
                gc=gc_percent(binding),
                penalty=round(penalty, 3),
                flags=flags,
            ))

    found.sort(key=lambda item: item.penalty)
    return found


def _occurrences(template: str, primer: str) -> int:
    """How many times a primer's 3' half matches the template, either strand.

    The 3' half is what determines priming; a mismatch at the 5' end is
    tolerated by the polymerase and by this count.
    """
    probe = primer[-(len(primer) // 2):]
    if len(probe) < 8:
        probe = primer
    plus = template.count(probe)
    minus = template.count(_revcomp(probe))
    return plus + minus


def _as_dict(candidate: Candidate, template: str) -> dict[str, Any]:
    return {
        "sequence": candidate.sequence,
        "length": len(candidate.sequence),
        "start": candidate.start,
        "end": candidate.end,
        "direction": candidate.direction,
        "tm": candidate.tm,
        "gc_percent": candidate.gc,
        "gc_clamp": has_gc_clamp(candidate.sequence),
        "longest_run": longest_run(candidate.sequence)[1],
        "self_dimer_bp": longest_complement(candidate.sequence, candidate.sequence),
        "hairpin_stem_bp": hairpin_stem(candidate.sequence),
        "matches_in_template": _occurrences(template, candidate.sequence),
        "penalty": candidate.penalty,
        "flags": candidate.flags,
    }
