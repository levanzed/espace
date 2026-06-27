from fastapi import APIRouter, Depends, HTTPException
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.security import decode_access_token
from app.services.activity import get_activity
from app.services.moodle import call

router = APIRouter()

security = HTTPBearer()


def _token_payload(
    credentials: HTTPAuthorizationCredentials = Depends(security),
):
    return decode_access_token(credentials.credentials)


@router.get("/activity/{cmid}")
def activity(
    cmid: int,
    payload: dict = Depends(_token_payload),
):
    data = get_activity(cmid, payload["moodle_token"])

    if isinstance(data, dict) and data.get("exception"):
        raise HTTPException(
            status_code=404,
            detail=data.get("message", "Activity not found"),
        )

    return data


@router.get("/activity/{cmid}/forum/discussions")
def forum_discussions(
    cmid: int,
    payload: dict = Depends(_token_payload),
):
    activity_data = get_activity(cmid, payload["moodle_token"])
    forum = activity_data.get("details", {}).get("forum", {})
    forum_id = forum.get("id")

    if not forum_id:
        raise HTTPException(status_code=404, detail="Forum not found")

    return call(
        "mod_forum_get_forum_discussions",
        forumid=forum_id,
        token=payload["moodle_token"],
    )


@router.get("/activity/{cmid}/forum/discussions/{discussion_id}/posts")
def forum_discussion_posts(
    cmid: int,
    discussion_id: int,
    payload: dict = Depends(_token_payload),
):
    return call(
        "mod_forum_get_discussion_posts",
        discussionid=discussion_id,
        token=payload["moodle_token"],
    )


@router.get("/activity/{cmid}/assign/status")
def assign_status(
    cmid: int,
    payload: dict = Depends(_token_payload),
):
    activity_data = get_activity(cmid, payload["moodle_token"])
    assignment = activity_data.get("details", {}).get("assignment", {})
    assign_id = assignment.get("id")

    if not assign_id:
        raise HTTPException(status_code=404, detail="Assignment not found")

    return call(
        "mod_assign_get_submission_status",
        assignid=assign_id,
        token=payload["moodle_token"],
    )


@router.get("/activity/{cmid}/quiz/attempts")
def quiz_attempts(
    cmid: int,
    payload: dict = Depends(_token_payload),
):
    activity_data = get_activity(cmid, payload["moodle_token"])
    quiz = activity_data.get("details", {}).get("quiz", {})
    quiz_id = quiz.get("id")

    if not quiz_id:
        raise HTTPException(status_code=404, detail="Quiz not found")

    return call(
        "mod_quiz_get_user_attempts",
        quizid=quiz_id,
        status="all",
        token=payload["moodle_token"],
    )
