"""Participants, enrolment, and groups via official Moodle WS."""

from __future__ import annotations

from typing import Any

from app.services.moodle import MoodleError, call, raise_http


def get_enrolled_users(course_id: int, token: str) -> Any:
    return call("core_enrol_get_enrolled_users", token=token, courseid=course_id)


def get_enrolment_methods(course_id: int, token: str) -> Any:
    return call(
        "core_enrol_get_course_enrolment_methods",
        token=token,
        courseid=course_id,
    )


def enrol_user(
    *,
    course_id: int,
    userid: int,
    roleid: int,
    suspend: int,
    token: str,
) -> Any:
    try:
        return call(
            "enrol_manual_enrol_users",
            token=token,
            enrolments=[
                {
                    "roleid": roleid,
                    "userid": userid,
                    "courseid": course_id,
                    "suspend": suspend,
                }
            ],
        )
    except MoodleError as exc:
        raise_http(exc)


def unenrol_user(*, course_id: int, userid: int, token: str) -> Any:
    try:
        return call(
            "enrol_manual_unenrol_users",
            token=token,
            enrolments=[{"userid": userid, "courseid": course_id}],
        )
    except MoodleError as exc:
        raise_http(exc)


def get_course_groups(course_id: int, token: str) -> Any:
    return call("core_group_get_course_groups", token=token, courseid=course_id)


def create_groups(course_id: int, name: str, description: str, token: str) -> Any:
    try:
        return call(
            "core_group_create_groups",
            token=token,
            groups=[
                {
                    "courseid": course_id,
                    "name": name,
                    "description": description,
                    "descriptionformat": 1,
                }
            ],
        )
    except MoodleError as exc:
        raise_http(exc)


def delete_groups(group_ids: list[int], token: str) -> Any:
    try:
        return call("core_group_delete_groups", token=token, groupids=group_ids)
    except MoodleError as exc:
        raise_http(exc)


def add_group_members(group_id: int, user_ids: list[int], token: str) -> Any:
    try:
        return call(
            "core_group_add_group_members",
            token=token,
            members=[{"groupid": group_id, "userid": uid} for uid in user_ids],
        )
    except MoodleError as exc:
        raise_http(exc)


def delete_group_members(group_id: int, user_ids: list[int], token: str) -> Any:
    try:
        return call(
            "core_group_delete_group_members",
            token=token,
            members=[{"groupid": group_id, "userid": uid} for uid in user_ids],
        )
    except MoodleError as exc:
        raise_http(exc)
