from __future__ import annotations

from typing import Any

from pydantic import BaseModel, Field


class CreateCourseRequest(BaseModel):
    fullname: str
    shortname: str
    categoryid: int = 1
    summary: str = ""
    visible: int = 1


class UpdateCourseRequest(BaseModel):
    fullname: str | None = None
    shortname: str | None = None
    summary: str | None = None
    visible: int | None = None
    categoryid: int | None = None


class DuplicateCourseRequest(BaseModel):
    fullname: str
    shortname: str
    categoryid: int | None = None
    visible: int = 1


class SectionActionRequest(BaseModel):
    action: str = Field(
        description=(
            "section_add | section_delete | section_hide | section_show | "
            "section_move | section_move_after"
        )
    )
    section_ids: list[int] = Field(default_factory=list)
    target_section_id: int | None = None


class RenameSectionRequest(BaseModel):
    name: str | None = None
    summary: str | None = None
    summaryformat: int = 1


class ModuleActionRequest(BaseModel):
    action: str = Field(
        description="cm_hide | cm_show | cm_delete | cm_duplicate | cm_move"
    )
    cmid: int
    target_section_id: int | None = None
    target_cmid: int | None = None


class NewModuleRequest(BaseModel):
    modname: str
    section_id: int
    target_cmid: int | None = None


class EnrolUserRequest(BaseModel):
    userid: int
    roleid: int = 5  # default student
    suspend: int = 0


class GroupCreateRequest(BaseModel):
    name: str
    description: str = ""


class AssignSubmitRequest(BaseModel):
    # Moodle plugindata for onlinetext / file submissions varies by assignment config.
    onlinetext: str | None = None
    draftitemid: int | None = None


class ForumDiscussionRequest(BaseModel):
    subject: str
    message: str
    groupid: int = 0


class ForumPostRequest(BaseModel):
    subject: str
    message: str
    parent: int | None = None


class ForumUpdatePostRequest(BaseModel):
    subject: str | None = None
    message: str | None = None


class QuizProcessRequest(BaseModel):
    data: list[dict[str, Any]] = Field(default_factory=list)
    finishattempt: int = 0
    timeup: int = 0


class ChoiceSubmitRequest(BaseModel):
    responses: list[int]


class ManualCompletionRequest(BaseModel):
    completed: bool = True


class CalendarEventRequest(BaseModel):
    name: str
    description: str = ""
    eventtype: str = "user"
    courseid: int = 0
    timestart: int
    timeduration: int = 0


class UnsupportedExtensionPoint(BaseModel):
    """Returned for operations that require local_espace."""

    status: str = "unsupported"
    reason: str
    extension: str
    todo: str
