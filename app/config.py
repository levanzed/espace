import os
from dotenv import load_dotenv

load_dotenv()

MOODLE_URL = os.getenv("MOODLE_URL")
MOODLE_TOKEN = os.getenv("MOODLE_TOKEN")
JWT_SECRET_KEY = os.getenv("JWT_SECRET_KEY")
