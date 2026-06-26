from fastapi import FastAPI

from app.routers.auth import router as auth_router
from app.routers.courses import router as courses_router
from app.routers.users import router as users_router

app = FastAPI(title="ESPACE API")


@app.get("/")
def root():
    return {
        "platform": "ESPACE",
        "status": "running",
    }


app.include_router(auth_router)
app.include_router(users_router)
app.include_router(courses_router)
