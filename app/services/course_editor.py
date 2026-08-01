"""Course structure editor.

Sections (Sprint 1A — Add / Hide / Show):
  - local_espace_create_section
  - local_espace_hide_section
  - local_espace_show_section

Sections (Sprint 1B — still via courseformat until wired):
  - section_delete / section_move / section_move_after
  - rename stub (unsupported_section_rename)

Modules:
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

    if action == "section_add":
        return _call_local_espace(
            "local_espace_create_section",
            token,
            courseid=course_id,
            position=0,
        )

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

    # Sprint 1B: delete / move still use courseformat until local_espace wiring.
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
    # Sprint 1B: wire POST .../rename → local_espace_rename_section.
    return {
        "status": "unsupported",
        "reason": (
            f"Section rename (section_id={section_id}) is deferred to Sprint 1B. "
            "Plugin WS local_espace_rename_section is already registered."
        ),
        "extension": "local_espace_rename_section",
        "todo": "Sprint 1B: rename section + edit section summary via local_espace",
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
