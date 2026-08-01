"""Activities facade — modname-aware create/update/get for teacher authoring.

Sprint A: only modname=assign is implemented.
Structural hide/show/delete/move stay on course_editor.module_action.
"""

from __future__ import annotations

from typing import Any

from fastapi import HTTPException

from app.services import assign_authoring
from app.services.moodle import MoodleError, call, raise_http


SUPPORTED_CREATE_MODNAMES = frozenset({"assign"})


def _unsupported(modname: str, *, operation: str) -> None:
    raise HTTPException(
        status_code=501,
        detail={
            "status": "unsupported",
            "reason": (
                f"Activity {operation} for modname='{modname}' is not implemented yet. "
                "Sprint A supports Assignment (modname=assign) only."
            ),
            "extension": "local_espace_upsert_module",
            "supported_modnames": sorted(SUPPORTED_CREATE_MODNAMES),
            "todo": f"TODO(local_espace): upsert branch for '{modname}'",
        },
    )


def _resolve_cm(
    *,
    course_id: int,
    cmid: int,
    token: str,
) -> dict[str, Any]:
    """Locate a course module in course contents."""
    try:
        contents = call("core_course_get_contents", token=token, courseid=course_id)
    except MoodleError as exc:
        raise_http(exc)

    if not isinstance(contents, list):
        raise HTTPException(status_code=404, detail="Course contents not available")

    for section in contents:
        for module in section.get("modules") or []:
            if int(module.get("id") or 0) == cmid:
                return {
                    "cm": module,
                    "sectionid": int(section.get("id") or 0),
                    "modname": str(module.get("modname") or ""),
                }

    raise HTTPException(
        status_code=404,
        detail=f"Course module cmid={cmid} not found in course {course_id}",
    )


def create_activity(
    *,
    course_id: int,
    section_id: int,
    modname: str,
    settings: dict[str, Any],
    token: str,
) -> Any:
    clean = (modname or "").strip().lower()
    if clean not in SUPPORTED_CREATE_MODNAMES:
        _unsupported(clean or modname, operation="create")

    if clean == "assign":
        return assign_authoring.upsert_assign(
            course_id=course_id,
            section_id=section_id,
            cmid=0,
            settings=settings,
            token=token,
        )

    _unsupported(clean, operation="create")


def update_activity(
    *,
    course_id: int,
    cmid: int,
    settings: dict[str, Any],
    token: str,
) -> Any:
    # TEMP DEBUG: Assignment edit — remove after root cause identified
    print(
        f"EDIT DEBUG: activities.update_activity course_id={course_id} cmid={cmid} "
        f"settings={settings!r}",
        flush=True,
    )
    resolved = _resolve_cm(course_id=course_id, cmid=cmid, token=token)
    resolved_modname = (resolved["modname"] or "").strip().lower()
    print(
        f"EDIT DEBUG: resolved activity type modname={resolved_modname!r} "
        f"sectionid={resolved.get('sectionid')!r}",
        flush=True,
    )

    if resolved_modname not in SUPPORTED_CREATE_MODNAMES:
        print(
            f"EDIT DEBUG: raising 501 unsupported modname={resolved_modname!r}",
            flush=True,
        )
        _unsupported(resolved_modname or "unknown", operation="update")

    if resolved_modname == "assign":
        return assign_authoring.upsert_assign(
            course_id=course_id,
            section_id=int(resolved["sectionid"] or 0),
            cmid=cmid,
            settings=settings,
            token=token,
        )

    print(
        f"EDIT DEBUG: raising 501 fallthrough modname={resolved_modname!r}",
        flush=True,
    )
    _unsupported(resolved_modname, operation="update")


def get_activity_authoring(
    *,
    course_id: int,
    cmid: int,
    token: str,
) -> Any:
    resolved = _resolve_cm(course_id=course_id, cmid=cmid, token=token)
    modname = (resolved["modname"] or "").strip().lower()

    if modname not in SUPPORTED_CREATE_MODNAMES:
        _unsupported(modname or "unknown", operation="get")

    if modname == "assign":
        return assign_authoring.get_assign_for_cm(
            course_id=course_id,
            cmid=cmid,
            token=token,
        )

    _unsupported(modname, operation="get")
