# ESPACE

**ESPACE (Educational Smart Platform for AI-Centered Education)** is a modern AI-powered learning management platform built on top of Moodle.

ESPACE combines Moodle's mature learning infrastructure with a modern backend architecture and AI-driven features to provide a better experience for students, teachers, administrators, and parents.

---

## Vision

To build an intelligent educational platform that goes beyond a traditional Learning Management System by integrating artificial intelligence into every aspect of teaching and learning.

---

## Current Architecture

```
Flutter / Web
        │
        ▼
ESPACE API (FastAPI)
        │
        ▼
Moodle Web Services
        │
        ▼
Moodle
        │
        ▼
PostgreSQL
```

---

## Technology Stack

- FastAPI
- Python
- Moodle 5.2
- PostgreSQL
- Docker
- Traefik
- Oracle Cloud Infrastructure (OCI)
- Git & GitHub

---

## Current Features

- Moodle integration via official Web Services only
- REST API adapter (FastAPI)
- JWT authentication (Moodle token embedded)
- Course listing, contents, create/update/delete/duplicate
- Section structural management (add/hide/show/delete/move)
- Module structural management (hide/show/delete/duplicate/move)
- Activity engine with enriched mod details
- Student interactions: assignment submit, forum, quiz attempts, choice
- Grades, completion, calendar, participants, groups, messages
- Extension points documented for `local_espace` gaps

See `docs/MOODLE_WS_CAPABILITIES.md` for the full capability matrix.

Deployment (single ESPACE Moodle service, env vars): `docs/DEPLOYMENT.md`  
WS allowlist (living): `docs/ESPACE_WS_ALLOWLIST.md`

---

## Roadmap

### Phase 1
- User Authentication
- JWT
- Student Dashboard

### Phase 2
- Assignments
- Quizzes
- Grades
- Notifications

### Phase 3
- AI Tutor
- AI Chat
- AI Quiz Generator
- AI Study Assistant

### Phase 4
- Parent Portal
- Analytics
- Smart Recommendations
- AI-powered Academic Insights

---

## Repository Structure

```
app/
│
├── main.py
├── config.py
│
├── routers/
├── services/
└── models/
```

---

## Author

**Dr. Sirajudheen K**

Assistant Professor of Physics

WMO Arts & Science College

---

## License

This project is currently under active development.
