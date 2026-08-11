# Architecture

## The problem this layout solves

The system has two halves that speak different languages in every sense: a Python
service that computes, and a PHP application that presents. The interesting
design question is not "how do we connect them" — that part is a POST — but
**where the words live**.

## The localisation contract

The previous version put Kurdish prose inside `main.py`:

```python
raise ValueError(f"زنجیرەی {record.id} پیتی نادروستی تێدایە: {', '.join(invalid)}")
```

That single line makes trilingual support impossible. The message crosses the
network already committed to one language, and the presentation layer receives a
finished sentence it cannot translate, pluralise, or re-word. Adding Arabic would
mean either passing a locale into the analysis service — making a computation
service care about human language — or maintaining a lookup table keyed by
Kurdish sentences, which breaks the moment anyone fixes a typo.

The backend now emits a code and its parameters:

```json
{ "error": { "code": "sequence_invalid_chars",
             "params": { "record_id": "gene_1", "characters": ["Z"] } } }
```

and the frontend owns the wording:

```php
'sequence_invalid_chars' => 'Record ":record_id" contains characters that are not nucleotides: :characters.',
```

**Consequence:** adding a fourth language costs three files under `lang/` and one
entry in `App\Support\Locales::SUPPORTED`. Zero backend changes. `ErrorTranslator`
logs any code it cannot translate and falls back to the generic message, so a new
backend error code degrades to something readable instead of printing a raw
identifier at the user.

## What is localised, and what deliberately is not

Translation is not the same as localisation, and localising the wrong thing is a
bug. Three cases where the interface stays in one form regardless of language:

| Element | Behaviour | Reason |
|---|---|---|
| Sequence tracks, coordinates, rulers | Always left-to-right | Nucleotide sequences read 5′ → 3′. Mirroring the track on an RTL page would mirror the biology. `.track { direction: ltr }` enforces this. |
| Base letters, codons, record IDs, ORF ranges | Always LTR, monospace | `ATG` inside an RTL paragraph reorders visually without `unicode-bidi: isolate`. `.ltr-data` isolates every such string. |
| Numerals | Latin digits, tabular figures | Scientific notation in Kurdish and Arabic publishing uses Latin digits, and tabular figures keep columns aligned in all three languages. |

## Language routing

```
/                       → redirect, using session then Accept-Language
/{ku|ar|en}             → upload page
/{ku|ar|en}/result/{id} → stored result
/health                 → unlocalised, for monitoring
```

`SetLocale` validates the segment, sets the app locale, remembers the choice, and
registers a URL default so every `route()` call inherits the current language
without each caller passing it. `Locales::urlFor()` rewrites the *current* URL
into another language, which is what makes the switcher keep you on the result
you were reading rather than dropping you back at the upload form.

`ku` in the URL, `ckb` in `<html lang>` and `hreflang`: `ku` is a macrolanguage
covering Kurmanji and Sorani, while `ckb` is Central Kurdish specifically. Text
shapers and search engines need the precise tag; readers recognise the short one.

## Why results are stored

The old flow rendered results directly from the POST response. Three consequences:
refreshing re-uploaded and re-ran the analysis; results had no URL to share; and
switching language discarded them entirely, since the new page was a fresh GET.

Post/Redirect/Get with a persisted `Analysis` row fixes all three at once, and
gives the MySQL container — previously provisioned but completely unused — an
actual job. `analyses:prune` bounds the retention.

## Analysis correctness

Two decisions in `DNA-Backend/app/services/` are worth knowing about.

**Alignment before variant calling.** `compare.py` runs Needleman–Wunsch with
affine gaps, then reads variants off the alignment. A positional diff is only
correct when sequences differ by substitutions alone: one inserted base shifts
every downstream position, so `test_insertion_is_one_event_not_a_cascade_of_substitutions`
guards a case where the old approach reported 27 substitutions instead of 1
insertion.

Alignment is O(n·m) in memory, so above `ALIGN_MAX_BP` the service falls back to
a positional diff — and says so, in the response (`method`) and on the page. A
number whose method is invisible is a number that gets trusted too much.

