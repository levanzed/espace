# Engineering Handoff — Assignment Sprint A

**Audience:** Any engineer or Cursor agent continuing ESPACE development without prior conversation history.  
**Created:** 2026-08-01  
**Repos:** `espace` (backend + `local_espace`), `espace-app` (Flutter)  
**Branches:** both repositories currently on `main` tracking `origin/main`

This document is the binding engineering handoff for **Assignment Sprint A (Teacher Authoring)** under the approved **Activity architecture**. Sprint A implementation is **complete** in code; treat remaining items as deploy/verify and later sprints only.

Related blueprints (read in this order after this handoff):

1. `docs/HANDOFF_ASSIGNMENT_SPRINT_A.md` *(this file)*
2. `docs/SECTION_SUBSYSTEM.md` — Section frozen status
3. `docs/ASSIGNMENT_SUBSYSTEM.md` — full Assignment capability matrix and later sprints B–G
4. `docs/MOODLE_WS_CAPABILITIES.md` — WS capability notes
5. `docs/DEPLOYMENT.md` — single ESPACE-service auth and deploy
6. `docs/ESPACE_WS_ALLOWLIST.md` — living Moodle WS allowlist
7. `AGENTS.md` — FastAPI agent responsibilities

---

## 1. Project Overview

### What ESPACE is

**ESPACE** (Educational Smart Platform for AI-Centered Education) is a modern learning client and API layer built **on top of Moodle**. Moodle remains the academic system of record. ESPACE improves UX for teachers and students; it does not replace Moodle’s academic engine.

### High-level architecture

```
Flutter app (espace-app)
        │  JWT REST only
        ▼
ESPACE FastAPI (espace/app)
        │
        ├──────────────► Official Moodle Web Services (preferred)
        │
        └──────────────► local_espace Moodle plugin (genuine WS gaps only)
                                │
                                ▼
                         Moodle 5.2 core APIs
                                │
                                ▼
                         Moodle database (PostgreSQL)
```

### Components

| Component | Location | Role |
|-----------|----------|------|
| **Flutter** | `~/Projects/espace-app` | UI only. Never calls Moodle directly. Uses `AuthenticatedApi` + feature repositories. |
| **FastAPI** | `~/Projects/espace/app` | Thin routers, JWT, services that call Moodle via `app/services/moodle.py`. |
| **Moodle 5.2** | Deployed Moodle instance | Source of truth for courses, sections, activities, grades, files, users. |
| **local_espace** | `~/Projects/espace/local_espace` → installed as `{moodle}/local/espace/` | Fills official WS gaps (e.g. section mutations; upcoming activity upsert). |
| **Oracle deployment** | OCI | Production stack: Docker, Traefik, FastAPI, Moodle, PostgreSQL. Operational. Do not redesign deployment in feature sprints. |

### Data flow rule

Flutter → FastAPI → Moodle (official WS and/or `local_espace`) → Moodle DB.

ESPACE must not introduce parallel academic databases for courses, sections, assignments, submissions, or grades.

---

## 2. Current Project Status

### Completed subsystems

| Area | Status |
|------|--------|
| Auth (JWT + Moodle token embedding) | Working |
| Course list / contents | Working (`core_course_get_contents`) |
| Activity engine (read enrich for many mod types) | Working (view-oriented) |
| Student assign submit (basic online text) | Partial (Sprint B) |
| **Section subsystem** | **Feature-complete and frozen** |
| **Assignment authoring (Sprint A)** | **Complete (plugin + FastAPI + Flutter)** |
| Quiz / other activity authoring | Not started (picker stubs only) |
| AI features | Future only (documented, not implemented) |

### Section subsystem status

**Officially complete and frozen** except for future bug fixes.

Documented in `docs/SECTION_SUBSYSTEM.md`.

Verified teacher capabilities:

- List, create (optional name + description), edit name + description (`summary` / `summaryformat`), move, hide, show, delete

Verified student capabilities:

- View titles and Moodle `summary` HTML; view modules; no teacher mutation controls

Architecture: Flutter → FastAPI → `local_espace_*` section WS → Moodle `course_sections`.

**Do not redesign Sections.**

### Assignment subsystem status (Sprint A)

| Layer | Status |
|-------|--------|
| Architecture | Activity picker + registry + `/activities` + `local_espace_upsert_module` |
| Plugin | `local_espace_upsert_module` registered; `AssignmentService::upsert` via Moodle `add_moduleinfo` / `update_moduleinfo` (verified Moodle 5.2.1) |
| FastAPI | `POST/PUT/GET` activities routes; `assign_authoring` mapper; `POST /files/upload` |
| Flutter | Activity Registry + Picker; Assignment Editor (create/edit); hide/show/delete via module actions |
| Public naming | **`/activities` + `local_espace_upsert_module`** (not `/assignments` / `local_espace_upsert_assign`) |

Blueprint: `docs/ASSIGNMENT_SUBSYSTEM.md`. Later sprints B–G unchanged.

### Current branch

| Repo | Branch | Notes |
|------|--------|-------|
| `espace` | `main` | Sprint A plugin + FastAPI + docs (commit when asked) |
| `espace-app` | `main` | Sprint A Flutter authoring (commit when asked) |

### Current deployment status

- Oracle Cloud deployment is operational
- Moodle 5.2.1+ target; sync `local_espace` and enable `local_espace_upsert_module` on the ESPACE external service
- After Sprint A code: deploy FastAPI, upgrade plugin, purge Moodle caches, hot-restart Flutter

---

## 3. Architecture Principles

These are permanent unless there is a **strong technical reason** documented in a later ADR-style note.

1. **Moodle is the source of truth** for all academic data.
2. **Flutter never talks to Moodle directly.**
3. **Reuse official Moodle Web Services whenever they exist.**
4. **Never duplicate Moodle academic logic** in FastAPI or Flutter.
5. **Never duplicate Moodle data** in ESPACE stores.
6. **Improve only UX** — do not replace Moodle behaviour.
7. **`local_espace` only for genuine capability gaps**, wrapping Moodle internals (not inventing semantics).
8. **Compatibility Promise:**
   - Everything created in ESPACE remains fully editable in Moodle.
   - Everything created in Moodle remains fully editable in ESPACE.
   - ESPACE must never reduce Moodle capability.
   - ESPACE must never create a parallel academic model Moodle already provides.
9. **Routers stay thin**; business logic lives in services; type hints everywhere.
10. **Minimal, architecture-consistent changes** — no drive-by refactors.
11. **Decision filter for every feature:**
    1. Does Moodle already provide this?
    2. Can ESPACE reuse an official WS/API?
    3. If not, is `local_espace` justified?
12. **Do not over-abstract** — smallest reusable Activity seams; Assignment-specific domain stays assign-specific.
13. **Sections are frozen** — bug fixes only.
14. **Prefer small focused commits**; commit only when asked; no force-push to main.

---

## 4. Stable Components

Do **not** redesign these unless fixing a proven bug.

| Component | Path | Why stable |
|-----------|------|------------|
| Moodle REST client | `app/services/moodle.py` | Canonical `call()` + flatten + `MoodleError` |
| JWT / deps | `app/security.py`, `app/deps.py` | Auth contract with Flutter |
| Config | `app/config.py` | Environment wiring |
| Section mutations | `app/services/course_editor.py` section paths + `local_espace` section WS | Feature-complete |
| Section Flutter UX | `espace-app/.../course_screen.dart` section controls | Frozen after summary support |
| CM structural ops | `course_editor.module_action` → `core_courseformat_update_course` | Correct for hide/show/delete/move cm |
| File drafts | `app/services/academic.py` `core_files_*` | Reuse for all activity attachments |
| Plugin permission gate | `local_espace/classes/permission/CapabilityChecker.php` | Enabled check lives in checker; `lib.php` thin wrappers |
| Plugin section subsystem | `local_espace` section external/service/validator/events | Do not regenerate |
| Activity read engine | `app/services/activity.py` | Enrichment for viewing; extend carefully |
| Flutter auth / API | `lib/core/api/*`, auth feature | Shared by all features |

