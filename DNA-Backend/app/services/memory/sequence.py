"""Reading the register's own bases to decide which way round to build it.

A recombinase memory works by inverting a piece of DNA. The two states are the
same bases in opposite orientations — and that is exactly why the orientation is
a design decision rather than a coin toss.

Inverting a sequence does not preserve its regulatory content. A run of bases
that is inert on one strand can read as a promoter on the other, because the
-35 and -10 elements RNA polymerase looks for are not palindromic. So one
orientation of the register can be quiet while the other quietly transcribes
into the neighbouring gene, or terminates a transcript that was supposed to
continue. The strand a sequence is read from is part of what it means.

Everything scanned here comes from the four bases and nothing else:

  cryptic promoters   -35 / -10 hexamers at a plausible spacing, either strand
  intrinsic terminators   a GC-rich hairpin followed by a run of T
  inverted repeats    hairpin potential, and homology that invites the wrong
                      recombination
  homopolymers        runs that make synthesis and replication slip
  GC content and skew

None of it is a substitute for measuring the construct. It is the set of
problems that are visible in the sequence before anyone spends money on it.
"""

from __future__ import annotations

import math
import re
from dataclasses import dataclass, field
from typing import Any

COMPLEMENT = str.maketrans("ACGTNRYSWKMBDHVacgtn", "TGCANYRSWMKVHDBtgcan")

# Sigma-70 consensus. Real promoters rarely match either hexamer perfectly; the
# scan therefore scores matches rather than demanding them, and only reports a
# hit when both halves are close and the spacing between them is one polymerase
# can actually bridge.
MINUS_35 = "TTGACA"
MINUS_10 = "TATAAT"
SPACER_RANGE = (15, 19)
PROMOTER_THRESHOLD = 9      # combined matches out of 12
STRONG_PROMOTER = 10

STEM_MIN = 5
LOOP_RANGE = (3, 9)
TERMINATOR_TAIL = 4
HOMOPOLYMER_MIN = 6
REPEAT_MIN = 12


def reverse_complement(sequence: str) -> str:
    return sequence.translate(COMPLEMENT)[::-1]


def clean(sequence: str) -> str:
    """Strip FASTA headers, whitespace and anything that is not a base."""
    lines = [line for line in sequence.splitlines() if not line.startswith(">")]
    joined = "".join(lines).upper()
    return re.sub(r"[^ACGTN]", "", joined)


# --------------------------------------------------------------------------
# Findings
# --------------------------------------------------------------------------

@dataclass
class Finding:
    kind: str
    start: int          # 1-based, on the orientation being scanned
    end: int
    strand: str         # + or -
    score: float
    detail: str = ""

    def as_dict(self) -> dict[str, Any]:
        return {
            "kind": self.kind, "start": self.start, "end": self.end,
            "strand": self.strand, "score": round(self.score, 3), "detail": self.detail,
        }


@dataclass
class OrientationReport:
    label: str
    length: int
    gc_percent: float
    gc_skew: float
    promoters: list[Finding] = field(default_factory=list)
    terminators: list[Finding] = field(default_factory=list)
    repeats: list[Finding] = field(default_factory=list)
    homopolymers: list[Finding] = field(default_factory=list)
    risk: float = 0.0

    def as_dict(self) -> dict[str, Any]:
        return {
            "label": self.label,
            "length": self.length,
            "gc_percent": round(self.gc_percent, 2),
            "gc_skew": round(self.gc_skew, 4),
            "promoters": [item.as_dict() for item in self.promoters],
            "terminators": [item.as_dict() for item in self.terminators],
            "repeats": [item.as_dict() for item in self.repeats],
            "homopolymers": [item.as_dict() for item in self.homopolymers],
            "counts": {
                "promoters": len(self.promoters),
                "terminators": len(self.terminators),
                "repeats": len(self.repeats),
                "homopolymers": len(self.homopolymers),
                # The two numbers the orientation choice actually turns on.
                "promoters_outward": sum(1 for item in self.promoters if item.strand == "+"),
                "promoters_inward": sum(1 for item in self.promoters if item.strand == "-"),
            },
            "strongest_outward": round(
                max((item.score for item in self.promoters if item.strand == "+"), default=0.0), 3
            ),
            "risk": round(self.risk, 3),
        }


