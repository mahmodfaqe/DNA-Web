"""BioCompiler: natural language to genetic circuit.

    compile_text("if temperature above 37 and lactose then produce green protein")

Returns the parsed specification, the gate netlist, the assembled transcriptional
units, the parts manifest, a FASTA rendering, and diagnostics explaining every
place where the sentence and biology did not line up.
"""

from __future__ import annotations

from typing import Any

from .diagnostics import Code, Report
from .parser import parse
from .synthesis import compile_specification

__all__ = ["compile_text", "Code", "Report"]

MAX_INPUT_CHARS = 2000


def compile_text(text: str) -> dict[str, Any]:
    report = Report()

    if text and len(text) > MAX_INPUT_CHARS:
        text = text[:MAX_INPUT_CHARS]

    specification = parse(text, report)

    if report.failed:
        result: dict[str, Any] = {
            "specification": specification.as_dict(),
            "expression": specification.boolean_expression(),
            "gates": [], "units": [], "parts": [],
            "totals": {"units": 0, "length": 0, "unresolved_bases": 0, "resolved_percent": 0.0},
            "fasta": "",
        }
    else:
        result = compile_specification(specification, report)

    result["ok"] = not report.failed
    result["diagnostics"] = report.as_list()
    result["diagnostic_counts"] = report.counts()
    return result
