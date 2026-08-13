"""Build the teaching samples, and refuse to write one that does not teach.

Each sample exists to make one specific thing visible when it is run through the
real service. A file that no longer produces that result is worse than no file:
a lesson plan that quietly stopped working wastes a class's time and the
instructor finds out in front of them.

So this is a generator with assertions rather than a folder of sequences. Run it
after any change to the analysis or cloning services:

    python tools/build_samples.py

It writes nothing unless every check passes.
"""

from __future__ import annotations

import random
import sys
import textwrap
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.services import cloning, compare  # noqa: E402
from app.services import sequence as seq  # noqa: E402

OUT = Path(__file__).resolve().parent.parent.parent / "DNA-Frontend" / "resources" / "samples"

CODONS = {
    "A": ["GCT", "GCC", "GCA", "GCG"], "R": ["CGT", "CGC", "CGA"],
    "N": ["AAT", "AAC"], "D": ["GAT", "GAC"], "C": ["TGT", "TGC"],
    "E": ["GAA", "GAG"], "Q": ["CAA", "CAG"], "G": ["GGT", "GGC", "GGA"],
    "H": ["CAT", "CAC"], "I": ["ATT", "ATC"], "L": ["CTT", "CTC", "CTG"],
    "K": ["AAA", "AAG"], "M": ["ATG"], "F": ["TTT", "TTC"],
    "P": ["CCT", "CCC", "CCA"], "S": ["TCT", "TCC", "AGT"],
    "T": ["ACT", "ACC", "ACA"], "W": ["TGG"], "Y": ["TAT", "TAC"],
    "V": ["GTT", "GTC", "GTA"],
}

COMPLEMENT = str.maketrans("ATCG", "TAGC")


def revcomp(text: str) -> str:
    return text.translate(COMPLEMENT)[::-1]


def wrap(text: str) -> str:
    return "\n".join(textwrap.wrap(text, 60))


def record(name: str, description: str, bases: str) -> str:
    return f">{name} {description}\n{wrap(bases)}\n"


def coding(rng: random.Random, residues: int) -> str:
    """A plausible coding sequence: start, sense codons, stop."""
    protein = [rng.choice("ARNDCEQGHILKFPSTWYV") for _ in range(residues)]
    body = "".join(rng.choice(CODONS[residue]) for residue in protein)
    return "ATG" + body + "TAA"


def biased(rng: random.Random, length: int, g: float, c: float, a: float, t: float) -> str:
    """Random sequence with a stated base composition, for skew."""
    return "".join(rng.choices("GCAT", weights=[g, c, a, t], k=length))


def strip_sites(text: str, *sites: str) -> str:
    """Remove recognition sites without changing length or composition much."""
    for site in sites:
        for alternative in (site[:-1] + "A", site[:-1] + "T", site[:-1] + "G"):
            if alternative != site:
                text = text.replace(site, alternative)
                break
    return text


# --------------------------------------------------------------------------
# The samples
# --------------------------------------------------------------------------

def sample_gc_skew() -> tuple[str, str]:
    """One replichore rich in G, the other rich in C, with a sharp switch.

    In a real bacterial chromosome the leading strand accumulates G over C, so
    the skew flips sign at the origin and again at the terminus. It is the
    cleanest example of a statistic that means nothing base by base and a great
    deal cumulatively.
    """
    rng = random.Random(101)
    left = biased(rng, 1500, g=34, c=16, a=25, t=25)
    right = biased(rng, 1500, g=16, c=34, a=25, t=25)
    return "gc-skew.fasta", record(
        "skew_demo", "engineered replichore switch at position 1500", left + right
    )


def sample_variants() -> tuple[str, str]:
    """A reference and a variant differing by one change of each consequence."""
    rng = random.Random(202)
    reference = coding(rng, 240)

    variant = list(reference)

    def find_codon(codon: str, after: int) -> int:
        """First codon-aligned occurrence at or after `after`.

        Aligned deliberately: a CAA that straddles a codon boundary is not a
        glutamine, and changing it produces a missense rather than the stop this
        sample is supposed to demonstrate. Searching from a 1-based position
        rather than a codon index is exactly how that goes wrong.
        """
        start = after + (-after % 3)
        for index in range(start, len(reference) - 3, 3):
            if reference[index:index + 3] == codon:
                return index
        raise AssertionError(f"no aligned {codon} after {after}")

    # Synonymous: third base of a leucine codon, CTT -> CTC.
    at_synonymous = find_codon("CTT", 3)
    variant[at_synonymous + 2] = "C"

    # Missense: first base of a glycine codon, GGT -> AGT (Gly -> Ser).
    at_missense = find_codon("GGT", at_synonymous + 30)
    variant[at_missense] = "A"

    # Nonsense: CAA -> TAA, a glutamine that becomes a stop.
    at_nonsense = find_codon("CAA", at_missense + 60)
    variant[at_nonsense] = "T"

    return "variants.fasta", (
        record("reference", "unmutated coding sequence", reference)
        + record(
            "variant",
            f"three substitutions: synonymous {at_synonymous + 3}, "
            f"missense {at_missense + 1}, nonsense {at_nonsense + 1}",
            "".join(variant),
        )
    )


def sample_reverse_orf() -> tuple[str, str]:
    """The longest reading frame is on the strand nobody looks at first."""
    rng = random.Random(303)
    gene = coding(rng, 210)
    filler_left = biased(rng, 400, g=25, c=25, a=25, t=25)
    filler_right = biased(rng, 400, g=25, c=25, a=25, t=25)
    return "reverse-strand-orf.fasta", record(
        "hidden_gene",
        "the longest reading frame runs right to left",
        filler_left + revcomp(gene) + filler_right,
    )


