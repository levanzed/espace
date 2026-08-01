from __future__ import annotations

from fastapi import APIRouter, Depends

from app.deps import current_userid, moodle_token
from app.models.academic import (
    CalendarEventRequest,
    EnrolUserRequest,
    FileUploadRequest,
    GroupCreateRequest,
    ManualCompletionRequest,
)
from app.services import academic, participants

router = APIRouter()


# Participants / enrolment / groups
@router.get("/courses/{course_id}/participants")
def list_participants(course_id: int, token: str = Depends(moodle_token)):
    return participants.get_enrolled_users(course_id, token)


@router.get("/courses/{course_id}/enrolment-methods")
def enrolment_methods(course_id: int, token: str = Depends(moodle_token)):
    return participants.get_enrolment_methods(course_id, token)


@router.post("/courses/{course_id}/enrolments")
def enrol_user(
    course_id: int,
    body: EnrolUserRequest,
    token: str = Depends(moodle_token),
):
    return participants.enrol_user(
        course_id=course_id,
        userid=body.userid,
        roleid=body.roleid,
        suspend=body.suspend,
        token=token,
    )


@router.delete("/courses/{course_id}/enrolments/{userid}")
def unenrol_user(
    course_id: int,
    userid: int,
    token: str = Depends(moodle_token),
):
    return participants.unenrol_user(course_id=course_id, userid=userid, token=token)


@router.get("/courses/{course_id}/groups")
def list_groups(course_id: int, token: str = Depends(moodle_token)):
    return participants.get_course_groups(course_id, token)


@router.post("/courses/{course_id}/groups")
def create_group(
    course_id: int,
    body: GroupCreateRequest,
    token: str = Depends(moodle_token),
):
    return participants.create_groups(course_id, body.name, body.description, token)


@router.delete("/courses/{course_id}/groups/{group_id}")
def delete_group(
    course_id: int,
    group_id: int,
    token: str = Depends(moodle_token),
):
    _ = course_id
    return participants.delete_groups([group_id], token)


# Grades / completion
@router.get("/courses/{course_id}/grades")
def course_grades(
    course_id: int,
    userid: int | None = None,
    token: str = Depends(moodle_token),
    current: int = Depends(current_userid),
):
    target = userid if userid is not None else current
    return academic.get_user_grade_items(course_id, target, token)


@router.get("/grades/overview")
def grades_overview(
    userid: int = Depends(current_userid),
    token: str = Depends(moodle_token),
):
    return academic.get_course_overview_grades(userid, token)


@router.get("/courses/{course_id}/completion")
def course_completion(
    course_id: int,
    userid: int = Depends(current_userid),
    token: str = Depends(moodle_token),
):
    return {
        "course": academic.get_course_completion(course_id, userid, token),
        "activities": academic.get_activities_completion(course_id, userid, token),
    }


@router.post("/activity/{cmid}/completion")
def manual_completion(
    cmid: int,
    body: ManualCompletionRequest,
    token: str = Depends(moodle_token),
):
    return academic.update_activity_completion_manual(cmid, body.completed, token)


# Calendar
@router.get("/calendar/upcoming")
def calendar_upcoming(token: str = Depends(moodle_token)):
    return academic.get_calendar_upcoming(token)


@router.get("/calendar/monthly")
def calendar_monthly(year: int, month: int, token: str = Depends(moodle_token)):
    return academic.get_calendar_monthly(year, month, token)


@router.post("/calendar/events")
def create_event(body: CalendarEventRequest, token: str = Depends(moodle_token)):
    return academic.create_calendar_events([body.model_dump()], token)


@router.delete("/calendar/events/{event_id}")
def delete_event(event_id: int, token: str = Depends(moodle_token)):
    return academic.delete_calendar_events([event_id], token)


# Files
@router.get("/files/draft-itemid")
def unused_draft_itemid(token: str = Depends(moodle_token)):
    return academic.get_unused_draft_itemid(token)


@router.post("/files/upload")
def upload_file(
    body: FileUploadRequest,
    token: str = Depends(moodle_token),
):
    """Upload one file into the Moodle user draft area (via /webservice/upload.php)."""
    return academic.upload_file(
        file_content_base64=body.filecontent_base64,
        filename=body.filename,
        contextid=body.contextid,
        component=body.component,
        filearea=body.filearea,
        itemid=body.itemid,
        filepath=body.filepath,
        token=token,
    )


# Messages / notifications
@router.get("/messages/conversations")
def conversations(
    userid: int = Depends(current_userid),
    token: str = Depends(moodle_token),
):
    return academic.get_conversations(userid, token)


@router.get("/messages/notifications")
def notifications(
    userid: int = Depends(current_userid),
    token: str = Depends(moodle_token),
):
    return academic.get_messages(userid, token)
