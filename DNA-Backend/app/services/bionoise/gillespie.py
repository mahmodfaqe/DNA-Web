"""The Gillespie stochastic simulation algorithm, direct method.

Deterministic rate equations describe a flask, not a cell. A promoter is one
molecule, an mRNA is present in single copies, and a "concentration of 0.4
transcripts" does not exist — the count is 0 or 1, and which one it is at any
moment is the thing that makes two genetically identical cells behave
differently. Gillespie's algorithm simulates the actual chemistry: it draws
*which* reaction fires next and *when*, from the exact distributions implied by
the propensities, so the trajectory it produces is a statistically exact sample
from the chemical master equation rather than an approximation of its mean.

Each step:

    a_i         propensity of reaction i in the current state
    a_0 = sum a_i
    tau  ~ Exponential(a_0)              when the next reaction happens
    j    ~ Categorical(a_i / a_0)        which reaction it is

The loop below is written for speed rather than elegance in one specific way:
after firing a reaction it recomputes only the propensities that reaction could
have changed, because recomputing every Hill function on every step made a
routine run take minutes. The total is re-summed each step rather than tracked
incrementally, which costs one cheap C-level ``sum`` and removes any chance of
floating-point drift accumulating over millions of steps.
"""

from __future__ import annotations

import math
import random
from dataclasses import dataclass, field

from .network import Network

# Propensity slots per gene, in the order they are laid out in the flat array.
OPEN, CLOSE, TRANSCRIBE, TRANSLATE, MRNA_DECAY, PROTEIN_DECAY = range(6)
SLOTS = 6


@dataclass(frozen=True)
class RunSettings:
    seconds: float
    samples: int
    induction: float
    crosstalk: float
    resource_coupling: bool
    max_steps_per_cell: int
    # Cell-to-cell spread in translation capacity, as a coefficient of
    # variation. Drawn once per cell and held for the whole run, because the
    # things it stands for — ribosome content, cell size, growth rate — change
    # on the timescale of a generation, not a reaction.
    extrinsic_cv: float = 0.0


@dataclass
class CellRun:
    """Everything one simulated cell contributes to the result."""

    protein: list[list[int]] = field(default_factory=list)   # [gene][sample]
    mrna: list[list[int]] = field(default_factory=list)
    transcripts: list[float] = field(default_factory=list)
    crosstalk_transcripts: list[float] = field(default_factory=list)
    leak_transcripts: list[float] = field(default_factory=list)
    availability: float = 1.0
    efficiency: float = 1.0
    switches: int = 0
    steps: int = 0
    truncated: bool = False


# --------------------------------------------------------------------------
# Sampling helpers
# --------------------------------------------------------------------------

def _poisson(rng: random.Random, mean: float) -> int:
    """Knuth's method, with a normal fallback where it would loop too long."""
    if mean <= 0:
        return 0
    if mean > 30:
        return max(0, round(rng.gauss(mean, math.sqrt(mean))))

    limit = math.exp(-mean)
    product = 1.0
    count = 0
    while True:
        product *= rng.random()
        if product <= limit:
            return count
        count += 1


def _gamma_counts(rng: random.Random, shape: float, scale: float) -> int:
    """Draw from the analytic steady state of a bursty gene.

    For the two-stage model the protein distribution at steady state is very
    close to a gamma with shape = bursts per protein lifetime and scale = burst
    size (Friedman, Cai & Xie 2006). Seeding the ensemble from it means the run
    starts with roughly the right cell-to-cell spread instead of every cell
    starting identical at zero, so the burn-in only has to settle the couplings
    rather than grow every protein from nothing.
    """
    if shape <= 0 or scale <= 0:
        return 0
    return max(0, round(rng.gammavariate(shape, scale)))


# --------------------------------------------------------------------------
# The simulator
# --------------------------------------------------------------------------

