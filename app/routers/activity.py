from __future__ import annotations

from fastapi import APIRouter, Body, Depends, HTTPException, Query

from app.deps import get_token_payload, moodle_token
from app.models.academic import (
    AssignFinalSubmitRequest,
    AssignParticipantsResponse,
    AssignSaveGradeRequest,
    AssignSaveGradeResponse,
    AssignSubmitRequest,
    ChoiceSubmitRequest,
    ForumDiscussionRequest,
    ForumPostRequest,
    ForumUpdatePostRequest,
    QuizProcessRequest,
)
from app.services import assign_grading
from app.services import assign_workflow
from app.services import interactions
from app.services.activity import get_activity
from app.services.moodle import MoodleError, call, raise_http

router = APIRouter()


@router.get("/activity/{cmid}")
def activity(
    cmid: int,
    token: str = Depends(moodle_token),
):
    try:
        return get_activity(cmid, token)
    except MoodleError as exc:
        raise_http(exc, status_code=404)


@router.get("/activity/{cmid}/forum/discussions")
def forum_discussions(
    cmid: int,
    token: str = Depends(moodle_token),
):
    activity_data = get_activity(cmid, token)
    forum = activity_data.get("details", {}).get("forum", {})
    forum_id = forum.get("id")
    if not forum_id:
        raise HTTPException(status_code=404, detail="Forum not found")
    try:
        return call("mod_forum_get_forum_discussions", forumid=forum_id, token=token)
    except MoodleError as exc:
        raise_http(exc)


@router.get("/activity/{cmid}/forum/discussions/{discussion_id}/posts")
def forum_discussion_posts(
    cmid: int,
    discussion_id: int,
    token: str = Depends(moodle_token),
):
    _ = cmid
    try:
        return call(
            "mod_forum_get_discussion_posts",
            discussionid=discussion_id,
            token=token,
        )
    except MoodleError as exc:
        raise_http(exc)


@router.post("/activity/{cmid}/forum/discussions")
def create_forum_discussion(
    cmid: int,
    body: ForumDiscussionRequest,
    token: str = Depends(moodle_token),
):
    return interactions.forum_add_discussion(
        cmid,
        subject=body.subject,
        message=body.message,
        groupid=body.groupid,
        token=token,
    )


@router.post("/activity/{cmid}/forum/posts/{post_id}/reply")
def reply_forum_post(
    cmid: int,
    post_id: int,
    body: ForumPostRequest,
    token: str = Depends(moodle_token),
):
    _ = cmid
    return interactions.forum_add_post(
        cmid,
        postid=post_id,
        subject=body.subject,
        message=body.message,
        token=token,
    )


@router.patch("/activity/{cmid}/forum/posts/{post_id}")
def update_forum_post(
    cmid: int,
    post_id: int,
    body: ForumUpdatePostRequest,
    token: str = Depends(moodle_token),
):
    _ = cmid
    return interactions.forum_update_post(
        postid=post_id,
        subject=body.subject,
        message=body.message,
        token=token,
    )


@router.delete("/activity/{cmid}/forum/posts/{post_id}")
def delete_forum_post(
    cmid: int,
    post_id: int,
    token: str = Depends(moodle_token),
):
    _ = cmid
    return interactions.forum_delete_post(postid=post_id, token=token)


@router.get("/activity/{cmid}/assign/status")
def assign_status(
    cmid: int,
    userid: int = 0,
    token: str = Depends(moodle_token),
):
    try:
        if userid > 0:
            return assign_grading.submission_status_for_student(cmid, token, userid)
        return assign_workflow.get_submission_status_for_cmid(cmid, token)
    except HTTPException:
        raise
    except MoodleError as exc:
        raise_http(exc)


@router.get("/activity/{cmid}/assign/participants", response_model=AssignParticipantsResponse)
def assign_participants(
    cmid: int,
    groupid: int = 0,
    filter: str = Query("", alias="filter"),
    skip: int = 0,
    limit: int = 0,
    token: str = Depends(moodle_token),
):
    try:
        return assign_grading.list_participants_for_cmid(
            cmid,
            token,
            groupid=groupid,
            filter_text=filter,
            skip=skip,
            limit=limit,
        )
    except HTTPException:
        raise
    except MoodleError as exc:
        raise_http(exc    )


