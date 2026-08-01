"""Official Moodle REST client for ESPACE.

Only call documented Moodle Web Service functions through this module.
Do not invent Moodle APIs here — unsupported capabilities belong in
TODO(local_espace) extension points.
"""

from __future__ import annotations

import logging
from typing import Any

import requests
from fastapi import HTTPException

from app.config import MOODLE_TOKEN, MOODLE_URL

# TEMP DEBUG: Sprint A integration — remove after root cause identified
logger = logging.getLogger("espace.moodle.debug")


class MoodleError(Exception):
    """Raised when Moodle returns an exception payload."""

    def __init__(self, message: str, errorcode: str | None = None, raw: dict | None = None):
        super().__init__(message)
        self.message = message
        self.errorcode = errorcode
        self.raw = raw or {}


def _redact_for_log(payload: dict[str, Any]) -> dict[str, Any]:
    """Copy Moodle REST payload with tokens removed for safe logging."""
    redacted = dict(payload)
    for key in ("wstoken", "token", "password"):
        if key in redacted and redacted[key] is not None:
            redacted[key] = "***REDACTED***"
    return redacted


def _flatten_params(params: dict[str, Any], prefix: str = "") -> dict[str, Any]:
    """Flatten nested dict/list params into Moodle REST form keys."""
    flat: dict[str, Any] = {}

    for key, value in params.items():
        full_key = f"{prefix}[{key}]" if prefix else str(key)

        if isinstance(value, dict):
            flat.update(_flatten_params(value, full_key))
        elif isinstance(value, list):
            for index, item in enumerate(value):
                item_key = f"{full_key}[{index}]"
                if isinstance(item, dict):
                    flat.update(_flatten_params(item, item_key))
                else:
                    flat[item_key] = item
        elif value is not None:
            flat[full_key] = value

    return flat


def call(
    function: str,
    token: str | None = None,
    *,
    method: str = "POST",
    **params: Any,
) -> Any:
    """Call a Moodle wsfunction and return JSON.

    Uses POST by default (required for write operations and large payloads).
    Nested lists/dicts are flattened to Moodle's bracket notation.
    """
    if not MOODLE_URL:
        raise HTTPException(status_code=500, detail="MOODLE_URL is not configured")

    payload = {
        "wstoken": token or MOODLE_TOKEN,
        "wsfunction": function,
        "moodlewsrestformat": "json",
        **_flatten_params(params),
    }

    # TEMP DEBUG: Sprint A integration — remove after root cause identified
    logger.error(
        "Moodle call → function=%s method=%s payload=%s",
        function,
        method.upper(),
        _redact_for_log(payload),
    )

    try:
        if method.upper() == "GET":
            response = requests.get(
                f"{MOODLE_URL}/webservice/rest/server.php",
                params=payload,
                timeout=60,
            )
        else:
            response = requests.post(
                f"{MOODLE_URL}/webservice/rest/server.php",
                data=payload,
                timeout=60,
            )
        response.raise_for_status()
        data = response.json()
    except requests.RequestException as exc:
        logger.exception(
            "Moodle transport failure function=%s: %s",
            function,
            exc,
        )
        raise HTTPException(status_code=502, detail=f"Moodle request failed: {exc}") from exc
    except ValueError as exc:
        logger.exception(
            "Moodle invalid JSON function=%s raw_text=%s",
            function,
            getattr(response, "text", None),
        )
        raise HTTPException(status_code=502, detail="Invalid JSON from Moodle") from exc

    if isinstance(data, dict) and data.get("exception"):
        # TEMP DEBUG: Sprint A integration — remove after root cause identified
        logger.error(
            "MoodleError from moodle.call() function=%s message=%s errorcode=%s raw=%s",
            function,
            data.get("message") or data.get("exception"),
            data.get("errorcode"),
            data,
        )
        raise MoodleError(
            message=data.get("message") or data.get("exception") or "Moodle error",
            errorcode=data.get("errorcode"),
            raw=data,
        )

    # TEMP DEBUG: Sprint A integration — remove after root cause identified
    if function == "local_espace_upsert_module":
        logger.error(
            "local_espace_upsert_module complete response=%s",
            data,
        )

    return data


def raise_http(exc: MoodleError, *, status_code: int = 400) -> None:
    """Convert a MoodleError into an HTTPException."""
    # TEMP DEBUG: Sprint A integration — remove after root cause identified
    logger.error(
        "raise_http status=%s message=%s errorcode=%s raw=%s",
        status_code,
        exc.message,
        exc.errorcode,
        exc.raw,
    )
    raise HTTPException(
        status_code=status_code,
        detail={
            "message": exc.message,
            "errorcode": exc.errorcode,
            "moodle": exc.raw,
        },
    )
