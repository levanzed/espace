"""Student/teacher interactive activity actions via official Moodle mod_* WS."""

from __future__ import annotations

from typing import Any

from fastapi import HTTPException

from app.services.activity import get_activity
from app.services.moodle import MoodleError, call, raise_http


def _require_detail(activity: dict[str, Any], *keys: str) -> Any:
    node: Any = activity.get("details", {})
    for key in keys:
        if not isinstance(node, dict) or key not in node:
            raise HTTPException(status_code=404, detail=f"Missing activity detail: {'.'.join(keys)}")
        node = node[key]
    return node


# ---------------------------------------------------------------------------
# Assignment
# ---------------------------------------------------------------------------

def assign_save_submission(
    cmid: int,
    *,
    onlinetext: str | None,
    draftitemid: int | None,
    token: str,
) -> Any:
    activity = get_activity(cmid, token)
    assignment = _require_detail(activity, "assignment")
    assign_id = assignment.get("id")
    if not assign_id:
        raise HTTPException(status_code=404, detail="Assignment not found")

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


def assign_submit_for_grading(cmid: int, token: str) -> Any:
    activity = get_activity(cmid, token)
    assignment = _require_detail(activity, "assignment")
    assign_id = assignment.get("id")
    if not assign_id:
        raise HTTPException(status_code=404, detail="Assignment not found")

    try:
        return call(
            "mod_assign_submit_for_grading",
            token=token,
            assignmentid=assign_id,
            acceptsubmissionstatement=1,
        )
    except MoodleError as exc:
        raise_http(exc)


def assign_save_grade(
    cmid: int,
    *,
    userid: int,
    grade: float,
    attemptnumber: int = -1,
    feedback_text: str = "",
    token: str,
) -> Any:
    activity = get_activity(cmid, token)
    assignment = _require_detail(activity, "assignment")
    assign_id = assignment.get("id")
    if not assign_id:
        raise HTTPException(status_code=404, detail="Assignment not found")

    try:
        return call(
            "mod_assign_save_grade",
            token=token,
            assignmentid=assign_id,
            userid=userid,
            grade=grade,
            attemptnumber=attemptnumber,
            addattempt=0,
            workflowstate="",
            applytoall=0,
            plugindata={
                "assignfeedbackcomments_editor": {
                    "text": feedback_text,
                    "format": 1,
                }
            },
        )
    except MoodleError as exc:
        raise_http(exc)


# ---------------------------------------------------------------------------
# Forum
# ---------------------------------------------------------------------------

def forum_add_discussion(cmid: int, *, subject: str, message: str, groupid: int, token: str) -> Any:
    activity = get_activity(cmid, token)
    forum = _require_detail(activity, "forum")
    forum_id = forum.get("id")
    if not forum_id:
        raise HTTPException(status_code=404, detail="Forum not found")

    try:
        return call(
            "mod_forum_add_discussion",
            token=token,
            forumid=forum_id,
            subject=subject,
            message=message,
            groupid=groupid,
        )
    except MoodleError as exc:
        raise_http(exc)


def forum_add_post(
    cmid: int,
    *,
    postid: int,
    subject: str,
    message: str,
    token: str,
) -> Any:
    try:
        return call(
            "mod_forum_add_discussion_post",
            token=token,
            postid=postid,
            subject=subject,
            message=message,
        )
    except MoodleError as exc:
        raise_http(exc)


def forum_update_post(
    *,
    postid: int,
    subject: str | None,
    message: str | None,
    token: str,
) -> Any:
    params: dict[str, Any] = {"postid": postid}
    if subject is not None:
        params["subject"] = subject
    if message is not None:
        params["message"] = message
    try:
        return call("mod_forum_update_discussion_post", token=token, **params)
    except MoodleError as exc:
        raise_http(exc)


def forum_delete_post(*, postid: int, token: str) -> Any:
    try:
        return call("mod_forum_delete_post", token=token, postid=postid)
    except MoodleError as exc:
        raise_http(exc)


