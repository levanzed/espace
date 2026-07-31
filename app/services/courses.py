"""Course listing and course-level management via official Moodle WS."""

from __future__ import annotations

from typing import Any

from app.services.moodle import MoodleError, call, raise_http


def list_user_courses(userid: int, token: str) -> list[dict[str, Any]]:
    data = call(
        "core_enrol_get_users_courses",
        token=token,
        userid=userid,
    )
    return [
        {
            "id": c["id"],
            "name": c["fullname"],
            "shortname": c["shortname"],
            "image": c.get("courseimage", ""),
            "summary": c.get("summary", ""),
            "progress": c.get("progress"),
            "startdate": c.get("startdate"),
            "enddate": c.get("enddate"),
        }
        for c in data
    ]


def get_contents(course_id: int, token: str) -> Any:
    return call("core_course_get_contents", token=token, courseid=course_id)


def get_administration_options(course_ids: list[int], token: str) -> Any:
    return call(
        "core_course_get_user_administration_options",
        token=token,
        courseids=course_ids,
    )


def get_navigation_options(course_ids: list[int], token: str) -> Any:
    return call(
        "core_course_get_user_navigation_options",
        token=token,
        courseids=course_ids,
    )


def get_categories(token: str) -> Any:
    return call("core_course_get_categories", token=token)


def create_course(
    *,
    fullname: str,
    shortname: str,
    categoryid: int,
    summary: str,
    visible: int,
    token: str,
) -> Any:
    try:
        return call(
            "core_course_create_courses",
            token=token,
            courses=[
                {
                    "fullname": fullname,
                    "shortname": shortname,
                    "categoryid": categoryid,
                    "summary": summary,
                    "visible": visible,
                }
            ],
        )
    except MoodleError as exc:
        raise_http(exc)


def update_course(course_id: int, fields: dict[str, Any], token: str) -> Any:
    payload = {"id": course_id, **{k: v for k, v in fields.items() if v is not None}}
    try:
        return call(
            "core_course_update_courses",
            token=token,
            courses=[payload],
        )
    except MoodleError as exc:
        raise_http(exc)


def delete_courses(course_ids: list[int], token: str) -> Any:
    try:
        return call(
            "core_course_delete_courses",
            token=token,
            courseids=course_ids,
        )
    except MoodleError as exc:
        raise_http(exc)


def duplicate_course(
    course_id: int,
    *,
    fullname: str,
    shortname: str,
    categoryid: int | None,
    visible: int,
    token: str,
) -> Any:
    params: dict[str, Any] = {
        "courseid": course_id,
        "fullname": fullname,
        "shortname": shortname,
        "visible": visible,
    }
    if categoryid is not None:
        params["categoryid"] = categoryid
    try:
        return call("core_course_duplicate_course", token=token, **params)
    except MoodleError as exc:
        raise_http(exc)


def view_course(course_id: int, token: str) -> Any:
    return call("core_course_view_course", token=token, courseid=course_id)
