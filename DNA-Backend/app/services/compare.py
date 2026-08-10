"""Pairwise sequence comparison.

The previous implementation walked two sequences index-by-index. That is only
correct when the sequences differ by substitutions alone: a single inserted base
shifts every downstream position, so one real indel was reported as hundreds of
false substitutions. Biologically that is the difference between "these genes are
nearly identical" and "these genes are unrelated".

This module aligns first (Needleman-Wunsch with affine gaps), then calls variants
off the alignment, and additionally classifies each substitution as synonymous,
missense or nonsense.
"""

from __future__ import annotations

from typing import Any

from Bio.Align import PairwiseAligner
from Bio.Seq import Seq

from ..config import settings

_STANDARD = set("ATCG")


def _build_aligner() -> PairwiseAligner:
    aligner = PairwiseAligner()
    aligner.mode = "global"
    aligner.match_score = 2.0
    aligner.mismatch_score = -1.0
    aligner.open_gap_score = -5.0
    aligner.extend_gap_score = -0.5
    # Do not penalise gaps at the ends: partial reads should not be punished for
    # simply starting or stopping at a different point.
    # Biopython renamed these attributes in 1.85; support both spellings so the
    # service runs on whichever version the host image resolves.
    for modern, legacy in (("end_insertion_score", "target_end_gap_score"),
                           ("end_deletion_score", "query_end_gap_score")):
        try:
            setattr(aligner, modern, 0.0)
        except AttributeError:  # pragma: no cover - older Biopython
            setattr(aligner, legacy, 0.0)

    return aligner


def _codon_at(sequence: str, position: int) -> tuple[str, int, int] | None:
    """Return (codon, codon_number, offset_in_codon) for a 0-based position."""
    codon_index = position // 3
    start = codon_index * 3
    codon = sequence[start:start + 3]
    if len(codon) != 3:
        return None
    return codon, codon_index + 1, position - start


def _classify_substitution(reference: str, ref_pos: int, alt_base: str) -> dict[str, Any]:
    """Synonymous / missense / nonsense, judged in reading frame 1."""
    codon_info = _codon_at(reference, ref_pos)
    if codon_info is None:
        return {"effect": "unknown"}

    ref_codon, codon_number, offset = codon_info
    if not set(ref_codon) <= _STANDARD or alt_base not in _STANDARD:
        return {"effect": "unknown", "codon": codon_number}

    alt_codon = ref_codon[:offset] + alt_base + ref_codon[offset + 1:]
    ref_aa = str(Seq(ref_codon).translate())
    alt_aa = str(Seq(alt_codon).translate())

    if ref_aa == alt_aa:
        effect = "synonymous"
    elif alt_aa == "*":
        effect = "nonsense"
    elif ref_aa == "*":
        effect = "stop_lost"
    else:
        effect = "missense"

    return {
        "effect": effect,
        "codon": codon_number,
        "ref_codon": ref_codon,
        "alt_codon": alt_codon,
        "ref_aa": ref_aa,
        "alt_aa": alt_aa,
    }