# ---------------------------------------------------------------------------
# Quiz
# ---------------------------------------------------------------------------

def quiz_start_attempt(cmid: int, token: str) -> Any:
    activity = get_activity(cmid, token)
    quiz = _require_detail(activity, "quiz")
    quiz_id = quiz.get("id")
    if not quiz_id:
        raise HTTPException(status_code=404, detail="Quiz not found")
    try:
        return call("mod_quiz_start_attempt", token=token, quizid=quiz_id)
    except MoodleError as exc:
        raise_http(exc)


def quiz_get_attempt_data(attempt_id: int, page: int, token: str) -> Any:
    try:
        return call(
            "mod_quiz_get_attempt_data",
            token=token,
            attemptid=attempt_id,
            page=page,
        )
    except MoodleError as exc:
        raise_http(exc)


def quiz_save_attempt(attempt_id: int, data: list[dict[str, Any]], token: str) -> Any:
    try:
        return call(
            "mod_quiz_save_attempt",
            token=token,
            attemptid=attempt_id,
            data=data,
        )
    except MoodleError as exc:
        raise_http(exc)


def quiz_process_attempt(
    attempt_id: int,
    *,
    data: list[dict[str, Any]],
    finishattempt: int,
    timeup: int,
    token: str,
) -> Any:
    try:
        return call(
            "mod_quiz_process_attempt",
            token=token,
            attemptid=attempt_id,
            data=data,
            finishattempt=finishattempt,
            timeup=timeup,
        )
    except MoodleError as exc:
        raise_http(exc)


def quiz_get_attempt_review(attempt_id: int, token: str) -> Any:
    try:
        return call(
            "mod_quiz_get_attempt_review",
            token=token,
            attemptid=attempt_id,
        )
    except MoodleError as exc:
        raise_http(exc)


# ---------------------------------------------------------------------------
# Choice
# ---------------------------------------------------------------------------

def choice_get_options(cmid: int, token: str) -> Any:
    activity = get_activity(cmid, token)
    choice = activity.get("details", {}).get("choice", {})
    choice_id = choice.get("id") or activity.get("instance")
    if not choice_id:
        raise HTTPException(status_code=404, detail="Choice not found")
    try:
        return call("mod_choice_get_choice_options", token=token, choiceid=choice_id)
    except MoodleError as exc:
        raise_http(exc)


def choice_submit(cmid: int, responses: list[int], token: str) -> Any:
    activity = get_activity(cmid, token)
    choice = activity.get("details", {}).get("choice", {})
    choice_id = choice.get("id") or activity.get("instance")
    if not choice_id:
        raise HTTPException(status_code=404, detail="Choice not found")
    try:
        return call(
            "mod_choice_submit_choice_response",
            token=token,
            choiceid=choice_id,
            responses=responses,
        )
    except MoodleError as exc:
        raise_http(exc)


# ---------------------------------------------------------------------------
# Glossary / Wiki / Feedback / Lesson (student-facing official WS)
# ---------------------------------------------------------------------------

def glossary_add_entry(cmid: int, *, concept: str, definition: str, token: str) -> Any:
    activity = get_activity(cmid, token)
    glossary = activity.get("details", {}).get("glossary", {})
    glossary_id = glossary.get("id") or activity.get("instance")
    if not glossary_id:
        raise HTTPException(status_code=404, detail="Glossary not found")
    try:
        return call(
            "mod_glossary_add_entry",
            token=token,
            glossaryid=glossary_id,
            concept=concept,
            definition=definition,
            definitionformat=1,
        )
    except MoodleError as exc:
        raise_http(exc)


def wiki_edit_page(*, pageid: int, content: str, token: str) -> Any:
    try:
        return call(
            "mod_wiki_edit_page",
            token=token,
            pageid=pageid,
            content=content,
        )
    except MoodleError as exc:
        raise_http(exc)
