import requests

from app.config import MOODLE_URL, MOODLE_TOKEN


def call(function, token=None, **params):

    response = requests.get(
        f"{MOODLE_URL}/webservice/rest/server.php",
        params={
            "wstoken": token or MOODLE_TOKEN,
            "wsfunction": function,
            "moodlewsrestformat": "json",
            **params,
        },
    )

    return response.json()
