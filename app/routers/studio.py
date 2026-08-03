from __future__ import annotations

from fastapi import APIRouter, Body, Depends

from app.deps import moodle_token
from app.models.quiz_publish import QUIZ_PUBLISH_REQUEST_EXAMPLE, QuizPublishRequest
from app.services import quiz_publish

router = APIRouter(tags=["quiz-studio"])


@router.post("/courses/{course_id}/sections/{section_id}/quiz/publish")
def publish_quiz_to_moodle(
    course_id: int,
    section_id: int,
    body: QuizPublishRequest = Body(
        openapi_examples={
            "phase0_smoke": {
                "summary": "MCQ + short answer (executable)",
                "description": (
                    "Set course_id and section_id in the path, authorize with a teacher "
                    "Bearer token, then Execute."
                ),
                "value": QUIZ_PUBLISH_REQUEST_EXAMPLE,
            },
        },
    ),
    token: str = Depends(moodle_token),
):
    """Phase 0: create mod_quiz + questions via local_espace_publish_quiz."""
    return quiz_publish.publish_quiz(
        course_id=course_id,
        section_id=section_id,
        payload=body.payload,
        token=token,
    )
