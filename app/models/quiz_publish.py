from __future__ import annotations

from pydantic import BaseModel, Field


class RichText(BaseModel):
    format: str = "html"
    text: str = ""


class McqChoice(BaseModel):
    text: RichText
    correct: bool = False


class ShortAnswerEntry(BaseModel):
    text: str
    fraction: float = 1.0


class QuizQuestion(BaseModel):
    type: str = Field(description="multiple_choice | short_answer")
    mark: float = 1.0
    stem: RichText
    choices: list[McqChoice] = Field(default_factory=list)
    answers: list[ShortAnswerEntry] = Field(default_factory=list)
    case_sensitive: bool = False


class QuizPublishPayload(BaseModel):
    title: str
    intro: RichText = Field(default_factory=RichText)
    questions: list[QuizQuestion] = Field(min_length=1)


class QuizPublishRequest(BaseModel):
    """Body for Phase 0 publish spike (course/section in URL)."""

    payload: QuizPublishPayload