**Method disclosure for Tm.** Nearest-neighbour thermodynamics model short
duplexes. Applied to a multi-kilobase gene the result is not wrong so much as
meaningless. `melting_temperature()` picks the method by length, returns
`{value, method, reliable}`, and the table prints the method next to the figure.

## Front-end dependencies

Four CDN scripts were removed: Tailwind's browser build, Lucide, Chart.js and
html2pdf — roughly 3 MB, none version-pinned, all a hard runtime dependency on an
outside network.

| | Before | After |
|---|---|---|
| CSS | Tailwind JIT in the browser | 34.6 kB built (7.9 kB gzip) |
| JS | ~3 MB across four CDNs | 1.8 kB |
| Fonts | Google Fonts, network round trip | 135 kB self-hosted, 3 subsets |
| Charts | Chart.js | server-rendered SVG |
| PDF | html2pdf (rasterised) | print stylesheet (vector) |

The font faces are declared by hand with `unicode-range` rather than importing
each package's `index.css`, which would pull seventeen subsets including Cyrillic
and Vietnamese. An English reader never downloads the Arabic face.

Server-rendered SVG is not only smaller. A rasterising PDF library cannot produce
selectable Kurdish or Arabic text, and a bar chart of GC percentages says nothing
about *where* along a sequence a difference sits — which, in genetics, is the
finding. The composition track plots variants at their real coordinates.

Because nothing loads from a third party, `SecurityHeaders` can set a strict CSP
(`script-src 'self'`). If someone later adds a CDN `<script>`, the page breaks
loudly in development rather than quietly acquiring a third-party dependency in
production.

## Known limitations

- **The async job store is process-local.** `JobStore` is bounded and expiring,
  but with more than one uvicorn worker a job submitted to worker A is invisible
  to worker B. It is the single seam to replace with Redis if async is used in
  earnest. The synchronous endpoint has no such issue.
- **Rate limiting is per process.** Adequate behind a single container; move it to
  the reverse proxy for multiple replicas.
- **Comparison is one-against-all.** Every record is aligned to the first. True
  multiple sequence alignment (MUSCLE, MAFFT) is a different tool and a much
  heavier dependency.
- **The default Laravel `users`, `cache` and `jobs` migrations are still
  present.** They are unused — the application has no accounts — but removing
  them would block anyone who later switches `SESSION_DRIVER` or `CACHE_STORE` to
  `database`.

---

# BioCompiler (second tab)

Natural language in, genetic circuit out:

```
sentence (ku/ar/en)
  → normalise + tokenise      trilingual lexicon, one grammar
  → Specification             conditions, connective, outputs, terminal action
  → gate netlist              SENSOR / AND / OR / NOT / OUTPUT / TERMINAL
  → transcriptional units     promoter + RBS + CDS + terminator, joined by scars
  → FASTA + parts manifest + diagnostics
```

## Why the parser is not a model call

The feature is named after language models, and the honest answer is that the
compiler does not use one. A compiler has to be **deterministic**: the same
sentence must give the same DNA next year, the parse has to be explainable when
it is wrong, and a design tool should not need a network round trip to work.

So the parser is a grammar over a trilingual lexicon. `parser.normalise_with_model()`
is the seam where a model belongs — as a *pre-processor* that rewrites loose
prose into the canonical shape the grammar accepts, leaving compilation itself
reproducible and auditable. That is the right division of labour: the model
handles the messiness of human phrasing, the compiler handles the biology.

## One grammar, three languages

A parser per language would triple the grammar and let the three drift apart.
Instead the grammar is language-neutral and only the *words* are per-language, so
a Kurdish sentence and its Arabic translation compile to byte-identical DNA — the
property `test_all_three_languages_compile_to_identical_dna` exists to protect.

Three problems this had to solve:

| Problem | Why it breaks a naive parser |
|---|---|
| **Arabic-script punctuation** | `،` and `؛` sit inside the Arabic Unicode block, so "keep everything Arabic" glues them to the last word and the final keyword never matches. |
| **Fused conjunction** | Arabic writes "and the lactose" as one word. The waw is split off only when the remainder is a word the lexicon knows, so ordinary Kurdish words starting with the same letter survive. |
| **Word order** | Kurdish is verb-final ("green protein produce"), Arabic and English are not; and Arabic puts the comparison verb *before* the sensor. Sensors and actuators are disjoint vocabularies, so both passes scan the whole sentence and neither arrangement can hide a term. |

