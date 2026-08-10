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
