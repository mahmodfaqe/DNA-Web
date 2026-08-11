"""The model: genes, promoters, and the couplings between them.

A gene is modelled as the standard three-layer stochastic construct used in the
single-cell literature — a promoter that switches between OFF and ON, mRNA
transcribed while it is ON, and protein translated from that mRNA:

    promoter  OFF <--> ON        k_on * drive  /  k_off
    mRNA      ON --> ON + mRNA   k_tx        (and leak * k_tx while OFF)
    protein   mRNA --> mRNA + P  k_tl * ribosome availability
    decay     mRNA --> 0         d_m
              P --> 0            d_p   (degradation plus dilution by growth)

Two-state promoter switching is not an embellishment. A promoter that is simply
"on at rate k" produces Poisson mRNA and far less protein variability than real
cells show; switching produces *bursts*, and bursting is the dominant source of
gene expression noise measured in bacteria. A simulator without it would report
a cell that is much quieter than any cell on a microscope.

Crosstalk enters in three separate places, because in a real cell it arrives
from three separate directions:

  chemical    an inducer meant for one promoter partly drives another
  regulatory  a transcription factor binds a promoter that is not its own
  resource    every gene translates from one shared pool of ribosomes, so a
              burst of one mRNA takes ribosomes away from every other gene

The first two push the affected gene's expression up or down. The third is
different in kind: it couples genes that have no regulatory connection at all,
purely through competition, and it is the reason two "independent" reporters in
one cell are never actually independent.

On top of all three sits cell-to-cell variability. Two cells in the same culture
differ in ribosome content, size and growth rate, and those differences persist
for something like a generation — far longer than any reaction in the model.
Each simulated cell therefore draws its own translation efficiency once and
keeps it. Without this the model produces two reporters whose extrinsic noise is
near zero, which is the opposite of what the dual-reporter experiments actually
measured, and would teach a student precisely the wrong lesson.
"""

from __future__ import annotations

import math
from dataclasses import dataclass, field
from typing import Any

# Rates are per second throughout. The values below are order-of-magnitude
# figures for E. coli in exponential growth:
#
#   mRNA half-life ~2 min          -> d_m = ln2/120  = 0.0058
#   protein stable, diluted by
#   growth, doubling ~33 min       -> d_p = ln2/2000 = 0.00035
#
# They are illustrative, not measured for any particular construct, and the
# result carries a diagnostic saying so.

MRNA_DECAY = 0.0058
PROTEIN_DILUTION = 0.00035
PROTEIN_TAGGED_DECAY = 0.0012  # ssrA-tagged, half-life ~10 min


@dataclass(frozen=True)
class Gene:
    """One transcriptional unit and its promoter."""

    id: str
    label: str            # translation key suffix, resolved by the frontend
    k_on: float           # promoter opening rate at full drive
    k_off: float          # promoter closing rate
    k_tx: float           # transcription initiation while ON
    k_tl: float           # translation initiation per mRNA
    d_m: float = MRNA_DECAY
    d_p: float = PROTEIN_DILUTION
    leak: float = 0.02    # transcription while OFF, as a fraction of k_tx
    basal: float = 0.0    # constitutive drive on the promoter, 0..1

    @property
    def burst_size(self) -> float:
        """Proteins made per mRNA before it is degraded."""
        return self.k_tl / self.d_m

    def as_dict(self) -> dict[str, Any]:
        return {
            "id": self.id,
            "label": self.label,
            "k_on": self.k_on,
            "k_off": self.k_off,
            "k_tx": self.k_tx,
            "k_tl": self.k_tl,
            "d_m": self.d_m,
            "d_p": self.d_p,
            "leak": self.leak,
            "basal": self.basal,
            "burst_size": round(self.burst_size, 2),
            "protein_half_life_minutes": round(math.log(2) / self.d_p / 60.0, 1),
        }


