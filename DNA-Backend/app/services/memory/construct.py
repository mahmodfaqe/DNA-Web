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
from . import eukaryote
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

@dataclass(frozen=True)
class PartsKit:
    """Every regulatory part a construct needs, for one host's machinery.

    The builders below describe the *architecture* — which unit expresses what,
    in which order, and which piece inverts. That much is identical in a
    bacterium and in a nucleus. What differs is every part it is made of, so the
    parts travel together in a kit and the architecture stays written once.
    """

    domain: str
    initiation_strong: Segment      # ribosome binding site, or initiation context
    initiation_medium: Segment
    terminator: Segment
    terminator_secondary: Segment
    spacer: Segment
    reporter: Segment
    second_promoter: Segment        # the eraser unit's inducible promoter
    # Fused to the integrase in a eukaryote. Without it the enzyme is expressed
    # perfectly and never reaches the DNA.
    localisation: Segment | None = None
    # Where a sensor promoter is looked up, and how a cargo defaults.
    default_payload: str = ""
    default_payload_bp: int = 0


def _eukaryotic(entry: dict[str, object], role: str) -> Segment:
    """A yeast part: named and sized, but not transcribed from memory."""
    return _placeholder(str(entry["id"]), str(entry["name"]), role, int(entry["length"]))


# Anderson constitutive promoter (J23119). The default cargo, because it makes
# the memory legible: flipping the register points this promoter at the output
# or away from it, so the stored bit is directly readable as fluorescence.
DEFAULT_PAYLOAD = "TTGACAGCTAGCTCAGTCCTAGGTATAATGCTAGC"

RBS_STRONG = _literal("BBa_B0034", "Strong RBS", "rbs", registry.RBS_STRONG.sequence)
RBS_MEDIUM = _literal("BBa_B0032", "Medium RBS", "rbs", registry.RBS_MEDIUM.sequence)
TERMINATOR = _literal("BBa_B0012", "T7 early terminator", "terminator", registry.TERMINATOR.sequence)
SPACER = _literal("SPACER", "Insulating spacer", "spacer", "TACTAGAGTTACTAGAG")


BACTERIAL_KIT = PartsKit(
    domain="bacteria",
    initiation_strong=RBS_STRONG,
    initiation_medium=RBS_MEDIUM,
    terminator=TERMINATOR,
    terminator_secondary=TERMINATOR,
    spacer=SPACER,
    reporter=_placeholder("BBa_E0040", "GFPmut3b reporter", "cds", 720),
    second_promoter=_placeholder("BBa_I0500", "Second inducible promoter", "promoter", 1210),
    localisation=None,
    default_payload=DEFAULT_PAYLOAD,
)

YEAST_KIT = PartsKit(
    domain="yeast",
    # Not a ribosome binding site: yeast ribosomes scan from the cap, so what
    # is placed here is the base context around the start codon.
    initiation_strong=Segment(
        "KOZAK_SC", "Yeast initiation context", "rbs", "designed",
        eukaryote.TRANSLATION_CONTEXT,
    ),
    initiation_medium=Segment(
        "KOZAK_SC", "Yeast initiation context", "rbs", "designed",
        eukaryote.TRANSLATION_CONTEXT,
    ),
    terminator=_eukaryotic(eukaryote.TERMINATORS["primary"], "terminator"),
    terminator_secondary=_eukaryotic(eukaryote.TERMINATORS["secondary"], "terminator"),
    spacer=Segment("SPACER_SC", "Insulating spacer", "spacer", "designed", eukaryote.SPACER),
    reporter=_eukaryotic(eukaryote.REPORTER, "cds"),
    second_promoter=_eukaryotic(eukaryote.PROMOTERS["copper"], "promoter"),
    localisation=Segment(
        "NLS_SV40", f"SV40 nuclear localisation signal ({eukaryote.NLS_PEPTIDE})",
        "tag", "designed", eukaryote.NLS_DNA,
    ),
    # A yeast cargo is a polymerase II promoter, which is hundreds of bases of
    # native sequence this tool will not invent. So there is no default: the
    # register is sized and labelled, and the reader supplies the bases.
    default_payload="",
    default_payload_bp=400,
)

KITS: dict[str, PartsKit] = {"bacteria": BACTERIAL_KIT, "yeast": YEAST_KIT}


