"""What the designer knows: hosts, signals, and the two ways to remember.

The central claim of this module is that "which memory should I use" is not a
matter of taste. The two architectures store the bit in physically different
places, and everything else follows from that one difference:

    Toggle switch     the bit lives in protein concentrations
    Recombinase       the bit lives in the DNA sequence itself

A protein-encoded bit is diluted every time the cell divides and has to be
continuously re-expressed to survive; it can be flipped by a large enough
expression burst, and it costs energy for as long as it is remembered. A
DNA-encoded bit is copied by the replisome along with everything else, costs
nothing to hold, and cannot be diluted away — but writing it is a one-way
chemical reaction, so erasing it needs a second enzyme, and any leaky
expression of the writing enzyme writes the bit when nobody asked.

So the trade is retention against reversibility, and which side wins depends on
how long the memory has to last, how many divisions happen in between, and
whether it ever has to be erased. Those are the questions the form asks.
"""

from __future__ import annotations

import math
from dataclasses import dataclass, field
from typing import Any


# --------------------------------------------------------------------------
# Host organisms
# --------------------------------------------------------------------------

@dataclass(frozen=True)
class Chassis:
    """A host cell, and what it does to a circuit placed inside it."""

    id: str
    doubling_minutes: float
    # Whether the bundled parts library — promoters, RBS and terminators drawn
    # from the iGEM Registry — actually works in this organism. Promoters read
    # by a bacterial sigma factor are not read at all by a eukaryotic
    # polymerase, and an RBS is meaningless where translation is cap-dependent.
    parts_available: bool
    sigma70: bool               # whether the cryptic-promoter scan applies
    plasmid_copy_number: int
    protein_half_life_minutes: float
    note: str = ""

    @property
    def growth_rate(self) -> float:
        """Dilution rate per minute, from the doubling time."""
        return math.log(2) / self.doubling_minutes

    def as_dict(self) -> dict[str, Any]:
        return {
            "id": self.id,
            "doubling_minutes": self.doubling_minutes,
            "parts_available": self.parts_available,
            "sigma70": self.sigma70,
            "plasmid_copy_number": self.plasmid_copy_number,
            "growth_rate": round(self.growth_rate, 6),
        }


CHASSIS: dict[str, Chassis] = {
    "ecoli": Chassis(
        id="ecoli",
        doubling_minutes=30.0,
        parts_available=True,
        sigma70=True,
        plasmid_copy_number=15,
        protein_half_life_minutes=2000.0,
    ),
    "bsubtilis": Chassis(
        id="bsubtilis",
        doubling_minutes=45.0,
        parts_available=True,
        sigma70=True,          # sigma-A, close enough for the -35/-10 scan
        plasmid_copy_number=6,
        protein_half_life_minutes=2000.0,
    ),
    "yeast": Chassis(
        id="yeast",
        doubling_minutes=90.0,
        # Deliberately false. Every promoter, RBS and terminator in this
        # library is bacterial; none of them function in a eukaryotic nucleus,
        # and a recombinase would additionally need a nuclear localisation
        # signal it does not carry here. Emitting a sequence anyway would be
        # the most expensive kind of helpfulness.
        parts_available=False,
        sigma70=False,
        plasmid_copy_number=1,
        protein_half_life_minutes=2000.0,
    ),
}


# --------------------------------------------------------------------------
# Signals
# --------------------------------------------------------------------------

@dataclass(frozen=True)
class Signal:
    """A chemical input, and the promoter that hears it.

    ``leak`` is the property that decides whether a recombinase memory is
    usable at all: a promoter that is 2% on with no inducer will, given enough
    hours, express enough integrase to flip a cell that was never induced —
    and because the flip is written into DNA, that false write is permanent.
    """

    id: str
    promoter_part: str          # key into the compiler's promoter library
    leak: float                 # fraction of full activity with no inducer
    dynamic_range: float        # fold change, induced over uninduced
    hill: int
    chassis: tuple[str, ...]    # hosts the sensor is characterised in

    def as_dict(self) -> dict[str, Any]:
        return {
            "id": self.id, "promoter_part": self.promoter_part, "leak": self.leak,
            "dynamic_range": self.dynamic_range, "hill": self.hill,
            "chassis": list(self.chassis),
        }


SIGNALS: dict[str, Signal] = {
    "lactose": Signal("lactose", "lactose", 0.02, 120.0, 2, ("ecoli", "bsubtilis")),
    "arabinose": Signal("arabinose", "arabinose", 0.005, 300.0, 3, ("ecoli",)),
    "tetracycline": Signal("tetracycline", "tetracycline", 0.01, 200.0, 2, ("ecoli", "bsubtilis")),
    "temperature": Signal("temperature", "temperature", 0.08, 30.0, 4, ("ecoli",)),
    "oxygen": Signal("oxygen", "oxygen", 0.10, 20.0, 2, ("ecoli",)),
    "quorum": Signal("quorum", "quorum", 0.04, 60.0, 2, ("ecoli",)),
    "ph_acid": Signal("ph_acid", "ph_acid", 0.12, 15.0, 2, ("ecoli",)),
}