def run_cell(net: Network, settings: RunSettings, seed: int, cell_index: int = 0) -> CellRun:
    rng = random.Random(seed)

    genes = net.genes
    n = len(genes)
    slots = SLOTS * n

    # This cell's own translation capacity, drawn once. A gamma with mean 1 and
    # the requested coefficient of variation: strictly positive, skewed, and the
    # standard choice for a multiplicative cellular factor.
    if settings.extrinsic_cv > 0:
        spread = settings.extrinsic_cv * settings.extrinsic_cv
        efficiency = rng.gammavariate(1.0 / spread, spread)
    else:
        efficiency = 1.0

    k_on = [g.k_on for g in genes]
    k_off = [g.k_off for g in genes]
    k_tx = [g.k_tx for g in genes]
    k_tl = [g.k_tl * efficiency for g in genes]
    d_m = [g.d_m for g in genes]
    d_p = [g.d_p for g in genes]
    leak = [g.leak * g.k_tx for g in genes]  # absolute rate while OFF

    induction = settings.induction
    chi = settings.crosstalk

    # --- drive terms, split by provenance so events can be attributed --------
    cognate_base = [g.basal for g in genes]
    crosstalk_base = [0.0] * n

    for inducer in net.inducers:
        target = net.index(inducer.target)
        if inducer.kind == "crosstalk":
            crosstalk_base[target] += inducer.weight * chi * induction
        else:
            cognate_base[target] += inducer.weight * induction

    base = [cognate_base[i] + crosstalk_base[i] for i in range(n)]

    # links_to[target] = (source, weight, k_half**hill, hill, is_crosstalk)
    links_to: list[list[tuple[int, float, float, int, bool]]] = [[] for _ in range(n)]
    targets_of: list[list[int]] = [[] for _ in range(n)]

    for link in net.links:
        source = net.index(link.source)
        target = net.index(link.target)
        weight = link.weight * chi if link.kind == "crosstalk" else link.weight
        if weight == 0.0:
            continue
        links_to[target].append((source, weight, link.k_half ** link.hill, link.hill,
                                 link.kind == "crosstalk"))
        targets_of[source].append(target)

    # A capacity of zero means "do not model competition at all" — used by the
    # isolated control network, where an infinite ribosome pool is the point.
    capacity = net.ribosome_capacity if settings.resource_coupling else 0.0
    competing = capacity > 0.0

    def drive(i: int) -> float:
        value = base[i]
        for source, weight, k_pow, hill, _ in links_to[i]:
            count = p[source]
            if count:
                occupancy = count ** hill
                value += weight * occupancy / (k_pow + occupancy)
        return 0.0 if value < 0.0 else (1.0 if value > 1.0 else value)

    # --- initial state ------------------------------------------------------
    g = [0] * n
    m = [0] * n
    p = [0] * n

    # Seed each species at its analytic steady state rather than at zero. The
    # protein relaxation time here is tens of minutes; starting empty would
    # spend most of a typical run climbing, and every "steady-state" statistic
    # would really be measuring that climb.
    on_fractions: list[float] = []
    tx_rates: list[float] = []
    for i in range(n):
        naive = cognate_base[i] + crosstalk_base[i]
        naive = 0.0 if naive < 0.0 else (1.0 if naive > 1.0 else naive)
        opening = k_on[i] * naive
        fraction = opening / (opening + k_off[i]) if (opening + k_off[i]) > 0 else 0.0
        on_fractions.append(fraction)
        tx_rates.append(k_tx[i] * fraction + leak[i] * (1.0 - fraction))

    # Translation is seeded through the ribosome availability the cell will
    # actually settle at, not through an unloaded one. Ignoring competition
    # here starts a heavily loaded cell at several times its steady state, and
    # the whole ensemble then decays together — which reads as a strong
    # correlation between genes that are not in fact correlated at all.
    expected_mrna = sum(tx_rates[i] / d_m[i] for i in range(n))
    seed_rho = capacity / (capacity + expected_mrna) if competing else 1.0

    for i in range(n):
        g[i] = 1 if rng.random() < on_fractions[i] else 0
        m[i] = _poisson(rng, tx_rates[i] / d_m[i])
        p[i] = _gamma_counts(rng, tx_rates[i] / d_p[i], k_tl[i] * seed_rho / d_m[i])

    # A bistable circuit has two steady states and no reason to prefer either,
    # so the population is started split between them. Seeding every cell the
    # same way would measure escapes from one well only.
    if net.bistable_pair:
        first = net.index(net.bistable_pair[0])
        second = net.index(net.bistable_pair[1])
        loser = second if cell_index % 2 == 0 else first
        p[loser] = 0
        m[loser] = 0
        g[loser] = 0

    # --- propensities -------------------------------------------------------
    a = [0.0] * slots
    total_mrna = sum(m)
    rho = capacity / (capacity + total_mrna) if competing else 1.0

    for i in range(n):
        offset = SLOTS * i
        a[offset + OPEN] = 0.0 if g[i] else k_on[i] * drive(i)
        a[offset + CLOSE] = k_off[i] if g[i] else 0.0
        a[offset + TRANSCRIBE] = k_tx[i] if g[i] else leak[i]
        a[offset + TRANSLATE] = k_tl[i] * m[i] * rho
        a[offset + MRNA_DECAY] = d_m[i] * m[i]
        a[offset + PROTEIN_DECAY] = d_p[i] * p[i]

    # --- crosstalk attribution ---------------------------------------------
    # Recorded when a promoter opens: the share of the positive drive that came
    # from somewhere it should not have. Transcripts produced during that open
    # interval inherit the share. Negative (repressive) terms are excluded —
    # a repressor does not cause the transcripts that happen anyway.
    share = [0.0] * n

    def opening_crosstalk_share(i: int) -> float:
        cognate = max(0.0, cognate_base[i])
        foreign = max(0.0, crosstalk_base[i])
        for source, weight, k_pow, hill, is_crosstalk in links_to[i]:
            if weight <= 0.0:
                continue
            count = p[source]
            if not count:
                continue
            occupancy = count ** hill
            contribution = weight * occupancy / (k_pow + occupancy)
            if is_crosstalk:
                foreign += contribution
            else:
                cognate += contribution
        supply = cognate + foreign
        return foreign / supply if supply > 0.0 else 0.0

    for i in range(n):
        share[i] = opening_crosstalk_share(i)

    transcripts = [0.0] * n
    crosstalk_transcripts = [0.0] * n
    leak_transcripts = [0.0] * n

    # --- output grid --------------------------------------------------------
    horizon = settings.seconds
    count = settings.samples
    grid = [horizon * index / (count - 1) for index in range(count)] if count > 1 else [horizon]

    protein_trace = [[0] * count for _ in range(n)]
    mrna_trace = [[0] * count for _ in range(n)]

    availability_sum = 0.0
    availability_count = 0

    switches = 0
    last_state: str | None = None
    threshold = net.switch_threshold
    bistable = net.bistable_pair is not None
    if bistable:
        first = net.index(net.bistable_pair[0])
        second = net.index(net.bistable_pair[1])

    def record(index: int) -> None:
        nonlocal switches, last_state, availability_sum, availability_count
        for gene in range(n):
            protein_trace[gene][index] = p[gene]
            mrna_trace[gene][index] = m[gene]

        availability_sum += rho
        availability_count += 1

        if bistable:
            if p[first] > threshold >= p[second]:
                state = "first"
            elif p[second] > threshold >= p[first]:
                state = "second"
            else:
                return
            if last_state is not None and state != last_state:
                switches += 1
            last_state = state

    # --- main loop ----------------------------------------------------------
    log = math.log
    uniform = rng.random

    time = 0.0
    cursor = 0
    steps = 0
    budget = settings.max_steps_per_cell
    truncated = False

    while cursor < count:
        total = sum(a)
        if total <= 0.0:
            break

        # 1 - random() is used rather than random() so the draw is in (0, 1]
        # and the logarithm can never be handed a zero.
        tau = -log(1.0 - uniform()) / total
        moment = time + tau

        while cursor < count and grid[cursor] < moment:
            record(cursor)
            cursor += 1

        if cursor >= count:
            break

        if steps >= budget:
            truncated = True
            break

        # --- choose the reaction -------------------------------------------
        pick = uniform() * total
        accumulated = 0.0
        index = slots - 1
        for candidate in range(slots):
            accumulated += a[candidate]
            if accumulated > pick:
                index = candidate
                break

        gene, reaction = divmod(index, SLOTS)
        offset = index - reaction
        time = moment
        steps += 1

        # --- apply it, and repair only the propensities it touched ----------
        if reaction == OPEN:
            g[gene] = 1
            share[gene] = opening_crosstalk_share(gene)
            a[offset + OPEN] = 0.0
            a[offset + CLOSE] = k_off[gene]
            a[offset + TRANSCRIBE] = k_tx[gene]

        elif reaction == CLOSE:
            g[gene] = 0
            a[offset + OPEN] = k_on[gene] * drive(gene)
            a[offset + CLOSE] = 0.0
            a[offset + TRANSCRIBE] = leak[gene]

        elif reaction == TRANSCRIBE:
            m[gene] += 1
            total_mrna += 1
            transcripts[gene] += 1.0
            if g[gene]:
                crosstalk_transcripts[gene] += share[gene]
            else:
                leak_transcripts[gene] += 1.0

            a[offset + MRNA_DECAY] = d_m[gene] * m[gene]
            if competing:
                rho = capacity / (capacity + total_mrna)
                for other in range(n):
                    a[SLOTS * other + TRANSLATE] = k_tl[other] * m[other] * rho
            else:
                a[offset + TRANSLATE] = k_tl[gene] * m[gene]

        elif reaction == MRNA_DECAY:
            m[gene] -= 1
            total_mrna -= 1
            a[offset + MRNA_DECAY] = d_m[gene] * m[gene]
            if competing:
                rho = capacity / (capacity + total_mrna)
                for other in range(n):
                    a[SLOTS * other + TRANSLATE] = k_tl[other] * m[other] * rho
            else:
                a[offset + TRANSLATE] = k_tl[gene] * m[gene]

        else:
            # TRANSLATE and PROTEIN_DECAY differ only in which way p moves.
            p[gene] += 1 if reaction == TRANSLATE else -1
            a[offset + PROTEIN_DECAY] = d_p[gene] * p[gene]

            for target in targets_of[gene]:
                if not g[target]:
                    a[SLOTS * target + OPEN] = k_on[target] * drive(target)

    # A run that stopped early still has to fill its remaining sample points,
    # or the ensemble average would silently be taken over fewer cells at late
    # times than at early ones. The last known state is held, and the caller is
    # told the run was truncated.
    while cursor < count:
        record(cursor)
        cursor += 1

    return CellRun(
        protein=protein_trace,
        mrna=mrna_trace,
        transcripts=transcripts,
        crosstalk_transcripts=crosstalk_transcripts,
        leak_transcripts=leak_transcripts,
        availability=availability_sum / availability_count if availability_count else 1.0,
        efficiency=efficiency,
        switches=switches,
        steps=steps,
        truncated=truncated,
    )


def run_ensemble(
    net: Network,
    settings: RunSettings,
    seed: int,
    cells: int,
) -> list[CellRun]:
    """A population of independent cells.

    Cell *k* always gets seed ``seed + k``, in both the full and the isolated
    ensemble. Sharing the seeds means the difference between the two runs is
    the couplings and not the random numbers, which is what makes subtracting
    one from the other a fair measurement rather than a coin flip.
    """
    return [run_cell(net, settings, seed + index, index) for index in range(cells)]
