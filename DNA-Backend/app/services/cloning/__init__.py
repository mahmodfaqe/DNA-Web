"""Cloning: restriction analysis and primer design, and the join between them.

    plan({"sequence": "...", "target": {"start": 120, "end": 890},
          "tails": {"forward_enzyme": "EcoRI", "reverse_enzyme": "XhoI"}})

Two tools that are usually separate are one tool here on purpose. The question a
student is actually answering at the bench is not "where does EcoRI cut" or
"what is a good primer" but the one that needs both at once: *can I amplify this
fragment and clone it into that vector*. Answering it requires knowing whether
the site you are about to add to a primer tail already occurs inside the
fragment you are amplifying — and getting that wrong is a week of cloning that
produces nothing, with no error message anywhere to explain why.

So the tail check is not an extra feature. It is the reason the two halves share
a module.
"""

from __future__ import annotations

from typing import Any

from . import primers as primer_service
from . import restriction as restriction_service
from .diagnostics import Code, Report

__all__ = ["plan", "Code", "Report", "PANELS"]

PANELS = restriction_service.PANELS

MAX_TEMPLATE_BP = 50_000
MIN_TEMPLATE_BP = 30
MIN_TARGET_BP = 50

# Bases added 5' of a restriction site so the enzyme has something to hold. Most
# enzymes cut poorly or not at all at the very end of a fragment; six extra bases
# is the conventional insurance and costs nothing but oligo length.
DEFAULT_CLAMP = "TTTTTT"


def _normalise(raw: Any) -> str:
    return "".join(str(raw or "").upper().split())


def _target_bounds(request: dict[str, Any], length: int, report: Report) -> tuple[int, int] | None:
    """Resolve the region to amplify as 0-based [start, end).

    Defaults to the whole template, which is what a user who only wants to know
    "what would amplify this" means.
    """
    target = request.get("target") or {}
    try:
        start = int(target.get("start", 1))
        end = int(target.get("end", length))
    except (TypeError, ValueError):
        report.error(Code.TARGET_OUT_OF_RANGE, start=target.get("start"), end=target.get("end"))
        return None

    if start < 1 or end > length or start >= end:
        report.error(Code.TARGET_OUT_OF_RANGE, start=start, end=end, length=length)
        return None

    if end - start + 1 < MIN_TARGET_BP:
        report.error(Code.TARGET_TOO_SHORT, found=end - start + 1, minimum=MIN_TARGET_BP)
        return None

    return (start - 1, end)


