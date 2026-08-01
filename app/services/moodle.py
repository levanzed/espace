"""Official Moodle REST client for ESPACE.

Only call documented Moodle Web Service functions through this module.
Do not invent Moodle APIs here — unsupported capabilities belong in
TODO(local_espace) extension points.

Draft file uploads use Moodle's official `/webservice/upload.php` endpoint
(same path as the Moodle Mobile App). `core_files_upload` is not available on
`moodle_mobile_app` and must not be used.
"""

from __future__ import annotations

import base64
from typing import Any

import requests
from fastapi import HTTPException

from app.config import MOODLE_TOKEN, MOODLE_URL


class MoodleError(Exception):
    """Raised when Moodle returns an exception payload."""

    def __init__(self, message: str, errorcode: str | None = None, raw: dict | None = None):
        super().__init__(message)
        self.message = message
        self.errorcode = errorcode
        self.raw = raw or {}


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

    The caller token must be issued for the ESPACE external service
    (shortname=espace). See docs/ESPACE_WS_ALLOWLIST.md.
    """
    if not MOODLE_URL:
        raise HTTPException(status_code=500, detail="MOODLE_URL is not configured")

    wstoken = token or MOODLE_TOKEN
    if not wstoken:
        raise HTTPException(status_code=500, detail="Moodle token is not configured")

    payload = {
        "wstoken": wstoken,
        "wsfunction": function,
        "moodlewsrestformat": "json",
        **_flatten_params(params),
    }

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
        raise HTTPException(status_code=502, detail=f"Moodle request failed: {exc}") from exc
    except ValueError as exc:
        raise HTTPException(status_code=502, detail="Invalid JSON from Moodle") from exc

    if isinstance(data, dict) and data.get("exception"):
        raise MoodleError(
            message=data.get("message") or data.get("exception") or "Moodle error",
            errorcode=data.get("errorcode"),
            raw=data,
        )

    return data


def upload_draft_file(
    *,
    file_content_base64: str,
    filename: str,
    itemid: int,
    filepath: str = "/",
    token: str | None = None,
) -> dict[str, Any]:
    """Upload one file into the user draft area via Moodle `/webservice/upload.php`.

    Matches Moodle 5.2.1 (`public/webservice/upload.php`): multipart POST with
    `token`, optional `itemid` / `filepath` (default `/`), and file field `file_1`.
    Area is always `user`/`draft` (hardcoded by Moodle). Used instead of
    `core_files_upload`, which is not on `moodle_mobile_app`.

    Returns the first successful file record as a dict (ESPACE/Flutter expect an
    object, not Moodle's JSON array).
    """
    if not MOODLE_URL:
        raise HTTPException(status_code=500, detail="MOODLE_URL is not configured")

    wstoken = token or MOODLE_TOKEN
    if not wstoken:
        raise HTTPException(status_code=500, detail="Moodle token is not configured")

    try:
        raw_bytes = base64.b64decode(file_content_base64, validate=False)
    except Exception as exc:
        raise HTTPException(status_code=400, detail="Invalid base64 file content") from exc

    # Moodle 5.2.1: required_param('token'); optional itemid (0 → new draft) and filepath.
    form: dict[str, Any] = {
        "token": wstoken,
        "filepath": filepath or "/",
        "itemid": int(itemid),
    }
    files = {
        # Moodle source iterates all $_FILES keys; file_1 is the documented example name.
        "file_1": (filename, raw_bytes),
    }

    try:
        response = requests.post(
            f"{MOODLE_URL}/webservice/upload.php",
            data=form,
            files=files,
            timeout=60,
        )
        response.raise_for_status()
        data = response.json()
    except requests.RequestException as exc:
        raise HTTPException(status_code=502, detail=f"Moodle upload failed: {exc}") from exc
    except ValueError as exc:
        raise HTTPException(status_code=502, detail="Invalid JSON from Moodle upload") from exc

    # AJAX_SCRIPT exceptions surface as a JSON object (not the success array).
    if isinstance(data, dict) and (data.get("exception") or data.get("errorcode")):
        raise MoodleError(
            message=data.get("message") or data.get("error") or data.get("exception") or "Moodle upload error",
            errorcode=data.get("errorcode"),
            raw=data,
        )

    if not isinstance(data, list) or not data:
        raise HTTPException(
            status_code=502,
            detail={"message": "Unexpected Moodle upload response", "moodle": data},
        )

    first = data[0]
    if not isinstance(first, dict):
        raise HTTPException(
            status_code=502,
            detail={"message": "Unexpected Moodle upload response entry", "moodle": data},
        )

    # Per-file soft errors (oversized, filenameexist) — do not treat as success.
    if first.get("error") or first.get("errortype"):
        raise HTTPException(
            status_code=400,
            detail={
                "message": first.get("error") or "Moodle draft upload failed",
                "errorcode": first.get("errortype"),
                "moodle": first,
            },
        )

    return first


def raise_http(exc: MoodleError, *, status_code: int = 400) -> None:
    """Convert a MoodleError into an HTTPException."""
    raise HTTPException(
        status_code=status_code,
        detail={
            "message": exc.message,
            "errorcode": exc.errorcode,
            "moodle": exc.raw,
        },
    )
