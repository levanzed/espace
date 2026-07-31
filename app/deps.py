"""Shared FastAPI dependencies."""

from __future__ import annotations

from typing import Any

from fastapi import Depends
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.security import decode_access_token

security = HTTPBearer()


def get_token_payload(
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> dict[str, Any]:
    return decode_access_token(credentials.credentials)


def moodle_token(payload: dict[str, Any] = Depends(get_token_payload)) -> str:
    return payload["moodle_token"]


def current_userid(payload: dict[str, Any] = Depends(get_token_payload)) -> int:
    return int(payload["userid"])
