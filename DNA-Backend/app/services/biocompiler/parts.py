"""The parts library.

Sequence provenance is tracked per part, and this is the most important design
decision in the module.

Short regulatory elements - promoters, ribosome binding sites, terminators,
assembly scars - are carried as literal sequence. They are tens of base pairs,
they are the part of the design the compiler actually *decides*, and they can be
checked by eye against the registry.

Coding sequences are not. A GFP CDS is ~720 bp; transcribing one from memory
risks a silent single-base error that changes a codon and produces a construct
that looks right and does not work. So a CDS is emitted as an annotated
placeholder carrying its registry ID and expected length, and the user either
fetches it from parts.igem.org or pastes their own. A design tool that quietly
guesses at a coding sequence is worse than one that admits it does not have it.

Biocontainment parts are placeholders on purpose, not for lack of data: which
kill switch a construct uses is a biosafety decision for the researcher and their
institution, not a default a compiler should pick.
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Literal

Role = Literal["promoter", "rbs", "cds", "terminator", "tag", "scar", "spacer"]
Provenance = Literal["literal", "placeholder", "designed"]

REGISTRY_URL = "https://parts.igem.org/Part:"


@dataclass(frozen=True)
class Part:
    id: str
    name: str
    role: Role
    provenance: Provenance
    length: int
    sequence: str = ""
    note: str = ""

    def resolved(self) -> str:
        """Sequence to place in the assembly.

        Placeholders become a run of N of the right length, so downstream
        coordinates, the annotation track and the total construct length are all
        correct even before the coding sequences are filled in.
        """
        if self.provenance == "placeholder":
            return "N" * self.length
        return self.sequence.upper()

    def as_dict(self) -> dict[str, object]:
        return {
            "id": self.id,
            "name": self.name,
            "role": self.role,
            "provenance": self.provenance,
            "length": self.length,
            "registry_url": REGISTRY_URL + self.id if self.id.startswith("BBa_") else None,
            "note": self.note,
        }


def _literal(part_id: str, name: str, role: Role, sequence: str, note: str = "") -> Part:
    clean = "".join(sequence.split()).upper()
    return Part(part_id, name, role, "literal", len(clean), clean, note)


def _placeholder(part_id: str, name: str, role: Role, length: int, note: str = "") -> Part:
    return Part(part_id, name, role, "placeholder", length, "", note)


# --------------------------------------------------------------------------
# Assembly grammar (BioBrick RFC[10])
# --------------------------------------------------------------------------

PREFIX = _literal("PREFIX", "BioBrick prefix", "scar", "gaattcgcggccgcttctagag")
SUFFIX = _literal("SUFFIX", "BioBrick suffix", "scar", "tactagtagcggccgctgcag")
SCAR = _literal("SCAR", "Standard assembly scar", "scar", "tactagag")

# --------------------------------------------------------------------------
# Promoters
# --------------------------------------------------------------------------

PROMOTERS: dict[str, Part] = {
    "lactose": _literal(
        "BBa_R0010", "pLac - LacI-repressed promoter", "promoter",
        "caatacgcaaaccgcctctccccgcgcgttggccgattcattaatgcagctggcacgacaggtttcccgactggaaagcgggcagtga"
        "gcgcaacgcaattaatgtgagttagctcactcattaggcaccccaggctttacactttatgcttccggctcgtatgttgtgtggaatt"
        "gtgagcggataacaatttcacaca",
        "Induced by lactose or IPTG. Requires LacI in the host or on the construct.",
    ),
    "tetracycline": _literal(
        "BBa_R0040", "pTet - TetR-repressed promoter", "promoter",
        "tccctatcagtgatagagattgacatccctatcagtgatagagatactgagcac",
        "De-repressed by tetracycline or aTc. Requires TetR.",
    ),
    "arabinose": _placeholder(
        "BBa_I0500", "pBAD/araC - arabinose-inducible promoter", "promoter", 1210,
        "Includes the araC coding region; fetch from the registry.",
    ),
    "temperature": _placeholder(
        "BBa_K115017", "cI857ts / pR - thermosensitive switch", "promoter", 750,
        "Repressed by cI857 below ~32 C, de-repressed above ~37 C. Threshold is a "
        "property of the protein, not a number the compiler can set.",
    ),
    "oxygen": _placeholder(
        "BBa_K1673017", "pFNR - anaerobic-induced promoter", "promoter", 130,
        "Active under low oxygen via the FNR regulator.",
    ),
    "quorum": _placeholder(
        "BBa_R0062", "pLux - AHL-responsive promoter", "promoter", 55,
        "Activated by LuxR bound to AHL; responds to cell density.",
    ),
    "ph_acid": _placeholder(
        "BBa_K1675002", "pAsr - acid-inducible promoter", "promoter", 220,
        "Induced at low external pH.",
    ),
}

# Promoters repressed by a repressor the circuit itself expresses. Used to build
# NOT gates: sense -> express repressor -> shut this promoter off.
INVERTING_PROMOTERS: dict[str, tuple[Part, Part]] = {
    "laci": (PROMOTERS["lactose"], _placeholder("BBa_C0012", "LacI repressor CDS", "cds", 1083)),
    "tetr": (PROMOTERS["tetracycline"], _placeholder("BBa_C0040", "TetR repressor CDS", "cds", 621)),
}

# --------------------------------------------------------------------------
# Ribosome binding sites
# --------------------------------------------------------------------------

RBS_STRONG = _literal(
    "BBa_B0034", "Strong RBS (Elowitz)", "rbs", "aaagaggagaaa",
    "Community reference RBS. Strength is context dependent on flanking sequence.",
)
RBS_MEDIUM = _literal("BBa_B0032", "Medium RBS", "rbs", "tcacacaggaaag")

# --------------------------------------------------------------------------
# Coding sequences
# --------------------------------------------------------------------------

ACTUATOR_CDS: dict[str, Part] = {
    "gfp": _placeholder("BBa_E0040", "GFPmut3b - green fluorescent protein", "cds", 720),
    "rfp": _placeholder("BBa_E1010", "mRFP1 - red fluorescent protein", "cds", 681),
    "yfp": _placeholder("BBa_E0030", "EYFP - yellow fluorescent protein", "cds", 720),
    "lacz": _placeholder("BBa_I732005", "lacZ - beta-galactosidase", "cds", 3075),
}

KILL_SWITCH = _placeholder(
    "SELECT_BIOCONTAINMENT_PART", "Biocontainment effector - not selected", "cds", 300,
    "Deliberately left unselected. Which effector a kill switch uses is a "
    "biosafety decision for the researcher and their institutional review, not a "
    "default for a compiler to choose.",
)

# --------------------------------------------------------------------------
# Degradation tags - the only lever the compiler has over output lifetime
# --------------------------------------------------------------------------

DEGRADATION_TAGS: dict[str, Part] = {
    "fast": _placeholder("BBa_M0050", "ssrA tag (LVA) - fastest degradation", "tag", 33),
    "medium": _placeholder("BBa_M0051", "ssrA tag (AAV) - medium degradation", "tag", 33),
    "slow": _placeholder("BBa_M0052", "ssrA tag (ASV) - slow degradation", "tag", 33),
}

# --------------------------------------------------------------------------
# Terminators
# --------------------------------------------------------------------------

TERMINATOR = _literal(
    "BBa_B0012", "TE - T7 early terminator", "terminator",
    "tcacactggctcaccttcgggtgggcctttctgcgtttata",
)
TERMINATOR_DOUBLE = _placeholder(
    "BBa_B0015", "Double terminator (B0010 + B0012)", "terminator", 129,
    "Preferred for real builds; stronger termination than B0012 alone.",
)


def hybrid_promoter(first: Part, second: Part) -> Part:
    """Fuse two promoters into an AND gate.

    Concatenating operator regions is the textbook way to build a
    two-input AND at the promoter, and it produces a real, orderable sequence.
    It is also the step most likely to behave differently from the prediction,
    which is why every hybrid carries a warning: spacing, operator order and
    RNA-polymerase footprint all shift the response curve, and the only way to
    know the transfer function is to measure it.
    """
    sequence = first.resolved() + SCAR.resolved() + second.resolved()

    return Part(
        id=f"HYB_{first.id}_{second.id}",
        name=f"Hybrid promoter ({first.name} x {second.name})",
        role="promoter",
        provenance="designed",
        length=len(sequence),
        sequence=sequence,
        note="Designed by the compiler. Requires experimental characterisation.",
    )
