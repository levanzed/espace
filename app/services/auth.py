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

    return response.json()