**Oracle / Docker / Traefik:** out of scope for Assignment Sprint A.

---

## 5. Activity Architecture

Assignment is the **first** Activity. The architecture must support future Moodle activities **without redesign**, without building a giant generic form framework.

### Approved shape

```
Section
  → Add Activity
  → Activity Picker
  → Activity Registry (modname → enabled?, editor launcher)
  → Assignment Editor          ← only concrete editor in Sprint A
       ↓
  FastAPI Activities API (modname-aware)
       ↓
  Official Moodle WS when possible
       ↓
  local_espace_upsert_module (dispatcher by modname)
       ↓
  AssignmentService::upsert → Moodle assign APIs / file areas
```

Later: register Quiz/Page/Forum editors and add dispatcher branches — same picker, same routes, same plugin entry.

### Activity Picker

- Reusable UI from every section’s **Add Activity** control.
- Lists Moodle activity types.
- Sprint A: **Assignment** fully enabled; others disabled or “Coming soon”.
- Must not require redesign when adding Quiz later.

### Activity Registry (Flutter)

Minimal registry entries, e.g.:

- `modname` (`assign`, `quiz`, …)
- label, icon
- `enabled` flag
- launcher → concrete editor screen (Assignment Editor only for now)

### Activities API (FastAPI) — approved long-term REST

