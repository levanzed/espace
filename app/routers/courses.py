from __future__ import annotations

from fastapi import APIRouter, Depends, HTTPException

from app.deps import current_userid, moodle_token
from app.models.academic import (
    CreateActivityRequest,
    CreateCourseRequest,
    DuplicateCourseRequest,
    ModuleActionRequest,
    NewModuleRequest,
    RenameSectionRequest,
    SectionActionRequest,
    UpdateActivityRequest,
    UpdateCourseRequest,
)
from app.services import activities, course_editor, courses
from app.services.moodle import MoodleError, raise_http

router = APIRouter()


@router.get("/courses")
def list_courses(
    userid: int = Depends(current_userid),
    token: str = Depends(moodle_token),
):
    return courses.list_user_courses(userid, token)


@router.get("/courses/categories")
def list_categories(token: str = Depends(moodle_token)):
    return courses.get_categories(token)


@router.post("/courses")
def create_course(
    body: CreateCourseRequest,
    token: str = Depends(moodle_token),
):
    return courses.create_course(
        fullname=body.fullname,
        shortname=body.shortname,
        categoryid=body.categoryid,
        summary=body.summary,
        visible=body.visible,
        token=token,
    )


@router.get("/courses/{course_id}")
def course_contents(
    course_id: int,
    token: str = Depends(moodle_token),
):
    try:
        return courses.get_contents(course_id, token)
    except MoodleError as exc:
        raise_http(exc, status_code=404)


@router.patch("/courses/{course_id}")
def update_course(
    course_id: int,
    body: UpdateCourseRequest,
    token: str = Depends(moodle_token),
):
    return courses.update_course(course_id, body.model_dump(exclude_none=True), token)


@router.delete("/courses/{course_id}")
def delete_course(
    course_id: int,
    token: str = Depends(moodle_token),
):
    return courses.delete_courses([course_id], token)


@router.post("/courses/{course_id}/duplicate")
def duplicate_course(
    course_id: int,
    body: DuplicateCourseRequest,
    token: str = Depends(moodle_token),
):
    return courses.duplicate_course(
        course_id,
        fullname=body.fullname,
        shortname=body.shortname,
        categoryid=body.categoryid,
        visible=body.visible,
        token=token,
    )


@router.get("/courses/{course_id}/administration")
def course_administration(
    course_id: int,
    token: str = Depends(moodle_token),
):
    return {
        "administration": courses.get_administration_options([course_id], token),
        "navigation": courses.get_navigation_options([course_id], token),
    }


@router.post("/courses/{course_id}/view")
def view_course(
    course_id: int,
    token: str = Depends(moodle_token),
):
    return courses.view_course(course_id, token)


@router.post("/courses/{course_id}/sections")
def section_action(
    course_id: int,
    body: SectionActionRequest,
    token: str = Depends(moodle_token),
):
    return course_editor.section_action(
        course_id=course_id,
        action=body.action,
        section_ids=body.section_ids,
        target_section_id=body.target_section_id,
        name=body.name,
        summary=body.summary,
        summaryformat=body.summaryformat,
        token=token,
    )


@router.post("/courses/{course_id}/sections/{section_id}/rename")
def section_rename(
    course_id: int,
    section_id: int,
    body: RenameSectionRequest,
    token: str = Depends(moodle_token),
):
    return course_editor.rename_section(
        course_id=course_id,
        section_id=section_id,
        name=body.name,
        summary=body.summary,
        summaryformat=body.summaryformat,
        token=token,
    )


@router.post("/courses/{course_id}/modules/actions")
def module_action(
    course_id: int,
    body: ModuleActionRequest,
    token: str = Depends(moodle_token),
):
    return course_editor.module_action(
        course_id=course_id,
        action=body.action,
        cmid=body.cmid,
        target_section_id=body.target_section_id,
        target_cmid=body.target_cmid,
        token=token,
    )


@router.post("/courses/{course_id}/sections/{section_id}/activities")
def create_activity(
    course_id: int,
    section_id: int,
    body: CreateActivityRequest,
    token: str = Depends(moodle_token),
):
    """Create an activity in a section (Sprint A: modname=assign)."""
    return activities.create_activity(
        course_id=course_id,
        section_id=section_id,
        modname=body.modname,
        settings=body.settings,
        token=token,
    )


@router.put("/courses/{course_id}/activities/{cmid}")
def update_activity(
    course_id: int,
    cmid: int,
    body: UpdateActivityRequest,
    token: str = Depends(moodle_token),
):
    """Update activity authoring settings (Sprint A: assign)."""
    # TEMP DEBUG: Assignment edit — remove after root cause identified
    import json
    import traceback

    body_json = json.dumps(body.model_dump(), default=str)
    print(
        f"EDIT DEBUG: PUT /courses/{course_id}/activities/{cmid} "
        f"courseid={course_id} cmid={cmid} request_body={body_json}",
        flush=True,
    )
    try:
        result = activities.update_activity(
            course_id=course_id,
            cmid=cmid,
            settings=body.settings,
            token=token,
        )
    except HTTPException as exc:
        print(
            f"EDIT DEBUG: HTTPException before re-raise status={exc.status_code} "
            f"detail={exc.detail!r}",
            flush=True,
        )
        raise
    except Exception as exc:
        print(
            f"EDIT DEBUG: unexpected exception type={type(exc).__name__} "
            f"message={exc!s}",
            flush=True,
        )
        print(f"EDIT DEBUG: traceback:\n{traceback.format_exc()}", flush=True)
        raise
    print(f"EDIT DEBUG: PUT activities success result={result!r}", flush=True)
    return result


@router.get("/courses/{course_id}/activities/{cmid}")
def get_activity_authoring(
    course_id: int,
    cmid: int,
    token: str = Depends(moodle_token),
):
    """Load authoring settings for the activity editor (Sprint A: assign)."""
    return activities.get_activity_authoring(
        course_id=course_id,
        cmid=cmid,
        token=token,
    )


@router.post("/courses/{course_id}/modules")
def new_module(
    course_id: int,
    body: NewModuleRequest,
    token: str = Depends(moodle_token),
):
    return course_editor.new_module(
        course_id=course_id,
        modname=body.modname,
        section_id=body.section_id,
        target_cmid=body.target_cmid,
        token=token,
    )


@router.put("/courses/{course_id}/modules/{cmid}/settings")
def module_settings(
    course_id: int,
    cmid: int,
    body: UpdateActivityRequest,
    token: str = Depends(moodle_token),
):
    """Legacy path — delegates to Activities update for supported types."""
    return activities.update_activity(
        course_id=course_id,
        cmid=cmid,
        settings=body.settings,
        token=token,
    )
