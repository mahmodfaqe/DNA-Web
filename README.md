# DNA Analytics

FASTA sequence analysis for teaching and research, in Kurdish, Arabic and English.

Upload a FASTA file and the system reports base composition, GC content and skew,
melting temperature, molecular weight, open reading frames across all six frames,
codon usage, and an aligned comparison that separates substitutions from
insertions and deletions and classifies each substitution as synonymous,
missense or nonsense.

**Other languages:** [کوردی](docs/README.ku.md) · [العربية](docs/README.ar.md)

---

## Four tools, and what leaves them

**Sequence analysis** — upload a FASTA file, get composition, thermodynamics,
reading frames and an aligned comparison.

**BioCompiler** — describe a genetic circuit in a sentence and get the logic
gates, the parts list and the assembled DNA:

```
"If temperature exceeds 37 and lactose is present,
 produce green protein for 24 hours then self destruct."

  → IF TEMPERATURE > 37 AND LACTOSE THEN GFP + SELF_DESTRUCT
  → SENSOR ─┐
            ├─ AND ─→ OUTPUT (GFP) ─→ BIOCONTAINMENT
    SENSOR ─┘
  → 2 transcriptional units, 3394 bp, FASTA
  → 4 warnings, including: no DNA sequence means "for 24 hours"
```

The same sentence in Kurdish, Arabic or English compiles to byte-identical DNA.

**BioNoise-Sim** — the circuit above assumes a cell behaves like a flask. It does
not. This simulates the actual chemistry, one reaction at a time, across a
population of cells:

```
"Signal and bystander" — 60 cells, 60 minutes

  Gene A  induced on purpose      611 copies   CV 23%
  Gene B  not meant to respond    328 copies   CV 28%

  → 78% of gene B's transcripts came from a promoter
    that gene A's signal opened
  → correlation A:B measured +0.50 … +0.06 once cell-to-cell
    variability is divided out
  → noise: 4% counting molecules, 30% bursting, 66% the cell
```

Two genes with no connection at all still correlate around +0.6, because a cell
rich in ribosomes makes more of both. Showing that number next to the one with
the shared factor removed is the point of the tab.

**DeepBio-Memory Architect** — where should a cell keep a bit? It can live in
protein concentrations or in the DNA itself, and everything else follows from
that one choice:

```
record lactose in E. coli, hold 24 h, may stay on a plasmid

  Recombinase register   retention  99%   fidelity  52%   → 78.0
  Toggle switch          retention 100%   fidelity 100%   → 90.8  ✓

  → 48% of an uninduced population writes itself within 24 h,
    from a promoter that is 2% active with no inducer at all
  → and because the flip is written into DNA, it is permanent
```

Switch the sensor to arabinose — 0.5% leak instead of 2% — and the answer
changes. Both architectures are modelled every time and the loser is shown in
full, because a recommendation is only checkable if what it beat is visible.

## Architecture

```
browser ──► DNA-Frontend (Laravel 13, Apache) ──► DNA-Backend (FastAPI, Biopython)
                     │
                     └──► MySQL 8 (stored results)
```

| Service | Role | Exposed |
|---|---|---|
| `frontend` | Interface, localisation, result storage, exports | `:8080` |
| `backend` | Sequence analysis, circuit compilation, simulation, memory design | internal only |
| `db` | Stored analyses | internal only |