Prefer **generic activity routes** (not `/assignments` as the primary public API):

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/courses/{course_id}/sections/{section_id}/activities` | Create; body includes `modname` + type settings |
| `PUT` | `/courses/{course_id}/activities/{cmid}` | Update; resolve type from CM; dispatch |
| `GET` | `/courses/{course_id}/activities/{cmid}` | Authoring settings for editor |
| Existing | `POST /courses/{course_id}/modules/actions` | `cm_hide` / `cm_show` / `cm_delete` / move |

Sprint A: only `modname=assign` succeeds; other modnames → **501** with clear unsupported / coming-soon detail.

### FastAPI service layering

| Module | Role |
|--------|------|
| `app/services/activities.py` | Facade: dispatch create/update/get by `modname` |
| Assign authoring helper (e.g. `assign_authoring.py` or `activities/assign.py`) | Map assign fields ↔ Moodle / plugin payload |
| `app/services/course_editor.py` | Keep **structural** cm ops only; do not dump assign settings here |

Avoid a heavy `Activity` ABC. A small handler registry keyed by `modname` is enough.

### Plugin dispatcher

**Public WS name (approved):** `local_espace_upsert_module`  
(Aligns with existing docs TODO naming; prefer this over a public-only `local_espace_upsert_assign`.)

Behaviour:

- Parameters include `modname`, course/section or `cmid`, settings payload, optional draft file itemids.
- Dispatch:
  - `assign` → `AssignmentService::upsert` (implement in Sprint A)
  - other → unsupported exception until later sprints

**Internal** `AssignmentService` remains Assignment-specific (correct). Do not invent a universal “save any mod_form” engine in Sprint A.

### Assignment as first implementation

Only Assignment is functional. The seams (picker, registry, activities API, plugin dispatcher) exist so Quiz etc. plug in later.

---

## 6. Assignment Sprint A

### Exact approved scope (Teacher Assignment Authoring)

A teacher must be able to, **without opening Moodle**:

1. Open a course → section  
2. Press **Add Activity** → Activity Picker → **Assignment**  
3. Configure essential Moodle-native settings in **Assignment Editor**  
4. Save → assignment appears in the selected section  
5. Edit later  
6. Delete (via existing Moodle `cm_delete` / courseformat path)  
7. Toggle visibility (existing `cm_hide` / `cm_show`)

### Included settings

**General**

- Assignment name  
- Description (`intro`) — rich text as HTML/`summaryformat`-style Moodle formats (practical: HTML text; not full Atto in v1)  

**Resources (optional)**

- Attachments via Moodle file infrastructure (`core_files_*` drafts → Moodle `introattachment` / equivalent areas)  
- Types: PDF, DOCX, PPT/PPTX, ZIP, images, video, audio (subject to site limits)  
- Text-only, files-only, or both allowed  

**Availability**

- Allow submissions from  
- Due date  
- Cut-off date  
- Remind me to grade by (`gradingduedate`)  

**Submission settings**

- Online text / file upload / both  
- Max number of uploaded files  
- Max upload size  
(Reuse Moodle plugin config behaviour)

**Grading configuration only**

- Grade type  
- Maximum grade  
- Scale  
- Grade category  

**Visibility**

- Moodle CM visibility  

### Explicitly excluded (later sprints)

- Student submissions (Sprint B)  
- Teacher grading / feedback (Sprint C)  
- Rubrics / marking guides authoring & scoring (Sprint D)  
- Blind marking / workflow / extensions / locks (Sprint E)  
- Completion config / restrict access / notifications delivery / calendar UI (Sprint F)  
- AI (Sprint G)  
- Annotate PDF, offline worksheet (later)  
- Implementing other activity types beyond picker stubs  

### Success criteria

- Create/edit/delete/visibility from ESPACE  
- Round-trip: ESPACE → Moodle web edit → ESPACE edit still works  
- Data only in Moodle tables/file areas  
- No ESPACE parallel assignment store  

---

## 7. Moodle Integration

### Official APIs reused in Sprint A

| Need | API |
|------|-----|
| Course/section browse | `core_course_get_contents` |
| Read assignment for edit | `mod_assign_get_assignments` (primary); add plugin get only if insufficient |
| File drafts / upload | `core_files_get_unused_draft_itemid`, `core_files_upload` |
| Hide / show / delete CM | `core_courseformat_update_course` (`cm_hide`, `cm_show`, `cm_delete`) |
| Grade categories / scales lists | `core_grades_*` / related **if available on site**; otherwise minimal safe defaults + document limitation |

**Do not use** `core_courseformat_new_module` as the Assignment authoring path (defaults-only / FEATURE_QUICKCREATE — inadequate for full settings).

### Plugin responsibilities (`local_espace`)

- Register and implement `local_espace_upsert_module` dispatcher  
- Implement `AssignmentService::upsert` using Moodle internals (`assign_add_instance` / update paths, CM section placement, submission plugin config, grade item fields, intro + attachments via Moodle file APIs)  
- Permissions via existing CapabilityChecker patterns (`managemodules` + core caps)  
- Return structured envelopes consistent with section WS  
- Rebuild course cache; fire Moodle events where appropriate  
- **Never** own academic data outside Moodle  

### FastAPI responsibilities

- JWT auth unchanged  
- Activities routes + facade + assign payload mapping  
- Forward official WS for read/files/cm lifecycle  
- Call `local_espace_upsert_module` for create/update  
- Preserve thin routers / typed models / `MoodleError` → HTTP  

### Flutter responsibilities

- Activity Picker + registry  
- Assignment Editor UI  
- Wire create/update/get; reuse module actions for delete/hide  
- Reuse existing file draft upload patterns if present; otherwise add minimal upload against existing FastAPI file endpoints  
- Do not talk to Moodle  

---

## 8. Architectural Decisions (Do Not Revisit Unless There Is a Strong Technical Reason)

| Decision | Reasoning | Rejected alternative |
|----------|-----------|----------------------|
| Moodle SoR; Flutter ↔ FastAPI only | Clean boundary; Moodle compatibility | Flutter → Moodle direct |
| Sections via `local_espace` not `core_courseformat` for section mutations | Official courseformat not available/reliable on ESPACE service; plugin adds validation/events/envelope | Keep legacy courseformat for sections |
| Section summary uses Moodle `summary`/`summaryformat` only | Compatibility Promise; no ESPACE field | ESPACE-only description store |
| Activity **picker + registry + activities API + plugin dispatcher** | Scales to Quiz/Page/Forum without redesign | Hard-wire Assignment-only stack |
| Primary REST = `/activities` with `modname` | One create/update/get surface | Per-type `/assignments`, `/quizzes`, … |
| Public plugin WS = `local_espace_upsert_module` | One gap entry; internal dispatch | Only `local_espace_upsert_assign` as sole public WS |
| Assignment-specific editor + `AssignmentService` body | Avoid fake generic form engine | Dynamic schema UI / god Activity class |
| Keep cm hide/show/delete on official courseformat | Already works; not a gap | Duplicate delete in local_espace |
| Abandon quick-create for Assignment authoring | Cannot set full Moodle fields | `core_courseformat_new_module` then patch |
| CapabilityChecker owns `is_plugin_enabled`; lib.php wraps | Moodle docs: logic in autoloaded classes; lib.php callback bridge | `require_once lib.php` from namespaced classes |
| AI out of Sprint A–F | Native parity first | Mix AI into authoring |
| Do not over-abstract | Delivery speed + clarity | Premature Activity framework |

**Public naming (final):** `local_espace_upsert_module` + FastAPI `/activities`. Do not introduce `local_espace_upsert_assign` or `/assignments` as public surfaces.

---

## 9. Known Technical Debt

List only genuine items — do not invent work.

1. **Legacy FastAPI surfaces still present:** `POST /courses/{id}/modules` (quick-create) and `PUT /courses/{id}/modules/{cmid}/settings` (delegates to Activities). Flutter no longer uses them for Assign; remove or clearly deprecate when safe.
2. **Unused Flutter `CoursesRepository.createModule`:** leftover after Activity Picker migration; no callers.
3. **Registry launch not fully data-driven:** course screen still branches on `modname == 'assign'` / `type == 'assign'` instead of a registry launcher callback.
4. **Grade category / scale UX:** editor uses numeric ids; no `core_grades_*` list dropdowns yet (site WS coverage dependent).
5. **MoodleError → HTTP mapping:** most Moodle failures become 400 via `raise_http`; no fine-grained 401/403/409 mapping.
6. **Student assign UI** remains basic (online text); file submit / status UX is Sprint B.
7. **Plugin `external/assignment.php`:** unregistered capability-matrix shell only (no public WS); harmless but unused for authoring.
8. **Intro editor:** plain/HTML text, not full Atto; max upload size not exposed in Flutter (site/plugin defaults).

---

## 10. Coding Standards

### General

- Prefer small, focused diffs; no unrelated refactors  
- Match existing naming and file layout  
- No secrets in commits  
- Commit only when the user asks; use repo commit-message style  

### FastAPI (`espace`)

- Thin routers; logic in `app/services/`  
- Call Moodle only through `moodle.call()`  
- Type hints on public functions  
- Pydantic models for request bodies  
- Propagate Moodle errors via `raise_http` / structured detail  
- Do not invent Moodle APIs in Python  

### local_espace

- External classes: validate params → service (no business logic)  
- Services use Moodle core APIs  
- CapabilityChecker for login / enabled / caps  
- Structured `ApiResponse` envelopes  
- Register WS in `db/services.php`  
- Moodle coding style; events for mutations where established  
- Enabled check: implement in CapabilityChecker; globals in `lib.php` are thin wrappers  

### Flutter (`espace-app`)

- Feature folders under `lib/features/`  
- Repositories talk to FastAPI only  
- Reuse `AuthenticatedApi`, themes, `HtmlContent` where appropriate  
- Dialog lifecycle: await API before `Navigator.pop` when mutations happen in-dialog; dispose controllers after frame  
- Minimal UI changes; no redesign for its own sake  

### Documentation

- Update `MOODLE_WS_CAPABILITIES.md` when capabilities land  
- Keep subsystem docs truthful (complete vs deferred)  

---

## 11. Verification Strategy

### Manual testing checklist (Sprint A exit)

Teacher account on a real course:

1. Open course; sections load (including summaries).  
2. **Add Activity** → picker shows Assignment enabled; others coming soon.  
3. Create Assignment with name + description; appears in correct section after reload.  
4. Create with optional attachments; files visible in Moodle and ESPACE.  
5. Set availability dates; verify in Moodle assignment settings.  
6. Set submission online text / files / both + max files/size; verify in Moodle.  
7. Set grade type / max / scale / category; verify in Moodle.  
8. Set hidden; student cannot see (per Moodle rules); teacher can manage.  
9. Edit all fields in ESPACE; changes persist.  
10. Edit same assignment in Moodle UI; reopen in ESPACE — values match.  
11. Delete via ESPACE; gone from course contents.  
12. Section operations still work (regression).  

### Deployment workflow

1. Commit focused Sprint A changes when asked.  
2. Deploy FastAPI container/service on OCI.  
3. Sync `local/espace` to Moodle; visit Notifications / upgrade if `version.php` bumped; purge caches.  
4. Ensure external service includes new `local_espace_upsert_module` (+ existing section functions).  
5. Ensure token user has `local/espace:use`, `local/espace:managemodules`, and Moodle activity management caps.  
6. Release / hot-restart Flutter app pointing at API.  

### Git workflow

- Repos: `espace`, `espace-app` (and Moodle plugin path inside `espace/local_espace`)  
- Branch: currently `main` — use feature branches if team prefers for Sprint A  
- Prefer small commits: plugin upsert → FastAPI activities API → Flutter picker/editor  
- Do not force-push `main`  
- Do not commit `.env` / secrets  

---

## 12. Next Immediate Task

**Sprint A code is complete.** Next work is deploy/verify on OCI, then Sprint B when approved.

### Ordered work for the next session

1. **Commit** Sprint A changes in `espace` and `espace-app` when asked.  
2. **Deploy** FastAPI; sync `local_espace` (version ≥ 2026080101); purge caches; enable `local_espace_upsert_module`.  
3. **Verify** using §11 checklist on real Moodle 5.2.1+.  
4. **Sprint B** (student submission) only after explicit go-ahead — official `mod_assign_*` only.  

### Do not start without explicit approval

- Student submission sprint (B)  
- Grading / rubrics / workflow (C–E)  
- Other activity upserts (Quiz, etc.) beyond picker stubs  
- Section redesign  
- AI  
- Oracle/Docker redesign  

---

## Appendix A — Key paths cheat sheet

```
espace/
  AGENTS.md
  docs/HANDOFF_ASSIGNMENT_SPRINT_A.md
  docs/ASSIGNMENT_SUBSYSTEM.md
  docs/SECTION_SUBSYSTEM.md
  docs/MOODLE_WS_CAPABILITIES.md
  app/services/moodle.py
  app/services/activities.py
  app/services/assign_authoring.py
  app/services/course_editor.py         ← sections + cm structural
  app/routers/courses.py                ← /activities + section/module actions
  app/routers/academic.py               ← /files/draft-itemid + /files/upload
  local_espace/                         ← local_espace_upsert_module

espace-app/
  lib/features/courses/presentation/course_screen.dart
  lib/features/courses/data/courses_repository.dart
  lib/features/activity/authoring/      ← registry, picker, assignment editor
  lib/features/activity/data/activity_repository.dart  ← draft/upload helpers
  lib/features/activity/                ← existing viewers
  lib/core/api/
```

## Appendix B — Compatibility Promise (copy for agents)

Everything created inside ESPACE must remain fully editable inside Moodle.  
Everything created inside Moodle must remain fully editable inside ESPACE.  
ESPACE may improve workflows and UX but must never reduce Moodle capability.  
ESPACE must never create a parallel academic model when Moodle already provides one.

---

*End of handoff. Assignment Sprint A is implemented. Deploy/verify, then proceed to Sprint B only with explicit approval.*
