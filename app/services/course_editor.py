"""Course structure editor.

Sections (all via local_espace):
  - local_espace_create_section
  - local_espace_rename_section
  - local_espace_hide_section
  - local_espace_show_section
  - local_espace_move_section
  - local_espace_delete_section

Modules (still official courseformat WS):
  - core_courseformat_update_course
  - core_courseformat_new_module (FEATURE_QUICKCREATE only)
"""

from __future__ import annotations

import json
from typing import Any

from fastapi import HTTPException

from app.services.moodle import MoodleError, call, raise_http

SECTION_ACTIONS = {
    "section_add",
    "section_delete",
    "section_hide",
    "section_show",
    "section_move_after",
    "section_move",
}

MODULE_ACTIONS = {
    "cm_hide",
    "cm_show",
    "cm_delete",
    "cm_duplicate",
    "cm_move",
    "cm_stealth",
}


def update_course_structure(
    *,
    course_id: int,
    action: str,
    ids: list[int] | None = None,
    target_section_id: int | None = None,
    target_cmid: int | None = None,
    token: str,
) -> Any:
    """Module structural actions via official courseformat WS."""
    params: dict[str, Any] = {
        "action": action,
        "courseid": course_id,
        "ids": ids or [],
    }
    if target_section_id is not None:
        params["targetsectionid"] = target_section_id
    if target_cmid is not None:
        params["targetcmid"] = target_cmid

    try:
        raw = call("core_courseformat_update_course", token=token, **params)
    except MoodleError as exc:
        raise_http(exc)

    if isinstance(raw, str):
        try:
            return json.loads(raw)
        except json.JSONDecodeError:
            return {"raw": raw}
    return raw


def _call_local_espace(function: str, token: str, **params: Any) -> Any:
    try:
        return call(function, token=token, **params)
    except MoodleError as exc:
        raise_http(exc)


def _require_section_ids(section_ids: list[int], action: str) -> list[int]:
    if not section_ids:
        raise HTTPException(
            status_code=400,
            detail=f"Action '{action}' requires at least one section_id in section_ids",
        )
    return section_ids


def _destination_section_number(
    *,
    course_id: int,
    target_section_id: int,
    token: str,
) -> int:
    """Resolve a section id to Moodle section number for local_espace_move_section."""
    payload = _call_local_espace(
        "local_espace_get_section",
        token,
        courseid=course_id,
        sectionid=target_section_id,
    )
    data = payload.get("data") if isinstance(payload, dict) else None
    section = data.get("section") if isinstance(data, dict) else None
    if not isinstance(section, dict) or section.get("section") is None:
        raise HTTPException(
            status_code=502,
            detail="Unexpected response from local_espace_get_section",
        )
    return int(section["section"])


def section_action(
    *,
    course_id: int,
    action: str,
    section_ids: list[int],
    target_section_id: int | None,
    token: str,
    name: str | None = None,
    summary: str | None = None,
    summaryformat: int = 1,
) -> Any:
    if action not in SECTION_ACTIONS:
        raise HTTPException(
            status_code=400,
            detail=f"Unsupported section action '{action}'. Allowed: {sorted(SECTION_ACTIONS)}",
        )

    if action == "section_add":
        params: dict[str, Any] = {
            "courseid": course_id,
            "position": 0,
            "summaryformat": summaryformat,
        }
        if name is not None:
            params["name"] = name
        if summary is not None:
            params["summary"] = summary
        return _call_local_espace("local_espace_create_section", token, **params)

    if action == "section_hide":
        results = [
            _call_local_espace(
                "local_espace_hide_section",
                token,
                courseid=course_id,
                sectionid=section_id,
            )
            for section_id in _require_section_ids(section_ids, action)
        ]
        return results[0] if len(results) == 1 else results

    if action == "section_show":
        results = [
            _call_local_espace(
                "local_espace_show_section",
                token,
                courseid=course_id,
                sectionid=section_id,
            )
            for section_id in _require_section_ids(section_ids, action)
        ]
        return results[0] if len(results) == 1 else results

    if action == "section_delete":
        results = [
            _call_local_espace(
                "local_espace_delete_section",
                token,
                courseid=course_id,
                sectionid=section_id,
            )
            for section_id in _require_section_ids(section_ids, action)
        ]
        return results[0] if len(results) == 1 else results

    if action in {"section_move", "section_move_after"}:
        section_id = _require_section_ids(section_ids, action)[0]
        if target_section_id is None:
            raise HTTPException(
                status_code=400,
                detail=f"Action '{action}' requires target_section_id",
            )
        destination = _destination_section_number(
            course_id=course_id,
            target_section_id=target_section_id,
            token=token,
        )
        return _call_local_espace(
            "local_espace_move_section",
            token,
            courseid=course_id,
            sectionid=section_id,
            destination=destination,
        )

    raise HTTPException(
        status_code=400,
        detail=f"Unsupported section action '{action}'",
    )


