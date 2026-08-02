"""Assignment student workflow: submission status, save, submit (official mod_assign WS)."""

from __future__ import annotations

from typing import Any

from fastapi import HTTPException

from app.services.moodle import MoodleError, call, raise_http


def append_token_to_fileurl(url: str | None, token: str) -> str | None:
    if not url:
        return url
    separator = "&" if "?" in url else "?"
    return f"{url}{separator}token={token}"


def tokenize_fileurls(value: Any, token: str) -> Any:
    """Recursively append the WS token to every ``fileurl`` in Moodle payloads."""
    if isinstance(value, dict):
        out: dict[str, Any] = {}
        for key, item in value.items():
            if key == "fileurl" and isinstance(item, str):
                out[key] = append_token_to_fileurl(item, token)
            else:
                out[key] = tokenize_fileurls(item, token)
        return out
    if isinstance(value, list):
        return [tokenize_fileurls(item, token) for item in value]
    return value


def tokenize_assignment_intro_files(assignment: dict[str, Any], token: str) -> dict[str, Any]:
    """Tokenize intro attachment lists on the assignment config object."""
    if not assignment:
        return assignment
    result = dict(assignment)
    for key in ("introattachments", "introfiles"):
        items = result.get(key)
        if isinstance(items, list):
            result[key] = tokenize_fileurls(items, token)
    return result


def fetch_submission_status(
    assign_id: int,
    token: str,
    *,
    userid: int = 0,
    raise_on_error: bool = True,
) -> dict[str, Any]:
    """Call mod_assign_get_submission_status and tokenize plugin file URLs."""
    try:
        status = call(
            "mod_assign_get_submission_status",
            token=token,
            assignid=assign_id,
            userid=userid,
        )
    except MoodleError as exc:
        if raise_on_error:
            raise_http(exc)
        return {}

    if isinstance(status, dict):
        return tokenize_fileurls(status, token)
    return {}


def assignment_requires_submission_statement(assignment: dict[str, Any]) -> bool:
    return bool(assignment.get("requiresubmissionstatement"))


def resolve_accept_submission_statement(
    assignment: dict[str, Any],
    accept_submission_statement: bool,
) -> int:
    """Map client acceptance to Moodle's acceptsubmissionstatement param."""
    if assignment_requires_submission_statement(assignment):
        if not accept_submission_statement:
            raise HTTPException(
                status_code=400,
                detail="accept_submission_statement must be true when this assignment requires a submission statement",
            )
        return 1
    return 0


def _assignment_from_cmid(cmid: int, token: str) -> dict[str, Any]:
    from app.services.activity import get_activity

    activity = get_activity(cmid, token)
    assignment = activity.get("details", {}).get("assignment")
    if not isinstance(assignment, dict) or not assignment.get("id"):
        raise HTTPException(status_code=404, detail="Assignment not found")
    return assignment


def save_submission(
    cmid: int,
    *,
    onlinetext: str | None,
    draftitemid: int | None,
    token: str,
) -> Any:
    assignment = _assignment_from_cmid(cmid, token)
    assign_id = int(assignment["id"])

    plugindata: dict[str, Any] = {}
    if onlinetext is not None:
        plugindata["onlinetext_editor"] = {
            "text": onlinetext,
            "format": 1,
            "itemid": 0,
        }
    if draftitemid is not None:
        plugindata["files_filemanager"] = draftitemid

    if not plugindata:
        raise HTTPException(
            status_code=400,
            detail="Provide onlinetext and/or draftitemid for the submission",
        )

    try:
        return call(
            "mod_assign_save_submission",
            token=token,
            assignmentid=assign_id,
            plugindata=plugindata,
        )
    except MoodleError as exc:
        raise_http(exc)


def submit_for_grading(
    cmid: int,
    *,
    accept_submission_statement: bool,
    token: str,
) -> Any:
    assignment = _assignment_from_cmid(cmid, token)
    assign_id = int(assignment["id"])
    accept_flag = resolve_accept_submission_statement(
        assignment,
        accept_submission_statement,
    )

    try:
        return call(
            "mod_assign_submit_for_grading",
            token=token,
            assignmentid=assign_id,
            acceptsubmissionstatement=accept_flag,
        )
    except MoodleError as exc:
        raise_http(exc)


def get_submission_status_for_cmid(
    cmid: int,
    token: str,
    *,
    userid: int = 0,
) -> dict[str, Any]:
    assignment = _assignment_from_cmid(cmid, token)
    return fetch_submission_status(int(assignment["id"]), token, userid=userid)


def assignment_for_cmid(cmid: int, token: str) -> dict[str, Any]:
    """Assignment config record from mod_assign_get_assignments (via activity payload)."""
    return _assignment_from_cmid(cmid, token)
