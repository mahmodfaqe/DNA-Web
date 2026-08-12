"""Natural language to specification.

Deliberately a deterministic grammar over a trilingual lexicon rather than a
model call. A compiler must be reproducible: the same sentence has to give the
same DNA today and next year, the parse has to be explainable when it is wrong,
and a design tool should not need a network round trip. Where the grammar cannot
cope, it says so through a diagnostic instead of guessing.

`normalise_with_model` marks the seam where a language model belongs: as a
*pre-processor* that rewrites free-form prose into the canonical form this
grammar accepts, leaving the compilation itself deterministic and auditable.
"""

from __future__ import annotations

import re
from dataclasses import dataclass, field
from typing import Any

from . import lexicon
from .diagnostics import Code, Report

MAX_CONDITIONS = 4

_SENSOR_INDEX = lexicon.build_phrase_index(lexicon.SENSORS)
_ACTUATOR_INDEX = lexicon.build_phrase_index(lexicon.ACTUATORS)
_TERMINAL_INDEX = lexicon.build_phrase_index(lexicon.TERMINAL_ACTIONS)
_OPERATOR_INDEX = lexicon.build_phrase_index(lexicon.OPERATORS)
_CONNECTIVE_INDEX = lexicon.build_phrase_index(lexicon.CONNECTIVES)
_DURATION_INDEX = lexicon.build_phrase_index(lexicon.DURATION_UNITS)
_NEGATION_INDEX = lexicon.build_phrase_list(lexicon.NEGATION, "not")
_IF_INDEX = lexicon.build_phrase_list(lexicon.IF_MARKERS, "if")
_THEN_INDEX = lexicon.build_phrase_list(lexicon.THEN_MARKERS, "then")

_NUMBER = re.compile(r"^\d+(?:\.\d+)?$")


@dataclass
class Condition:
    sensor: str
    operator: str | None = None
    value: float | None = None
    unit: str | None = None
    negated: bool = False
    span: str = ""

    def as_dict(self) -> dict[str, Any]:
        return {
            "sensor": self.sensor,
            "operator": self.operator,
            "value": self.value,
            "unit": self.unit,
            "negated": self.negated,
            "span": self.span,
        }


@dataclass
class Output:
    actuator: str
    duration_seconds: int | None = None
    duration_value: float | None = None
    duration_unit: str | None = None

    def as_dict(self) -> dict[str, Any]:
        return {
            "actuator": self.actuator,
            "duration_seconds": self.duration_seconds,
            "duration_value": self.duration_value,
            "duration_unit": self.duration_unit,
        }


@dataclass
class Specification:
    conditions: list[Condition] = field(default_factory=list)
    connective: str = "and"
    outputs: list[Output] = field(default_factory=list)
    terminal: str | None = None
    language: str = "unknown"
    source: str = ""

    def as_dict(self) -> dict[str, Any]:
        return {
            "language": self.language,
            "connective": self.connective,
            "conditions": [c.as_dict() for c in self.conditions],
            "outputs": [o.as_dict() for o in self.outputs],
            "terminal": self.terminal,
            "source": self.source,
        }

    def boolean_expression(self) -> str:
        """Human-checkable rendering of what the parser understood."""
        if not self.conditions:
            return ""

        parts = []
        for condition in self.conditions:
            atom = condition.sensor.upper()
            if condition.operator and condition.value is not None:
                symbol = {"above": ">", "below": "<", "equals": "="}[condition.operator]
                atom = f"{atom} {symbol} {condition.value:g}"
            if condition.negated:
                atom = f"NOT({atom})"
            parts.append(atom)

        joined = f" {self.connective.upper()} ".join(parts)
        actions = [output.actuator.upper() for output in self.outputs]
        if self.terminal:
            actions.append(self.terminal.upper())

        return f"IF {joined} THEN {' + '.join(actions)}"


