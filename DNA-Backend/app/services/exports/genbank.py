"""GenBank export for compiled circuits.

SBOL is the standard the synthetic biology *design* ecosystem speaks. GenBank is
the format the software on a student's laptop actually opens — SnapGene, ApE,
Benchling, Geneious all read it, and none of them reads SBOL. Exporting both is
not indecision: they answer different questions, and a teaching tool that can
only produce the more principled one has made a point rather than a plasmid map.

The annotations become features with `/label` and `/note` qualifiers, which is
what those programs draw on the map, so a circuit compiled here opens as an
annotated construct rather than as an unlabelled run of bases.
"""

from __future__ import annotations

from io import StringIO
from typing import Any

from Bio.Seq import Seq
from Bio.SeqFeature import FeatureLocation, SeqFeature
from Bio.SeqRecord import SeqRecord

# GenBank feature keys for what the compiler emits. `misc_feature` is the
# correct answer for anything without a dedicated key, not a fallback we are
# embarrassed about — assembly scars genuinely have no better one.
FEATURE_KEYS: dict[str, str] = {
    "promoter": "promoter",
    "rbs": "RBS",
    "cds": "CDS",
    "terminator": "terminator",
    "tag": "misc_feature",
    "scar": "misc_feature",
    "spacer": "misc_feature",
}

# GenBank LOCUS names are limited and conventionally alphanumeric.
_MAX_LOCUS = 16


def _locus(name: str, index: int) -> str:
    cleaned = "".join(ch if ch.isalnum() else "_" for ch in str(name or ""))
    cleaned = cleaned.strip("_") or f"unit_{index + 1}"
    return cleaned[:_MAX_LOCUS]


def _feature(annotation: dict[str, Any]) -> SeqFeature | None:
    try:
        start = int(annotation["start"])
        end = int(annotation["end"])
    except (KeyError, TypeError, ValueError):
        return None

    if end < start:
        return None

    role = str(annotation.get("role") or "")
    qualifiers: dict[str, list[str]] = {
        "label": [str(annotation.get("name") or annotation.get("part_id") or role or "feature")],
    }

    part_id = str(annotation.get("part_id") or "")
    if part_id:
        qualifiers["note"] = [f"part={part_id}"]

    provenance = str(annotation.get("provenance") or "")
    if provenance == "placeholder":
        # The single most important thing a reader of this file needs to know:
        # these bases are N because the compiler refused to guess a CDS, not
        # because sequencing failed.
        qualifiers.setdefault("note", []).append(
            "sequence not included; fetch from the registry before ordering"
        )

    return SeqFeature(
        # Biopython locations are 0-based half-open; the compiler's annotations
        # are 1-based inclusive. This is the only place the two meet.
        FeatureLocation(start - 1, end),
        type=FEATURE_KEYS.get(role, "misc_feature"),
        qualifiers=qualifiers,
    )


def _record(unit: dict[str, Any], index: int, source: str) -> SeqRecord:
    sequence = str(unit.get("sequence") or "")
    name = str(unit.get("name") or f"unit_{index + 1}")

    record = SeqRecord(
        Seq(sequence),
        id=_locus(name, index),
        name=_locus(name, index),
        description=f"{name} ({unit.get('purpose', 'unit')}) - compiled from: {source}"[:200],
        annotations={
            "molecule_type": "DNA",
            "topology": "linear",
            "data_file_division": "SYN",
            "source": "synthetic construct",
            "organism": "synthetic construct",
            "comment": (
                "Teaching draft, not an order-ready construct. Coding sequences are "
                "placeholders of the correct length; verify every part before ordering."
            ),
        },
    )

    for annotation in unit.get("annotations") or []:
        feature = _feature(annotation)
        if feature is not None:
            record.features.append(feature)

    return record


def document(compiled: dict[str, Any], *, source: str = "") -> str:
    """Serialise a compiler result as a multi-record GenBank file."""
    records = [
        _record(unit, index, source)
        for index, unit in enumerate(compiled.get("units") or [])
        if unit.get("sequence")
    ]

    if not records:
        return ""

    handle = StringIO()
    from Bio import SeqIO

    SeqIO.write(records, handle, "genbank")
    return handle.getvalue()