def _design_pair(
    template: str,
    bounds: tuple[int, int],
    request: dict[str, Any],
    report: Report,
) -> dict[str, Any] | None:
    start, end = bounds
    target_tm = float(request.get("target_tm") or primer_service.DEFAULT_TARGET_TM)
    min_length = int(request.get("min_length") or primer_service.MIN_LENGTH)
    max_length = int(request.get("max_length") or primer_service.MAX_LENGTH)
    min_length = max(15, min(min_length, 40))
    max_length = max(min_length, min(max_length, 45))

    forward = primer_service._candidates(template, start, "forward", target_tm, min_length, max_length)
    reverse = primer_service._candidates(template, end - 1, "reverse", target_tm, min_length, max_length)

    if not forward or not reverse:
        report.error(
            Code.NO_PRIMER_FOUND,
            forward_found=len(forward),
            reverse_found=len(reverse),
            target_tm=target_tm,
        )
        return None

    # Pair search rather than best-of-each: two individually excellent primers
    # that anneal 6 degrees apart are a worse pair than two merely good ones
    # that match. Only the strongest few of each are considered, because the
    # penalty ordering already means the rest are worse on every axis.
    best: tuple[float, Any, Any] | None = None
    for f in forward[:25]:
        for r in reverse[:25]:
            delta = abs(f.tm - r.tm)
            cross = primer_service.longest_complement(f.sequence, r.sequence)
            cross_three = primer_service.three_prime_complement(f.sequence, r.sequence)
            combined = (
                f.penalty + r.penalty
                + delta * 3.0
                + max(0, cross - primer_service.SELF_DIMER_MIN) * 2.0
                + (5.0 if cross_three >= primer_service.THREE_PRIME_DIMER_MIN else 0.0)
            )
            if best is None or combined < best[0]:
                best = (combined, f, r)

    assert best is not None
    _, chosen_f, chosen_r = best

    amplicon_start, amplicon_end = chosen_f.start, chosen_r.end
    amplicon = template[amplicon_start - 1:amplicon_end]
    delta = round(abs(chosen_f.tm - chosen_r.tm), 2)

    if delta > primer_service.MAX_PAIR_TM_DELTA:
        report.warn(
            Code.PRIMER_TM_MISMATCH,
            forward_tm=chosen_f.tm,
            reverse_tm=chosen_r.tm,
            delta=delta,
            maximum=primer_service.MAX_PAIR_TM_DELTA,
        )

    cross = primer_service.longest_complement(chosen_f.sequence, chosen_r.sequence)
    if cross >= primer_service.SELF_DIMER_MIN:
        report.warn(Code.PRIMER_PAIR_DIMER, bases=cross, threshold=primer_service.SELF_DIMER_MIN)

    for candidate, role in ((chosen_f, "forward"), (chosen_r, "reverse")):
        if "self_dimer" in candidate.flags or "three_prime_self_dimer" in candidate.flags:
            report.warn(
                Code.PRIMER_SELF_DIMER,
                span=role,
                primer=role,
                bases=primer_service.longest_complement(candidate.sequence, candidate.sequence),
            )
        if "hairpin" in candidate.flags:
            report.warn(
                Code.PRIMER_HAIRPIN,
                span=role,
                primer=role,
                stem=primer_service.hairpin_stem(candidate.sequence),
            )
        if "gc_out_of_range" in candidate.flags:
            report.warn(
                Code.PRIMER_GC_OUT_OF_RANGE,
                span=role,
                primer=role,
                gc=candidate.gc,
                minimum=primer_service.GC_MIN_PERCENT,
                maximum=primer_service.GC_MAX_PERCENT,
            )
        if "homopolymer_run" in candidate.flags:
            report.warn(
                Code.PRIMER_RUN_OF_BASES,
                span=role,
                primer=role,
                base=primer_service.longest_run(candidate.sequence)[0],
                run=primer_service.longest_run(candidate.sequence)[1],
            )
        matches = primer_service._occurrences(template, candidate.sequence)
        if matches > 1:
            report.warn(Code.PRIMER_NOT_UNIQUE, span=role, primer=role, matches=matches)

    # Said once, every time: a primer unique in the submitted template is not a
    # primer unique in a genome, and this tool has never seen the genome.
    report.info(Code.NOT_A_SPECIFICITY_CHECK)

    return {
        "forward": primer_service._as_dict(chosen_f, template),
        "reverse": primer_service._as_dict(chosen_r, template),
        "pair": {
            "tm_delta": delta,
            "cross_dimer_bp": cross,
            "annealing_suggestion": round(min(chosen_f.tm, chosen_r.tm) - 5.0, 1),
        },
        "amplicon": {
            "start": amplicon_start,
            "end": amplicon_end,
            "length": len(amplicon),
            "gc_percent": primer_service.gc_percent(amplicon),
            "sequence": amplicon,
        },
        "conditions": dict(primer_service.PCR_CONDITIONS),
        "criteria": {
            "target_tm": target_tm,
            "length_range": [min_length, max_length],
            "gc_range": [primer_service.GC_MIN_PERCENT, primer_service.GC_MAX_PERCENT],
            "max_pair_tm_delta": primer_service.MAX_PAIR_TM_DELTA,
        },
    }


