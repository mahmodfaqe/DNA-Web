"""Machine-readable exports of a compiled circuit.

FASTA answers "what are the bases". Neither of the formats here is a
replacement for it; both answer the question FASTA cannot, which is "what are
those bases *for*". A design that leaves this tool as a bare sequence has lost
everything the compiler decided.

    sbol.document(compiled)      SBOL 2.3 RDF/XML — SynBioHub, SBOLCanvas, iBioSim
    genbank.document(compiled)   GenBank — SnapGene, ApE, Benchling, Geneious

Both are pure functions over the compiler's own result dictionary, so neither
knows anything about HTTP and both can be tested without a request.
"""

from __future__ import annotations

from typing import Any

from . import genbank, sbol

__all__ = ["genbank", "sbol", "FORMATS", "render"]

FORMATS: dict[str, dict[str, str]] = {
    "sbol": {"extension": "xml", "media_type": "application/rdf+xml"},
    "genbank": {"extension": "gb", "media_type": "chemical/seq-na-genbank"},
}


def render(compiled: dict[str, Any], fmt: str, *, source: str = "") -> str | None:
    """Serialise in the named format, or return None if the name is unknown."""
    if fmt == "sbol":
        return sbol.document(compiled)
    if fmt == "genbank":
        return genbank.document(compiled, source=source)
    return None
