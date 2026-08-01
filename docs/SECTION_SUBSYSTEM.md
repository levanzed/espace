# Section Subsystem — Final Status

**Status: Feature-complete** for Moodle-compatible course sections.

Architecture: Flutter → FastAPI → `local_espace` → Moodle core (`course_sections`).

## Teacher workflow

| Action | Status |
|--------|--------|
| List sections | ✓ |
| Create (optional name + description) | ✓ |
| Edit name + description (summary) | ✓ |
| Move up / down | ✓ |
| Hide / Show | ✓ |
| Delete | ✓ |

## Student workflow

| Action | Status |
|--------|--------|
| View section titles | ✓ |
| View section descriptions (Moodle `summary`) | ✓ |
| See activities under sections | ✓ |
| No teacher mutation controls | ✓ |

## Data ownership

| Field | Owner |
|-------|-------|
| `name` | Moodle `course_sections` |
| `summary` / `summaryformat` | Moodle `course_sections` |
| `visible` | Moodle `course_sections` |
| Section order | Moodle section numbers |

No ESPACE-only section fields.

## APIs

- Read: `core_course_get_contents`
- Mutations: `local_espace_create_section`, `rename_section`, `hide_section`, `show_section`, `move_section`, `delete_section`

## Out of scope (later subsystems)

Restrict access, completion, calendar, activities inside sections, AI.
