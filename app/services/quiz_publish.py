"""Quiz Studio Phase 0 — publish ESPACE quiz JSON to Moodle."""

from __future__ import annotations

from typing import Any

from fastapi import HTTPException

from app.models.quiz_publish import QuizPublishPayload
from app.services.moodle import MoodleError, call, raise_http


def _payload_for_moodle(payload: QuizPublishPayload) -> dict[str, Any]:
    """Pydantic model → Moodle WS payload (bools as 0/1 for REST)."""
    data = payload.model_dump()
    for question in data.get("questions") or []:
        for choice in question.get("choices") or []:
            if "correct" in choice:
                choice["correct"] = 1 if choice["correct"] else 0
        if "case_sensitive" in question:
            question["case_sensitive"] = 1 if question["case_sensitive"] else 0
    return data


def publish_quiz(
    *,
    course_id: int,
    section_id: int,
    payload: QuizPublishPayload,
    token: str,
) -> Any:
    if not payload.title.strip():
        raise HTTPException(status_code=400, detail="payload.title is required")

    types = {q.type.strip().lower() for q in payload.questions}
    allowed = {"multiple_choice", "short_answer"}
    unknown = types - allowed
    if unknown:
        raise HTTPException(
            status_code=400,
            detail=f"Unsupported question types: {sorted(unknown)}",
        )

    try:
        return call(
            "local_espace_publish_quiz",
            token=token,
            courseid=course_id,
            sectionid=section_id,
            payload=_payload_for_moodle(payload),
        )
    except MoodleError as exc:
        raise_http(exc)
