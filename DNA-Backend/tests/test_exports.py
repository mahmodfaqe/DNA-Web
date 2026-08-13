"""Machine-readable export of a compiled circuit.

The point of both formats is that *something else* reads them, so nothing here
asserts against a remembered string. Every document is parsed back by the real
consumer — `sbol2` for SBOL, Biopython for GenBank — and checked for the parts,
roles and coordinates that went in. A serialiser that only satisfies its own
opinion of correctness is a serialiser that has not been tested.

`sbol2` is a development dependency. It is not installed in the runtime image,
which is the whole reason the writer is hand-rolled: the image keeps four
dependencies and the correctness claim still rests on the reference library.
"""

from __future__ import annotations

from io import StringIO

import pytest
import sbol2
from app.services import biocompiler
from app.services.exports import genbank, render, sbol
from Bio import SeqIO

SENTENCE = "if lactose and temperature above 37 then produce green protein for 24 hours"


@pytest.fixture(scope="module")
def compiled():
    result = biocompiler.compile_text(SENTENCE)
    assert result["ok"], "the fixture sentence must compile"
    return result


@pytest.fixture(scope="module")
def sbol_document(compiled, tmp_path_factory):
    """The exported document, read back by the reference SBOL library."""
    path = tmp_path_factory.mktemp("sbol") / "circuit.xml"
    path.write_text(sbol.document(compiled), encoding="utf-8")

    document = sbol2.Document()
    document.read(str(path))
    return document


# --------------------------------------------------------------------------
# SBOL: does a real library accept it
# --------------------------------------------------------------------------

def test_the_document_parses_as_sbol(sbol_document):
    assert len(sbol_document.componentDefinitions) > 0


def test_every_part_becomes_a_component_definition(compiled, sbol_document):
    exported = {cd.displayId for cd in sbol_document.componentDefinitions}

    for part in compiled["parts"]:
        expected = sbol._display_id(part["id"])
        assert expected in exported, f"{part['id']} missing from the document"


def test_every_unit_becomes_a_component_definition(compiled, sbol_document):
    exported = {cd.displayId for cd in sbol_document.componentDefinitions}

    for unit in compiled["units"]:
        assert sbol._display_id(unit["name"]) in exported


def test_roles_are_sequence_ontology_terms_not_local_words(compiled, sbol_document):
    """A consumer that has never heard of this project still knows SO:0000167.
    Exporting the compiler's own vocabulary would make the document unreadable
    to everything except this compiler."""
    by_id = {cd.displayId: cd for cd in sbol_document.componentDefinitions}

    for part in compiled["parts"]:
        definition = by_id[sbol._display_id(part["id"])]
        expected = sbol.SO_TERMS.get(part["role"], sbol.SO_ENGINEERED_REGION)
        assert definition.roles == [sbol.SO_PREFIX + expected]


def test_a_promoter_is_exported_as_a_promoter(sbol_document):
    promoters = [
        cd for cd in sbol_document.componentDefinitions
        if cd.roles == [sbol.SO_PREFIX + sbol.SO_TERMS["promoter"]]
    ]

    assert promoters, "the circuit has a promoter and the document should say so"


def test_annotations_keep_their_coordinates(compiled, sbol_document):
    """Ranges are 1-based inclusive in both the compiler and SBOL, so they pass
    through unchanged. This is the assertion that catches an off-by-one if
    either convention is ever misread."""
    by_id = {cd.displayId: cd for cd in sbol_document.componentDefinitions}

    for unit in compiled["units"]:
        definition = by_id[sbol._display_id(unit["name"])]
        exported = {
            (int(sa.locations[0].start), int(sa.locations[0].end))
            for sa in definition.sequenceAnnotations
        }
        for annotation in unit["annotations"]:
            assert (annotation["start"], annotation["end"]) in exported


def test_each_unit_carries_its_assembled_sequence(compiled, sbol_document):
    by_id = {cd.displayId: cd for cd in sbol_document.componentDefinitions}

    for unit in compiled["units"]:
        definition = by_id[sbol._display_id(unit["name"])]
        assert definition.sequences
        sequence = sbol_document.getSequence(definition.sequences[0])
        assert sequence.elements.upper() == unit["sequence"].upper()


def test_a_part_carries_its_own_bases_sliced_from_the_unit(compiled, sbol_document):
    """The compiler's public parts manifest has no sequence field; the bases
    live in the assembled unit and are recovered by coordinate. Without this a
    part is a bare identifier that no tool can draw."""
    by_id = {cd.displayId: cd for cd in sbol_document.componentDefinitions}
    unit = compiled["units"][0]
    annotation = unit["annotations"][1]

    definition = by_id[sbol._display_id(annotation["part_id"])]
    sequence = sbol_document.getSequence(definition.sequences[0])
    expected = unit["sequence"][annotation["start"] - 1:annotation["end"]]

    assert sequence.elements.upper() == expected.upper()