def rename_section(
    *,
    course_id: int,
    section_id: int,
    name: str | None,
    summary: str | None,
    summaryformat: int,
    token: str,
) -> Any:
    if name is None and summary is None:
        raise HTTPException(
            status_code=400,
            detail="Provide at least one of name or summary",
        )
    params: dict[str, Any] = {
        "courseid": course_id,
        "sectionid": section_id,
        "summaryformat": summaryformat,
    }
    if name is not None:
        params["name"] = name
    if summary is not None:
        params["summary"] = summary
    return _call_local_espace("local_espace_rename_section", token, **params)


def module_action(
    *,
    course_id: int,
    action: str,
    cmid: int,
    target_section_id: int | None,
    target_cmid: int | None,
    token: str,
) -> Any:
    if action not in MODULE_ACTIONS:
        raise HTTPException(
            status_code=400,
            detail=f"Unsupported module action '{action}'. Allowed: {sorted(MODULE_ACTIONS)}",
        )
    return update_course_structure(
        course_id=course_id,
        action=action,
        ids=[cmid],
        target_section_id=target_section_id,
        target_cmid=target_cmid,
        token=token,
    )


def new_module(
    *,
    course_id: int,
    modname: str,
    section_id: int,
    target_cmid: int | None,
    token: str,
) -> Any:
    """Create a module via core_courseformat_new_module.

    Only works for modules advertising FEATURE_QUICKCREATE.
    Full Assignment authoring uses Activities API → local_espace_upsert_module.
    """
    params: dict[str, Any] = {
        "courseid": course_id,
        "modname": modname,
        "targetsectionid": section_id,
    }
    if target_cmid is not None:
        params["targetcmid"] = target_cmid

    try:
        raw = call("core_courseformat_new_module", token=token, **params)
    except MoodleError as exc:
        if exc.errorcode or "quick creation" in (exc.message or "").lower():
            raise HTTPException(
                status_code=501,
                detail={
                    "status": "unsupported",
                    "reason": (
                        f"Moodle module '{modname}' does not support FEATURE_QUICKCREATE "
                        "or the course format does not support components. "
                        "For Assignment authoring use POST "
                        f"/courses/{course_id}/sections/{section_id}/activities "
                        "with modname=assign."
                    ),
                    "extension": "local_espace_upsert_module",
                    "todo": "Use Activities API for assign; other modnames TBD",
                    "moodle": exc.raw,
                },
            ) from exc
        raise_http(exc)

    if isinstance(raw, str):
        try:
            return json.loads(raw)
        except json.JSONDecodeError:
            return {"raw": raw}
    return raw


def unsupported_module_settings(cmid: int, modname: str) -> dict[str, Any]:
    """Legacy helper retained for callers that still need an unsupported envelope."""
    return {
        "status": "unsupported",
        "reason": (
            f"Use Activities API for authoring. Sprint A supports assign via "
            f"PUT /courses/{{id}}/activities/{cmid} (modname={modname})."
        ),
        "extension": "local_espace_upsert_module",
        "todo": "Prefer /courses/{id}/activities/{cmid}; other modnames coming later",
    }
