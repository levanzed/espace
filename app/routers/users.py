from fastapi import APIRouter

from app.services.moodle import call

router = APIRouter()


@router.get("/me")
def me():

    data = call("core_webservice_get_site_info")

    return {
        "userid": data["userid"],
        "fullname": data["fullname"],
        "username": data["username"],
        "site": data["sitename"],
    }
