# ESPACE API deployment notes

## Authentication (current)

ESPACE authenticates users via Moodle Mobile web service:

```
Flutter → FastAPI POST /login
       → Moodle login/token.php (service=moodle_mobile_app)
       → per-user Moodle token embedded in JWT
```

| Item | Value |
|------|--------|
| Login service | `moodle_mobile_app` |
| Token | Per-user Mobile-service token in JWT |
| Fallback | Optional `MOODLE_TOKEN` (same service family if used) |

`local_espace_*` functions are registered onto the official Mobile service so section and Assignment upsert calls work with that token.

## Environment variables

| Variable | Required | Purpose |
|----------|----------|---------|
| `MOODLE_URL` | Yes | Moodle site base URL |
| `MOODLE_TOKEN` | Optional | Fallback site token |
| `JWT_SECRET_KEY` | Yes | Signs ESPACE JWTs |

```env
MOODLE_URL=https://moodle.example.com
MOODLE_TOKEN=
JWT_SECRET_KEY=change-me
```

Do not use `service=espace` or `MOODLE_ESPACE_TOKEN` for app login (abandoned experiment).

## Plugin

Install/upgrade `local/espace/` (Notifications). Ensure Mobile service remains enabled and `local_espace_*` functions are listed on it after upgrade.

## Assignment intro attachments (next)

Do **not** rely on `core_files_upload` with Mobile tokens. Prefer Moodle’s official
`POST /webservice/upload.php` draft upload (same path as the Moodle App). See the
implementation plan produced with the auth revert / upload.php research.

## Verification

- [ ] Login
- [ ] Courses / sections
- [ ] Assignment create (without attachments)
- [ ] Assignment edit
- [ ] Module hide/show/delete
