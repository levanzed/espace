"""Grades, completion, calendar, files, and messages via official Moodle WS."""

from __future__ import annotations

from typing import Any

from app.services.moodle import MoodleError, call, raise_http


# Grades
def get_user_grade_items(course_id: int, userid: int | None, token: str) -> Any:
    params: dict[str, Any] = {"courseid": course_id}
    if userid is not None:
        params["userid"] = userid
    return call("gradereport_user_get_grade_items", token=token, **params)


def get_grades_table(course_id: int, userid: int | None, token: str) -> Any:
    params: dict[str, Any] = {"courseid": course_id}
    if userid is not None:
        params["userid"] = userid
    return call("gradereport_user_get_grades_table", token=token, **params)


def get_course_overview_grades(userid: int, token: str) -> Any:
    return call("gradereport_overview_get_course_grades", token=token, userid=userid)


# Completion
def get_activities_completion(course_id: int, userid: int, token: str) -> Any:
    return call(
        "core_completion_get_activities_completion_status",
        token=token,
        courseid=course_id,
        userid=userid,
    )


def get_course_completion(course_id: int, userid: int, token: str) -> Any:
    return call(
        "core_completion_get_course_completion_status",
        token=token,
        courseid=course_id,
        userid=userid,
    )


def update_activity_completion_manual(cmid: int, completed: bool, token: str) -> Any:
    try:
        return call(
            "core_completion_update_activity_completion_status_manually",
            token=token,
            cmid=cmid,
            completed=1 if completed else 0,
        )
    except MoodleError as exc:
        raise_http(exc)


# Calendar
def get_calendar_upcoming(token: str) -> Any:
    return call("core_calendar_get_calendar_upcoming_view", token=token)


def get_calendar_monthly(year: int, month: int, token: str) -> Any:
    return call(
        "core_calendar_get_calendar_monthly_view",
        token=token,
        year=year,
        month=month,
    )


def create_calendar_events(events: list[dict[str, Any]], token: str) -> Any:
    try:
        return call("core_calendar_create_calendar_events", token=token, events=events)
    except MoodleError as exc:
        raise_http(exc)


def delete_calendar_events(event_ids: list[int], token: str) -> Any:
    try:
        return call(
            "core_calendar_delete_calendar_events",
            token=token,
            events=[{"eventid": eid, "repeat": 0} for eid in event_ids],
        )
    except MoodleError as exc:
        raise_http(exc)


# Files
def get_unused_draft_itemid(token: str) -> Any:
    return call("core_files_get_unused_draft_itemid", token=token)


def upload_file(
    *,
    file_content_base64: str,
    filename: str,
    contextid: int,
    component: str,
    filearea: str,
    itemid: int,
    filepath: str,
    token: str,
) -> Any:
    try:
        return call(
            "core_files_upload",
            token=token,
            contextid=contextid,
            component=component,
            filearea=filearea,
            itemid=itemid,
            filepath=filepath,
            filename=filename,
            filecontent=file_content_base64,
        )
    except MoodleError as exc:
        raise_http(exc)


# Messages / notifications
def get_conversations(userid: int, token: str) -> Any:
    return call(
        "core_message_get_conversations",
        token=token,
        userid=userid,
    )


def get_conversation_messages(
    current_userid: int,
    conversation_id: int,
    token: str,
) -> Any:
    return call(
        "core_message_get_conversation_messages",
        token=token,
        currentuserid=current_userid,
        convid=conversation_id,
    )


def send_messages_to_conversation(
    conversation_id: int,
    text: str,
    token: str,
) -> Any:
    try:
        return call(
            "core_message_send_messages_to_conversation",
            token=token,
            conversationid=conversation_id,
            messages=[{"text": text, "textformat": 1}],
        )
    except MoodleError as exc:
        raise_http(exc)


def get_messages(useridto: int, token: str) -> Any:
    return call(
        "core_message_get_messages",
        token=token,
        useridto=useridto,
        type="notifications",
        read=0,
    )
