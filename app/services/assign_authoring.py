"""Assignment authoring helpers for Activity Sprint A.

Maps ESPACE assign settings ↔ Moodle / local_espace_upsert_module payload.
Reads use official mod_assign_get_assignments; writes use local_espace only.
"""

from __future__ import annotations

from typing import Any

from fastapi import HTTPException

from app.services.moodle import MoodleError, call, raise_http


def _call_local_espace(function: str, token: str, **params: Any) -> Any:
    try:
        return call(function, token=token, **params)
    except MoodleError as exc:
        raise_http(exc)


def settings_for_plugin(settings: dict[str, Any]) -> dict[str, Any]:
    """Strip None values so Moodle optional WS fields stay omitted."""
    return {key: value for key, value in settings.items() if value is not None}


def upsert_assign(
    *,
    course_id: int,
    section_id: int,
    cmid: int,
    settings: dict[str, Any],
    token: str,
) -> Any:
    """Create (cmid=0) or update an assignment via local_espace_upsert_module."""
    if "name" not in settings or not str(settings.get("name", "")).strip():
        raise HTTPException(status_code=400, detail="settings.name is required")

    payload = settings_for_plugin(settings)
    return _call_local_espace(
        "local_espace_upsert_module",
        token,
        courseid=course_id,
        modname="assign",
        sectionid=section_id,
        cmid=cmid,
        settings=payload,
    )


def _config_enabled(assignment: dict[str, Any], plugin: str) -> int:
    for row in assignment.get("configs") or []:
        if (
            row.get("subtype") == "assignsubmission"
            and row.get("plugin") == plugin
            and row.get("name") == "enabled"
        ):
            return 1 if str(row.get("value")) in ("1", "true", "True") else 0
    return 0


def _config_int(assignment: dict[str, Any], plugin: str, name: str) -> int | None:
    for row in assignment.get("configs") or []:
        if (
            row.get("subtype") == "assignsubmission"
            and row.get("plugin") == plugin
            and row.get("name") == name
        ):
            try:
                return int(row.get("value") or 0)
            except (TypeError, ValueError):
                return None
    return None


def _grade_fields(raw_grade: Any) -> dict[str, Any]:
    grade = float(raw_grade or 0)
    if grade == 0:
        return {"grade_type": "none", "grade": 0.0, "scaleid": None}
    if grade < 0:
        return {"grade_type": "scale", "grade": None, "scaleid": int(abs(grade))}
    return {"grade_type": "point", "grade": grade, "scaleid": None}


def assignment_to_authoring_settings(
    assignment: dict[str, Any],
    *,
    visible: int | None = None,
) -> dict[str, Any]:
    """Map Moodle assign WS record → Sprint A authoring settings."""
    grade_info = _grade_fields(assignment.get("grade"))

    settings: dict[str, Any] = {
        "name": assignment.get("name") or "",
        "intro": assignment.get("intro") or "",
        "introformat": int(assignment.get("introformat") or 1),
        "allowsubmissionsfromdate": int(assignment.get("allowsubmissionsfromdate") or 0),
        "duedate": int(assignment.get("duedate") or 0),
        "cutoffdate": int(assignment.get("cutoffdate") or 0),
        "gradingduedate": int(assignment.get("gradingduedate") or 0),
        "onlinetext_enabled": _config_enabled(assignment, "onlinetext"),
        "file_enabled": _config_enabled(assignment, "file"),
        "maxfiles": _config_int(assignment, "file", "maxfilesubmissions"),
        "maxsizebytes": _config_int(assignment, "file", "maxsubmissionsizebytes"),
        **grade_info,
    }
    if visible is not None:
        settings["visible"] = int(visible)
    return settings


def get_assign_for_cm(
    *,
    course_id: int,
    cmid: int,
    token: str,
) -> dict[str, Any]:
    """Fetch assignment authoring settings for a course module."""
    try:
        data = call(
            "mod_assign_get_assignments",
            token=token,
            courseids=[course_id],
        )
    except MoodleError as exc:
        raise_http(exc)

    assignment: dict[str, Any] = {}
    if isinstance(data, dict):
        for course in data.get("courses") or []:
            for item in course.get("assignments") or []:
                if int(item.get("cmid") or 0) == cmid:
                    assignment = item
                    break
            if assignment:
                break

    if not assignment:
        raise HTTPException(
            status_code=404,
            detail=f"Assignment not found for cmid={cmid} in course {course_id}",
        )

    visible = None
    try:
        contents = call("core_course_get_contents", token=token, courseid=course_id)
        if isinstance(contents, list):
            for section in contents:
                for module in section.get("modules") or []:
                    if int(module.get("id") or 0) == cmid:
                        visible = module.get("visible")
                        break
    except MoodleError:
        visible = None

    return {
        "modname": "assign",
        "cmid": cmid,
        "courseid": course_id,
        "instance": int(assignment.get("id") or 0),
        "settings": assignment_to_authoring_settings(assignment, visible=visible),
        "moodle": {
            "assignment": assignment,
        },
    }
