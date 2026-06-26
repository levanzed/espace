from fastapi import APIRouter, HTTPException

from app.models.auth import LoginRequest
from app.security import create_access_token
from app.services.auth import authenticate

router = APIRouter()


@router.post("/login")
def login(request: LoginRequest):

    data = authenticate(
        request.username,
        request.password
    )

    if "token" not in data:
        raise HTTPException(
            status_code=401,
            detail="Invalid username or password",
        )

    access_token = create_access_token(
        {
            "username": request.username,
            "moodle_token": data["token"],
        }
    )

    return {
        "access_token": access_token,
        "token_type": "bearer",
    }
