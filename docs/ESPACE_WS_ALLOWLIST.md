# ESPACE Web Service allowlist (living document)

**Service:** built-in **ESPACE** (`shortname=espace`)  
**Source of truth in code:** `local_espace/db/services.php` → `$services['ESPACE']['functions']`  
**Auth:** FastAPI login requests `service=espace` (`app/services/auth.py`).

This list is **minimal and grows with shipped features**. Do not preload every Moodle WS FastAPI might call someday. When a new feature adds a `moodle.call("…")` target, add that function here and to `services.php` in the same change, then bump the plugin version.

## Current allowlist (v1.1.5)

### Authentication / site
- `core_webservice_get_site_info`

### Course browse (reach sections & activities)
- `core_enrol_get_users_courses`
- `core_course_get_contents`
- `core_course_get_user_administration_options`
- `core_course_get_user_navigation_options`

### Sections (`local_espace`)
- `local_espace_create_section`
- `local_espace_rename_section`
- `local_espace_move_section`
- `local_espace_hide_section`
- `local_espace_show_section`
- `local_espace_delete_section`
- `local_espace_get_section`
- `local_espace_list_sections`

### Activity structural ops
- `core_courseformat_update_course`

### Assignment Sprint A / Activities authoring
- `local_espace_upsert_module`
- `mod_assign_get_assignments`

### File drafts / upload
- `core_files_get_unused_draft_itemid`
- `core_files_upload`

## Explicitly not included yet

Examples that exist in FastAPI but are **out of this allowlist** until those features are brought onto the ESPACE service intentionally:

- Full activity-engine `mod_*_get_*_by_courses` enrichment
- Student forum / quiz / choice interaction WS
- Grades, calendar, messages, participants, groups, enrolment writes
- Course create / update / delete / duplicate

Those calls may return `webservice_access_exception` until added.

## How to extend

1. Implement the FastAPI `call("new_wsfunction", …)`.
2. Add `new_wsfunction` to `$services['ESPACE']['functions']` in `db/services.php`.
3. Update this document.
4. Bump `local_espace` version + upgrade savepoint.
5. Deploy plugin → Moodle Notifications → re-test.
