# Engineering Handoff — Assignment Sprint B (Submission + Grading)

**Audience:** Engineers and agents implementing post–Sprint A assignment workflow.  
**Prerequisite:** Sprint A complete and deployed (`docs/HANDOFF_ASSIGNMENT_SPRINT_A.md`).  
**Repos:** `espace` (FastAPI), `espace-app` (Flutter). **No `local_espace` changes** expected for B unless Mobile WS audit fails.

**Read first:** `docs/ASSIGNMENT_ROADMAP.md`, `docs/ASSIGNMENT_SUBSYSTEM.md`, `docs/ESPACE_WS_ALLOWLIST.md`.

---

## 1. Goal

Deliver the **daily university assignment loop** in ESPACE:

1. Student views assignment, uploads work (files and/or online text), submits, sees status and own submission.
2. Teacher lists participants, opens a submission, grades with comment (± feedback file).
3. Student sees grade and feedback on the **same assignment activity** (via refreshed `mod_assign_get_submission_status` — not a separate grades product).

**Out of scope:** blind/anonymous marking, workflow, teams, rubrics, PDF annotate, bulk zip, multiple attempts (later / Moodle web).

---

## 2. Architecture (unchanged)

```text
Flutter → FastAPI JWT → per-user moodle_mobile_app token
       → mod_assign_* (submit, status, grade, participants)
       → /webservice/upload.php (draft files for submission + feedback files)
Sprint A authoring only: local_espace_upsert_module
```

Do not use `core_files_upload`. Do not redesign auth.

---

## 3. Moodle 5.2.1 official surfaces

| Flow | Moodle API |
|------|------------|
| Own / student status | `mod_assign_get_submission_status` (`assignid`, `userid=0` or teacher views `userid`) |
| Save draft | `mod_assign_save_submission` (`plugindata`: `onlinetext_editor`, `files_filemanager`) |
| Submit final | `mod_assign_submit_for_grading` (`acceptsubmissionstatement` when required) |
| Assignment config | `mod_assign_get_assignments` (submission types, `requiresubmissionstatement`, `submissionstatement`) |
| Teacher inbox | `mod_assign_list_participants` (`submitted`, `requiregrading`, `submissionstatus`) |
| Grade + feedback | `mod_assign_save_grade` (comments editor + feedback file `files_filemanager` draft) |
| File bytes | `POST /webservice/upload.php` + `core_files_get_unused_draft_itemid` |

Status shape (consume, do not duplicate): `lastattempt` (submission plugins, `canedit`, `cansubmit`, `timemodified`), `feedback` (`gradefordisplay`, `plugins`).

---

## 4. ESPACE baseline (before Sprint B code)

| Piece | State |
|-------|--------|
| `GET /activity/{cmid}` | Embeds `assignment` + `submission_status` (intro attachments tokenized) |
| `GET /activity/{cmid}/assign/status` | Raw `get_submission_status` |
| `POST /activity/{cmid}/assign/submission` | `save_submission` (text + `draftitemid`) |
| `POST /activity/{cmid}/assign/submit` | `submit_for_grading` (`acceptsubmissionstatement=1` hardcoded) |
| `POST /files/upload` | `upload.php` |
| `interactions.assign_save_grade` | **Service only** — no HTTP route |
| Flutter `AssignRenderer` | Online text + submit; **no** submission file picker; weak status/feedback UI |
| Teacher grading UI | **Missing** |

---

## 5. Sprint B phases

### B1 — Student submission

- Tokenize plugin file URLs in submission status (same idea as intro attachments).
- Optional `accept_submission_statement` on submit when assignment requires it.
- Flutter: status refresh, own text/files, file upload, submit receipt, statement dialog.

### B2 — Teacher grading + student feedback

- Routes: participants, per-user status, save grade (comment + optional feedback file draft).
- Flutter: grading screen; student feedback from `feedback` on status refresh.

---

## 6. Commit plan (one at a time)

Deploy and test after **each** commit. Do not mix Flutter + FastAPI in one commit when avoidable.

| # | Repo | Scope | Message (suggested) |
|---|------|--------|---------------------|
| 1 | espace | **Docs only** | `docs: add Sprint B handoff and WS checklist` |
| 2 | espace | B1 backend | `feat(assign): tokenize submission status files and submission statement on submit` |
| 3 | espace-app | B1 Flutter | `feat(assign): student file and text submission with status and receipt` |
| 4 | espace | B2 backend | `feat(assign): teacher participants, per-user status, save grade with feedback` |
| 5 | espace-app | B2 Flutter | `feat(assign): grading screen and student feedback display` |

**Plugin:** No deploy for commits 1–5 unless Mobile WS audit (§7) requires adding functions to `local_espace` Mobile registration.

---

## 7. Pre-flight: Mobile service checklist

Before commit 2, verify on target Moodle (**Site administration → Server → Web services → External services → Moodle mobile web service**):

- [ ] Service **enabled**
- [ ] **uploadfiles** enabled (for `upload.php`)
- [ ] Functions present (add via plugin upgrade or admin if missing):
  - `mod_assign_get_submission_status`
  - `mod_assign_save_submission`
  - `mod_assign_submit_for_grading`
  - `mod_assign_list_participants`
  - `mod_assign_save_grade`
  - `mod_assign_get_assignments` (already used in Sprint A)
  - `core_files_get_unused_draft_itemid`

Test with a teacher/student token: call `mod_assign_get_submission_status` from API documentation or FastAPI after B1 routes exist.

---

## 8. Verification (incremental)

**After B1 (commits 2–3):**

- Student opens assign activity; sees status and dates.
- Upload file(s) via app → save draft → see files in own submission.
- Online text save works; submit shows submitted state and timestamp.
- If assignment requires submission statement, submit blocked until accepted.

**After B2 (commits 4–5):**

- Teacher opens grading from assignment; sees participant list.
- Open student → view submission files/text → grade + comment (+ feedback file).
- Student reopens assignment → sees grade, comment, feedback files.

---

## 9. Key paths (planned)

```
espace/
  app/routers/activity.py          # assign routes (extend)
  app/services/interactions.py     # assign_* (extend)
  app/services/activity.py         # tokenize submission_status (B1)
  app/services/assign_workflow.py  # optional helper (B1/B2)
  app/models/academic.py           # request bodies

espace-app/
  lib/features/activity/renderers/assign_renderer.dart
  lib/features/activity/data/activity_repository.dart
  lib/features/activity/grading/assign_grading_screen.dart  # B2 new
```

---

*End of Sprint B handoff. Implement commits sequentially; stop after each for deploy/test approval.*
