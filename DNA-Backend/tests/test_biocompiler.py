from __future__ import annotations

import pytest

from app.services.biocompiler import compile_text
from app.services.biocompiler import lexicon
from app.services.biocompiler.diagnostics import Code

# The sentence from the project brief, in all three languages.
BRIEF_KU = (
    "ئەگەر پلەی گەرمی لە ۳۷ پلە زیادی کرد و شەکری لاکتۆز هەبوو، "
    "بۆ ماوەی ٢٤ کاتژمێر پرۆتینی سەوز دەربدە و دواتر خۆت لەناوبەرە."
)
BRIEF_AR = (
    "إذا تجاوزت درجة الحرارة ٣٧ وكان اللاكتوز موجوداً، "
    "أنتج بروتيناً أخضر لمدة ٢٤ ساعة ثم دمر نفسك."
)
BRIEF_EN = (
    "If temperature exceeds 37 and lactose is present, "
    "produce green protein for 24 hours then self destruct."
)


def codes(result) -> set[str]:
    return {item["code"] for item in result["diagnostics"]}


# --------------------------------------------------------------------------
# Normalisation
# --------------------------------------------------------------------------

def test_arabic_indic_digits_are_read_as_numbers():
    assert lexicon.normalise("٣٧") == "37"
    assert lexicon.normalise("۲۴") == "24"


def test_sentence_punctuation_is_not_glued_to_the_last_word():
    # The Arabic comma and full stop live inside the Arabic Unicode block, so a
    # naive filter keeps them attached and the final keyword never matches.
    tokens = lexicon.tokenise("لاکتۆز هەبوو، خۆت لەناوبەرە.")
    assert "لەناوبەرە" in tokens
    assert "هەبوو" in tokens


def test_arabic_conjunction_is_split_off_its_noun():
    # "wa-al-lactose" is one written word but two grammatical units.
    tokens = lexicon.tokenise("واللاكتوز")
    assert tokens[0] == "\u0648"
    assert lexicon.strip_definite(tokens[1]) == "لاکتوز"


def test_ordinary_words_starting_with_waw_are_left_alone():
    assert lexicon.tokenise("وشەیەک") == ["وشەیەک"]


def test_keyboard_variants_normalise_to_one_form():
    # Arabic YEH vs Farsi YEH, Arabic KAF vs KEHEH.
    assert lexicon.normalise("لاكتوز") == lexicon.normalise("لاکتوز")


# --------------------------------------------------------------------------
# The headline property
# --------------------------------------------------------------------------

@pytest.mark.parametrize("text,expected", [
    (BRIEF_KU, "ku"),
    (BRIEF_AR, "ar"),
    (BRIEF_EN, "en"),
])
def test_each_language_parses_the_brief_sentence(text, expected):
    result = compile_text(text)

    assert result["ok"] is True
    assert result["specification"]["language"] == expected
    assert result["expression"] == "IF TEMPERATURE > 37 AND LACTOSE THEN GFP + SELF_DESTRUCT"


def test_all_three_languages_compile_to_identical_dna():
    """The point of a language-neutral IR: translation must not change the output."""
    sequences = [
        "".join(unit["sequence"] for unit in compile_text(text)["units"])
        for text in (BRIEF_KU, BRIEF_AR, BRIEF_EN)
    ]

    assert sequences[0] == sequences[1] == sequences[2]
    assert len(sequences[0]) > 0


def test_word_order_does_not_change_the_result():
    """Kurdish is verb-final, English verb-medial. Both must parse."""
    verb_last = compile_text("ئەگەر لاکتۆز هەبوو پرۆتینی سەوز دەربدە")
    verb_first = compile_text("produce green protein if lactose is present")

    assert verb_last["expression"] == verb_first["expression"]


# --------------------------------------------------------------------------
# Logic synthesis
# --------------------------------------------------------------------------

def test_single_condition_needs_no_logic_gate():
    result = compile_text("if lactose then produce green protein")
    types = [gate["type"] for gate in result["gates"]]

    assert "AND" not in types and "OR" not in types
    assert types.count("SENSOR") == 1


def test_and_builds_a_hybrid_promoter_and_warns_about_it():
    result = compile_text("if lactose and arabinose then produce green protein")

    assert any(g["type"] == "AND" for g in result["gates"])
    assert Code.HYBRID_PROMOTER_UNCHARACTERISED in codes(result)

    promoters = [p for p in result["parts"] if p["role"] == "promoter"]
    assert any(p["provenance"] == "designed" for p in promoters)


def test_or_builds_parallel_units_rather_than_a_hybrid():
    result = compile_text("if lactose or arabinose then produce green protein")

    assert any(g["type"] == "OR" for g in result["gates"])
    # One transcriptional unit per input promoter, both making the same protein.
    assert result["totals"]["units"] >= 2
    assert Code.HYBRID_PROMOTER_UNCHARACTERISED not in codes(result)


def test_negation_builds_an_inverter():
    result = compile_text("if lactose is absent then produce green protein")

    assert result["specification"]["conditions"][0]["negated"] is True
    assert any(g["type"] == "NOT" for g in result["gates"])
    assert any(u["purpose"] == "NOT" for u in result["units"])
    assert Code.INVERTER_ADDS_DELAY in codes(result)