def normalise_with_model(text: str) -> str:
    """Seam for an optional language-model pre-processor.

    A model would rewrite loose prose into the canonical
    "if <sensor> <operator> <value> then <actuator> for <duration>" shape that
    the grammar below accepts, and the compilation would stay deterministic.
    Not wired up: the compiler runs offline and must stay reproducible.
    """
    return text


def _match_at(tokens: list[str], position: int, index: list[tuple[list[str], str]]) -> tuple[str, int] | None:
    """Longest-first phrase match anchored at `position`.

    Compares against each token's surface variants so one lexicon entry covers
    the bare noun and its definite form: "lactose" matches "al-lactose" without
    a second entry.
    """
    for phrase, symbol in index:
        end = position + len(phrase)
        window = tokens[position:end]
        if len(window) != len(phrase):
            continue
        # `window` was cut to len(phrase) above, so strict= only asserts that.
        pairs = zip(window, phrase, strict=True)
        if all(expected in lexicon.variants(actual) for actual, expected in pairs):
            return symbol, len(phrase)
    return None


def _find_first(tokens: list[str], index: list[tuple[list[str], str]]) -> int | None:
    for position in range(len(tokens)):
        if _match_at(tokens, position, index):
            return position
    return None


def _unit_after(tokens: list[str], position: int) -> str | None:
    """The measurement unit following a number, if any."""
    for probe in range(position, min(position + 3, len(tokens))):
        if tokens[probe] in lexicon.TEMPERATURE_UNITS:
            return "celsius"
        found = _match_at(tokens, probe, _DURATION_INDEX)
        if found:
            return found[0]
    return None


def _scan_number(
    tokens: list[str],
    start: int,
    window: int = 6,
    kind: str = "any",
) -> tuple[float | None, str | None, int | None]:
    """Find a number and its unit.

    `kind` filters by unit family, which is what keeps "37 degrees" from being
    read as a duration and "24 hours" from being read as a temperature threshold
    when both appear in one sentence.
    """
    for offset in range(start, min(start + window, len(tokens))):
        if not _NUMBER.match(tokens[offset]):
            continue

        unit = _unit_after(tokens, offset + 1)

        if kind == "duration" and unit not in lexicon.DURATION_SECONDS:
            continue
        if kind == "threshold" and unit in lexicon.DURATION_SECONDS:
            continue

        return float(tokens[offset]), unit, offset

    return None, None, None


def _split_clauses(tokens: list[str]) -> tuple[list[str], list[str]]:
    """Separate the condition clause from the action clause.

    Falls back to treating the whole sentence as both, so a sentence without an
    explicit "if"/"then" still parses rather than failing outright.
    """
    then_at = _find_first(tokens, _THEN_INDEX)
    if_at = _find_first(tokens, _IF_INDEX)

    if then_at is not None and (if_at is None or then_at > if_at):
        return tokens[:then_at], tokens[then_at:]

    return tokens, tokens