## Sequence provenance

This is the most consequential decision in the module, and it is a refusal.

Short regulatory elements — promoters, RBS, terminators, assembly scars — are
carried as **literal sequence**. They are tens of base pairs, they are the part
the compiler actually *decides*, and they can be checked by eye against the
registry.

Coding sequences are **not**. A GFP CDS is ~720 bp; transcribing one from memory
risks a silent single-base error that changes a codon and produces a construct
that looks right and does not work. So a CDS is emitted as an annotated
placeholder carrying its registry ID and expected length, and the FASTA header
says so. A design tool that quietly guesses at a coding sequence is worse than
one that admits it does not have it.

The biocontainment effector is a placeholder for a different reason: which gene
kills the cell is a biosafety decision for the researcher and their institution,
not a default for a compiler to pick.

## Diagnostics are the product

The most useful output is not the FASTA, it is the list of places where the
sentence and biology did not line up. Three that fire on the example from the
brief:

- **"for 24 hours"** — no DNA sequence means that. Degradation tags act over
  minutes. Timing on this scale comes from the experiment, not the construct.
- **"above 37 °C"** — the threshold is a property of the thermosensitive
  repressor, not a number the compiler can set.
- **AND gate** — building it fuses two promoters into a hybrid. That produces a
  real, orderable sequence whose response curve nobody can predict without
  measuring it.

A student who reads those three warnings has learned more than one who receives
a sequence and trusts it.

## Limitations

- **Four conditions maximum**, flat logic only. Mixing "and" with "or" needs
  bracketing the grammar cannot recover, so it warns and applies the first
  connective.
- **Output is a teaching draft, not an order-ready construct.** Every compile
  says so, in the interface and in the FASTA header.

---

# BioNoise-Sim (third tab)

A population of cells, simulated one reaction at a time:

```
preset network + conditions
  → reaction network            promoter OFF/ON, mRNA, protein, per gene
  → Gillespie SSA, N cells      exact sample from the chemical master equation
  → control ensemble            same seeds, couplings removed
  → statistics                  Fano, CV, noise budget, crosstalk attribution
  → trajectories + distributions + diagnostics
```

## Why an exact stochastic simulation rather than rate equations

A differential equation describes a flask. In a cell a promoter is one molecule
and an mRNA is present in single copies, so "0.4 transcripts" is not a state the
system can occupy — the count is 0 or 1, and which one it is at a given moment is
precisely what makes two genetically identical cells behave differently.

Gillespie's direct method draws which reaction fires next and when, from the
exact distributions the propensities imply. The trajectory it produces is a
statistically exact sample from the chemical master equation, not an
approximation of its mean. Everything the tab exists to show — bursts, spread
across a population, a memory circuit flipping on its own — is invisible to a
model that only tracks averages.

## Two-state promoters are not an embellishment

A promoter that is simply "on at rate k" produces Poisson mRNA and far less
protein variability than real bacteria show. Switching between OFF and ON
produces **bursts**, and bursting is the dominant measured source of expression
noise. A simulator without it would report a cell much quieter than any cell on
a microscope, which is a comfortable and useless answer.

## Cell-to-cell variability is a separate axis

Each simulated cell draws its own translation capacity once and keeps it, from a
gamma with mean 1. It stands for ribosome content, cell size and growth rate —
things that differ between cells and stay different for about a generation, far
longer than any reaction in the model.

Without it, two identical reporters come out with almost no extrinsic noise,
which is the opposite of what the dual-reporter experiments measured. The tab
would then teach precisely the wrong lesson.

## The control ensemble

Where a network contains any coupling, the same cells are simulated a second time
with the crosstalk and the shared ribosome pool removed and **nothing else
changed** — cell *k* gets seed `seed + k` in both runs, so the two ensembles
differ in the couplings and not in the random numbers.

Subtracting one from the other is what turns "this gene is noisy" into "this much
of the noise is other genes". It costs a second ensemble, so it is skipped
entirely when the network has nothing to isolate.

## Crosstalk is attributed, not inferred

