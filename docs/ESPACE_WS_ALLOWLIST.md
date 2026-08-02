# ESPACE Web Service allowlist (living document)

**App authentication (current):** `moodle_mobile_app` via `login/token.php`.  
**local_espace functions:** registered onto Mobile (`MOODLE_OFFICIAL_MOBILE_SERVICE` in `db/services.php`).

The built-in **ESPACE** service (`shortname=espace`) retains a curated function list for optional/future use; it is **not** used for app login.

Grow Mobile / ESPACE memberships when new FastAPI `call()` targets are required. Prefer documenting each addition here.

## Current Sprint A–relevant Mobile surface

Via official Mobile membership + `local_espace` `services` declarations:

- `local_espace_*` section + `local_espace_upsert_module`
- Core/mod functions already on Mobile (courses, contents, assign reads, draft itemid, courseformat where enabled, etc.)
- Draft files: **`/webservice/upload.php`** (not a WS function; requires service `uploadfiles=1`)

## Sprint B — assign WS (verify on Mobile before each phase)

**Handoff:** `docs/HANDOFF_ASSIGNMENT_SPRINT_B.md`  
**Commit plan:** docs-only → B1 FastAPI → B1 Flutter → B2 FastAPI → B2 Flutter (one commit at a time).

### B1 — Student submission

| Function | FastAPI (current / planned) |
|----------|----------------------------|
| `mod_assign_get_submission_status` | `GET /activity/{cmid}/assign/status` |
| `mod_assign_save_submission` | `POST /activity/{cmid}/assign/submission` |
| `mod_assign_submit_for_grading` | `POST /activity/{cmid}/assign/submit` |
| `core_files_get_unused_draft_itemid` | `GET /files/draft-itemid` |
| `/webservice/upload.php` | `POST /files/upload` (`uploadfiles=1` on service) |

### B2 — Teacher grading

| Function | FastAPI (planned) |
|----------|-------------------|
| `mod_assign_list_participants` | `GET /activity/{cmid}/assign/participants` |
| `mod_assign_get_submission_status` + `userid` | `GET /activity/{cmid}/assign/status?userid=` |
| `mod_assign_save_grade` | `POST /activity/{cmid}/assign/grades` |

`mod_assign_get_submissions` / `mod_assign_get_grades` — optional; not required for v1 inbox.

## Not on Mobile (known gap)

- `core_files_upload` — do not use for ESPACE; use `webservice/upload.php` with Mobile token instead (Moodle App pattern).

## How to extend

1. Add FastAPI `call("…")` or upload.php client usage.
2. If a WS function is required and missing from Mobile, document the gap; prefer official Mobile-compatible paths when available.
3. For `local_espace` WS only: add to `$functions` with `services => [MOODLE_OFFICIAL_MOBILE_SERVICE]` and bump plugin version.