def _parse_conditions(tokens: list[str], report: Report) -> tuple[list[Condition], str]:
    conditions: list[Condition] = []
    connectives: list[str] = []
    position = 0
    pending_negation = False

    while position < len(tokens):
        negation = _match_at(tokens, position, _NEGATION_INDEX)
        if negation:
            pending_negation = True
            position += negation[1]
            continue

        connective = _match_at(tokens, position, _CONNECTIVE_INDEX)
        if connective and conditions:
            connectives.append(connective[0])
            position += connective[1]
            continue

        sensor = _match_at(tokens, position, _SENSOR_INDEX)
        if sensor:
            symbol, width = sensor
            span_end = min(position + width + 6, len(tokens))
            span = " ".join(tokens[position:span_end])

            # Word order differs by language: Arabic puts the comparison verb
            # before the sensor, Kurdish after it. Scan both sides rather than
            # writing a parser per language.
            operator = None
            for probe in range(position + width, min(position + width + 6, len(tokens))):
                found = _match_at(tokens, probe, _OPERATOR_INDEX)
                if found:
                    operator = found[0]
                    break

            if operator is None:
                for probe in range(max(0, position - 4), position):
                    found = _match_at(tokens, probe, _OPERATOR_INDEX)
                    if found:
                        operator = found[0]
                        break

            value, unit, _ = _scan_number(tokens, position + width, kind="threshold")

            # Negation is pre-nominal in Arabic ("without lactose") and
            # post-nominal in Kurdish and English ("lactose is absent"), so a
            # marker already seen counts, and so does one just ahead — stopping
            # at the next connective so it cannot be stolen from a later clause.
            negated = pending_negation
            if not negated:
                for probe in range(position + width, min(position + width + 4, len(tokens))):
                    if _match_at(tokens, probe, _CONNECTIVE_INDEX) or _match_at(tokens, probe, _SENSOR_INDEX):
                        break
                    if _match_at(tokens, probe, _NEGATION_INDEX):
                        negated = True
                        break

            # A sensor with no comparison is a presence test, which is exactly
            # what an inducible promoter does natively.
            conditions.append(Condition(
                sensor=symbol,
                operator=operator,
                value=value,
                unit=unit,
                negated=negated,
                span=span,
            ))
            pending_negation = False
            position += width
            continue

        position += 1

    if not conditions:
        report.error(Code.NO_CONDITION)
        return [], "and"

    if len(conditions) > MAX_CONDITIONS:
        report.error(Code.TOO_MANY_CONDITIONS, found=len(conditions), maximum=MAX_CONDITIONS)
        conditions = conditions[:MAX_CONDITIONS]

    unique = set(connectives)
    if len(unique) > 1:
        # Mixing "and" with "or" needs bracketing the grammar cannot recover.
        report.warn(Code.MIXED_CONNECTIVES, connectives=sorted(unique))

    connective = connectives[0] if connectives else "and"

    for condition in conditions:
        if condition.sensor == "temperature" and condition.value is None:
            report.error(Code.MISSING_THRESHOLD, span=condition.span, sensor=condition.sensor)

    return conditions, connective


def _parse_outputs(tokens: list[str], report: Report) -> tuple[list[Output], str | None]:
    outputs: list[Output] = []
    terminal: str | None = None
    position = 0

    while position < len(tokens):
        terminal_match = _match_at(tokens, position, _TERMINAL_INDEX)
        if terminal_match:
            terminal = terminal_match[0]
            position += terminal_match[1]
            continue

        actuator = _match_at(tokens, position, _ACTUATOR_INDEX)
        if actuator:
            symbol, width = actuator
            outputs.append(Output(actuator=symbol))
            position += width
            continue

        position += 1

    # Duration binds to the whole action clause rather than to one actuator:
    # "produce GFP and RFP for 24 hours" times both.
    value, unit, _ = _scan_number(tokens, 0, window=len(tokens) or 1, kind="duration")
    if value is not None and unit in lexicon.DURATION_SECONDS:
        seconds = int(value * lexicon.DURATION_SECONDS[unit])
        for output in outputs:
            output.duration_seconds = seconds
            output.duration_value = value
            output.duration_unit = unit

    if not outputs and terminal is None:
        report.error(Code.NO_OUTPUT)

    return outputs, terminal


def parse(text: str, report: Report) -> Specification:
    if not text or not text.strip():
        report.error(Code.EMPTY_INPUT)
        return Specification(source=text or "")

    tokens = lexicon.tokenise(normalise_with_model(text))
    language = lexicon.detect_language(tokens)
    report.info(Code.LANGUAGE_DETECTED, language=language)

    # Sensors and actuators are disjoint vocabularies, so both passes can scan
    # the whole sentence. That is what makes the parser word-order independent:
    # Kurdish puts the verb last ("green protein produce"), Arabic and English
    # put it first, and neither arrangement can hide a term from the other pass.
    conditions, connective = _parse_conditions(tokens, report)
    outputs, terminal = _parse_outputs(tokens, report)

    return Specification(
        conditions=conditions,
        connective=connective,
        outputs=outputs,
        terminal=terminal,
        language=language,
        source=text.strip(),
    )
