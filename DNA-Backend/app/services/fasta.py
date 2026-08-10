"""FASTA ingestion: parse, analyse every record, summarise the dataset."""

from __future__ import annotations

import hashlib
from io import StringIO
from typing import Any

from Bio import SeqIO

from ..config import settings
from ..errors import AnalysisError, ErrorCode
from . import compare, sequence as seq_service

# Keys never sent to the client. Full sequences can be megabytes each and would
# bloat every response; the UI only needs them on explicit request.
_PRIVATE_KEYS = {"sequence"}


def parse(contents: str) -> list[Any]:
    try:
        records = list(SeqIO.parse(StringIO(contents), "fasta"))
    except Exception as exc:  # pragma: no cover - Biopython rarely raises here
        raise AnalysisError(ErrorCode.FASTA_UNPARSABLE) from exc

    if not records:
        raise AnalysisError(ErrorCode.FASTA_EMPTY)

    if len(records) > settings.max_records:
        raise AnalysisError(
            ErrorCode.TOO_MANY_RECORDS,
            status_code=413,
            found=len(records),
            maximum=settings.max_records,
        )

    return records


def summarise(genes: list[dict[str, Any]]) -> dict[str, Any]:
    lengths = [gene["length"] for gene in genes]
    gc_values = [gene["gc_content"] for gene in genes]
    total_bases = sum(lengths)

    unknown = sum(gene["base_composition"]["unknown_bases"] for gene in genes)

    return {
        "total_genes": len(genes),
        "total_bases": total_bases,
        "average_length": round(total_bases / len(genes), 2),
        "average_gc": round(sum(gc_values) / len(genes), 2),
        "min_length": min(lengths),
        "max_length": max(lengths),
        "min_gc": min(gc_values),
        "max_gc": max(gc_values),
        "unknown_bases": unknown,
        "unknown_fraction": round(unknown / total_bases * 100, 2) if total_bases else 0.0,
        "records_with_ambiguity": sum(1 for gene in genes if gene["quality"]["has_ambiguity"]),
    }


def analyse(contents: str) -> dict[str, Any]:
    records = parse(contents)

    genes = [
        seq_service.analyse_record(record.id, record.description, str(record.seq))
        for record in records
    ]

    comparisons = compare.compare_records(genes)
    public_genes = [
        {key: value for key, value in gene.items() if key not in _PRIVATE_KEYS}
        for gene in genes
    ]

    return {
        "status": "success",
        "version": settings.version,
        "checksum": hashlib.sha256(contents.encode("utf-8")).hexdigest()[:16],
        "summary": summarise(genes),
        "genes": public_genes,
        "comparisons": comparisons,
        "limits": {
            "align_max_bp": settings.align_max_bp,
            "orf_max_scan_bp": settings.orf_max_scan_bp,
            "tm_nn_max_bp": settings.tm_nn_max_bp,
        },
    }
