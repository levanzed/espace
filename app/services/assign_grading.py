"""Teacher assignment grading workflow (official mod_assign WS)."""

from __future__ import annotations

from typing import Any

from fastapi import HTTPException

from app.services.assign_workflow import (
    assignment_for_cmid,
    fetch_submission_status,
)
from app.services.moodle import MoodleError, call, raise_http


def _normalize_participants(raw: Any) -> list[dict[str, Any]]:
    if isinstance(raw, list):
        return [dict(item) for item in raw if isinstance(item, dict)]
    if isinstance(raw, dict):
        for key in ("participants", "users"):
            items = raw.get(key)
            if isinstance(items, list):
                return [dict(item) for item in items if isinstance(item, dict)]
    return []


def _submission_times_by_user(assign_id: int, token: str) -> dict[int, dict[str, Any]]:
    try:
        data = call(
            "mod_assign_get_submissions",
            token=token,
            assignmentids=[assign_id],
        )
    except MoodleError:
        return {}

    by_user: dict[int, dict[str, Any]] = {}
    assignments = data.get("assignments") if isinstance(data, dict) else None
    if not isinstance(assignments, list):
        return by_user

    for block in assignments:
        if not isinstance(block, dict):
            continue
        submissions = block.get("submissions")
        if not isinstance(submissions, list):
            continue
        for submission in submissions:
            if not isinstance(submission, dict):
                continue
            uid = submission.get("userid")
            if uid is None:
                continue
            try:
                user_id = int(uid)
            except (TypeError, ValueError):
                continue
            by_user[user_id] = submission
    return by_user


def _grade_summary_by_user(assign_id: int, token: str) -> dict[int, dict[str, Any]]:
    try:
        data = call(
            "mod_assign_get_grades",
            token=token,
            assignmentids=[assign_id],
        )
    except MoodleError:
        return {}

    by_user: dict[int, dict[str, Any]] = {}
    assignments = data.get("assignments") if isinstance(data, dict) else None
    if not isinstance(assignments, list):
        return by_user

    for block in assignments:
        if not isinstance(block, dict):
            continue
        grades = block.get("grades")
        if not isinstance(grades, list):
            continue
        for grade in grades:
            if not isinstance(grade, dict):
                continue
            uid = grade.get("userid")
            if uid is None:
                continue
            try:
                user_id = int(uid)
            except (TypeError, ValueError):
                continue
            by_user[user_id] = grade
    return by_user


def grading_status_label(participant: dict[str, Any], grade: dict[str, Any] | None) -> str:
    if participant.get("requiregrading"):
        return "Needs grading"
    if grade:
        raw = grade.get("grade")
        if raw is not None and str(raw) != "-1":
            return "Graded"
    submission = participant.get("submissionstatus")
    if submission:
        return str(submission).replace("_", " ")
    if participant.get("submitted"):
        return "Submitted"
    return "No submission"


def list_participants_for_cmid(
    cmid: int,
    token: str,
    *,
    groupid: int = 0,
    filter_text: str = "",
    skip: int = 0,
    limit: int = 0,
) -> dict[str, Any]:
    assignment = assignment_for_cmid(cmid, token)
    assign_id = int(assignment["id"])

    try:
        raw = call(
            "mod_assign_list_participants",
            token=token,
            assignid=assign_id,
            groupid=groupid,
            filter=filter_text,
            skip=skip,
            limit=limit,
            onlyids=False,
            includeenrolments=False,
            tablesort=False,
        )
    except MoodleError as exc:
        raise_http(exc)
        return {"participants": [], "warnings": []}

    participants = _normalize_participants(raw)
    warnings: list[Any] = []
    if isinstance(raw, dict):
        warnings = list(raw.get("warnings") or [])

    submissions = _submission_times_by_user(assign_id, token)
    grades = _grade_summary_by_user(assign_id, token)

    enriched: list[dict[str, Any]] = []
    for row in participants:
        entry = dict(row)
        user_id = entry.get("id")
        try:
            uid = int(user_id)
        except (TypeError, ValueError):
            enriched.append(entry)
            continue

        submission = submissions.get(uid)
        if submission:
            entry["timemodified"] = submission.get("timemodified")
            entry["submissionstatus"] = entry.get("submissionstatus") or submission.get("status")

        grade = grades.get(uid)
        if grade:
            entry["grade"] = grade.get("grade")
            entry["gradetimemodified"] = grade.get("timemodified")

        entry["gradingstatus"] = grading_status_label(entry, grade)
        enriched.append(entry)

    return {"participants": enriched, "warnings": warnings}


def submission_status_for_student(
    cmid: int,
    token: str,
    userid: int,
) -> dict[str, Any]:
    if userid <= 0:
        raise HTTPException(status_code=400, detail="userid is required for teacher view")
    assignment = assignment_for_cmid(cmid, token)
    return fetch_submission_status(int(assignment["id"]), token, userid=userid)


def save_grade_for_student(
    cmid: int,
    token: str,
    *,
    userid: int,
    grade: float,
    attemptnumber: int = -1,
    feedback_text: str = "",
    feedback_draftitemid: int | None = None,
) -> dict[str, Any]:
    assignment = assignment_for_cmid(cmid, token)
    assign_id = int(assignment["id"])

    plugindata: dict[str, Any] = {}
    if feedback_text.strip():
        plugindata["assignfeedbackcomments_editor"] = {
            "text": feedback_text,
            "format": 1,
        }
    if feedback_draftitemid is not None:
        plugindata["files_filemanager"] = feedback_draftitemid

    try:
        call(
            "mod_assign_save_grade",
            token=token,
            assignmentid=assign_id,
            userid=userid,
            grade=grade,
            attemptnumber=attemptnumber,
            addattempt=0,
            workflowstate="",
            applytoall=0,
            plugindata=plugindata,
        )
    except MoodleError as exc:
        raise_http(exc)

    return {"success": True, "userid": userid}