# --------------------------------------------------------------------------
# Scans
# --------------------------------------------------------------------------

def _matches(window: str, consensus: str) -> int:
    return sum(1 for index, base in enumerate(window) if base == consensus[index])


def find_promoters(sequence: str, strand: str = "+") -> list[Finding]:
    """Score every position for a sigma-70 promoter and keep the plausible ones.

    A promoter is two short, degenerate hexamers separated by a spacer whose
    *length* matters more than its sequence, because the spacer's only job is to
    put the two hexamers on the same face of the helix. So the scan walks every
    -35 candidate, looks for a -10 at each allowed spacing, and scores the pair
    together — a good -35 with no -10 in reach is not a promoter.
    """
    found: list[Finding] = []
    low, high = SPACER_RANGE

    for start in range(len(sequence) - (6 + high + 6)):
        first = sequence[start:start + 6]
        score_35 = _matches(first, MINUS_35)
        if score_35 < 3:
            continue

        # Every allowed spacing is tried and the best kept. Stopping at the
        # first one over the threshold reports a real promoter at the wrong
        # spacing and the wrong strength, because a weak -10 nearer the -35
        # gets found before the strong one further along.
        best: Finding | None = None
        for spacer in range(low, high + 1):
            second_start = start + 6 + spacer
            second = sequence[second_start:second_start + 6]
            if len(second) < 6:
                continue

            score_10 = _matches(second, MINUS_10)
            total = score_35 + score_10
            if total < PROMOTER_THRESHOLD or score_10 < 4:
                continue

            if best is None or total / 12.0 > best.score:
                best = Finding(
                    kind="promoter",
                    start=start + 1,
                    end=second_start + 6,
                    strand=strand,
                    score=total / 12.0,
                    detail=f"{first}-N{spacer}-{second}",
                )

        if best is not None:
            found.append(best)

    return found


def find_terminators(sequence: str, strand: str = "+") -> list[Finding]:
    """A GC-rich hairpin followed closely by a run of T.

    That combination is what makes RNA polymerase let go without help: the
    hairpin stalls it and the weak rU-dA duplex behind it slips. Inside an
    inverted register an unintended one truncates whatever was meant to be
    transcribed through.
    """
    found: list[Finding] = []
    loop_low, loop_high = LOOP_RANGE

    for start in range(len(sequence) - 40):
        for stem in range(STEM_MIN, 11):
            for loop in range(loop_low, loop_high + 1):
                left = sequence[start:start + stem]
                right_start = start + stem + loop
                right = sequence[right_start:right_start + stem]
                if len(right) < stem:
                    continue
                if left != reverse_complement(right):
                    continue

                tail = sequence[right_start + stem:right_start + stem + 8]
                thymines = tail[:TERMINATOR_TAIL].count("T")
                if thymines < TERMINATOR_TAIL - 1:
                    continue

                gc = sum(1 for base in left if base in "GC") / stem
                found.append(Finding(
                    kind="terminator",
                    start=start + 1,
                    end=right_start + stem + TERMINATOR_TAIL,
                    strand=strand,
                    score=gc * (stem / 10.0),
                    detail=f"stem {stem} bp, loop {loop} nt, GC {round(gc * 100)}%",
                ))
                break
            else:
                continue
            break

    return found


def find_inverted_repeats(sequence: str) -> list[Finding]:
    """Long repeats that give the cell somewhere to recombine that you did not choose.

    Homologous stretches inside one construct are a standing invitation to
    RecA: the region between them can be deleted or inverted spontaneously,
    which in a memory circuit means the register changing state with no signal
    and no integrase involved.
    """
    found: list[Finding] = []
    seen: set[str] = set()
    length = len(sequence)

    for start in range(length - REPEAT_MIN):
        window = sequence[start:start + REPEAT_MIN]
        if "N" in window or window in seen:
            continue

        target = reverse_complement(window)
        position = sequence.find(target, start + REPEAT_MIN)
        if position != -1:
            seen.add(window)
            found.append(Finding(
                kind="inverted_repeat",
                start=start + 1,
                end=position + REPEAT_MIN,
                strand="±",
                score=REPEAT_MIN / 20.0,
                detail=f"{window} at {start + 1} and {position + 1}",
            ))

        direct = sequence.find(window, start + REPEAT_MIN)
        if direct != -1:
            seen.add(window)
            found.append(Finding(
                kind="direct_repeat",
                start=start + 1,
                end=direct + REPEAT_MIN,
                strand="+",
                score=REPEAT_MIN / 20.0,
                detail=f"{window} at {start + 1} and {direct + 1}",
            ))

    return found[:12]


