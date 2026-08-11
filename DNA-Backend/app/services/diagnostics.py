"""Diagnostics shared by every tool in the service.

A tool's most useful output is often not the artefact but the diagnostics:
"this part of what you asked maps cleanly onto biology, this part does not, and
here is why." That is the difference between a tool that produces a number and a
tool that teaches what the number can and cannot support.

Like the analysis errors, a diagnostic carries a code and parameters and never
prose, so the same result reads correctly in Kurdish, Arabic or English. Each
tool owns its own ``Code`` table; the collection machinery lives here so the
compiler and the simulator cannot drift apart in how severity is counted or
ordered.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any, Literal

Severity = Literal["error", "warning", "info"]

SEVERITY_ORDER: dict[str, int] = {"error": 0, "warning": 1, "info": 2}


@dataclass
class Diagnostic:
    code: str
    severity: Severity
    params: dict[str, Any] = field(default_factory=dict)
    span: str | None = None  # the phrase or parameter that triggered it

    def as_dict(self) -> dict[str, Any]:
        return {
            "code": self.code,
            "severity": self.severity,
            "params": self.params,
            "span": self.span,
        }


class Report:
    """Collects diagnostics during a single run of a tool."""

    def __init__(self) -> None:
        self._items: list[Diagnostic] = []

    def error(self, code: str, span: str | None = None, **params: Any) -> None:
        self._items.append(Diagnostic(code, "error", params, span))

    def warn(self, code: str, span: str | None = None, **params: Any) -> None:
        self._items.append(Diagnostic(code, "warning", params, span))

    def info(self, code: str, span: str | None = None, **params: Any) -> None:
        self._items.append(Diagnostic(code, "info", params, span))

    @property
    def failed(self) -> bool:
        return any(item.severity == "error" for item in self._items)

    def counts(self) -> dict[str, int]:
        return {
            severity: sum(1 for item in self._items if item.severity == severity)
            for severity in ("error", "warning", "info")
        }

    def as_list(self) -> list[dict[str, Any]]:
        return [item.as_dict() for item in sorted(self._items, key=lambda d: SEVERITY_ORDER[d.severity])]
