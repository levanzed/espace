# Assignment post–Sprint A: feature audit and roadmap

**Status:** Sprint A (teacher authoring) **complete**. Next: Sprint B (Phase 1–2) — see implementation handoff.  
**Audience:** Product + engineering.  
**Repos:** `espace`, `espace-app`  
**Related:** [`ASSIGNMENT_SUBSYSTEM.md`](ASSIGNMENT_SUBSYSTEM.md), [`HANDOFF_ASSIGNMENT_SPRINT_A.md`](HANDOFF_ASSIGNMENT_SPRINT_A.md), [`HANDOFF_ASSIGNMENT_SPRINT_B.md`](HANDOFF_ASSIGNMENT_SPRINT_B.md)

---

## Product stance

Build the **daily university assignment loop** (author → student submit → teacher grade → student sees feedback). Reuse official `mod_assign_*` + `upload.php` wherever they exist. Use `local_espace` only for authoring gaps already closed in Sprint A. Do **not** clone blind marking, rubrics, teams, PDF annotate, etc. in early phases.

```text
Sprint A done → Phase 1 (student submit) → Phase 2 (teacher grade) → Phase 3 (polish)
```

---

## Tier A — Essential (must have)

| Feature | What it does | Moodle WS / API | ESPACE today | Effort | Recommendation |
|---------|--------------|-----------------|--------------|--------|----------------|
| **Create assignment** | Teacher creates assign in a section | `local_espace_upsert_module` | **Done** | — | Keep |
| **Edit assignment** | Change settings / intro / plugins | upsert + `mod_assign_get_assignments` | **Done** | — | Keep |
| **Delete assignment** | Remove CM | `core_courseformat_update_course` / `cm_delete` | **Partial** (API; confirm UX) | S | Ensure course UI |
| **Hide / show** | Visibility | CM actions | **Partial** (API) | S | Wire in course UI if needed |
| **Availability dates** | Open / due / cutoff / grade-by | upsert settings | **Done** | — | Keep |
| **File submission settings** | Enable file plugin, max files/size | upsert | **Done** | — | Keep |
| **Online text settings** | Enable onlinetext | upsert | **Done** | — | Keep |
| **Intro attachments** | Teacher instruction files | `upload.php` + upsert; read via `get_assignments` | **Done** | — | Keep |
| **View assignment (student)** | Intro, dates, teacher files | `GET /activities/{cmid}` | **Done** | — | Keep |
| **Download teacher attachments** | Open introattachment `fileurl` | pluginfile + token | **Done** | — | Keep |
| **Submit online text** | Draft/save text | `mod_assign_save_submission` | **Partial** | M | Phase 1: harden UX |
| **Submit files** | Upload submission files | `upload.php` → `files_filemanager` | **Partial** (API; no Flutter picker) | M–L | **Phase 1 priority** |
| **Edit before deadline** | Update draft / resubmit rules | `save_submission` + Moodle window | **Partial** | M | Phase 1 |
| **View own submission** | See text/files already sent | `mod_assign_get_submission_status` | **Partial** | M | Phase 1 |
| **Submission status** | Draft / submitted / late / locked | `get_submission_status` | **Partial** | S–M | Phase 1 |
| **Submission receipt** | Confirmation after submit | `submit_for_grading` + status | **Missing** UX | S | Phase 1 |
| **View grade** | Released grade | status / `get_grades` / gradebook | **Partial** | M | Phase 2 |
| **View teacher feedback** | Comments | feedback plugins in status | **Partial** | M | Phase 2 |
| **Download feedback files** | Feedback file plugin | status + pluginfile | **Missing** | M | Phase 2 |
| **View submissions (teacher)** | List students’ work | `list_participants` + `get_submissions` | **Missing** | L | **Phase 2** |
| **Grade submission** | Numeric / scale grade | `mod_assign_save_grade` | **Partial** (API; no UI) | M–L | Phase 2 |
| **Feedback comments** | Text feedback | `save_grade` plugindata | **Partial** | M | Phase 2 |
| **Feedback files** | Attach feedback files | `upload.php` + feedback file plugin | **Missing** | M–L | Phase 2 |
| **Download student submission** | Open student files | submissions/status + pluginfile | **Missing** teacher UI | M | Phase 2 |

