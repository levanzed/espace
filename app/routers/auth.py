from fastapi import APIRouter, HTTPException

from app.models.auth import LoginRequest
from app.security import create_access_token
from app.services.auth import authenticate

router = APIRouter()


@router.post("/login")
def login(request: LoginRequest):

    # TEMP DEBUG: prove this handler executes — remove after debugging
    print("AUTH DEBUG: routers.auth.login() entered", flush=True)

    data = authenticate(
        request.username,
        request.password,
    )

    if "token" not in data:
        print(
            "AUTH DEBUG: routers.auth.login() raising 401 "
            f"(moodle payload keys={list(data.keys()) if isinstance(data, dict) else type(data)})",
            flush=True,
        )
        raise HTTPException(
            status_code=401,
            detail="Invalid username or password",
        )

    access_token = create_access_token(
        {
            "userid": data["userid"],
            "username": data["username"],
            "fullname": data["fullname"],
            "moodle_token": data["token"],
        }
    )

    print("AUTH DEBUG: routers.auth.login() success", flush=True)
    return {
        "access_token": access_token,
        "token_type": "bearer",
    }