def test_a_placeholder_cds_exports_as_n_rather_than_as_a_guess(compiled, sbol_document):
    """The compiler refuses to transcribe a CDS from memory. The export has to
    carry that refusal rather than quietly omitting the part."""
    placeholders = [part for part in compiled["parts"] if part["provenance"] == "placeholder"]
    assert placeholders, "the fixture circuit should contain a placeholder CDS"

    by_id = {cd.displayId: cd for cd in sbol_document.componentDefinitions}
    definition = by_id[sbol._display_id(placeholders[0]["id"])]
    sequence = sbol_document.getSequence(definition.sequences[0])

    assert set(sequence.elements.upper()) == {"N"}


def test_components_and_annotations_are_linked(compiled, sbol_document):
    by_id = {cd.displayId: cd for cd in sbol_document.componentDefinitions}
    definition = by_id[sbol._display_id(compiled["units"][0]["name"])]

    component_uris = {component.identity for component in definition.components}
    linked = [sa for sa in definition.sequenceAnnotations if sa.component]

    assert linked, "annotations should point at the component they describe"
    for annotation in linked:
        assert annotation.component in component_uris


def test_display_ids_are_legal_sbol_identifiers():
    """The spec allows alphanumerics and underscore and forbids a leading digit.
    Tools reject documents that break this, so a part named "pLac (repressed)"
    has to become something legal."""
    assert sbol._display_id("pLac (LacI-repressed)") == "pLac_LacI_repressed"
    assert sbol._display_id("2020_part").startswith("_")
    assert sbol._display_id("") == "part"


def test_an_empty_result_produces_an_empty_but_valid_document():
    xml = sbol.document({"units": [], "parts": []})

    assert xml.startswith("<?xml")
    assert "RDF" in xml


# --------------------------------------------------------------------------
# GenBank: does the software a student actually has read it
# --------------------------------------------------------------------------

@pytest.fixture(scope="module")
def genbank_records(compiled):
    text = genbank.document(compiled, source=SENTENCE)
    return list(SeqIO.parse(StringIO(text), "genbank"))


def test_the_genbank_file_parses(genbank_records):
    assert genbank_records


def test_one_record_per_transcriptional_unit(compiled, genbank_records):
    assert len(genbank_records) == len(compiled["units"])


def test_sequences_survive_the_round_trip(compiled, genbank_records):
    for unit, record in zip(compiled["units"], genbank_records, strict=True):
        assert str(record.seq).upper() == unit["sequence"].upper()


def test_every_annotation_becomes_a_feature(compiled, genbank_records):
    for unit, record in zip(compiled["units"], genbank_records, strict=True):
        assert len(record.features) == len(unit["annotations"])


def test_feature_coordinates_convert_from_one_based_to_biopython(compiled, genbank_records):
    """The compiler counts 1-based inclusive; Biopython counts 0-based
    half-open. This is the only place the two meet, and a mistake here shifts
    every feature on the map by one base."""
    unit, record = compiled["units"][0], genbank_records[0]

    for annotation, feature in zip(unit["annotations"], record.features, strict=True):
        assert int(feature.location.start) == annotation["start"] - 1
        assert int(feature.location.end) == annotation["end"]


def test_a_promoter_gets_the_genbank_promoter_key(compiled, genbank_records):
    """`misc_feature` everywhere would parse fine and draw nothing useful."""
    keys = {feature.type for record in genbank_records for feature in record.features}

    assert "promoter" in keys
    assert "CDS" in keys


def test_features_are_labelled_with_the_part_name(compiled, genbank_records):
    unit, record = compiled["units"][0], genbank_records[0]

    for annotation, feature in zip(unit["annotations"], record.features, strict=True):
        assert feature.qualifiers["label"] == [annotation["name"]]
        assert f"part={annotation['part_id']}" in feature.qualifiers["note"]


def test_a_placeholder_feature_says_why_its_bases_are_n(genbank_records):
    notes = [
        note
        for record in genbank_records
        for feature in record.features
        for note in feature.qualifiers.get("note", [])
    ]

    assert any("fetch from the registry" in note for note in notes)


def test_the_file_says_it_is_not_order_ready(genbank_records):
    assert "Teaching draft" in genbank_records[0].annotations["comment"]


def test_an_empty_result_produces_no_genbank_rather_than_a_broken_one():
    assert genbank.document({"units": []}) == ""


# --------------------------------------------------------------------------
# Format dispatch
# --------------------------------------------------------------------------

def test_render_dispatches_on_format(compiled):
    assert render(compiled, "sbol").startswith("<?xml")
    assert render(compiled, "genbank").startswith("LOCUS")


def test_an_unknown_format_returns_none_rather_than_guessing(compiled):
    assert render(compiled, "snapgene") is None
