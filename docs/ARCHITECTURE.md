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