def _add_tails(
    designed: dict[str, Any],
    request: dict[str, Any],
    report: Report,
) -> dict[str, Any] | None:
    """Prepend restriction sites to the primers, and check they are safe to use.

    The check is the point. A site added to a tail must not occur inside the
    amplicon, or the digest that is supposed to open the ends will also cut the
    insert in half.
    """
    tails = request.get("tails") or {}
    forward_enzyme = str(tails.get("forward_enzyme") or "").strip()
    reverse_enzyme = str(tails.get("reverse_enzyme") or "").strip()
    if not forward_enzyme and not reverse_enzyme:
        return None

    clamp = _normalise(tails.get("clamp", DEFAULT_CLAMP)) or DEFAULT_CLAMP
    amplicon = designed["amplicon"]["sequence"]

    result: dict[str, Any] = {"clamp": clamp, "ends": {}}
    overhangs: list[str] = []

    for enzyme_name, role in ((forward_enzyme, "forward"), (reverse_enzyme, "reverse")):
        if not enzyme_name:
            continue

        details = restriction_service.overhang_of(enzyme_name)
        if details is None:
            report.error(Code.UNKNOWN_ENZYME, span=enzyme_name, name=enzyme_name)
            continue

        cuts = restriction_service.cuts_within(amplicon, enzyme_name)
        if cuts:
            report.warn(
                Code.TAIL_SITE_CUTS_AMPLICON,
                span=enzyme_name,
                enzyme=details["enzyme"],
                site=details["site"],
                cuts=cuts,
                end=role,
            )

        binding = designed[role]["sequence"]
        full = clamp + details["site"] + binding

        # Two temperatures, and the difference between them is the thing most
        # often got wrong. In cycle one only the binding region is annealing —
        # the tail has nothing to pair with — so the annealing temperature must
        # come from the binding region alone. From cycle three the whole primer
        # is templated and the full-length Tm applies.
        result["ends"][role] = {
            "enzyme": details["enzyme"],
            "site": details["site"],
            "overhang": {
                "kind": details["kind"],
                "length": details["length"],
                "sequence": details["sequence"],
            },
            "sequence": full,
            "length": len(full),
            "binding_region": binding,
            "binding_tm": designed[role]["tm"],
            "full_length_tm": primer_service.tm(full),
            "cuts_inside_amplicon": cuts,
        }
        overhangs.append(f"{details['kind']}:{details['sequence']}")

    if not result["ends"]:
        return None

    # Two ends left with the same overhang can religate to each other, and the
    # insert goes in either way round. Directional cloning needs them different.
    if len(overhangs) == 2 and overhangs[0] == overhangs[1]:
        report.warn(
            Code.TAIL_SITES_INCOMPATIBLE,
            forward=forward_enzyme,
            reverse=reverse_enzyme,
            overhang=overhangs[0].split(":", 1)[1],
        )

    report.info(Code.TAIL_TM_EXCLUDES_TAIL)
    return result


def plan(request: dict[str, Any]) -> dict[str, Any]:
    """Analyse a template for cloning: where it cuts, and what would amplify it."""
    report = Report()
    sequence = _normalise(request.get("sequence"))

    if not sequence:
        report.error(Code.EMPTY_SEQUENCE)
        return _envelope(report, None, None, None)

    if len(sequence) > MAX_TEMPLATE_BP:
        sequence = sequence[:MAX_TEMPLATE_BP]

    if len(sequence) < MIN_TEMPLATE_BP:
        report.error(Code.SEQUENCE_TOO_SHORT, found=len(sequence), minimum=MIN_TEMPLATE_BP)
        return _envelope(report, None, None, None)

    invalid = sorted(set(sequence) - set("ATCGN"))
    ambiguous = sum(1 for base in sequence if base not in "ATCG")
    if invalid or ambiguous:
        report.warn(
            Code.AMBIGUOUS_BASES_IN_TEMPLATE,
            count=ambiguous,
            characters=invalid[:8],
        )

    digest = restriction_service.digest(
        sequence,
        report,
        enzymes=request.get("enzymes"),
        panel=str(request.get("panel") or "teaching"),
        circular=bool(request.get("circular")),
    )

    designed = None
    tails = None
    if request.get("design_primers", True):
        bounds = _target_bounds(request, len(sequence), report)
        if bounds is not None:
            designed = _design_pair(sequence, bounds, request, report)
            if designed is not None:
                tails = _add_tails(designed, request, report)

    return _envelope(report, digest, designed, tails)


def _envelope(
    report: Report,
    digest: dict[str, Any] | None,
    designed: dict[str, Any] | None,
    tails: dict[str, Any] | None,
) -> dict[str, Any]:
    return {
        "ok": not report.failed,
        "digest": digest,
        "primers": designed,
        "tails": tails,
        "diagnostics": report.as_list(),
        "diagnostic_counts": report.counts(),
    }
