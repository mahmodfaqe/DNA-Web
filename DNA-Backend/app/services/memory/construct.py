"""From a chosen architecture to DNA a synthesiser will accept.

The provenance rule is the same one the compiler follows, for the same reason.
Short regulatory elements — promoters, ribosome binding sites, terminators,
recombination sites — are carried as literal sequence: they are tens of base
pairs, they are the part the tool actually *decides*, and they can be checked by
eye against the registry. Coding sequences are not. A Bxb1 integrase is 1416 bp,
and transcribing one from memory risks a silent single-base error that produces
a construct which looks right and does not work.

Att sites sit awkwardly between those two categories and are treated as literal
with a warning attached. They are short enough to include and short enough to
check — but their central dinucleotide is the entire mechanism of
directionality, so a single wrong base there does not break the construct
loudly. It builds a recombinase memory that writes in both directions, which is
not a memory at all.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

from ..biocompiler import parts as registry
from .library import Architecture, Recombinase, Signal
from .sequence import reverse_complement

LINE_WIDTH = 70


@dataclass(frozen=True)
class Segment:
    """One labelled stretch of the finished construct."""

    part_id: str
    name: str
    role: str
    provenance: str
    sequence: str
    direction: str = "forward"

    @property
    def length(self) -> int:
        return len(self.sequence)

    def as_dict(self, start: int) -> dict[str, Any]:
        return {
            "part_id": self.part_id, "name": self.name, "role": self.role,
            "provenance": self.provenance, "direction": self.direction,
            "start": start, "end": start + self.length - 1, "length": self.length,
        }


@dataclass
class Construct:
    name: str
    purpose: str
    segments: list[Segment] = field(default_factory=list)

    @property
    def sequence(self) -> str:
        return "".join(segment.sequence for segment in self.segments)

    @property
    def length(self) -> int:
        return len(self.sequence)

    @property
    def resolved_percent(self) -> float:
        if not self.length:
            return 0.0
        return round((self.length - self.sequence.count("N")) / self.length * 100, 1)

    def annotations(self) -> list[dict[str, Any]]:
        cursor = 1
        items = []
        for segment in self.segments:
            items.append(segment.as_dict(cursor))
            cursor += segment.length
        return items

    def as_dict(self) -> dict[str, Any]:
        return {
            "name": self.name,
            "purpose": self.purpose,
            "length": self.length,
            "resolved_percent": self.resolved_percent,
            "sequence": self.sequence,
            "annotations": self.annotations(),
        }


# --------------------------------------------------------------------------
# Parts this tool adds to the shared registry
# --------------------------------------------------------------------------

def _literal(part_id: str, name: str, role: str, sequence: str, direction: str = "forward") -> Segment:
    return Segment(part_id, name, role, "literal", "".join(sequence.split()).upper(), direction)


def _placeholder(part_id: str, name: str, role: str, length: int) -> Segment:
    """A coding sequence the tool refuses to guess at, sized so coordinates hold."""
    return Segment(part_id, name, role, "placeholder", "N" * length)


# Bxb1 recombination sites. The central dinucleotide (GT here) is what makes the
# reaction directional: attB x attP can only pair one way round, and the products
# attL and attR are not substrates for the integrase on its own.
ATT_SITES: dict[str, dict[str, str]] = {
    "bxb1": {
        "attB": "GGCTTGTCGACGACGGCGGTCTCCGTCGTCAGGATCAT",
        "attP": "GGTTTGTCTGGTCAACCACCGCGGTCTCAGTGGTGTACGGTACAAACC",
    },
    "phic31": {
        "attB": "GTGCCAGGGCGTGCCCTTGGGCTCCCCGGGCGCG",
        "attP": "CCCCAACTGGGGTAACCTTTGAGTTCTCTCAGTTGGGGG",
    },
}

# Anderson constitutive promoter (J23119). The default cargo, because it makes
# the memory legible: flipping the register points this promoter at the output
# or away from it, so the stored bit is directly readable as fluorescence.
DEFAULT_PAYLOAD = "TTGACAGCTAGCTCAGTCCTAGGTATAATGCTAGC"

RBS_STRONG = _literal("BBa_B0034", "Strong RBS", "rbs", registry.RBS_STRONG.sequence)
RBS_MEDIUM = _literal("BBa_B0032", "Medium RBS", "rbs", registry.RBS_MEDIUM.sequence)
TERMINATOR = _literal("BBa_B0012", "T7 early terminator", "terminator", registry.TERMINATOR.sequence)
SPACER = _literal("SPACER", "Insulating spacer", "spacer", "TACTAGAGTTACTAGAG")


def sensor_promoter(signal: Signal) -> Segment:
    """The promoter that hears the signal, taken from the shared parts library."""
    part = registry.PROMOTERS[signal.promoter_part]
    if part.provenance == "placeholder":
        return _placeholder(part.id, part.name, "promoter", part.length)
    return _literal(part.id, part.name, "promoter", part.sequence)


# --------------------------------------------------------------------------
# Recombinase memory
# --------------------------------------------------------------------------

def build_recombinase(
    architecture: Architecture,
    signal: Signal,
    enzyme: Recombinase,
    payload: str,
    orientation: str,
) -> list[Construct]:
    """Two units, and the register between them is the memory itself.

    Nothing in the second unit is expressed to hold the bit. The bit *is* the
    orientation of the DNA between the att sites, so it is copied by the
    replisome along with the rest of the chromosome and survives division
    without anything being spent on it.
    """
    sites = ATT_SITES[enzyme.id]
    cargo = payload if orientation != "reverse" else reverse_complement(payload)

    writer = Construct(
        name="writer",
        purpose="WRITE",
        segments=[
            sensor_promoter(signal),
            RBS_STRONG,
            _placeholder(f"{enzyme.id.upper()}_INT", f"{enzyme.id} integrase", "cds", enzyme.cds_length),
            TERMINATOR,
        ],
    )

    register = Construct(
        name="register",
        purpose="STORE",
        segments=[
            _literal(f"attB_{enzyme.id}", f"{enzyme.id} attB", "att", sites["attB"]),
            Segment("PAYLOAD", "Invertible cargo", "payload", "literal", cargo, orientation),
            _literal(f"attP_{enzyme.id}", f"{enzyme.id} attP", "att", sites["attP"]),
            SPACER,
            RBS_STRONG,
            _placeholder("BBa_E0040", "GFPmut3b reporter", "cds", 720),
            TERMINATOR,
        ],
    )

    constructs = [writer, register]

    if architecture.reversible:
        # Erasing needs a second enzyme, not a second dose of the first. The
        # directionality factor redirects the integrase onto attL and attR, and
        # it needs its own promoter — which is a second thing that can leak.
        constructs.append(Construct(
            name="eraser",
            purpose="ERASE",
            segments=[
                _placeholder("BBa_I0500", "Second inducible promoter", "promoter", 1210),
                RBS_MEDIUM,
                _placeholder(f"{enzyme.id.upper()}_RDF", f"{enzyme.id} directionality factor", "cds",
                             enzyme.rdf_cds_length),
                TERMINATOR,
            ],
        ))

    return constructs


# --------------------------------------------------------------------------
# Toggle switch
# --------------------------------------------------------------------------

def build_toggle(signal: Signal, payload: str) -> list[Construct]:
    """Gardner's toggle: two repressors, each shutting off the other's promoter.

    The bit is which repressor is winning, and it is held only for as long as
    both keep being expressed. Every unit here is running continuously — that
    is what a protein-encoded memory costs.
    """
    lac_promoter, lac_cds = registry.INVERTING_PROMOTERS["laci"]
    tet_promoter, tet_cds = registry.INVERTING_PROMOTERS["tetr"]

    return [
        Construct(
            name="repressor_a",
            purpose="STORE",
            segments=[
                _literal(tet_promoter.id, tet_promoter.name, "promoter", tet_promoter.sequence),
                RBS_STRONG,
                _placeholder(lac_cds.id, lac_cds.name, "cds", lac_cds.length),
                TERMINATOR,
            ],
        ),
        Construct(
            name="repressor_b",
            purpose="STORE",
            segments=[
                _literal(lac_promoter.id, lac_promoter.name, "promoter", lac_promoter.sequence),
                RBS_STRONG,
                _placeholder(tet_cds.id, tet_cds.name, "cds", tet_cds.length),
                TERMINATOR,
            ],
        ),
        Construct(
            name="readout",
            purpose="READ",
            segments=[
                _literal(lac_promoter.id, lac_promoter.name, "promoter", lac_promoter.sequence),
                Segment("PAYLOAD", "Cargo", "payload", "literal", payload, "forward"),
                RBS_STRONG,
                _placeholder("BBa_E0040", "GFPmut3b reporter", "cds", 720),
                TERMINATOR,
            ],
        ),
    ]


# --------------------------------------------------------------------------
# Output
# --------------------------------------------------------------------------

def to_fasta(constructs: list[Construct], notes: list[str]) -> str:
    """FASTA carrying the design intent in every header.

    Anyone opening the file six months later can see what it was for and which
    regions still need a coding sequence, without the web page.
    """
    lines = [f"; {note}" for note in notes]

    for construct in constructs:
        composition = " + ".join(
            item["part_id"] for item in construct.annotations() if item["role"] != "spacer"
        )
        lines.append(
            f">{construct.name} purpose={construct.purpose} length={construct.length}bp "
            f"resolved={construct.resolved_percent}% parts=[{composition}]"
        )
        sequence = construct.sequence
        lines.extend(sequence[index:index + LINE_WIDTH] for index in range(0, len(sequence), LINE_WIDTH))

    return "\n".join(lines) + "\n"


def parts_manifest(constructs: list[Construct]) -> list[dict[str, Any]]:
    used: dict[str, dict[str, Any]] = {}

    for construct in constructs:
        for item in construct.annotations():
            used.setdefault(item["part_id"], {
                "id": item["part_id"],
                "name": item["name"],
                "role": item["role"],
                "provenance": item["provenance"],
                "length": item["length"],
                "registry_url": (
                    registry.REGISTRY_URL + item["part_id"]
                    if item["part_id"].startswith("BBa_") else None
                ),
            })

    order = {"promoter": 0, "rbs": 1, "cds": 2, "att": 3, "payload": 4, "terminator": 5, "spacer": 6}
    return sorted(used.values(), key=lambda part: (order.get(part["role"], 9), part["id"]))
