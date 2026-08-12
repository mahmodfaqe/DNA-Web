"""Single-sequence metrics.

Everything here is a pure function over an already-normalised sequence string,
which makes the whole module trivially unit-testable without FastAPI.
"""

from __future__ import annotations

from typing import Any

from Bio.Seq import Seq
from Bio.SeqUtils import MeltingTemp as mt
from Bio.SeqUtils import molecular_weight

from ..config import settings
from ..errors import AnalysisError, ErrorCode

# Unambiguous bases plus N.
CORE_BASES = frozenset("ATCGN")

# IUPAC ambiguity codes. Real-world FASTA (NCBI, Ensembl, Sanger reads) is full
# of these; rejecting them outright made the previous version unusable on any
# genuine dataset. We accept them and report them instead.
IUPAC_AMBIGUITY = {
    "R": "AG", "Y": "CT", "S": "GC", "W": "AT", "K": "GT", "M": "AC",
    "B": "CGT", "D": "AGT", "H": "ACT", "V": "ACG",
}

ALLOWED_BASES = CORE_BASES | set(IUPAC_AMBIGUITY)

_COMPLEMENT = str.maketrans("ATCGN", "TAGCN")


def normalise(sequence: str) -> str:
    """Upper-case and strip every whitespace character, including newlines."""
    return "".join(str(sequence).upper().split())


def validate(record_id: str, sequence: str) -> list[str]:
    """Validate a sequence, returning the list of ambiguity codes it contains."""
    if not sequence:
        raise AnalysisError(ErrorCode.SEQUENCE_EMPTY, record_id=record_id)

    present = set(sequence)
    invalid = sorted(present - ALLOWED_BASES)
    if invalid:
        raise AnalysisError(
            ErrorCode.SEQUENCE_INVALID_CHARS,
            record_id=record_id,
            characters=invalid,
        )

    return sorted(present & set(IUPAC_AMBIGUITY))


def base_composition(sequence: str) -> dict[str, Any]:
    counts = {base: sequence.count(base) for base in "ATCG"}
    n_count = sequence.count("N")
    ambiguous = len(sequence) - sum(counts.values()) - n_count
    known = sum(counts.values())

    return {
        **counts,
        "N": n_count,
        "ambiguous": ambiguous,
        "known_bases": known,
        "unknown_bases": n_count + ambiguous,
    }


def gc_content(composition: dict[str, Any]) -> float:
    """GC% over *called* bases only — N and IUPAC codes are excluded, not
    silently counted as non-GC, which would deflate the value."""
    known = composition["known_bases"]
    if known == 0:
        return 0.0
    return round((composition["G"] + composition["C"]) / known * 100, 2)


def gc_skew(composition: dict[str, Any]) -> float | None:
    """(G-C)/(G+C). Used to locate replication origins in bacterial genomes."""
    gc = composition["G"] + composition["C"]
    if gc == 0:
        return None
    return round((composition["G"] - composition["C"]) / gc, 4)


def reverse_complement(sequence: str) -> str:
    core = "".join(ch if ch in CORE_BASES else "N" for ch in sequence)
    return core.translate(_COMPLEMENT)[::-1]


def melting_temperature(sequence: str) -> dict[str, Any]:
    """Melting temperature with an explicitly reported method.

    Nearest-neighbour thermodynamics (Tm_NN) model a short duplex in solution;
    applying it to a multi-kilobase gene produces a number with no physical
    meaning. So the method is chosen by length and *named in the response*, and
    long sequences are labelled as an empirical GC estimate rather than a
    measurement.
    """
    clean = "".join(ch for ch in sequence if ch in "ATCG")
    if not clean:
        return {"value": None, "method": "none", "reliable": False}

    length = len(clean)
    try:
        if length <= 13:
            return {"value": round(float(mt.Tm_Wallace(clean)), 2), "method": "wallace", "reliable": True}
        if length <= settings.tm_nn_max_bp:
            value = round(float(mt.Tm_NN(clean)), 2)
            return {"value": value, "method": "nearest_neighbour", "reliable": True}
        return {"value": round(float(mt.Tm_GC(clean)), 2), "method": "gc_empirical", "reliable": False}
    except Exception:  # pragma: no cover - Biopython edge cases
        return {"value": None, "method": "none", "reliable": False}


def estimated_molecular_weight(sequence: str) -> float | None:
    clean = "".join(ch for ch in sequence if ch in "ATCG")
    if not clean:
        return None
    try:
        return round(float(molecular_weight(Seq(clean), seq_type="DNA", double_stranded=True)), 2)
    except Exception:  # pragma: no cover
        return None


