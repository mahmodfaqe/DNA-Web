"""Restriction analysis.

The enzyme data is Biopython's ``Bio.Restriction``, which is generated from
REBASE. Recognition sites, cut offsets, overhangs and supplier lists are
therefore not this project's assertions about biochemistry — they are REBASE's,
and they are the same numbers the enzyme's own datasheet carries.

What this module adds is the part a student actually has to reason about:

* which enzymes cut **exactly once**, because those are the only ones that can
  open a vector without also fragmenting it;
* which enzymes **do not cut at all**, because that is the list you choose a
  primer tail from;
* what the digest looks like **on a gel**, including which bands are too close
  together to tell apart — a digest that is correct on paper and unreadable in
  agarose has not answered the question that was asked.

Cut coordinates follow Biopython's convention: 1-based, naming the first base of
the downstream fragment. The recognition site's own start is reported alongside,
because those two numbers differ for every enzyme that does not cut in the
middle of its site, and confusing them is how a construct ends up one base out.
"""

from __future__ import annotations

from typing import Any

from Bio import Restriction

# Bio.Restriction builds its enzyme namespace at import time from REBASE, so
# these names exist at runtime but not to a static analyser.
from Bio.Restriction import CommOnly, RestrictionBatch  # type: ignore[attr-defined]
from Bio.Restriction.Restriction import RestrictionType
from Bio.Seq import Seq

from .diagnostics import Code, Report


def lookup(name: str) -> Any:
    """Find an enzyme by name, or return None.

    ``AllEnzymes.get`` raises on an unknown name, which turns "the user typed
    EccRI" into a 500. Attribute lookup on the module gives the same enzyme
    objects and lets a typo stay a diagnostic.
    """
    candidate = getattr(Restriction, str(name).strip(), None)
    return candidate if isinstance(candidate, RestrictionType) else None

# --------------------------------------------------------------------------
# Panels
# --------------------------------------------------------------------------

# What a teaching lab actually has in the freezer. Chosen to cover the pUC19 and
# pBluescript polylinkers plus the workhorses, and to include at least one
# blunt cutter (SmaI, EcoRV), one 3' overhang (KpnI, PstI) and one 8-cutter
# (NotI), so a class can see that "sticky ends" is three different behaviours.
TEACHING_PANEL: tuple[str, ...] = (
    "EcoRI", "BamHI", "HindIII", "XhoI", "XbaI", "PstI", "SalI", "NotI",
    "NcoI", "NdeI", "KpnI", "SacI", "SpeI", "SmaI", "ApaI", "BglII",
    "EcoRV", "NheI", "AgeI", "MluI", "SphI", "StuI", "ClaI", "AatII",
)

# Type IIS enzymes cut outside their recognition site, which is what makes
# scarless assembly possible. Kept as a separate panel because their cut
# coordinates surprise anyone who has only met the palindromic six-cutters.
GOLDEN_GATE_PANEL: tuple[str, ...] = (
    "BsaI", "BsmBI", "BbsI", "SapI", "AarI", "PaqCI",
)

PANELS: dict[str, tuple[str, ...]] = {
    "teaching": TEACHING_PANEL,
    "golden_gate": GOLDEN_GATE_PANEL,
}

# Two bands closer than this in relative size are one band on a normal agarose
# gel. It is a rule of thumb, not a measurement, and it is reported as such.
GEL_RESOLUTION_RATIO = 0.10

# Below this a fragment runs off the end of a normal gel and is simply not seen.
GEL_MIN_VISIBLE_BP = 100


def available_panels() -> dict[str, list[str]]:
    return {name: list(members) for name, members in PANELS.items()}


def _resolve_batch(names: list[str] | None, panel: str, report: Report) -> RestrictionBatch:
    """Turn a panel name or an explicit enzyme list into a RestrictionBatch."""
    if names:
        wanted: list[Any] = []
        for raw in names:
            name = str(raw).strip()
            enzyme = lookup(name)
            if enzyme is None:
                report.warn(Code.UNKNOWN_ENZYME, span=name, name=name)
                continue
            wanted.append(enzyme)
        if wanted:
            return RestrictionBatch(wanted)

    if panel == "commercial":
        report.info(Code.PANEL_SELECTED, panel="commercial", enzymes=len(CommOnly))
        return CommOnly

    members = PANELS.get(panel, TEACHING_PANEL)
    resolved = [lookup(name) for name in members]
    batch = RestrictionBatch([enzyme for enzyme in resolved if enzyme is not None])
    report.info(Code.PANEL_SELECTED, panel=panel if panel in PANELS else "teaching", enzymes=len(batch))
    return batch


def _overhang(enzyme: Any) -> dict[str, Any]:
    """How the enzyme leaves the ends, in the terms a ligation depends on."""
    if enzyme.is_blunt():
        kind = "blunt"
    elif enzyme.is_5overhang():
        kind = "five_prime"
    else:
        kind = "three_prime"

    return {
        "kind": kind,
        "length": abs(int(enzyme.ovhg)),
        "sequence": str(enzyme.ovhgseq or ""),
    }


