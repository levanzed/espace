from fastapi import APIRouter

from app.services.moodle import call

router = APIRouter()


@router.get("/courses")
def courses():

    data = call(
        "core_enrol_get_users_courses",
        userid=2,
    )

    return [
        {
            "id": c["id"],
            "name": c["fullname"],
            "shortname": c["shortname"],
            "image": c["courseimage"],
        }
        for c in data
    ]


@router.get("/courses/{course_id}")
def course(course_id: int):

    return call(
        "core_course_get_contents",
        courseid=course_id,
    )