def _variants_from_alignment(
    aligned_ref: str,
    aligned_alt: str,
    reference: str,
) -> list[dict[str, Any]]:
    """Walk an alignment and emit one event per variant, merging runs of gaps."""
    variants: list[dict[str, Any]] = []
    ref_pos = 0  # 0-based index into the ungapped reference
    alt_pos = 0
    index = 0
    total = len(aligned_ref)

    while index < total:
        ref_char = aligned_ref[index]
        alt_char = aligned_alt[index]

        if ref_char == "-":
            # Insertion in the alternative sequence: collect the whole run.
            run_start_alt = alt_pos
            inserted: list[str] = []
            while index < total and aligned_ref[index] == "-":
                inserted.append(aligned_alt[index])
                alt_pos += 1
                index += 1
            variants.append({
                "type": "insertion",
                "position": ref_pos + 1,
                "codon": (ref_pos // 3) + 1,
                "length": len(inserted),
                "inserted": "".join(inserted)[:60],
                "alt_position": run_start_alt + 1,
                "frameshift": len(inserted) % 3 != 0,
            })
            continue

        if alt_char == "-":
            run_start_ref = ref_pos
            deleted: list[str] = []
            while index < total and aligned_alt[index] == "-":
                deleted.append(aligned_ref[index])
                ref_pos += 1
                index += 1
            variants.append({
                "type": "deletion",
                "position": run_start_ref + 1,
                "codon": (run_start_ref // 3) + 1,
                "length": len(deleted),
                "deleted": "".join(deleted)[:60],
                "frameshift": len(deleted) % 3 != 0,
            })
            continue

        if ref_char != alt_char:
            variant = {
                "type": "substitution",
                "position": ref_pos + 1,
                "codon": (ref_pos // 3) + 1,
                "reference_base": ref_char,
                "alternative_base": alt_char,
                "transition": {ref_char, alt_char} in ({"A", "G"}, {"C", "T"}),
            }
            variant.update(_classify_substitution(reference, ref_pos, alt_char))
            variants.append(variant)

        ref_pos += 1
        alt_pos += 1
        index += 1

    return variants


def _positional_fallback(reference: str, alternative: str) -> list[dict[str, Any]]:
    """Index-by-index diff, used only when sequences are too long to align."""
    variants: list[dict[str, Any]] = []
    for position in range(min(len(reference), len(alternative))):
        if reference[position] != alternative[position]:
            variant = {
                "type": "substitution",
                "position": position + 1,
                "codon": (position // 3) + 1,
                "reference_base": reference[position],
                "alternative_base": alternative[position],
                "transition": {reference[position], alternative[position]} in ({"A", "G"}, {"C", "T"}),
            }
            variant.update(_classify_substitution(reference, position, alternative[position]))
            variants.append(variant)

    difference = abs(len(reference) - len(alternative))
    if difference:
        shorter = min(len(reference), len(alternative))
        variants.append({
            "type": "length_difference",
            "position": shorter + 1,
            "codon": (shorter // 3) + 1,
            "length": difference,
        })
    return variants


def compare_pair(
    reference_id: str,
    reference: str,
    alternative_id: str,
    alternative: str,
) -> dict[str, Any]:
    """Compare two sequences and summarise the differences."""
    can_align = (
        len(reference) <= settings.align_max_bp
        and len(alternative) <= settings.align_max_bp
        and reference
        and alternative
    )

    if can_align:
        aligner = _build_aligner()
        alignment = aligner.align(reference, alternative)[0]
        aligned_ref, aligned_alt = str(alignment[0]), str(alignment[1])
        variants = _variants_from_alignment(aligned_ref, aligned_alt, reference)
        matches = sum(
            1 for a, b in zip(aligned_ref, aligned_alt)
            if a == b and a != "-"
        )
        identity = round(matches / len(aligned_ref) * 100, 2) if aligned_ref else 0.0
        method = "global_alignment"
        aligned_length = len(aligned_ref)
    else:
        variants = _positional_fallback(reference, alternative)
        compared = min(len(reference), len(alternative))
        matches = compared - sum(1 for v in variants if v["type"] == "substitution")
        identity = round(matches / compared * 100, 2) if compared else 0.0
        method = "positional_diff"
        aligned_length = compared

    counts = {"substitution": 0, "insertion": 0, "deletion": 0, "length_difference": 0}
    effects = {"synonymous": 0, "missense": 0, "nonsense": 0, "stop_lost": 0, "unknown": 0}
    frameshifts = 0

    for variant in variants:
        counts[variant["type"]] = counts.get(variant["type"], 0) + 1
        if variant["type"] == "substitution":
            effects[variant.get("effect", "unknown")] = effects.get(variant.get("effect", "unknown"), 0) + 1
        if variant.get("frameshift"):
            frameshifts += 1

    return {
        "reference_id": reference_id,
        "alternative_id": alternative_id,
        "method": method,
        "identity_percent": identity,
        "aligned_length": aligned_length,
        "total_variants": len(variants),
        "counts": counts,
        "effects": effects,
        "frameshift_events": frameshifts,
        # Cap the payload: a UI table cannot render 50 000 rows usefully, and the
        # counts above already carry the summary.
        "variants": variants[:500],
        "variants_truncated": len(variants) > 500,
    }


def compare_records(records: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Compare the first record against every subsequent record.

    The old behaviour compared only records 1 and 2 and silently ignored the rest
    of the file, which was surprising for anyone uploading a multi-species set.
    """
    if len(records) < 2:
        return []

    reference = records[0]
    return [
        compare_pair(reference["id"], reference["sequence"], other["id"], other["sequence"])
        for other in records[1:]
    ]
