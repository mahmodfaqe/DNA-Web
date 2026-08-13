"""SBOL 2.3 export for compiled circuits.

Why this exists
---------------
Until now the compiler's output ended at FASTA. FASTA carries bases and a header
line and nothing else — the moment a design leaves this tool, the fact that
those 22 bases are a BioBrick prefix and the next 200 are a promoter is gone.
SBOL is the standard that carries the structure: each part keeps its own
identity, its role as a Sequence Ontology term, and its coordinates inside the
construct, so the design can be opened in SynBioHub, SBOLCanvas or iBioSim
rather than only read.

Why it is written by hand
-------------------------
The `sbol2` library would produce this in fewer lines, but it drags in rdflib,
lxml, requests and urllib3, and pins them. The runtime image currently has four
dependencies, and none of them is a web client. So the serialiser here is plain
`xml.etree`, and correctness is not asserted by this module's own opinion of
itself: the test suite reads every document back with the real `sbol2` library,
which is a development dependency only, and checks that what comes out the other
side has the parts, roles and coordinates that went in.

What SBOL2 rather than SBOL3
----------------------------
SBOL2 is what the tools a student can actually open a file in accept today.
SBOL3 is the newer standard and the natural next step; the mapping from the
structures here to SBOL3 is direct, and the seam is `document()`.
"""

from __future__ import annotations

import re
from typing import Any
from xml.etree import ElementTree as ET

NS = {
    "rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
    "sbol": "http://sbols.org/v2#",
    "dcterms": "http://purl.org/dc/terms/",
    "prov": "http://www.w3.org/ns/prov#",
}

# Sequence Ontology terms. These are the identifiers SBOL uses for what a part
# *is*, and they are what makes the export mean anything to another tool: a
# consumer that has never heard of this project still knows SO:0000167.
SO_TERMS: dict[str, str] = {
    "promoter": "SO:0000167",
    "rbs": "SO:0000139",
    "cds": "SO:0000316",
    "terminator": "SO:0000141",
    "tag": "SO:0000324",
    "scar": "SO:0001953",     # assembly scar
    "spacer": "SO:0000330",   # conserved region, the closest honest match
}
SO_ENGINEERED_REGION = "SO:0000804"

BIOPAX_DNA = "http://www.biopax.org/release/biopax-level3.owl#DnaRegion"
IUPAC_ENCODING = "http://www.chem.qmul.ac.uk/iubmb/misc/naseq.html"
SO_PREFIX = "http://identifiers.org/so/"

DEFAULT_NAMESPACE = "https://dna.uor.edu.krd/sbol"

_SAFE = re.compile(r"[^A-Za-z0-9_]+")


def _display_id(raw: str, fallback: str = "part") -> str:
    """An SBOL displayId: alphanumerics and underscore, not starting with a digit.

    The spec is strict about this and tools reject documents that break it, so a
    part called "pLac (LacI-repressed)" has to become something legal without
    two different parts collapsing onto the same identifier.
    """
    cleaned = _SAFE.sub("_", str(raw or "")).strip("_")
    if not cleaned:
        cleaned = fallback
    if cleaned[0].isdigit():
        cleaned = f"_{cleaned}"
    return cleaned


def _qname(prefix: str, local: str) -> str:
    return f"{{{NS[prefix]}}}{local}"


def _child(parent: ET.Element, prefix: str, local: str) -> ET.Element:
    return ET.SubElement(parent, _qname(prefix, local))


def _resource(parent: ET.Element, prefix: str, local: str, uri: str) -> ET.Element:
    element = _child(parent, prefix, local)
    element.set(_qname("rdf", "resource"), uri)
    return element


def _literal(parent: ET.Element, prefix: str, local: str, value: str) -> ET.Element:
    element = _child(parent, prefix, local)
    element.text = str(value)
    return element


def _top_level(root: ET.Element, kind: str, uri: str, display_id: str, name: str = "",
               description: str = "") -> ET.Element:
    element = _child(root, "sbol", kind)
    element.set(_qname("rdf", "about"), uri)
    _literal(element, "sbol", "persistentIdentity", uri)
    _literal(element, "sbol", "displayId", display_id)
    if name:
        _literal(element, "dcterms", "title", name)
    if description:
        _literal(element, "dcterms", "description", description)
    return element


def _sequence(root: ET.Element, namespace: str, display_id: str, elements: str) -> str:
    """A Sequence object, returned by URI so a ComponentDefinition can point at it."""
    uri = f"{namespace}/{display_id}_sequence"
    element = _top_level(root, "Sequence", uri, f"{display_id}_sequence")
    _literal(element, "sbol", "elements", elements.upper())
    _resource(element, "sbol", "encoding", IUPAC_ENCODING)
    return uri


def _part_definition(root: ET.Element, namespace: str, part: dict[str, Any]) -> str:
    """One ComponentDefinition per distinct part, with its role and its sequence."""
    display_id = _display_id(str(part.get("part_id") or part.get("id") or part.get("name") or ""))
    uri = f"{namespace}/{display_id}"

    definition = _top_level(
        root, "ComponentDefinition", uri, display_id,
        name=str(part.get("name") or display_id),
        description=str(part.get("note") or ""),
    )
    _resource(definition, "sbol", "type", BIOPAX_DNA)

    role = str(part.get("role") or "")
    _resource(definition, "sbol", "role", SO_PREFIX + SO_TERMS.get(role, SO_ENGINEERED_REGION))

    sequence = str(part.get("sequence") or "")
    if sequence:
        _resource(definition, "sbol", "sequence", _sequence(root, namespace, display_id, sequence))

    return uri