@dataclass(frozen=True)
class Link:
    """A protein acting on a promoter.

    ``weight`` is signed: positive activates, negative represses, in the same
    0..1 units as the promoter drive. ``kind`` records whether the interaction
    is part of the design (``cognate``) or an unwanted one (``crosstalk``) —
    that distinction is what makes the crosstalk accounting possible, and it is
    a modelling assertion, not something the simulation could discover.
    """

    source: str
    target: str
    weight: float
    k_half: float = 200.0   # protein copies for half occupancy
    hill: int = 2           # cooperativity
    kind: str = "cognate"   # cognate | crosstalk

    def as_dict(self) -> dict[str, Any]:
        return {
            "source": self.source, "target": self.target, "weight": self.weight,
            "k_half": self.k_half, "hill": self.hill, "kind": self.kind,
        }


@dataclass(frozen=True)
class Inducer:
    """The externally supplied signal reaching a promoter.

    A cognate inducer is the one the promoter was chosen for. A crosstalk
    inducer is the same molecule reaching a promoter it was never meant to
    touch — the small-molecule equivalent of a transcription factor binding the
    wrong operator.
    """

    target: str
    weight: float
    kind: str = "cognate"

    def as_dict(self) -> dict[str, Any]:
        return {"target": self.target, "weight": self.weight, "kind": self.kind}


@dataclass
class Network:
    preset: str
    genes: list[Gene]
    links: list[Link] = field(default_factory=list)
    inducers: list[Inducer] = field(default_factory=list)
    ribosome_capacity: float = 40.0
    dual_reporters: tuple[str, str] | None = None
    bistable_pair: tuple[str, str] | None = None
    switch_threshold: float = 0.0

    def index(self, gene_id: str) -> int:
        for position, gene in enumerate(self.genes):
            if gene.id == gene_id:
                return position
        raise KeyError(gene_id)

    @property
    def has_crosstalk(self) -> bool:
        """Whether any gene is wired to a signal that was not meant for it.

        Used to decide if the isolated control ensemble is worth running: with
        no unwanted interaction at all, the control would reproduce the full
        model exactly and would only double the compute.
        """
        return any(link.kind == "crosstalk" for link in self.links) or any(
            inducer.kind == "crosstalk" for inducer in self.inducers
        )

    def as_dict(self) -> dict[str, Any]:
        return {
            "preset": self.preset,
            "genes": [gene.as_dict() for gene in self.genes],
            "links": [link.as_dict() for link in self.links],
            "inducers": [inducer.as_dict() for inducer in self.inducers],
            "ribosome_capacity": self.ribosome_capacity,
            "dual_reporters": list(self.dual_reporters) if self.dual_reporters else None,
            "bistable_pair": list(self.bistable_pair) if self.bistable_pair else None,
        }


# --------------------------------------------------------------------------
# Presets
#
# Each preset is a question the simulator can answer, not just a set of
# parameters. They are ordered from "what does noise look like at all" to
# "what does noise do to a circuit that has to hold a decision".
# --------------------------------------------------------------------------

def _reporter(gene_id: str, label: str, **overrides: Any) -> Gene:
    """A standard inducible reporter: bursty promoter, stable protein."""
    defaults: dict[str, Any] = {"k_on": 0.02, "k_off": 0.02, "k_tx": 0.05, "k_tl": 0.05}
    defaults.update(overrides)
    return Gene(id=gene_id, label=label, **defaults)


def _independent() -> Network:
    """Two identical reporters, wired to nothing. The control experiment.

    Nothing here couples the genes on purpose, and a large ribosome pool keeps
    competition negligible, so whatever noise appears is the gene's own: the
    baseline every other preset should be read against.
    """
    return Network(
        preset="independent",
        genes=[_reporter("A", "reporter_a"), _reporter("B", "reporter_b")],
        inducers=[Inducer("A", 1.0), Inducer("B", 1.0)],
        ribosome_capacity=200.0,
    )


def _crosstalk_pair() -> Network:
    """A signalling gene and a bystander.

    A is induced on purpose. B has its own weak constitutive drive and is not
    meant to respond to anything — but the inducer partly reaches its promoter,
    and A's protein binds there too. Both are wrong in the direction that
    matters: B lights up when only A was asked for.
    """
    return Network(
        preset="crosstalk_pair",
        genes=[
            _reporter("A", "reporter_a"),
            _reporter("B", "reporter_b", basal=0.12),
        ],
        links=[Link("A", "B", 0.6, k_half=400.0, hill=1, kind="crosstalk")],
        inducers=[Inducer("A", 1.0), Inducer("B", 0.45, kind="crosstalk")],
        # Deliberately generous, so that what shows up here is the regulatory
        # crosstalk being demonstrated and not ribosome competition on top of it.
        ribosome_capacity=200.0,
    )


