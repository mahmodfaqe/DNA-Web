# DNA Analytics

FASTA sequence analysis for teaching and research, in Kurdish, Arabic and English.

Upload a FASTA file and the system reports base composition, GC content and skew,
melting temperature, molecular weight, open reading frames across all six frames,
codon usage, and an aligned comparison that separates substitutions from
insertions and deletions and classifies each substitution as synonymous,
missense or nonsense.

**Other languages:** [کوردی](docs/README.ku.md) · [العربية](docs/README.ar.md)

---

## Two tools

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

## Architecture

```
browser ──► DNA-Frontend (Laravel 13, Apache) ──► DNA-Backend (FastAPI, Biopython)
                     │
                     └──► MySQL 8 (stored results)
```

| Service | Role | Exposed |
|---|---|---|
| `frontend` | Interface, localisation, result storage, exports | `:8080` |
| `backend` | Sequence analysis and variant calling | internal only |
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
pytest                                # 54 tests

# Web application
cd DNA-Frontend
composer install
php artisan test
```

The backend suite includes a regression test for the variant-calling bug
described in [docs/ASSESSMENT.ku.md](docs/ASSESSMENT.ku.md): a single inserted base
used to be reported as dozens of false substitutions.

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
| `CORS_ORIGINS` | `http://localhost:8080` | Set to your real domain in production |

Keep `MAX_FILE_SIZE` and `MAX_UPLOAD_KB` consistent, or a file will pass one
check and fail the other with a less helpful message.

## API

The analysis service documents itself at `/docs` when reachable.

| Endpoint | Purpose |
|---|---|
| `GET /health` | Liveness, version, in-memory job count |
| `POST /api/v1/analyze` | Analyse a FASTA file, returns the full result |
| `POST /api/v1/compile` | Compile a description into a circuit |
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
docker compose exec frontend php artisan analyses:prune --days=30
```

This runs daily through the scheduler. Adjust the window to whatever policy your
department settles on.

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