Effort: S = days, M ≈ 1 week, L = multi-week.

---

## Tier B — Useful (not Phase 1 blockers)

| Feature | What it does | Moodle WS | ESPACE | Effort | Recommendation |
|---------|--------------|-----------|--------|--------|----------------|
| Multiple attempts | Resubmit / new attempt | `copy_previous_attempt` | Missing UX | M | Phase 3 |
| Submission comments | Student comment plugin | `save_submission` | Missing | M | Phase 3 if needed |
| Bulk download | Zip all submissions | Often web-only | Missing | L | Defer / Moodle web |
| Grade categories | Authoring category | upsert `gradecat` | **Partial** | S | Improve later |
| Max upload size | Authoring | upsert | **Done** | — | Keep |
| Completion tracking | Activity completion | core completion | Out of scope | M | Separate track |
| Submission statement | Accept checkbox | `submit_for_grading` | Hardcoded `1` | S | Surface when required |
| Notifications | Due / graded | messaging / calendar | Not assign-specific | M | Phase 3 polish |

---

## Tier C — Advanced (exclude early)

Blind/anonymous marking, marking workflow/allocation, team/group submissions, rubrics/marking guides, PDF annotation, offline worksheets, grade history, outcomes/competencies, advanced grading plugins.

Prefer Moodle web until Tier A/B loop is solid.

---

## ESPACE gap analysis

### Strong (Sprint A)

- Teacher authoring create/edit (dates, submission types, grade type, intro, intro attachments)
- Auth = `moodle_mobile_app` + JWT
- Activity page shows intro + intro attachments
- Structural CM hide/show/delete via course editor APIs

### Thin / stub

| Area | Code today | Gap |
|------|------------|-----|
| Student online text | `assign_renderer` + `interactions.assign_save_submission` | Weak draft/submitted UX |
| Student file submit | FastAPI `draftitemid` | Flutter never uploads submission drafts |
| Status | `get_submission_status` | Not mapped to human statuses / own files |
| Teacher grade | `assign_save_grade` | No list UI, no feedback files |

### Missing

- Teacher submissions inbox
- Feedback file upload/download in app
- Submission receipt / polished student submission review
- Multiple attempts UX

### Architecture rule (unchanged)

```text
Flutter → FastAPI → mod_assign_* / upload.php → Moodle
         local_espace only for authoring upsert (done)
```

No parallel submission/grade stores. No auth redesign.

---

## Implementation roadmap

### Phase 1 — Student submission workflow (next)

**Goal:** Student can complete a normal assignment in ESPACE alone.

1. Reuse `upload.php` for **submission** drafts (same helper as intro).
2. Flutter: file picker → upload → `POST .../assign/submission` with `draftitemid` (+ online text).
3. Parse `get_submission_status`: can edit?, last modified, files, online text, due/late.
4. Submit for grading CTA + success receipt (status refresh).
5. Submission statement when Moodle requires it.

**Touch:** Flutter assign renderer + activity repository; thin FastAPI shaping if needed. **No plugin.**

### Phase 2 — Teacher grading workflow

**Goal:** Teacher grades the common case in ESPACE.

1. Participants + submissions (`list_participants`, `get_submissions`).
2. Open one student: files + text.
3. Grade + comment (`save_grade`).
4. Feedback files via `upload.php` + feedback file plugin.
5. Student side: grade + comments + feedback files.

**No plugin** unless a WS gap appears (unlikely for simple grade/comments/files).

### Phase 3 — Quality improvements

- Status chips (draft / submitted / graded / late / locked)
- Multiple attempts when configured
- Light notifications / calendar cues
- Submission statement polish
- Grade category picker (authoring)

---

## What not to do next

- Rubrics, blind marking, team submit, PDF annotate
- Auth redesign / `core_files_upload` / dual-token
- Expand `local_espace` for submit/grade — official WS cover Phase 1–2

---

## Success criteria

After Phase 1–2:

1. Teacher creates assignment with dates + file/text + intro PDF  
2. Student downloads intro, uploads work, submits, sees status  
3. Teacher opens inbox, grades with comment (± feedback file)  
4. Student sees grade and feedback  

Moodle web remains the escape hatch for Tier C.