# --------------------------------------------------------------------------
# Honest diagnostics — the part that makes this teachable
# --------------------------------------------------------------------------

def test_a_24_hour_timer_is_reported_as_not_encodable():
    """There is no sequence that means "for 24 hours".

    Degradation tags act on a scale of minutes. Saying so is the whole value of
    the diagnostic: the user learns the limit instead of trusting the output.
    """
    result = compile_text(BRIEF_EN)
    diagnostic = next(d for d in result["diagnostics"] if d["code"] == Code.DURATION_NOT_ENCODABLE)

    assert diagnostic["severity"] == "warning"
    assert diagnostic["params"]["requested_hours"] == 24.0


def test_temperature_threshold_cannot_be_dialled_in():
    result = compile_text("if temperature above 42 then produce green protein")
    diagnostic = next(d for d in result["diagnostics"] if d["code"] == Code.TEMPERATURE_THRESHOLD_FIXED)

    assert diagnostic["params"]["requested"] == 42.0
    assert diagnostic["params"]["actual"] == "32-37"


def test_kill_switch_is_never_chosen_automatically():
    """Which effector kills the cell is a biosafety decision, not a default."""
    result = compile_text(BRIEF_EN)

    assert Code.KILL_SWITCH_PLACEHOLDER in codes(result)

    effector = next(p for p in result["parts"] if p["id"] == "SELECT_BIOCONTAINMENT_PART")
    assert effector["provenance"] == "placeholder"


def test_coding_sequences_are_placeholders_not_guesses():
    result = compile_text(BRIEF_EN)
    coding = [p for p in result["parts"] if p["role"] == "cds"]

    assert coding, "expected at least one CDS"
    assert all(p["provenance"] == "placeholder" for p in coding)
    assert Code.CDS_PLACEHOLDER in codes(result)


def test_regulatory_parts_carry_real_sequence():
    result = compile_text("if lactose then produce green protein")
    promoter = next(p for p in result["parts"] if p["id"] == "BBa_R0010")

    assert promoter["provenance"] == "literal"

    sequence = result["units"][0]["sequence"]
    assert "GAATTCGCGGCCGCTTCTAGAG" in sequence          # BioBrick prefix
    assert "AAAGAGGAGAAA" in sequence                     # BBa_B0034 RBS
    assert sequence.endswith("TACTAGTAGCGGCCGCTGCAG")     # BioBrick suffix


def test_every_output_says_it_is_not_ready_for_synthesis():
    result = compile_text(BRIEF_EN)
    assert Code.NOT_FOR_SYNTHESIS in codes(result)
    assert "before ordering synthesis" in result["fasta"]


# --------------------------------------------------------------------------
# Failure modes
# --------------------------------------------------------------------------

def test_empty_input_is_an_error_not_a_crash():
    result = compile_text("")
    assert result["ok"] is False
    assert Code.EMPTY_INPUT in codes(result)


def test_a_sentence_with_no_recognised_sensor_fails_clearly():
    result = compile_text("please make the cells happy")
    assert result["ok"] is False
    assert Code.NO_CONDITION in codes(result)


def test_a_condition_with_no_output_fails():
    result = compile_text("if lactose is present")
    assert result["ok"] is False
    assert Code.NO_OUTPUT in codes(result)


def test_temperature_without_a_threshold_is_rejected():
    result = compile_text("if temperature then produce green protein")
    assert result["ok"] is False
    assert Code.MISSING_THRESHOLD in codes(result)


def test_diagnostics_never_contain_prose():
    """Same contract as the analysis API: codes and params, no sentences."""
    result = compile_text(BRIEF_KU)

    for diagnostic in result["diagnostics"]:
        assert set(diagnostic) == {"code", "severity", "params", "span"}
        assert diagnostic["severity"] in {"error", "warning", "info"}


def test_a_failed_compile_produces_no_dna():
    result = compile_text("")
    assert result["units"] == []
    assert result["fasta"] == ""


# --------------------------------------------------------------------------
# Assembly integrity
# --------------------------------------------------------------------------

def test_annotations_tile_the_sequence_exactly():
    """Every base must belong to exactly one annotated part.

    A gap or an overlap would mean the coordinates shown next to the sequence
    are lying about what is where.
    """
    result = compile_text(BRIEF_EN)

    for unit in result["units"]:
        cursor = 0
        for annotation in unit["annotations"]:
            assert annotation["start"] == cursor + 1, "gap or overlap in annotations"
            cursor = annotation["end"]
        assert cursor == unit["length"]


def test_sequence_contains_only_valid_symbols():
    result = compile_text(BRIEF_EN)

    for unit in result["units"]:
        assert set(unit["sequence"]) <= set("ATCGN")


def test_fasta_records_match_the_units():
    result = compile_text(BRIEF_EN)
    headers = [line for line in result["fasta"].splitlines() if line.startswith(">")]

    assert len(headers) == len(result["units"])
    for unit in result["units"]:
        assert any(unit["name"] in header for header in headers)


def test_compilation_is_deterministic():
    """A compiler that gives a different answer on Tuesday is not a compiler."""
    first = compile_text(BRIEF_KU)
    second = compile_text(BRIEF_KU)

    assert first["fasta"] == second["fasta"]
    assert first["diagnostics"] == second["diagnostics"]