def _dual_reporter() -> Network:
    """Two copies of the same reporter in one cell.

    This is the Elowitz-Swain experiment, and the only configuration in which
    total noise can honestly be split into intrinsic and extrinsic parts: the
    two reporters share every parameter and every cell-wide fluctuation, so
    whatever makes them differ is intrinsic and whatever moves them together is
    extrinsic.
    """
    return Network(
        preset="dual_reporter",
        genes=[
            _reporter("A", "reporter_cfp"),
            _reporter("B", "reporter_yfp"),
        ],
        inducers=[Inducer("A", 1.0), Inducer("B", 1.0)],
        ribosome_capacity=25.0,
        dual_reporters=("A", "B"),
    )


def _resource_competition() -> Network:
    """Two reporters and one heavily expressed protein they must share with.

    Nothing regulates anything here. The only connection is the ribosome pool,
    which is enough to make the reporters anticorrelate with the load and with
    each other — coupling with no wire between the genes.
    """
    return Network(
        preset="resource_competition",
        genes=[
            _reporter("A", "reporter_a"),
            _reporter("B", "reporter_b"),
            _reporter("L", "burden_protein", k_tx=0.30, k_tl=0.06, k_on=0.06, k_off=0.01),
        ],
        inducers=[Inducer("A", 1.0), Inducer("B", 1.0), Inducer("L", 1.0)],
        ribosome_capacity=30.0,
    )


def _toggle_switch() -> Network:
    """Two genes repressing each other: a one-bit memory made of DNA.

    Deterministically this circuit has two stable states and stays in whichever
    one it starts in, for ever. Stochastically it does not: a large enough
    burst of the repressed protein flips the cell into the other state, and the
    memory has a half-life. Protein copy numbers are deliberately low and the
    proteins are ssrA-tagged, which is what makes flipping observable inside an
    hour instead of a week.
    """
    return Network(
        preset="toggle_switch",
        genes=[
            Gene("A", "repressor_laci", k_on=0.05, k_off=0.02, k_tx=0.02, k_tl=0.02,
                 d_p=PROTEIN_TAGGED_DECAY, basal=1.0, leak=0.01),
            Gene("B", "repressor_tetr", k_on=0.05, k_off=0.02, k_tx=0.02, k_tl=0.02,
                 d_p=PROTEIN_TAGGED_DECAY, basal=1.0, leak=0.01),
        ],
        links=[
            Link("A", "B", -1.0, k_half=15.0, hill=2),
            Link("B", "A", -1.0, k_half=15.0, hill=2),
        ],
        ribosome_capacity=200.0,
        bistable_pair=("A", "B"),
        switch_threshold=15.0,
    )


PRESETS = {
    "independent": _independent,
    "crosstalk_pair": _crosstalk_pair,
    "dual_reporter": _dual_reporter,
    "resource_competition": _resource_competition,
    "toggle_switch": _toggle_switch,
}


def build(preset: str) -> Network:
    factory = PRESETS.get(preset)
    if factory is None:
        raise KeyError(preset)
    return factory()


def isolate(network: Network) -> Network:
    """The same network with every unwanted interaction between genes removed.

    Running this alongside the real model is what turns "this gene is noisy"
    into "this much of the noise is crosstalk": the two ensembles share their
    seeds and differ only in the couplings, so subtracting one from the other
    leaves the contribution of the couplings alone.

    Cell-to-cell variability is deliberately *kept*. It is not a coupling
    between genes and not something a better design could remove — it is the
    population the circuit has to work in. Stripping it here would let it be
    counted as crosstalk in the subtraction.
    """
    return Network(
        preset=network.preset,
        genes=list(network.genes),
        links=[link for link in network.links if link.kind != "crosstalk"],
        inducers=[i for i in network.inducers if i.kind != "crosstalk"],
        ribosome_capacity=0.0,  # zero disables competition; see gillespie.run_cell
        dual_reporters=network.dual_reporters,
        bistable_pair=network.bistable_pair,
        switch_threshold=network.switch_threshold,
    )