def find_homopolymers(sequence: str) -> list[Finding]:
    """Runs of one base: hard to synthesise, and unstable once replicated."""
    return [
        Finding(
            kind="homopolymer",
            start=match.start() + 1,
            end=match.end(),
            strand="+",
            score=min(1.0, (match.end() - match.start()) / 12.0),
            detail=f"{match.group()[0]} x {match.end() - match.start()}",
        )
        for match in re.finditer(_HOMOPOLYMER_PATTERN, sequence)
    ]


# --------------------------------------------------------------------------
# Composition
# --------------------------------------------------------------------------

def composition(sequence: str) -> tuple[float, float]:
    """GC percentage, and GC skew — (G−C)/(G+C).

    Skew is included because a strongly skewed insert behaves differently
    depending on which way round it sits relative to the replication fork; a
    register that is skewed one way in state 0 is skewed the other way in
    state 1, and that asymmetry is real even when nothing else distinguishes
    the two orientations.
    """
    if not sequence:
        return 0.0, 0.0

    g = sequence.count("G")
    c = sequence.count("C")
    gc = (g + c) / len(sequence) * 100.0
    skew = (g - c) / (g + c) if (g + c) else 0.0
    return gc, skew


# --------------------------------------------------------------------------
# One orientation, and then the comparison
# --------------------------------------------------------------------------

# How much each hazard counts, by the direction it fires in.
#
# These weights are the whole comparison, so they are stated rather than
# buried. A cryptic promoter firing *outward* — past the att site and into
# whatever the register was supposed to be controlling — is the worst thing on
# the list: it makes the construct read as written when it is not, which
# downstream is indistinguishable from the memory having been set. One firing
# *inward*, back across the integrase cassette, is milder but not harmless: it
# runs antisense to the enzyme and puts two polymerases on the same DNA head
# to head.
OUTWARD_PROMOTER = 3.0
INWARD_PROMOTER = 1.5
OUTWARD_TERMINATOR = 1.5
INWARD_TERMINATOR = 0.4
REPEAT_WEIGHT = 1.0
HOMOPOLYMER_WEIGHT = 0.5


def analyse(sequence: str, label: str, sigma70: bool = True) -> OrientationReport:
    """Scan one orientation, recording which way round each hazard points.

    Strand is recorded relative to the construct, not to the payload: `+` means
    a feature that fires away from the integrase cassette and out through the
    far att site, `-` means one firing back the other way. That is the only
    thing inverting the register changes about these features, and it is the
    entire reason orientation is a decision.
    """
    gc, skew = composition(sequence)
    report = OrientationReport(label=label, length=len(sequence), gc_percent=gc, gc_skew=skew)

    if sigma70:
        report.promoters = find_promoters(sequence, "+") + find_promoters(
            reverse_complement(sequence), "-"
        )
        report.terminators = find_terminators(sequence, "+") + find_terminators(
            reverse_complement(sequence), "-"
        )

    # Repeats and homopolymers are the same bases whichever way round the
    # register sits, so they raise the absolute risk of the design and cannot
    # discriminate between the two orientations. They are counted, and the
    # comparison does not lean on them.
    report.repeats = find_inverted_repeats(sequence)
    report.homopolymers = find_homopolymers(sequence)

    outward = sum(item.score for item in report.promoters if item.strand == "+")
    inward = sum(item.score for item in report.promoters if item.strand == "-")
    stop_out = sum(item.score for item in report.terminators if item.strand == "+")
    stop_in = sum(item.score for item in report.terminators if item.strand == "-")

    report.risk = (
        OUTWARD_PROMOTER * outward
        + INWARD_PROMOTER * inward
        + OUTWARD_TERMINATOR * stop_out
        + INWARD_TERMINATOR * stop_in
        + REPEAT_WEIGHT * sum(item.score for item in report.repeats)
        + HOMOPOLYMER_WEIGHT * sum(item.score for item in report.homopolymers)
    )
    return report


