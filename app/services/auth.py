import requests

from app.config import MOODLE_URL


def authenticate(username: str, password: str):

    response = requests.post(
        f"{MOODLE_URL}/login/token.php",
        data={
            "username": username,
            "password": password,
            "service": "moodle_mobile_app",
        },
    )

    data = response.json()

    if "token" not in data:
        return data

    user_response = requests.post(
        f"{MOODLE_URL}/webservice/rest/server.php",
        data={
            "wstoken": data["token"],
            "wsfunction": "core_webservice_get_site_info",
            "moodlewsrestformat": "json",
        },
    )

    site_info = user_response.json()

    data["userid"] = site_info["userid"]
    data["fullname"] = site_info["fullname"]
    data["username"] = site_info["username"]

    return data
