from __future__ import annotations

from fastapi import APIRouter, Depends

from app.deps import moodle_token
from app.models.quiz_publish import QuizPublishRequest
from app.services import quiz_publish

router = APIRouter(tags=["quiz-studio"])


@router.post("/courses/{course_id}/sections/{section_id}/quiz/publish")
def publish_quiz_to_moodle(
    course_id: int,
    section_id: int,
    body: QuizPublishRequest,
    token: str = Depends(moodle_token),
):
    """Phase 0: create mod_quiz + questions via local_espace_publish_quiz."""
    return quiz_publish.publish_quiz(
        course_id=course_id,
        section_id=section_id,
        payload=body.payload,
        token=token,
    )
