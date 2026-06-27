from fastapi import APIRouter, Depends
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.security import decode_access_token
from app.services.moodle import call

router = APIRouter()

security = HTTPBearer()


@router.get("/courses")
def courses(
    credentials: HTTPAuthorizationCredentials = Depends(security),
):
    payload = decode_access_token(credentials.credentials)

    data = call(
        "core_enrol_get_users_courses",
        userid=payload["userid"],
        token=payload["moodle_token"],
    )

    return [
        {
            "id": c["id"],
            "name": c["fullname"],
            "shortname": c["shortname"],
            "image": c.get("courseimage", ""),
        }
        for c in data
    ]


@router.get("/courses/{course_id}")
def course(
    course_id: int,
    credentials: HTTPAuthorizationCredentials = Depends(security),
):
    payload = decode_access_token(credentials.credentials)

    return call(
        "core_course_get_contents",
        courseid=course_id,
        token=payload["moodle_token"],
    )
