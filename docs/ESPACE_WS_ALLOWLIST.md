# ESPACE Web Service allowlist (living document)

**App authentication (current):** `moodle_mobile_app` via `login/token.php`.  
**local_espace functions:** registered onto Mobile (`MOODLE_OFFICIAL_MOBILE_SERVICE` in `db/services.php`).

The built-in **ESPACE** service (`shortname=espace`) retains a curated function list for optional/future use; it is **not** used for app login.

Grow Mobile / ESPACE memberships when new FastAPI `call()` targets are required. Prefer documenting each addition here.

## Current Sprint A–relevant Mobile surface

Via official Mobile membership + `local_espace` `services` declarations:

- `local_espace_*` section + `local_espace_upsert_module`
- Core/mod functions already on Mobile (courses, contents, assign reads, draft itemid, courseformat where enabled, etc.)

## Not on Mobile (known gap)

- `core_files_upload` — do not use for ESPACE; use `webservice/upload.php` with Mobile token instead (Moodle App pattern).

## How to extend

1. Add FastAPI `call("…")` or upload.php client usage.
2. If a WS function is required and missing from Mobile, document the gap; prefer official Mobile-compatible paths when available.
3. For `local_espace` WS only: add to `$functions` with `services => [MOODLE_OFFICIAL_MOBILE_SERVICE]` and bump plugin version.