Every time a promoter opens, the simulator records what share of the *positive*
drive that opened it came from a foreign signal, and the transcripts made during
that open interval inherit the share. Repressive terms are excluded: a repressor
does not cause the transcripts that happen anyway.

This is exact inside the model, which matters because the obvious alternative —
reading crosstalk off a correlation — is unreliable in both directions. Constant
crosstalk raises a gene's output without correlating its fluctuations, and
unconnected genes correlate strongly for reasons that have nothing to do with
crosstalk.

## Two correlation matrices, and why both

| Matrix | What it is | What it is for |
|---|---|---|
| Measured | Pearson correlation of deviations from the ensemble mean at each moment | What a microscope reports |
| Partial | The same, after regressing out each cell's own translation capacity | What the wiring and the competition actually did |

On the `independent` preset — two genes with no link, no shared inducer and
ribosomes to spare — the measured correlation runs around **+0.6** and the
partial around **0**. Reading the first as evidence of a regulatory connection is
one of the easiest mistakes to make with single-cell data, and showing both
numbers side by side is the most direct way to inoculate against it.

Deviations are taken from the ensemble mean *at each time point* rather than from
the pooled mean. A population still settling would otherwise have its shared
drift counted as correlation between genes that are not correlated at all.

## The noise budget

Noise expressed as CV² is additive across independent sources, which is the only
reason the bar can be drawn. Two terms are theory and two are measurement, and
the interface labels which is which:

| Term | Source |
|---|---|
| `floor` | 1/⟨p⟩, the Poisson limit of counting molecules |
| `bursting` | analytic two-stage excess, `b/(1 + d_p/d_m)` over ⟨p⟩ |
| `extrinsic` | the declared cell-to-cell CV, squared |
| `promoter` | whatever the isolated gene still shows beyond those three |
| `coupling` | full ensemble minus control ensemble |

`coupling` is **signed**. Coupling that acts as negative feedback reduces noise,
and clamping that to zero would hide one of the more useful things a student can
see.

## Reporting precision, not just numbers

The number of samples is not the number of measurements. A protein with a
half-life of half an hour is barely changed a second later, so sampling one cell
densely produces many numbers and little extra information. Independent
observations accumulate at roughly one per two protein lifetimes per cell, and
never fewer than one per cell.

Every result therefore carries the relative error on its noise figures
(`√(2/n_eff)`), and the steady-state check compares the observed drift against
what sampling alone would produce rather than against a fixed percentage — a
noisy gene measured in few cells looks like it is drifting when it is not.

## Validating a simulator that has no expected output

Every run is different on purpose, so the suite is built on what can be pinned
down:

- **Theory.** With `leak = 1.0` transcription no longer depends on the promoter
  state, which reduces the model to the two-stage system that has been solved
  exactly: mRNA must be Poisson (Fano 1) and protein Fano must equal
  `1 + b/(1 + d_p/d_m)`. Both are asserted directly.
- **Direction.** A bursty promoter must come out *above* that constitutive
  prediction, never below; turning crosstalk up must move transcripts onto the
  wrong gene; competition must show as negative partial correlation.
- **Invariance.** The same seed reproduces a run exactly; a knob at zero has no
  effect.
- **Refusal.** Bad input produces a diagnostic rather than a plausible number.

## Limitations

Attached to every result as diagnostics rather than left in this file, because a
simulator whose assumptions live only in the documentation is a simulator whose
assumptions nobody reads.

- **One well-mixed compartment.** Molecules have no position, nothing diffuses,
  nothing is membrane-bound or localised at a pole.
- **No growth or division.** Dilution is folded into the protein decay rate, so
  there is no cell cycle and no partitioning of molecules between daughters —
  itself a real source of noise this model does not have.
- **Illustrative parameters.** Order-of-magnitude values for *E. coli* in
  exponential growth, not measurements of anyone's construct.
- **Ribosome competition only.** RNA polymerase is not modelled as a shared
  resource; with two or three genes it is the smaller effect, and modelling one
  shared pool honestly is better than modelling two badly.
- **Cost is in reaction events, not input size.** A hundred cells watched for
  four hours is millions of them, so the ensemble runs against a step budget and
  a run that exhausts it stops and says so rather than holding the worker.