@router.post("/activity/{cmid}/assign/grades", response_model=AssignSaveGradeResponse)
def save_assign_grade(
    cmid: int,
    body: AssignSaveGradeRequest,
    token: str = Depends(moodle_token),
):
    try:
        return assign_grading.save_grade_for_student(
            cmid,
            token,
            userid=body.userid,
            grade=body.grade,
            attemptnumber=body.attemptnumber,
            feedback_text=body.feedback_text,
            feedback_draftitemid=body.feedback_draftitemid,
        )
    except HTTPException:
        raise
    except MoodleError as exc:
        raise_http(exc)


@router.post("/activity/{cmid}/assign/submission")
def save_assign_submission(
    cmid: int,
    body: AssignSubmitRequest,
    token: str = Depends(moodle_token),
):
    return interactions.assign_save_submission(
        cmid,
        onlinetext=body.onlinetext,
        draftitemid=body.draftitemid,
        token=token,
    )


@router.post("/activity/{cmid}/assign/submit")
def submit_assign(
    cmid: int,
    body: AssignFinalSubmitRequest | None = Body(None),
    token: str = Depends(moodle_token),
):
    accept = False if body is None else body.accept_submission_statement
    return interactions.assign_submit_for_grading(
        cmid,
        accept_submission_statement=accept,
        token=token,
    )


@router.get("/activity/{cmid}/quiz/attempts")
def quiz_attempts(
    cmid: int,
    token: str = Depends(moodle_token),
):
    activity_data = get_activity(cmid, token)
    quiz = activity_data.get("details", {}).get("quiz", {})
    quiz_id = quiz.get("id")
    if not quiz_id:
        raise HTTPException(status_code=404, detail="Quiz not found")
    try:
        return call(
            "mod_quiz_get_user_attempts",
            quizid=quiz_id,
            status="all",
            token=token,
        )
    except MoodleError as exc:
        raise_http(exc)


@router.post("/activity/{cmid}/quiz/attempts")
def start_quiz_attempt(
    cmid: int,
    token: str = Depends(moodle_token),
):
    return interactions.quiz_start_attempt(cmid, token)


@router.get("/activity/{cmid}/quiz/attempts/{attempt_id}")
def get_quiz_attempt_data(
    cmid: int,
    attempt_id: int,
    page: int = 0,
    token: str = Depends(moodle_token),
):
    _ = cmid
    return interactions.quiz_get_attempt_data(attempt_id, page, token)


@router.post("/activity/{cmid}/quiz/attempts/{attempt_id}/save")
def save_quiz_attempt(
    cmid: int,
    attempt_id: int,
    body: QuizProcessRequest,
    token: str = Depends(moodle_token),
):
    _ = cmid
    return interactions.quiz_save_attempt(attempt_id, body.data, token)


@router.post("/activity/{cmid}/quiz/attempts/{attempt_id}/process")
def process_quiz_attempt(
    cmid: int,
    attempt_id: int,
    body: QuizProcessRequest,
    token: str = Depends(moodle_token),
):
    _ = cmid
    return interactions.quiz_process_attempt(
        attempt_id,
        data=body.data,
        finishattempt=body.finishattempt,
        timeup=body.timeup,
        token=token,
    )


@router.get("/activity/{cmid}/quiz/attempts/{attempt_id}/review")
def review_quiz_attempt(
    cmid: int,
    attempt_id: int,
    token: str = Depends(moodle_token),
):
    _ = cmid
    return interactions.quiz_get_attempt_review(attempt_id, token)


@router.get("/activity/{cmid}/choice/options")
def choice_options(
    cmid: int,
    token: str = Depends(moodle_token),
):
    return interactions.choice_get_options(cmid, token)


@router.post("/activity/{cmid}/choice/submit")
def choice_submit(
    cmid: int,
    body: ChoiceSubmitRequest,
    token: str = Depends(moodle_token),
):
    return interactions.choice_submit(cmid, body.responses, token)
