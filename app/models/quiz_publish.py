from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, ConfigDict, Field

QuizQuestionType = Literal["multiple_choice", "short_answer"]

TextFormat = Literal["html", "plain", "markdown", "moodle"]


class RichText(BaseModel):
    format: TextFormat = "html"
    text: str = ""


class McqChoice(BaseModel):
    text: RichText
    correct: bool = False


class ShortAnswerEntry(BaseModel):
    text: str
    fraction: float = Field(default=1.0, gt=0)


class QuizQuestion(BaseModel):
    type: QuizQuestionType
    mark: float = Field(default=1.0, gt=0)
    stem: RichText
    choices: list[McqChoice] = Field(default_factory=list)
    answers: list[ShortAnswerEntry] = Field(default_factory=list)
    case_sensitive: bool = False


QUIZ_PUBLISH_PAYLOAD_EXAMPLE: dict = {
    "title": "API smoke test quiz",
    "intro": {
        "format": "html",
        "text": "<p>Published from ESPACE API (Phase 0).</p>",
    },
    "questions": [
        {
            "type": "multiple_choice",
            "mark": 1.0,
            "stem": {"format": "html", "text": "<p>2 + 2 = ?</p>"},
            "choices": [
                {"text": {"format": "plain", "text": "3"}, "correct": False},
                {"text": {"format": "plain", "text": "4"}, "correct": True},
            ],
        },
        {
            "type": "short_answer",
            "mark": 1.0,
            "stem": {"format": "html", "text": "<p>Capital of France?</p>"},
            "answers": [{"text": "Paris", "fraction": 1.0}],
        },
    ],
}


class QuizPublishPayload(BaseModel):
    model_config = ConfigDict(
        json_schema_extra={
            "examples": [QUIZ_PUBLISH_PAYLOAD_EXAMPLE],
        }
    )

    title: str = Field(min_length=1)
    intro: RichText = Field(default_factory=RichText)
    questions: list[QuizQuestion] = Field(min_length=1)


QUIZ_PUBLISH_REQUEST_EXAMPLE: dict = {
    "payload": QUIZ_PUBLISH_PAYLOAD_EXAMPLE,
}


class QuizPublishRequest(BaseModel):
    """Body for Phase 0 publish spike (course/section in URL)."""

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [QUIZ_PUBLISH_REQUEST_EXAMPLE],
        }
    )

    payload: QuizPublishPayload