The backend is a pure computation service. It never returns human-readable prose
— only error **codes** and structured parameters — so every word the user reads
lives in the frontend's translation files. See
[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for why that matters and how to add
a fourth language.

## Getting started

```bash
cp .env.example .env
```

Generate an application key and paste it into `.env` as `APP_KEY`:

```bash
docker compose run --rm --no-deps --entrypoint "" frontend php artisan key:generate --show
```

Set `MYSQL_PASSWORD` and `MYSQL_ROOT_PASSWORD` to values of your own, then:

```bash
docker compose up -d --build
```

Open <http://localhost:8080>. The interface starts in Kurdish; `/ku`, `/ar` and
`/en` all serve the same pages.

Compose refuses to start if `APP_KEY` or the database passwords are empty. That
is deliberate: the previous version shipped with working default passwords, and a
default password that works is a password that reaches production.

## Sample files

```
DNA-Backend/data/sample.fasta         two homologous genes, two substitutions
DNA-Backend/data/mutation_demo.fasta  substitution, frameshift and in-frame deletion
```

## Tests

```bash
# Analysis service
cd DNA-Backend
pip install -r requirements-dev.txt
pytest                                # 242 tests

# Web application
cd DNA-Frontend
composer install
php artisan test
```

The backend suite includes a regression test for the variant-calling bug
described in [docs/ASSESSMENT.ku.md](docs/ASSESSMENT.ku.md): a single inserted base
used to be reported as dozens of false substitutions.

The simulator is checked against theory rather than against itself. With
transcription made independent of the promoter state, the model reduces to a
system with a closed-form solution, and the suite asserts that mRNA comes out
Poisson and that the protein Fano factor equals `1 + b/(1 + d_p/d_m)`. A bursty
promoter then has to measure *above* that figure, never below.

## Configuration

Everything is set through `.env`. The values worth knowing:

| Variable | Default | Meaning |
|---|---|---|
| `APP_LOCALE` | `ku` | Language for visitors with no preference |
| `MAX_FILE_SIZE` | `10485760` | Upload ceiling in bytes (backend) |
| `MAX_UPLOAD_KB` | `10240` | Upload ceiling in kilobytes (frontend) |
| `MAX_RECORDS` | `500` | Records allowed per file |
| `ALIGN_MAX_BP` | `3000` | Above this, comparison falls back to a positional diff |
| `RATE_LIMIT_PER_MINUTE` | `30` | Analyses per client per minute |
| `SIM_MAX_STEPS` | `6000000` | Reaction events one simulation may spend |
| `BACKEND_SIMULATION_TIMEOUT` | `180` | Seconds the frontend waits for a simulation |
| `CORS_ORIGINS` | `http://localhost:8080` | Set to your real domain in production |
| `RETENTION_DAYS` | `30` | Days a stored result survives; shown in the footer |
| `RATE_LIMIT_MAX_CLIENTS` | `4096` | Addresses the throttle keeps state for |
| `ENABLE_DOCS` | `false` | Serve the interactive schema browser at `/docs` |
| `BACKEND_CPUS` / `BACKEND_MEMORY` | `2.0` / `1g` | Ceilings on the analysis container |

Keep `MAX_FILE_SIZE` and `MAX_UPLOAD_KB` consistent, or a file will pass one
check and fail the other with a less helpful message.

## API

The analysis service is not published to the host: it listens on the internal
compose network and the web application is its only caller. To reach it while
developing, use `docker compose exec backend …`, or add a
`ports: ["127.0.0.1:8000:8000"]` override in an uncommitted
`compose.override.yml`.

It can describe itself interactively at `/docs`, but only when `ENABLE_DOCS=true`.
That is off by default because the page enumerates every endpoint and payload
shape, and nothing in production needs it.

| Endpoint | Purpose |
|---|---|
| `GET /health` | Liveness, version, and the size of both in-memory tables |
| `POST /api/v1/analyze` | Analyse a FASTA file, returns the full result |
| `POST /api/v1/compile` | Compile a description into a circuit |
| `POST /api/v1/simulate` | Run a stochastic simulation, returns the full result |
| `GET /api/v1/simulate/presets` | The networks the simulator can run |
| `POST /api/v1/memory` | Compare memory architectures, returns the design and its DNA |
| `GET /api/v1/memory/options` | Signals, hosts and recombinases available |
| `POST /api/v1/compile/export` | Compile and return SBOL 2.3 or GenBank |
| `GET /api/v1/compile/formats` | Design formats a circuit can be exported as |
| `POST /api/v1/cloning` | Restriction analysis and primer design for a template |
| `GET /api/v1/cloning/panels` | Enzyme panels the service searches |
| `POST /api/v1/analyze-async` | Queue an analysis, returns a `job_id` |
| `GET /api/v1/job/{job_id}` | Poll an async job |

Errors always take this shape:

```json
{ "error": { "code": "sequence_invalid_chars",
             "params": { "record_id": "gene_1", "characters": ["Z"] } } }
```

## Data retention

Uploaded sequences are research material, so results are not kept indefinitely:

```bash
docker compose exec frontend php artisan analyses:prune
```

This covers stored analyses, compiled circuits, simulation runs and memory
designs alike. It runs daily in the `scheduler` container, which exists only to
call `php artisan schedule:work`; without it the schedule is a registration that
nothing ever executes, and nothing is ever deleted.

The window is `RETENTION_DAYS`, and the footer shows the same value, so what a
visitor is told about their sequence data is what the job enforces. Pass
`--days=` to override it for one run.

## Backups

```bash
docker compose exec db mysqldump -u root -p dna_db > dna_db_backup.sql
docker compose exec -T db mysql -u root -p dna_db < dna_db_backup.sql
```

## Deployment checklist

- [ ] `APP_DEBUG=false` and `APP_ENV=production`
- [ ] `APP_KEY` generated fresh for this deployment
- [ ] Database passwords changed from the example file
- [ ] `CORS_ORIGINS` narrowed to your domain
- [ ] `APP_URL` set to the public URL, over HTTPS
- [ ] TLS terminated by a reverse proxy in front of `frontend`
- [ ] A retention window agreed with whoever owns the data
- [ ] `docker compose ps` shows `scheduler` running, or nothing is ever pruned
- [ ] Port 8000 is not reachable from outside the compose network

## A note on the memory architect's output

The recommendation depends on parameters the tool does not measure — above all
how leaky your sensor actually is in your hands. Both architectures are modelled
and both are shown, with the scoring weights stated on the page, so the verdict
can be argued with rather than taken on trust.

Att sites are included as literal sequence and must be verified before ordering:
their central dinucleotide is the entire mechanism of directionality, and a
single wrong base there builds a recombinase that writes in both directions
rather than failing loudly.

## A note on the simulator's output

The parameters are order-of-magnitude figures for *E. coli* in exponential
growth, not measurements of your construct. The cell is one well-mixed
compartment: nothing diffuses, nothing is membrane-bound, and cells neither grow
nor divide — dilution is folded into the protein decay rate.

Use it to understand which effects matter and how they behave. Do not use it to
predict what your plasmid will do. Every result says as much, on the page.

## Cloning: restriction analysis and primer design

Two questions that are usually separate tools are one tool here, because the
question a student is actually answering needs both at once: *can I amplify this
fragment and clone it into that vector*.

```
600 bp template, amplify 20..560, tails EcoRI / XhoI

  unique cutters   BglII  EcoRV  KpnI  NdeI  SphI
  EcoRI            cuts 0x inside the amplicon  ✓ safe as a tail
  forward   TTTTTT GAATTC TTCACTCTGAAACATAAGGATAGAATAG
                          └ binding Tm 59.9      full-length Tm 64.7
```

Two temperatures, because in cycle one only the binding region has anything to
pair with — the tail is hanging free. Annealing temperature comes from the
binding region alone, and reporting only the full-length figure is how a PCR
gets run five degrees too hot.

Enzyme data is Biopython's `Bio.Restriction`, generated from REBASE, so
recognition sites and cut offsets are the same numbers as the supplier's
datasheet. What this adds is the reasoning around them: which enzymes cut
exactly once, which never cut, and which fragment pairs are too close in size to
tell apart on a gel.

The primer designer is teaching-grade and says so. Melting temperatures are
nearest-neighbour under one named condition set, reported with the result so
they can be recomputed. Secondary structure is a complementarity screen, not a
free-energy calculation. The uniqueness check is against the submitted template
only — it has never seen a genome, and every result says as much.

## Exporting a circuit

FASTA carries bases. It does not carry the fact that those 22 bases are a
BioBrick prefix and the next 200 are a promoter, so a design that leaves as
FASTA has lost everything the compiler decided.

```bash
curl -X POST localhost:8000/api/v1/compile/export \
  -d '{"text": "if lactose then produce green protein", "format": "sbol"}'
```

**SBOL 2.3** for the design ecosystem — SynBioHub, SBOLCanvas, iBioSim. Each
part keeps its identity, its role as a Sequence Ontology term, and its
coordinates. **GenBank** for the software on a student's laptop — SnapGene, ApE,
Benchling, Geneious — which read GenBank and not SBOL.

The SBOL writer is hand-rolled `xml.etree` rather than the `sbol2` library,
which would pull rdflib, lxml, requests and urllib3 into a runtime image that
has four dependencies and is not a web client. `sbol2` is a *development*
dependency instead: the test suite reads every exported document back with the
reference implementation and checks the parts, roles and coordinates survived.

A placeholder CDS exports as a run of N. That is the honest export of what the
compiler decided — the region is this long and its bases are not known here —
and the GenBank feature carries a note saying so.

## Samples, and whether they still teach

Six sequences in `DNA-Frontend/resources/samples`, each engineered so that one
specific thing becomes visible when it is run through the real service. Each
arrives with its question attached, in all three languages — a sequence handed
over without one is a sequence nobody knows what to do with.

| Sample | What it makes visible |
|---|---|
| `gc-skew.fasta` | A statistic that means nothing base by base and a great deal cumulatively |
| `variants.fasta` | One synonymous, one missense and one nonsense change — all single bases |
| `reverse-strand-orf.fasta` | Why a reading-frame search has to look at six frames, not three |
| `ambiguous-bases.fasta` | What the tool stops being able to say when a base is N |
| `cloning-trap.fasta` | An insert carrying the site you were about to clone it with |
| `plasmid-topology.fasta` | One molecule, one enzyme, two different digests |

They are generated rather than committed by hand:

```bash
cd DNA-Backend && python tools/build_samples.py
```

That script **refuses to write anything** unless every sample still produces the
result it claims — the variants file must still yield exactly one change of each
consequence, the trap must still spring the tail-site warning, the reverse-strand
ORF must still be the longest one. Run it after any change to the analysis or
cloning services. A lesson plan that quietly stopped working wastes a class's
time, and the instructor finds out in front of them.

`cloning-trap.fasta` is the one worth using first. The form arrives pre-filled,
because a reader asked to choose the enzymes themselves picks a different pair
and never meets the warning.

## Validation

The numbers are checked against implementations written by people who had never
seen this codebase — `primer3` for melting temperatures and secondary structure,
EMBOSS `getorf` for reading frames. Melting temperatures agree with primer3
**exactly**, to the two decimal places reported, and any of them can be
reproduced in one line:

```bash
oligotm -tp 1 -sc 1 -mv 50 -dv 1.5 -n 0.2 -d 250 ATGCGTAAAGGAGAAGAACT
```

That was not the first result. Two conventions had to change to get there, and
the full record — including what is still unchecked — is in
[docs/VALIDATION.md](docs/VALIDATION.md).

## A note on the compiler's output

The compiler produces a **teaching draft, not an order-ready construct**.

Regulatory sequences (promoters, RBS, terminators) are included in full. Coding
sequences are not: they are referenced by registry ID and emitted as annotated
placeholders, because transcribing a 720 bp CDS from memory risks a silent
single-base error that produces a construct which looks right and does not work.
Fetch each one from parts.igem.org.

The biocontainment effector is deliberately left unselected. Which gene kills the
cell is a biosafety decision for you and your institution.

Any real build needs part verification, a compatible host, and institutional
biosafety review.

## Credits

Built for the College of Science, University of Raparin.
Sequence analysis by [Biopython](https://biopython.org/).
Fonts: Vazirmatn, Inter and JetBrains Mono, all under the SIL Open Font License.
