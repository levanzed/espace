# local_espace

Official Moodle 5.2+ extension plugin for the **ESPACE** platform.

Install path: `{moodle}/local/espace/`  
Component: `local_espace`  
PHP: 8.3+

## Purpose

Flutter never talks to Moodle directly.

```
Flutter → FastAPI → Official Moodle Web Services
                         ↓ (only when insufficient)
                    local_espace
                         ↓
                  Moodle internal APIs
```

`local_espace` exposes **only** capabilities that official Moodle Web Services do not cover adequately.

It does **not** duplicate `core_course_*`, `core_courseformat_*`, or `mod_*` functions when those are sufficient.

## Architecture

```
external/     → parameter validation, context, thin delegation
service/      → business logic using Moodle core APIs
validator/    → input rules
permission/   → login / context / capability gates
helper/       → course, section, file utilities
output/       → structured API envelopes
event/        → Moodle events (audit trail)
```

Dependency flow:

```
External → Service → (Validator + CapabilityChecker + Helper) → Moodle core
```

External classes contain **no** business logic.

## This release

### Fully implemented: Section subsystem

| Web service | Description |
|-------------|-------------|
| `local_espace_create_section` | Create section (optional name/summary) |
| `local_espace_rename_section` | Rename / update summary (**official WS gap**) |
| `local_espace_move_section` | Move section |
| `local_espace_hide_section` | Hide section |
| `local_espace_show_section` | Show section |
| `local_espace_delete_section` | Delete section |
| `local_espace_get_section` | Get one section |
| `local_espace_list_sections` | List sections |

Every section mutation:

1. Checks plugin enabled  
2. Requires login  
3. Validates course context  
4. Requires `local/espace:use` + `local/espace:managesections` (+ core caps as needed)  
5. Validates input  
6. Calls Moodle core APIs (`course_create_section`, `course_update_section`, `move_section_to`, `set_section_visible`, `course_delete_section`)  
7. Rebuilds course cache  
8. Triggers a Moodle event  
9. Returns a structured envelope  

### Implemented: Module upsert (Assignment Sprint A)

| Web service | Description |
|-------------|-------------|
| `local_espace_upsert_module` | Create/update activity settings (**official WS gap**). Sprint A: `modname=assign` only. |

Behaviour:

- Dispatches by `modname` → `AssignmentService::upsert` for `assign`
- Other modnames → unsupported exception (coming soon)
- Writes via Moodle `add_moduleinfo` / `update_moduleinfo` (Compatibility Promise)
- Requires `local/espace:use` + `local/espace:managemodules` + `moodle/course:manageactivities`
- Hide/show/delete CM remain on official `core_courseformat_update_course`

### Framework ready (typed shells; upsert not yet wired)

Service + external shells for:

- Course, Page, Quiz, Resource  
- Availability, Completion  

These classes provide capability matrices and constructor wiring so future sprints add dispatcher branches without redesign.

## Installation

1. Copy this directory to `{moodle}/local/espace/`
2. Visit **Site administration → Notifications** to install
3. Enable the **ESPACE** external service  
   (**Site administration → Server → Web services → External services**)
4. Add authorised users / tokens used by the FastAPI backend
5. Ensure the token user has:
   - `local/espace:use`
   - `local/espace:managesections` (sections)
   - `local/espace:managemodules` (module upsert)
   - `moodle/course:update`
   - `moodle/course:manageactivities` (module upsert)
   - `moodle/course:movesections` (for move)
   - `moodle/course:sectionvisibility` (for hide/show)

## Admin settings

- **Enable ESPACE local services** — fail-closed kill switch  
- **Strict permission mode** — require all listed capabilities  

## Response envelope

```json
{
  "status": "ok",
  "operation": "rename_section",
  "data": { "section": { "id": 12, "course": 3, "section": 2, "name": "Week 2", "visible": 1 } },
  "warnings": [],
  "timemodified": 1710000000
}
```

## Official WS vs local_espace

| Need | Use |
|------|-----|
| Create/update/delete/duplicate course | Official `core_course_*` |
| Hide/show/delete/duplicate/move cm | Official `core_courseformat_update_course` |
| Rename section / edit summary | **`local_espace_rename_section`** |
| Create section with name in one call | **`local_espace_create_section`** |
| Assignment create/update settings + plugins + attachments | **`local_espace_upsert_module`** (`modname=assign`) |
| Other activity upserts (quiz, page, …) | Future dispatcher branches on same WS |
| Availability conditions write | Future `AvailabilityService` |
| Completion settings write | Future `CompletionService` |

## Testing

From the Moodle root:

```bash
vendor/bin/phpunit --testsuite local_espace_testsuite
# or
vendor/bin/phpunit local/espace/tests/section/section_service_test.php
```

## Coding standards

- Moodle coding style (PHP-CS / moodlecheck)
- Strict types avoided where Moodle core APIs are untyped; PHP 8.3 type hints used on new APIs
- No `echo`, `die()`, or HTML from services
- Exceptions via `moodle_exception` / capability exceptions
- Events for audit; no custom logging tables

## Upgrade path

`db/upgrade.php` contains savepoints. This release has **no custom tables** (`install.xml` is empty by design). Future schema must be added with `upgrade_plugin_savepoint()`.

## Security

Every write path goes through `CapabilityChecker`:

- login  
- plugin enabled  
- system use capability  
- course context + require_login($course)  
- local + core capabilities  

## License

GNU GPL v3 or later
