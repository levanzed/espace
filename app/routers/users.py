from fastapi import APIRouter, Depends

from app.deps import moodle_token
from app.services.moodle import MoodleError, call, raise_http

router = APIRouter()


@router.get("/me")
def me(token: str = Depends(moodle_token)):
    try:
        data = call("core_webservice_get_site_info", token=token)
    except MoodleError as exc:
        raise_http(exc, status_code=401)

    return {
        "userid": data["userid"],
        "fullname": data["fullname"],
        "username": data["username"],
        "site": data["sitename"],
    }