# --------------------------------------------------------------------------
# Recombinases
# --------------------------------------------------------------------------

@dataclass(frozen=True)
class Recombinase:
    """A serine integrase and its att sites.

    Serine integrases are unidirectional by construction: the enzyme converts
    attB x attP into attL x attR, and cannot run the reaction backwards because
    it does not recognise the products. Reversal requires a second protein, the
    recombination directionality factor, expressed separately. That asymmetry
    is the whole reason this class of memory is stable, and the whole reason
    erasing it is a second engineering problem rather than a second dose.
    """

    id: str
    cds_length: int             # integrase coding sequence, base pairs
    rdf_cds_length: int         # directionality factor, for the erase path
    core: str                   # central dinucleotide that sets orientation
    k_recombination: float      # per minute at saturating integrase
    hill: int = 2               # integrase acts as a dimer pair across the synapse
    k_half: float = 250.0       # integrase copies for half-maximal recombination

    def as_dict(self) -> dict[str, Any]:
        return {
            "id": self.id, "cds_length": self.cds_length,
            "rdf_cds_length": self.rdf_cds_length, "core": self.core,
            "k_recombination": self.k_recombination,
        }


RECOMBINASES: dict[str, Recombinase] = {
    "bxb1": Recombinase("bxb1", 1416, 330, "GT", 0.05),
    "phic31": Recombinase("phic31", 1863, 738, "TT", 0.03),
}


# --------------------------------------------------------------------------
# Architectures
# --------------------------------------------------------------------------

@dataclass(frozen=True)
class Architecture:
    """One way of storing a bit, with the parameters the ODE model needs."""

    id: str
    stores_in_dna: bool
    reversible: bool
    # Protein synthesis rate at full promoter activity, copies per minute.
    alpha: float = 40.0
    # Repressor copies for half occupancy, and cooperativity. Toggle only.
    k_half: float = 150.0
    hill: int = 2
    # Translational burst size — proteins made per mRNA. Sets how big a
    # spontaneous excursion can be, and therefore how often noise flips a
    # protein-encoded bit.
    burst_size: float = 15.0
    burst_frequency: float = 1.2   # bursts per minute
    units: int = 2                 # transcriptional units, for the burden estimate

    def as_dict(self) -> dict[str, Any]:
        return {
            "id": self.id, "stores_in_dna": self.stores_in_dna,
            "reversible": self.reversible, "alpha": self.alpha,
            "k_half": self.k_half, "hill": self.hill, "units": self.units,
        }


ARCHITECTURES: dict[str, Architecture] = {
    # The bit is a physical DNA inversion between att sites. Nothing has to be
    # expressed to hold it, so it survives division and costs nothing to keep.
    "recombinase": Architecture(
        id="recombinase", stores_in_dna=True, reversible=False, units=2,
    ),
    # The same inversion, plus a second inducible unit expressing the
    # directionality factor that flips it back. Erasable, at the price of a
    # third protein and a second promoter that can also leak.
    "recombinase_reversible": Architecture(
        id="recombinase_reversible", stores_in_dna=True, reversible=True, units=3,
    ),
    # Two mutually repressing genes. The bit is the ratio of two protein
    # concentrations, which means it is diluted by growth and re-established by
    # expression, continuously, for as long as it is remembered.
    "toggle": Architecture(
        id="toggle", stores_in_dna=False, reversible=True, units=2,
        alpha=40.0, k_half=150.0, hill=2, burst_size=15.0, burst_frequency=1.2,
    ),
}


@dataclass
class Requirements:
    """What the memory has to do, in the user's terms rather than the model's."""

    signal: str
    chassis: str
    hold_hours: float
    signal_minutes: float
    must_be_reversible: bool
    on_plasmid: bool
    recombinase: str = "bxb1"
    payload_bp: int = 900
    extras: dict[str, Any] = field(default_factory=dict)

    def as_dict(self) -> dict[str, Any]:
        return {
            "signal": self.signal,
            "chassis": self.chassis,
            "hold_hours": self.hold_hours,
            "signal_minutes": self.signal_minutes,
            "must_be_reversible": self.must_be_reversible,
            "on_plasmid": self.on_plasmid,
            "recombinase": self.recombinase,
            "payload_bp": self.payload_bp,
        }
