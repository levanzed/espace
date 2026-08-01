import requests

from app.config import MOODLE_URL


def authenticate(username: str, password: str):

    # TEMP DEBUG: prove this module executes — remove after debugging
    print("AUTH DEBUG: authenticate() entered", flush=True)

    token_url = f"{MOODLE_URL}/login/token.php"
    service = "espace"

    # TEMP DEBUG: prove this module executes — remove after debugging
    print(
        f"AUTH DEBUG: calling login/token.php url={token_url} service={service}",
        flush=True,
    )

    response = requests.post(
        token_url,
        data={
            "username": username,
            "password": password,
            "service": service,
        },
    )

    # TEMP DEBUG: prove this module executes — remove after debugging
    print(
        f"AUTH DEBUG: login/token.php http_status={response.status_code}",
        flush=True,
    )

    try:
        data = response.json()
        print(f"AUTH DEBUG: login/token.php response json={data}", flush=True)
    except ValueError:
        print(
            f"AUTH DEBUG: login/token.php non-JSON body={response.text!r}",
            flush=True,
        )
        data = response.json()

    if "token" not in data:
        print(
            "AUTH DEBUG: authenticate() returning without token (caller will 401)",
            flush=True,
        )
        return data

    print("AUTH DEBUG: authenticate() token present, fetching site info", flush=True)

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

    print("AUTH DEBUG: authenticate() success", flush=True)
    return data