def compare_orientations(payload: str, sigma70: bool = True) -> dict[str, Any]:
    """Score the register both ways round and say which way to build it.

    Inverting the payload does not change which hazards it contains — the bases
    are the same bases. What it changes is the direction each one fires in
    relative to the rest of the construct, and since the construct is not
    symmetric about the register, the two orientations are not equally safe.

    Scanning the reverse complement and re-scoring is therefore not redundant
    work: a promoter that read outward now reads inward, and its weight changes
    with it. When the totals come out equal the payload simply has nothing that
    points anywhere in particular, and the honest answer is that the sequence
    does not decide — pick the orientation on the biology instead.
    """
    forward = analyse(payload, "forward", sigma70)
    reverse = analyse(reverse_complement(payload), "reverse", sigma70)

    difference = forward.risk - reverse.risk

    # A single mid-strength cryptic promoter moves the total by about 1.5, and
    # a permissive scan finds one of those in almost any few hundred bases of
    # random sequence. Calling an orientation on that would be reading noise, so
    # the threshold is set where a real, strong asymmetry is needed to trigger a
    # recommendation at all.
    if abs(difference) < 1.5:
        preferred = "either"
    else:
        preferred = "forward" if difference < 0 else "reverse"

    return {
        "forward": forward.as_dict(),
        "reverse": reverse.as_dict(),
        "preferred": preferred,
        "difference": round(abs(difference), 3),
        "decided_by_sequence": preferred != "either",
    }


def synthesis_difficulty(sequence: str) -> dict[str, Any]:
    """What a synthesis provider will object to before they quote you.

    Extreme GC in either direction, long homopolymers and long repeats are the
    three reasons a sequence comes back as "complex" — which in practice means
    slower, dearer, or declined.
    """
    gc, _ = composition(sequence)
    homopolymers = find_homopolymers(sequence)
    repeats = find_inverted_repeats(sequence)
    longest_run = max((item.end - item.start + 1 for item in homopolymers), default=0)

    reasons: list[str] = []
    if gc < 30 or gc > 70:
        reasons.append("gc_extreme")
    if longest_run >= 9:
        reasons.append("homopolymer")
    if len(repeats) >= 4:
        reasons.append("repeats")

    return {
        "gc_percent": round(gc, 2),
        "longest_homopolymer": longest_run,
        "repeat_count": len(repeats),
        "reasons": reasons,
        "difficult": bool(reasons),
    }


def hairpin_energy(stem: str) -> float:
    """A crude stability proxy for a hairpin stem, in arbitrary negative units.

    Not a nearest-neighbour calculation and not presented as one — G:C pairs
    are counted at three and A:T at two, which is enough to rank two stems and
    nowhere near enough to predict a melting temperature.
    """
    return -sum(3.0 if base in "GC" else 2.0 for base in stem)


def to_fasta(name: str, sequence: str, width: int = 70, header_notes: list[str] | None = None) -> str:
    lines = list(header_notes or [])
    lines.append(f">{name} length={len(sequence)}bp")
    lines.extend(sequence[index:index + width] for index in range(0, len(sequence), width))
    return "\n".join(lines) + "\n"


def gc_ramp(sequence: str, window: int = 50) -> list[float]:
    """GC in a sliding window, for drawing the register's composition."""
    if len(sequence) < window:
        return [composition(sequence)[0]] if sequence else []

    step = max(1, len(sequence) // 120)
    values: list[float] = []
    for start in range(0, len(sequence) - window + 1, step):
        chunk = sequence[start:start + window]
        values.append(round((chunk.count("G") + chunk.count("C")) / window * 100.0, 2))
    return values


def entropy(sequence: str) -> float:
    """Shannon entropy over the four bases, in bits.

    A payload close to 2 bits is compositionally balanced. Well below it means
    the sequence is dominated by one or two bases, which is both a synthesis
    problem and a sign the register will behave differently in its two
    orientations.
    """
    if not sequence:
        return 0.0

    total = len(sequence)
    value = 0.0
    for base in "ACGT":
        count = sequence.count(base)
        if count:
            share = count / total
            value -= share * math.log2(share)
    return value