def _fragments(cuts: list[int], length: int, circular: bool) -> list[int]:
    """Fragment lengths from a sorted list of cut positions.

    A linear molecule with n cuts gives n+1 fragments and the ends are free; a
    circular one gives n fragments and the last wraps through the origin. Getting
    this wrong is the classic off-by-one of every digest calculator, so the two
    cases are written out rather than shared.
    """
    if not cuts:
        return [length]

    ordered = sorted(cuts)

    if circular:
        sizes = [ordered[i + 1] - ordered[i] for i in range(len(ordered) - 1)]
        sizes.append(length - ordered[-1] + ordered[0])
        return sorted(sizes, reverse=True)

    sizes = [ordered[0] - 1]
    sizes += [ordered[i + 1] - ordered[i] for i in range(len(ordered) - 1)]
    sizes.append(length - ordered[-1] + 1)
    return sorted((size for size in sizes if size > 0), reverse=True)


def _unresolvable_pairs(sizes: list[int]) -> list[dict[str, int]]:
    """Adjacent band pairs a normal agarose gel would not separate."""
    pairs = []
    ordered = sorted(sizes, reverse=True)
    for larger, smaller in zip(ordered, ordered[1:], strict=False):
        if larger <= 0:
            continue
        if (larger - smaller) / larger < GEL_RESOLUTION_RATIO:
            pairs.append({"larger": larger, "smaller": smaller})
    return pairs


def digest(
    sequence: str,
    report: Report,
    *,
    enzymes: list[str] | None = None,
    panel: str = "teaching",
    circular: bool = False,
) -> dict[str, Any]:
    """Search a sequence with a panel and describe every enzyme's behaviour."""
    seq = Seq(sequence)
    length = len(sequence)
    batch = _resolve_batch(enzymes, panel, report)

    found = batch.search(seq, linear=not circular)

    results: list[dict[str, Any]] = []
    for enzyme, cuts in found.items():
        sites = [
            {
                "site_start": cut - enzyme.fst5,
                "cut_after": cut - 1,
                "cut_position": cut,
            }
            for cut in sorted(cuts)
        ]
        sizes = _fragments(list(cuts), length, circular)
        unresolvable = _unresolvable_pairs(sizes) if len(cuts) > 0 else []

        results.append({
            "enzyme": str(enzyme),
            "site": str(enzyme.site),
            "recognition_length": int(enzyme.size),
            "cuts_outside_site": bool(enzyme.fst5 > enzyme.size or enzyme.fst5 < 0),
            "overhang": _overhang(enzyme),
            "cut_count": len(cuts),
            "sites": sites,
            "fragments": sizes,
            "invisible_fragments": [size for size in sizes if size < GEL_MIN_VISIBLE_BP],
            "unresolvable_pairs": unresolvable,
            "suppliers": len(enzyme.supplier_list()),
        })

    results.sort(key=lambda item: (item["cut_count"], item["enzyme"]))

    unique = [item["enzyme"] for item in results if item["cut_count"] == 1]
    absent = [item["enzyme"] for item in results if item["cut_count"] == 0]
    cutters = [item for item in results if item["cut_count"] > 0]

    if not unique:
        report.warn(Code.NO_UNIQUE_CUTTER, panel=panel, searched=len(results))

    for item in cutters:
        if item["unresolvable_pairs"]:
            # Scalars, not the nested list. A diagnostic parameter is something
            # a translated sentence substitutes into, so it has to be a value a
            # sentence can hold — the renderer that formats these is shared by
            # every tool and joins lists into text. Handing it a list of
            # dictionaries took the whole result page down with a 500.
            closest = min(
                item["unresolvable_pairs"],
                key=lambda pair: pair["larger"] - pair["smaller"],
            )
            report.warn(
                Code.FRAGMENTS_UNRESOLVABLE,
                span=item["enzyme"],
                enzyme=item["enzyme"],
                larger=closest["larger"],
                smaller=closest["smaller"],
                pairs=len(item["unresolvable_pairs"]),
            )

    # Two properties this tool does not model, said once rather than implied by
    # silence. Dam/Dcm methylation blocks a real digest that this analysis calls
    # clean, and star activity produces cuts at sites that are not in the list.
    report.info(Code.METHYLATION_UNCHECKED)
    report.info(Code.STAR_ACTIVITY_UNCHECKED)

    return {
        "length": length,
        "topology": "circular" if circular else "linear",
        "searched": len(results),
        "enzymes": results,
        "unique_cutters": unique,
        "non_cutters": absent,
        "cutter_count": len(cutters),
    }


def cuts_within(sequence: str, enzyme_name: str) -> int | None:
    """How many times one named enzyme cuts a sequence.

    Used by the primer designer to answer the question that decides whether a
    cloning strategy works at all: does the site I am about to add to a tail
    already occur inside the fragment I am trying to clone?

    Returns ``None`` when the enzyme is not in REBASE, so the caller can tell
    "does not cut" apart from "never looked".
    """
    enzyme = lookup(enzyme_name)
    if enzyme is None:
        return None
    return len(enzyme.search(Seq(sequence), linear=True))


def overhang_of(enzyme_name: str) -> dict[str, Any] | None:
    enzyme = lookup(enzyme_name)
    if enzyme is None:
        return None
    return {"enzyme": str(enzyme), "site": str(enzyme.site), **_overhang(enzyme)}
