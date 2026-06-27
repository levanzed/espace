from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.routers.auth import router as auth_router
from app.routers.courses import router as courses_router
from app.routers.users import router as users_router
from app.routers.activity import router as activity_router

app = FastAPI(title="ESPACE API")

app.add_middleware(
    CORSMiddleware,
    allow_origin_regex=r"https?://(localhost|127\.0\.0\.1)(:\d+)?",
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/")
def root():
    return {
        "platform": "ESPACE",
        "status": "running",
    }

app.include_router(auth_router)
app.include_router(users_router)
app.include_router(courses_router)
app.include_router(activity_router)