def sensor_promoter(signal: Signal, kit: PartsKit = BACTERIAL_KIT) -> Segment:
    """The promoter that hears the signal, from the kit that matches the host."""
    if kit.domain == "yeast":
        return _eukaryotic(eukaryote.PROMOTERS[signal.promoter_part], "promoter")

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
    kit: PartsKit = BACTERIAL_KIT,
) -> list[Construct]:
    """Two units, and the register between them is the memory itself.

    Nothing in the second unit is expressed to hold the bit. The bit *is* the
    orientation of the DNA between the att sites, so it is copied by the
    replisome along with the rest of the chromosome and survives division
    without anything being spent on it.
    """
    sites = ATT_SITES[enzyme.id]
    cargo = payload if orientation != "reverse" else reverse_complement(payload)

    # In a nucleus the integrase is translated in the cytoplasm and has to be
    # carried back in. The localisation signal is fused ahead of the coding
    # sequence, in frame, and is the difference between a construct that works
    # and one that is correct in every base and inert.
    writer_segments: list[Segment] = [
        sensor_promoter(signal, kit),
        kit.initiation_strong,
    ]
    if kit.localisation is not None:
        writer_segments.append(kit.localisation)
    writer_segments.append(
        _placeholder(f"{enzyme.id.upper()}_INT", f"{enzyme.id} integrase", "cds", enzyme.cds_length)
    )
    writer_segments.append(kit.terminator)

    writer = Construct(name="writer", purpose="WRITE", segments=writer_segments)

    register = Construct(
        name="register",
        purpose="STORE",
        segments=[
            _literal(f"attB_{enzyme.id}", f"{enzyme.id} attB", "att", sites["attB"]),
            Segment("PAYLOAD", "Invertible cargo", "payload", "literal", cargo, orientation),
            _literal(f"attP_{enzyme.id}", f"{enzyme.id} attP", "att", sites["attP"]),
            kit.spacer,
            kit.initiation_strong,
            kit.reporter,
            kit.terminator_secondary,
        ],
    )

    constructs = [writer, register]

    if architecture.reversible:
        # Erasing needs a second enzyme, not a second dose of the first. The
        # directionality factor redirects the integrase onto attL and attR, and
        # it needs its own promoter — which is a second thing that can leak.
        eraser_segments: list[Segment] = [kit.second_promoter, kit.initiation_medium]
        if kit.localisation is not None:
            eraser_segments.append(kit.localisation)
        eraser_segments.append(
            _placeholder(f"{enzyme.id.upper()}_RDF", f"{enzyme.id} directionality factor", "cds",
                         enzyme.rdf_cds_length)
        )
        eraser_segments.append(kit.terminator)

        constructs.append(Construct(name="eraser", purpose="ERASE", segments=eraser_segments))

    return constructs


# --------------------------------------------------------------------------
# Toggle switch
# --------------------------------------------------------------------------

def _toggle_arms(kit: PartsKit) -> tuple[tuple[Segment, Segment], tuple[Segment, Segment]]:
    """The two mutually repressing units, as (promoter, repressor CDS) pairs.

    In a bacterium these come straight from the registry. In yeast the same two
    repressor proteins are used — LacI and TetR both work in a nucleus — but
    their operators have to sit in a polymerase II core promoter, and the
    proteins themselves have to be carried into that nucleus. Those are
    different parts, not the bacterial ones relabelled.
    """
    if kit.domain == "yeast":
        first = eukaryote.REPRESSORS["tetr"]
        second = eukaryote.REPRESSORS["lexa"]
        return (
            (_eukaryotic(first["promoter"], "promoter"), _eukaryotic(first["cds"], "cds")),
            (_eukaryotic(second["promoter"], "promoter"), _eukaryotic(second["cds"], "cds")),
        )

    lac_promoter, lac_cds = registry.INVERTING_PROMOTERS["laci"]
    tet_promoter, tet_cds = registry.INVERTING_PROMOTERS["tetr"]
    return (
        (_literal(tet_promoter.id, tet_promoter.name, "promoter", tet_promoter.sequence),
         _placeholder(lac_cds.id, lac_cds.name, "cds", lac_cds.length)),
        (_literal(lac_promoter.id, lac_promoter.name, "promoter", lac_promoter.sequence),
         _placeholder(tet_cds.id, tet_cds.name, "cds", tet_cds.length)),
    )


def build_toggle(signal: Signal, payload: str, kit: PartsKit = BACTERIAL_KIT) -> list[Construct]:
    """Gardner's toggle: two repressors, each shutting off the other's promoter.

    The bit is which repressor is winning, and it is held only for as long as
    both keep being expressed. Every unit here is running continuously — that
    is what a protein-encoded memory costs.
    """
    (promoter_a, cds_a), (promoter_b, cds_b) = _toggle_arms(kit)

    return [
        Construct(
            name="repressor_a",
            purpose="STORE",
            segments=[promoter_a, kit.initiation_strong, cds_a, kit.terminator],
        ),
        Construct(
            name="repressor_b",
            purpose="STORE",
            segments=[promoter_b, kit.initiation_strong, cds_b, kit.terminator_secondary],
        ),
        Construct(
            name="readout",
            purpose="READ",
            segments=[
                promoter_b,
                Segment("PAYLOAD", "Cargo", "payload", "literal", payload, "forward"),
                kit.initiation_strong,
                kit.reporter,
                kit.terminator,
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


def _part_url(part_id: str) -> str | None:
    """Where the real sequence lives, for the parts this tool will not invent."""
    if part_id.startswith("BBa_"):
        return registry.REGISTRY_URL + part_id

    for group in (eukaryote.PROMOTERS, eukaryote.TERMINATORS):
        for entry in group.values():
            if entry["id"] == part_id:
                return eukaryote.locus_url(entry["locus"])  # type: ignore[arg-type]

    return None


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
                "registry_url": _part_url(str(item["part_id"])),
            })

    order = {"promoter": 0, "rbs": 1, "tag": 2, "cds": 3, "att": 4,
             "payload": 5, "terminator": 6, "spacer": 7}
    return sorted(used.values(), key=lambda part: (order.get(part["role"], 9), part["id"]))