def _unit_definition(
    root: ET.Element,
    namespace: str,
    unit: dict[str, Any],
    part_uris: dict[str, str],
    index: int,
) -> str:
    """A transcriptional unit: an engineered region built from the parts above.

    Every part gets both a Component (it is made of this) and a
    SequenceAnnotation with a Range (it is at these coordinates), because a
    consumer that only reads one of the two still gets a usable picture.
    """
    display_id = _display_id(str(unit.get("name") or ""), f"unit_{index + 1}")
    uri = f"{namespace}/{display_id}"

    definition = _top_level(
        root, "ComponentDefinition", uri, display_id,
        name=str(unit.get("name") or display_id),
        description=str(unit.get("purpose") or ""),
    )
    _resource(definition, "sbol", "type", BIOPAX_DNA)
    _resource(definition, "sbol", "role", SO_PREFIX + SO_ENGINEERED_REGION)

    sequence = str(unit.get("sequence") or "")
    if sequence:
        _resource(definition, "sbol", "sequence", _sequence(root, namespace, display_id, sequence))

    for position, annotation in enumerate(unit.get("annotations") or [], start=1):
        part_id = str(annotation.get("part_id") or "")
        part_uri = part_uris.get(part_id)
        component_id = f"component_{position}"
        component_uri = f"{uri}/{component_id}"

        if part_uri:
            wrapper = _child(definition, "sbol", "component")
            component = _child(wrapper, "sbol", "Component")
            component.set(_qname("rdf", "about"), component_uri)
            _literal(component, "sbol", "persistentIdentity", component_uri)
            _literal(component, "sbol", "displayId", component_id)
            _resource(component, "sbol", "access", "http://sbols.org/v2#public")
            _resource(component, "sbol", "definition", part_uri)

        annotation_id = f"annotation_{position}"
        annotation_uri = f"{uri}/{annotation_id}"
        wrapper = _child(definition, "sbol", "sequenceAnnotation")
        node = _child(wrapper, "sbol", "SequenceAnnotation")
        node.set(_qname("rdf", "about"), annotation_uri)
        _literal(node, "sbol", "persistentIdentity", annotation_uri)
        _literal(node, "sbol", "displayId", annotation_id)
        _literal(node, "dcterms", "title", str(annotation.get("name") or part_id))

        range_uri = f"{annotation_uri}/range"
        location = _child(node, "sbol", "location")
        range_node = _child(location, "sbol", "Range")
        range_node.set(_qname("rdf", "about"), range_uri)
        _literal(range_node, "sbol", "persistentIdentity", range_uri)
        _literal(range_node, "sbol", "displayId", "range")
        # SBOL ranges are 1-based and inclusive, which is the convention the
        # compiler's annotations already use, so these pass through unchanged.
        _literal(range_node, "sbol", "start", str(annotation.get("start")))
        _literal(range_node, "sbol", "end", str(annotation.get("end")))
        _resource(range_node, "sbol", "orientation", "http://sbols.org/v2#inline")

        if part_uri:
            _resource(node, "sbol", "component", component_uri)

    return uri


def _part_sequences(compiled: dict[str, Any]) -> dict[str, str]:
    """Recover each part's own bases from the assembled units.

    The compiler's public parts manifest carries identity and length but not
    sequence — the bases live in the assembled unit. Slicing them back out by
    the annotation's coordinates gives every ComponentDefinition a real Sequence
    instead of a bare identifier, which is the difference between a document a
    tool can render and one it can only list.

    A placeholder CDS comes back as a run of N. That is the honest export of
    what the compiler decided: the region is this long and its bases are not
    known here.
    """
    sequences: dict[str, str] = {}
    for unit in compiled.get("units") or []:
        assembled = str(unit.get("sequence") or "")
        for annotation in unit.get("annotations") or []:
            part_id = str(annotation.get("part_id") or "")
            if not part_id or part_id in sequences:
                continue
            try:
                start = int(annotation["start"])
                end = int(annotation["end"])
            except (KeyError, TypeError, ValueError):
                continue
            fragment = assembled[start - 1:end]
            if fragment:
                sequences[part_id] = fragment
    return sequences


def document(compiled: dict[str, Any], *, namespace: str = DEFAULT_NAMESPACE) -> str:
    """Serialise a compiler result as an SBOL 2.3 RDF/XML document."""
    for prefix, uri in NS.items():
        ET.register_namespace(prefix, uri)

    root = ET.Element(_qname("rdf", "RDF"))
    bases = _part_sequences(compiled)

    # Parts first: a unit's components point at these, and a consumer reading
    # the document in order should never meet a reference before its target.
    part_uris: dict[str, str] = {}
    for part in compiled.get("parts") or []:
        part_id = str(part.get("id") or part.get("part_id") or "")
        if part_id and part_id not in part_uris:
            enriched = {**part, "sequence": part.get("sequence") or bases.get(part_id, "")}
            part_uris[part_id] = _part_definition(root, namespace, enriched)

    for index, unit in enumerate(compiled.get("units") or []):
        _unit_definition(root, namespace, unit, part_uris, index)

    ET.indent(root, space="  ")
    return ET.tostring(root, encoding="unicode", xml_declaration=True)