def sample_ambiguity() -> tuple[str, str]:
    """Real sequencing output has positions the machine would not commit to."""
    rng = random.Random(404)
    bases = list(coding(rng, 180))
    for position in (61, 130, 244, 331, 402):
        bases[position] = rng.choice("NRYWSKM")
    return "ambiguous-bases.fasta", record(
        "sanger_read", "five positions the basecaller left ambiguous", "".join(bases)
    )


def sample_cloning_trap() -> tuple[str, str]:
    """An insert that carries the site you were about to clone it with.

    The exercise this dataset exists for. A reader asked to clone this with
    EcoRI and XhoI tails gets a plan that looks correct everywhere except one
    warning, and the warning is the entire lesson.
    """
    rng = random.Random(505)
    left = strip_sites(biased(rng, 300, 25, 25, 25, 25), "GAATTC", "CTCGAG")
    right = strip_sites(biased(rng, 300, 25, 25, 25, 25), "GAATTC", "CTCGAG")
    return "cloning-trap.fasta", record(
        "gene_of_interest",
        "amplify 20..580 with EcoRI and XhoI tails, then read the warning",
        left + "GAATTC" + right,
    )


def sample_plasmid() -> tuple[str, str]:
    """The same molecule is two different digests depending on its topology."""
    rng = random.Random(606)
    body = strip_sites(biased(rng, 2000, 25, 25, 25, 25), "GAATTC")
    return "plasmid-topology.fasta", record(
        "mini_plasmid",
        "analyse once as linear and once as circular, and count the bands",
        body[:900] + "GAATTC" + body[900:],
    )


BUILDERS = [
    sample_gc_skew,
    sample_variants,
    sample_reverse_orf,
    sample_ambiguity,
    sample_cloning_trap,
    sample_plasmid,
]


# --------------------------------------------------------------------------
# The assertions: does each sample still teach what it claims?
# --------------------------------------------------------------------------

def bases_of(text: str, index: int = 0) -> str:
    blocks = text.split(">")[1:]
    return "".join(blocks[index].splitlines()[1:])


def check(name: str, content: str) -> list[str]:
    """Return a list of failures. Empty means the sample still works."""
    failures = []

    if name == "gc-skew.fasta":
        bases = bases_of(content)
        left, right = bases[:1500], bases[1500:]

        def skew(text: str) -> float:
            g, c = text.count("G"), text.count("C")
            return (g - c) / (g + c)

        if not (skew(left) > 0.2 > -0.2 > skew(right)):
            failures.append(f"skew does not flip sharply: {skew(left):+.2f} then {skew(right):+.2f}")

    if name == "variants.fasta":
        found = compare.compare_pair(
            "reference", bases_of(content, 0), "variant", bases_of(content, 1)
        )
        effects = found["effects"]
        for wanted in ("synonymous", "missense", "nonsense"):
            if effects.get(wanted, 0) < 1:
                failures.append(f"no {wanted} change is detected; effects were {effects}")
        if found["counts"]["substitution"] != 3:
            failures.append(f"expected exactly three substitutions, found {found['counts']}")

    if name == "reverse-strand-orf.fasta":
        found = seq.find_open_reading_frames(bases_of(content), top=5)
        longest = found.get("longest") or {}
        if longest.get("strand") != "-":
            failures.append(f"longest ORF is on the {longest.get('strand')} strand, not the reverse")
        if longest.get("length_bp", 0) < 500:
            failures.append(f"longest ORF is only {longest.get('length_bp')} bp")

    if name == "ambiguous-bases.fasta":
        bases = bases_of(content)
        if sum(1 for base in bases if base not in "ATCG") != 5:
            failures.append("the read no longer carries exactly five ambiguous positions")

    if name == "cloning-trap.fasta":
        plan = cloning.plan({
            "sequence": bases_of(content),
            "target": {"start": 20, "end": 580},
            "tails": {"forward_enzyme": "EcoRI", "reverse_enzyme": "XhoI"},
        })
        codes = {item["code"] for item in plan["diagnostics"]}
        if cloning.Code.TAIL_SITE_CUTS_AMPLICON not in codes:
            failures.append("the trap no longer springs: no tail-site warning")
        if plan["primers"] is None:
            failures.append("no primer pair is designed, so there is nothing to warn about")

    if name == "plasmid-topology.fasta":
        bases = bases_of(content)
        linear = cloning.plan({"sequence": bases, "enzymes": ["EcoRI"], "design_primers": False})
        circular = cloning.plan({
            "sequence": bases, "enzymes": ["EcoRI"], "circular": True, "design_primers": False,
        })
        linear_bands = len(linear["digest"]["enzymes"][0]["fragments"])
        circular_bands = len(circular["digest"]["enzymes"][0]["fragments"])
        if (linear_bands, circular_bands) != (2, 1):
            failures.append(
                f"the topology lesson is gone: {linear_bands} linear bands, {circular_bands} circular"
            )

    return failures


def main() -> int:
    built = dict(builder() for builder in BUILDERS)

    problems = {name: check(name, content) for name, content in built.items()}
    broken = {name: issues for name, issues in problems.items() if issues}

    if broken:
        print("Not writing anything. These samples no longer teach what they claim:\n")
        for name, issues in broken.items():
            print(f"  {name}")
            for issue in issues:
                print(f"      {issue}")
        return 1

    OUT.mkdir(parents=True, exist_ok=True)
    for name, content in built.items():
        (OUT / name).write_text(content, encoding="utf-8")
        print(f"  wrote {name:28} {len(bases_of(content)):>6} bp")

    print(f"\n{len(built)} samples written to {OUT}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