def _translate_frame(sequence: str) -> str:
    trimmed = sequence[: len(sequence) - (len(sequence) % 3)]
    if not trimmed:
        return ""
    return str(Seq(trimmed).translate())


def find_open_reading_frames(sequence: str, top: int = 3) -> dict[str, Any]:
    """Find ORFs across all six reading frames.

    Implemented in protein space: each frame is translated once (a single C-level
    Biopython call), then split on stop codons. Within each inter-stop segment the
    first Met marks the ORF start. This is O(n) per frame instead of a Python-level
    codon loop, which matters on multi-megabase input.
    """
    truncated = len(sequence) > settings.orf_max_scan_bp
    scan = sequence[: settings.orf_max_scan_bp] if truncated else sequence
    reverse = reverse_complement(scan)
    length = len(scan)

    orfs: list[dict[str, Any]] = []

    for strand, strand_seq in (("+", scan), ("-", reverse)):
        for frame in range(3):
            protein = _translate_frame(strand_seq[frame:])
            if not protein:
                continue

            cursor = 0  # amino-acid offset of the current segment
            for segment in protein.split("*"):
                met = segment.find("M")
                if met != -1 and len(segment) - met >= 1:
                    peptide = segment[met:]
                    aa_start = cursor + met
                    nt_start = frame + aa_start * 3
                    nt_end = nt_start + len(peptide) * 3  # excludes the stop codon

                    # Report coordinates on the forward strand for both strands so
                    # a user can locate the ORF in the original file.
                    if strand == "+":
                        start, end = nt_start + 1, nt_end
                    else:
                        start, end = length - nt_end + 1, length - nt_start

                    orfs.append({
                        "strand": strand,
                        "frame": frame + 1,
                        "start": start,
                        "end": end,
                        "length_bp": len(peptide) * 3,
                        "length_aa": len(peptide),
                        "protein": peptide,
                    })
                cursor += len(segment) + 1  # +1 for the stop codon we split on

    orfs.sort(key=lambda item: item["length_aa"], reverse=True)
    best = orfs[:top]

    return {
        "count": len(orfs),
        "truncated": truncated,
        "scanned_bp": length,
        "longest": best[0] if best else None,
        "top": best,
    }


def codon_usage(sequence: str, top: int = 8) -> list[dict[str, Any]]:
    """Codon frequency in reading frame 1, most frequent first."""
    trimmed = sequence[: len(sequence) - (len(sequence) % 3)]
    if not trimmed:
        return []

    counts: dict[str, int] = {}
    for index in range(0, len(trimmed), 3):
        codon = trimmed[index:index + 3]
        if set(codon) <= set("ATCG"):
            counts[codon] = counts.get(codon, 0) + 1

    total = sum(counts.values())
    if total == 0:
        return []

    ranked = sorted(counts.items(), key=lambda item: (-item[1], item[0]))[:top]
    return [
        {
            "codon": codon,
            "amino_acid": str(Seq(codon).translate()),
            "count": count,
            "frequency": round(count / total * 100, 2),
        }
        for codon, count in ranked
    ]


def analyse_record(record_id: str, description: str, raw_sequence: str) -> dict[str, Any]:
    """Full metric set for one FASTA record."""
    sequence = normalise(raw_sequence)
    ambiguity_codes = validate(record_id, sequence)

    composition = base_composition(sequence)
    orfs = find_open_reading_frames(sequence)
    longest = orfs["longest"]

    return {
        "id": record_id,
        "description": description,
        "length": len(sequence),
        "gc_content": gc_content(composition),
        "at_content": round(100 - gc_content(composition), 2) if composition["known_bases"] else 0.0,
        "gc_skew": gc_skew(composition),
        "melting_temp": melting_temperature(sequence),
        "molecular_weight": estimated_molecular_weight(sequence),
        "base_composition": composition,
        "ambiguity_codes": ambiguity_codes,
        "quality": {
            "unknown_fraction": (
                round(composition["unknown_bases"] / len(sequence) * 100, 2) if sequence else 0.0
            ),
            "has_ambiguity": bool(ambiguity_codes) or composition["N"] > 0,
        },
        "orfs": orfs,
        "protein_length": longest["length_aa"] if longest else 0,
        "protein_sequence": longest["protein"] if longest else "",
        "codon_usage": codon_usage(sequence),
        "sequence": sequence,  # stripped before the response leaves the service
    }
