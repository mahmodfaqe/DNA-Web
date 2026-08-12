"""The yeast parts kit, and why almost none of it is literal sequence.

Everything in the bacterial library is read by a sigma factor and translated
from a Shine-Dalgarno sequence. A nucleus does neither. So a memory circuit in
*S. cerevisiae* is not the bacterial design with different labels — four things
change, and each of them is a way the naive port fails:

  transcription   RNA polymerase II reads a core promoter with a TATA box and
                  an upstream activating sequence, not a -35/-10 pair. A
                  bacterial promoter placed in a nucleus is simply not read.

  translation     There is no ribosome binding site. The 40S subunit loads at
                  the cap and scans to the first AUG, so what matters is the
                  base context around that AUG — A at -3 above all.

  termination     Transcription ends by cleavage and polyadenylation directed
                  by an efficiency element and a positioning element, not by a
                  GC hairpin followed by a run of T. A bacterial terminator
                  does not terminate here.

  localisation    This is the one that is easy to miss. An integrase expressed
                  in the cytoplasm never meets the DNA it is supposed to cut.
                  It needs a nuclear localisation signal fused to it, and
                  without one the construct is correct in every base and does
                  nothing at all.

The provenance rule is the one the rest of the project follows. A yeast
promoter is four hundred to seven hundred base pairs of native upstream
sequence; transcribing one from memory is exactly the silent-error risk that
rule exists to prevent, so every one of them is a placeholder carrying its
systematic name for retrieval from SGD. What *is* carried literally is what is
short enough to check by eye: the translation initiation context and the
nuclear localisation signal, both of which are design decisions rather than
retrieved parts, and are labelled as such.

The consequence is honest and worth stating plainly: a yeast construct from
this tool resolves to a low percentage of real bases. It gives the
architecture, the part list, the coordinates and the model — not a file to send
to a synthesiser. The page says so, and so does every FASTA header.
"""

from __future__ import annotations

from dataclasses import dataclass

# Systematic identifiers, for retrieval rather than decoration. SGD serves each
# of these on a stable URL, so a part list is something a reader can act on.
SGD_URL = "https://www.yeastgenome.org/locus/"


@dataclass(frozen=True)
class YeastPart:
    """A part named and sized for retrieval, not transcribed from memory."""

    id: str
    name: str
    length: int
    locus: str | None = None
    note: str = ""

    @property
    def url(self) -> str | None:
        return SGD_URL + self.locus if self.locus else None


# --------------------------------------------------------------------------
# RNA polymerase II promoters
# --------------------------------------------------------------------------

# Lengths are the span of native upstream sequence conventionally cloned as the
# promoter, which is what a reader will actually retrieve.
PROMOTERS: dict[str, YeastPart] = {
    "galactose": YeastPart(
        id="pGAL1",
        name="GAL1 promoter - galactose-inducible",
        length=450,
        locus="YBR020W",
        note="Glucose-repressed. Induce in raffinose plus galactose, not in glucose.",
    ),
    "copper": YeastPart(
        id="pCUP1",
        name="CUP1-1 promoter - copper-inducible",
        length=450,
        locus="YHR053C",
        note="Fast and dose-tunable, but with real basal expression.",
    ),
    "estradiol": YeastPart(
        id="pZ3EV",
        name="Z3EV promoter - beta-estradiol-inducible",
        length=400,
        note="Synthetic: Z3 sites over a minimal GAL1 core. Needs the Z3EV activator expressed separately.",
    ),
    # Constitutive, for the units that must simply run: the readout, and the
    # repressors of a toggle.
    "constitutive_strong": YeastPart(
        id="pTEF1",
        name="TEF1 promoter - strong constitutive",
        length=412,
        locus="YPR080W",
    ),
    "constitutive_medium": YeastPart(
        id="pADH1",
        name="ADH1 promoter - medium constitutive",
        length=700,
        locus="YOL086C",
    ),
}


# --------------------------------------------------------------------------
# Terminators
# --------------------------------------------------------------------------

TERMINATORS: dict[str, YeastPart] = {
    "primary": YeastPart(
        id="tCYC1",
        name="CYC1 terminator",
        length=250,
        locus="YJR048W",
    ),
    # A second, different terminator for the neighbouring unit. Repeating one
    # terminator twice in a construct hands homologous recombination a substrate
    # — and in this organism that is not a remote risk, it is the mechanism the
    # whole field uses to assemble plasmids.
    "secondary": YeastPart(
        id="tADH1",
        name="ADH1 terminator",
        length=190,
        locus="YOL086C",
        note="Different from tCYC1 on purpose: two copies of one terminator invite recombination.",
    ),
}


# --------------------------------------------------------------------------
# Short elements, carried literally
# --------------------------------------------------------------------------

# The context immediately 5' of the start codon. Yeast initiation is not
# Kozak's mammalian consensus; scanning ribosomes in S. cerevisiae are most
# sensitive to an A at -3, and an A-rich stretch upstream raises initiation
# further. This is a design choice rather than a retrieved part, so it is
# labelled "designed" and not attributed to any locus.
TRANSLATION_CONTEXT = "AAAACAAA"

# SV40 large-T nuclear localisation signal: Pro-Lys-Lys-Lys-Arg-Lys-Val, the
# most-characterised monopartite NLS there is. The peptide is the part that
# matters; the codons below are one choice among many, which is exactly why the
# provenance is "designed" rather than a registry identifier.
#
#   CCA AAA AAG AAG AGA AAG GTA
#    P   K   K   K   R   K   V
NLS_PEPTIDE = "PKKKRKV"
NLS_DNA = "CCAAAAAAGAAGAGAAAGGTA"

# A neutral run to separate units. Kept short and free of the A-rich runs that
# read as initiation context or as a polyadenylation efficiency element.
SPACER = "GTCTAGCTGACTGCATCGTG"


# --------------------------------------------------------------------------
# Reporters and repressors
# --------------------------------------------------------------------------

REPORTER = YeastPart(
    id="yEGFP3",
    name="yEGFP3 - yeast-codon-optimised GFP",
    length=717,
    note="Codon-optimised for S. cerevisiae; the bacterial GFPmut3b CDS folds poorly here.",
)

# The toggle needs two repressors that both work in a nucleus. Bacterial LacI
# and TetR do function as repressors in yeast, but only when their operators
# are placed in a polymerase II core promoter — so the promoters below are
# synthetic hybrids, and are named as such rather than borrowed from the
# bacterial library.
REPRESSORS: dict[str, dict[str, YeastPart]] = {
    "tetr": {
        "promoter": YeastPart(
            id="ptetO7",
            name="tetO7-CYC1core - TetR-repressed promoter",
            length=350,
            note="Seven tetO operators over a minimal CYC1 core promoter.",
        ),
        "cds": YeastPart(
            id="tetR_NLS",
            name="TetR repressor with NLS",
            length=642,
            note="621 bp TetR plus a 21 bp nuclear localisation signal.",
        ),
    },
    "lexa": {
        "promoter": YeastPart(
            id="plexA8",
            name="lexAop8-CYC1core - LexA-repressed promoter",
            length=380,
            note="Eight lexA operators over a minimal CYC1 core promoter.",
        ),
        "cds": YeastPart(
            id="lexA_NLS",
            name="LexA repressor with NLS",
            length=630,
            note="609 bp LexA plus a 21 bp nuclear localisation signal.",
        ),
    },
}
