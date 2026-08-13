# Validation

Every number this service prints was produced by code written for this project.
That is a reason to distrust it, not a reason to trust it. This document records
what happened when the numbers were checked against implementations written by
people who had never seen this codebase, what agreed, what did not, and what was
changed as a result.

The checks live in `DNA-Backend/tests/test_validation.py` and run in CI, which
installs the reference tools. They skip on a machine without them, and CI asserts
that they did not skip — a validation that quietly stops running is a claim
rather than a check.

## References used

| Tool | Version | What it checks |
|---|---|---|
| `oligotm` (primer3) | 2.6.1 | Melting temperature |
| `ntthal` (primer3) | 2.6.1 | Hairpins and dimers, thermodynamically |
| `getorf` (EMBOSS) | 6.6.0 | Open reading frames |

`Bio.Restriction` is not validated against EMBOSS `restrict`, because `restrict`
needs a REBASE extract that has to be downloaded, and the offline environment
these checks run in cannot fetch it. Both tools read REBASE anyway, so agreement
would have shown little; the coordinate arithmetic this project adds on top is
checked against hand-specified sites in `test_cloning.py` instead.

---

## Melting temperature

**Result: exact agreement with primer3, to the two decimal places this service
rounds to.** 200 random oligos of 18–32 bases, worst case 0.005 °C.

Any temperature on the page can be reproduced in one line:

```bash
oligotm -tp 1 -sc 1 -mv 50 -dv 1.5 -n 0.2 -d 250 ATGCGTAAAGGAGAAGAACT
```

That was not the first result. Two things had to change, and neither would have
been found by reading the code.

### The concentration convention was wrong

The first implementation passed the stated 250 nM as `dnac1` with `dnac2=0`.
That models a primer in vast excess over its template, which is physically what
a PCR is, and it reads **1.7 °C hotter** than primer3 at the same nominal
figure. Biopython's own documentation gives the mapping: a total oligo
concentration of 250 nM means `dnac1=125, dnac2=125`.

A degree and a half is not a rounding difference. The suggested annealing
temperature is the lower Tm minus five, so the error goes straight onto a
thermocycler, and every calculator the reader might check against — primer3,
IDT, NEB — would have disagreed with this tool by more than a degree with no
explanation available on the page.

### The newer salt model was further from everything else

With the concentration fixed, Owczarzy 2008 (`saltcorr=7`) still differed from
primer3, and the difference **depended on base composition**, which a single
mean would have hidden:

| 24-mers, 60 per group | shipped: SantaLucia 1998 | Owczarzy 2008 | 250 nM as one strand |
|---|---|---|---|
| GC-rich (75%) | −0.00 | +0.61 | +1.71 |
| mixed | +0.00 | −0.41 | +1.69 |
| AT-rich (75%) | +0.00 | −1.33 | +1.67 |

Owczarzy 2008 is the more modern model and is defensible on accuracy grounds.
SantaLucia 1998 was shipped anyway, because under these conditions it is the
same calculation primer3 performs, and a number a student can verify against a
free reference implementation is worth more to a teaching tool than a degree of
contested accuracy. The choice is stated in the result payload as
`salt_correction`, so it travels with the number.

---

## Hairpins and dimers

**Result: the screen is one-directional, as documented, and the documentation is
not being modest.**

This service counts complementary bases; it does not fold anything or compute a
free energy. Two things were checked against `ntthal`, which does:

* **What it flags is real.** Of the primers whose stem reaches the reporting
  threshold, over 75% are structures `ntthal` also finds at ΔG below
  −500 cal/mol. The screen is not inventing hairpins.
* **What it misses is real too.** There are sequences with no stem long enough to
  report that `ntthal` scores below −2000 cal/mol. The test asserts this set is
  **non-empty** — if the screen ever caught everything, the stated limitation
  would be understating the tool and should be rewritten.

The second test is the unusual one, and it is deliberate. A limitation that is
documented but never demonstrated tends to drift into being false, and then the
documentation is lying in the safe-sounding direction.

---

## Open reading frames

**Result: agreement with EMBOSS `getorf` on the longest frame, within 3 bases.**

Checked on a 1.2 kb sequence across all six frames, comparing
`getorf -find 3 -minsize 150` against this service's own scan.

The tolerance is 3 bases rather than 0 because the two tools differ on whether
the terminating stop codon belongs to the ORF. That is a convention, not a
disagreement about where the frame is. Set equality was deliberately not
asserted: `getorf` also reports frames that run off the end of the sequence,
which this service excludes, and forcing the two to match exactly would have
meant weakening one of them to agree with the other.

---

## What is still unchecked

Stated plainly, because an unfinished list is more useful than an implied one.

* **Restriction sites** against an independent REBASE consumer (see above).
* **Primer *selection*** — that the pair chosen is a good pair. Only the
  properties of the reported primers are validated, not the search that picked
  them out of the candidates. primer3's own selection could be compared here and
  has not been.
* **The circuit compiler, simulator and memory designer.** The simulator is
  checked against closed-form theory in its own suite, which is a different and
  in some ways stronger kind of check; the compiler and the memory designer are
  checked only against themselves.
* **The SBOL and GenBank exports** are read back by the reference parsers
  (`sbol2` and Biopython), which proves they are well-formed and lossless. It
  does not prove SynBioHub or SnapGene will render them the way a reader expects.
