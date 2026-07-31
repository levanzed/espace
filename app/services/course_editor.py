"""Course structure editor via official Moodle courseformat WS.

Uses:
  - core_courseformat_update_course  (section/module structural actions)
  - core_courseformat_new_module     (quick-create where FEATURE_QUICKCREATE)

Unsupported (TODO local_espace):
  - section rename / section summary edit
  - full module settings & content editing
  - availability & completion configuration UIs
"""

from __future__ import annotations

import json
from typing import Any

from fastapi import HTTPException

from app.services.moodle import MoodleError, call, raise_http

# Official actions exposed by core_courseformat\stateactions
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

    # Moodle returns a JSON-encoded string of state updates.
    if isinstance(raw, str):
        try:
            return json.loads(raw)
        except json.JSONDecodeError:
            return {"raw": raw}
    return raw


def section_action(
    *,
    course_id: int,
    action: str,
    section_ids: list[int],
    target_section_id: int | None,
    token: str,
) -> Any:
    if action not in SECTION_ACTIONS:
        raise HTTPException(
            status_code=400,
            detail=f"Unsupported section action '{action}'. Allowed: {sorted(SECTION_ACTIONS)}",
        )
    return update_course_structure(
        course_id=course_id,
        action=action,
        ids=section_ids,
        target_section_id=target_section_id,
        token=token,
    )


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
    Full content/settings editing remains TODO(local_espace).
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
        # Surface a clear extension hint when quick-create is unavailable.
        if exc.errorcode or "quick creation" in (exc.message or "").lower():
            raise HTTPException(
                status_code=501,
                detail={
                    "status": "unsupported",
                    "reason": (
                        f"Moodle module '{modname}' does not support FEATURE_QUICKCREATE "
                        "or the course format does not support components."
                    ),
                    "extension": "local_espace_upsert_module",
                    "todo": "TODO(local_espace): implement full module create/edit with settings and content",
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


def unsupported_section_rename(section_id: int) -> dict[str, Any]:
    # TODO(local_espace): local_espace_update_section
    return {
        "status": "unsupported",
        "reason": (
            "Official Moodle WS has no dedicated stable function to rename a course "
            f"section (section_id={section_id}). core_update_inplace_editable is an "
            "AJAX UI helper, not a course-structure API."
        ),
        "extension": "local_espace_update_section",
        "todo": "TODO(local_espace): rename section + edit section summary",
    }


def unsupported_module_settings(cmid: int, modname: str) -> dict[str, Any]:
    # TODO(local_espace): local_espace_upsert_module
    return {
        "status": "unsupported",
        "reason": (
            f"Official Moodle WS cannot edit full settings/content for '{modname}' "
            f"(cmid={cmid}). Only structural actions and limited student-facing "
            "mod_* APIs exist."
        ),
        "extension": "local_espace_upsert_module",
        "todo": "TODO(local_espace): create/edit module settings, files, HTML content, availability, completion",
    }
