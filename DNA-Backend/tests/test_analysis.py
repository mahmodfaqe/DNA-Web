from __future__ import annotations

import pytest

from app.errors import AnalysisError, ErrorCode
from app.services import compare, fasta
from app.services import sequence as seq


# --------------------------------------------------------------------------
# Composition and GC
# --------------------------------------------------------------------------

def test_normalise_strips_newlines_and_case():
    assert seq.normalise("at\ncg\r\n gg ") == "ATCGGG"


def test_gc_excludes_unknown_bases():
    composition = seq.base_composition("GGCCNNNN")
    # 4 called bases, all G/C -> 100%, not 50%.
    assert composition["known_bases"] == 4
    assert seq.gc_content(composition) == 100.0


def test_iupac_codes_are_accepted_and_reported():
    codes = seq.validate("rec", "ATCGRYSW")
    assert codes == ["R", "S", "W", "Y"]


def test_truly_invalid_characters_are_rejected():
    with pytest.raises(AnalysisError) as info:
        seq.validate("rec", "ATCGZ")
    assert info.value.code == ErrorCode.SEQUENCE_INVALID_CHARS
    assert info.value.params["characters"] == ["Z"]


def test_gc_skew():
    assert seq.gc_skew(seq.base_composition("GGGC")) == 0.5
    assert seq.gc_skew(seq.base_composition("ATATAT")) is None


def test_reverse_complement():
    assert seq.reverse_complement("ATCGN") == "NCGAT"


# --------------------------------------------------------------------------
# Melting temperature: the method must match the sequence length
# --------------------------------------------------------------------------

def test_short_oligo_uses_wallace():
    assert seq.melting_temperature("ATCGATCGAT")["method"] == "wallace"


def test_primer_length_uses_nearest_neighbour():
    result = seq.melting_temperature("ATCGATCGATCGATCGATCG")
    assert result["method"] == "nearest_neighbour"
    assert result["reliable"] is True


def test_long_sequence_is_flagged_as_an_estimate():
    result = seq.melting_temperature("ATCG" * 200)
    assert result["method"] == "gc_empirical"
    assert result["reliable"] is False


# --------------------------------------------------------------------------
# ORF discovery
# --------------------------------------------------------------------------

def test_finds_orf_on_forward_strand():
    dna = "AAA" + "ATG" + "GCTGCTGCT" + "TAA" + "AAA"
    orfs = seq.find_open_reading_frames(dna)
    assert orfs["longest"]["protein"] == "MAAA"
    assert orfs["longest"]["strand"] == "+"
    assert orfs["longest"]["start"] == 4


def test_finds_orf_on_reverse_strand():
    forward = "ATG" + "GCTGCTGCTGCTGCT" + "TAA"
    dna = seq.reverse_complement(forward)
    orfs = seq.find_open_reading_frames(dna)
    assert orfs["longest"]["strand"] == "-"
    assert orfs["longest"]["protein"].startswith("M")


def test_codon_usage_ranks_by_frequency():
    usage = seq.codon_usage("ATGATGATGGCT")
    assert usage[0]["codon"] == "ATG"
    assert usage[0]["count"] == 3
    assert usage[0]["amino_acid"] == "M"


# --------------------------------------------------------------------------
# Variant calling — the core correctness fix
# --------------------------------------------------------------------------

def test_single_substitution():
    result = compare.compare_pair("a", "ATGAAATTT", "b", "ATGAACTTT")
    assert result["counts"]["substitution"] == 1
    assert result["counts"]["insertion"] == 0
    variant = result["variants"][0]
    assert variant["position"] == 6
    assert variant["reference_base"] == "A"
    assert variant["alternative_base"] == "C"


def test_insertion_is_one_event_not_a_cascade_of_substitutions():
    """A naive positional diff reports ~every downstream base as mutated.

    This is the bug the alignment layer exists to fix.
    """
    reference = "ATGAAATTTCCCGGGAAATTTCCC"
    alternative = "ATGAAAGTTTCCCGGGAAATTTCCC"  # one G inserted at position 7

    result = compare.compare_pair("a", reference, "b", alternative)

    assert result["counts"]["insertion"] == 1
    assert result["counts"]["substitution"] == 0
    assert result["total_variants"] == 1
    assert result["variants"][0]["frameshift"] is True
    assert result["identity_percent"] > 95


def test_deletion_is_detected_with_length():
    reference = "ATGAAATTTCCCGGGAAATTTCCC"
    alternative = "ATGAAATTTGGGAAATTTCCC"  # CCC deleted
    result = compare.compare_pair("a", reference, "b", alternative)

    assert result["counts"]["deletion"] == 1
    assert result["variants"][0]["length"] == 3
    assert result["variants"][0]["frameshift"] is False


def test_synonymous_substitution_is_classified():
    # GCT and GCC both encode alanine.
    result = compare.compare_pair("a", "ATGGCTTAA", "b", "ATGGCCTAA")
    variant = result["variants"][0]
    assert variant["effect"] == "synonymous"
    assert variant["ref_aa"] == variant["alt_aa"] == "A"


def test_nonsense_substitution_is_classified():
    # TGG (Trp) -> TGA (stop)
    result = compare.compare_pair("a", "ATGTGGAAA", "b", "ATGTGAAAA")
    effects = [v.get("effect") for v in result["variants"]]
    assert "nonsense" in effects


def test_identical_sequences_have_no_variants():
    result = compare.compare_pair("a", "ATGCATGC", "b", "ATGCATGC")
    assert result["total_variants"] == 0
    assert result["identity_percent"] == 100.0


def test_all_records_are_compared_against_the_first():
    records = [
        {"id": "a", "sequence": "ATGAAA"},
        {"id": "b", "sequence": "ATGAAC"},
        {"id": "c", "sequence": "ATGAAG"},
    ]
    comparisons = compare.compare_records(records)
    assert len(comparisons) == 2
    assert [c["alternative_id"] for c in comparisons] == ["b", "c"]


# --------------------------------------------------------------------------
# End-to-end FASTA handling
# --------------------------------------------------------------------------

SAMPLE = """>gene_1 first
ATGGCTGCTGCTTAA
>gene_2 second
ATGGCCGCTGCTTAA
"""


def test_analyse_returns_summary_and_comparisons():
    result = fasta.analyse(SAMPLE)
    assert result["summary"]["total_genes"] == 2
    assert result["summary"]["total_bases"] == 30
    assert len(result["comparisons"]) == 1
    assert result["checksum"]


def test_raw_sequences_never_leave_the_service():
    result = fasta.analyse(SAMPLE)
    assert all("sequence" not in gene for gene in result["genes"])


def test_non_fasta_text_raises_a_coded_error():
    with pytest.raises(AnalysisError) as info:
        fasta.analyse("no fasta here")
    assert info.value.code == ErrorCode.FASTA_UNPARSABLE


def test_empty_file_raises_a_coded_error():
    with pytest.raises(AnalysisError) as info:
        fasta.analyse("")
    assert info.value.code == ErrorCode.FASTA_EMPTY


def test_errors_carry_no_human_prose():
    """The whole i18n contract depends on this."""
    with pytest.raises(AnalysisError) as info:
        fasta.analyse(">x\nATCGZZ\n")
    payload = info.value.payload()
    assert set(payload["error"]) == {"code", "params"}
    assert payload["error"]["code"] == ErrorCode.SEQUENCE_INVALID_CHARS
