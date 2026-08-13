"""Language-neutral error contract.

The API is consumed by a trilingual UI (Kurdish / Arabic / English). If the
backend returned human prose, every message would be locked to one language and
the UI could never translate it. So the backend returns a stable *code* plus
structured *params*, and the presentation layer decides the wording.

Adding a language therefore costs three translation files and zero backend
changes.
"""

from __future__ import annotations

from typing import Any


class ErrorCode:
    FILE_MISSING = "file_missing"
    FILE_TOO_LARGE = "file_too_large"
    FILE_ENCODING = "file_encoding"
    FASTA_UNPARSABLE = "fasta_unparsable"
    FASTA_EMPTY = "fasta_empty"
    TOO_MANY_RECORDS = "too_many_records"
    SEQUENCE_EMPTY = "sequence_empty"
    SEQUENCE_INVALID_CHARS = "sequence_invalid_chars"
    JOB_NOT_FOUND = "job_not_found"
    RATE_LIMITED = "rate_limited"
    UNSUPPORTED_FORMAT = "unsupported_format"
    INTERNAL = "internal_error"


class AnalysisError(Exception):
    """Raised by the analysis layer; translated into an HTTP response upstream."""

    def __init__(self, code: str, status_code: int = 400, **params: Any) -> None:
        super().__init__(code)
        self.code = code
        self.status_code = status_code
        self.params = params

    def payload(self) -> dict[str, Any]:
        return {"error": {"code": self.code, "params": self.params}}

    def headers(self) -> dict[str, str]:
        """HTTP headers this error implies.

        A 429 whose wait is only in the JSON body is a 429 that every generic
        HTTP client ignores. `Retry-After` is the standard field, and the
        translated message keeps using the param, so the UI is unaffected.
        """
        retry_after = self.params.get("retry_after")
        if self.status_code == 429 and isinstance(retry_after, int):
            return {"Retry-After": str(retry_after)}

        return {}
