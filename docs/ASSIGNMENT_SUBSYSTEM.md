# ESPACE Assignment Subsystem

**Blueprint** for the Assignment subsystem on Moodle **5.2**.  
This document is the permanent architecture reference for Assignments and the **reference pattern** for future academic modules (Quiz, Forum, etc.).

**Status:** Sprint A (teacher authoring) **implemented**. Later sprints B–G remain as planned.  
**Public surfaces:** FastAPI `/activities` + plugin `local_espace_upsert_module` (modname dispatch).  
**Code:** See handoff `docs/HANDOFF_ASSIGNMENT_SPRINT_A.md` for paths and deploy notes.

---

## Architectural principle (mandatory)

This principle overrides everything and matches the Section subsystem:

1. **Reuse Moodle whenever possible.**
2. **Never duplicate official Moodle functionality.**
3. **If Moodle already has an official Web Service or stable API, reuse it.**
4. **Improve only the user experience** — do not replace Moodle logic.
5. **Extend Moodle via `local_espace` only when there is a genuine capability gap.**

### Decision filter (every feature)

Before any design or code:

1. Does Moodle already provide this?
2. Can ESPACE simply reuse it (official WS / API)?
3. If not — is `local_espace` justified as a thin wrapper over Moodle internals?

If (1) and (2) are yes → FastAPI forwards; Flutter presents.  
If (3) is yes → plugin calls Moodle core APIs; ESPACE still does not own the data.

---

## Compatibility Promise

Permanent architectural rule for Assignments and all future academic modules:

- **Everything created inside ESPACE must remain fully editable inside Moodle.**
- **Everything created inside Moodle must remain fully editable inside ESPACE.**
- **ESPACE may improve workflows and UX but must never reduce Moodle capability.**
- **ESPACE must never create a parallel academic model when Moodle already provides one.**

Implications:

- No shadow assignment tables, grade stores, or submission stores in ESPACE.
- `local_espace` writes only through Moodle APIs (`assign_add_instance` / update, file APIs, grading APIs, etc.).
- Round-trip integrity is a release criterion: create in ESPACE → edit in Moodle → edit again in ESPACE (and the reverse).

---

## Data Ownership

Every important field has exactly one **system of record**. ESPACE may cache nothing durable for these entities.

| Feature / Field | Owner | Stored In | Notes |
|-----------------|-------|-----------|-------|
| Assignment name | Moodle | `course_modules` / `assign` | ESPACE read/write via WS or upsert gap |
| Description (`intro`) | Moodle | `assign.intro` | |
| Activity instructions (`activity`) | Moodle | `assign.activity` | |
| Intro / instruction attachments | Moodle | Moodle file areas (`mod_assign/introattachment`, activity attachments) | Optional; text-only / files-only / both allowed |
| Allow submissions from | Moodle | `assign.allowsubmissionsfromdate` | |
| Due date | Moodle | `assign.duedate` | |
| Cut-off date | Moodle | `assign.cutoffdate` | |
| Grade-by date | Moodle | `assign.gradingduedate` | Drives teacher calendar/timeline |
| Time limit | Moodle | `assign.timelimit` | Site must enable timed assignments |
| Submission types (online text / file / comments) | Moodle | Assign submission plugin config | |
| Maximum files / max size / accepted types | Moodle | `assignsubmission_file` config | |
| Online text word limit | Moodle | `assignsubmission_onlinetext` config | |
| Submission drafts / statement / attempts | Moodle | `assign.*` settings | |
| Team / group submission settings | Moodle | `assign.*` + groups/groupings | |
| Grade type / maximum grade / scale | Moodle | Assign + grade item | |
| Grade category | Moodle | Gradebook category link | |
| Grade to pass | Moodle | Grade item | |
| Rubric definition | Moodle | Advanced grading (`gradingform_rubric`) | |
| Marking guide definition | Moodle | Advanced grading (`gradingform_guide`) | |
| Simple / advanced grades | Moodle | `assign_grades` + gradebook | |
| Feedback comments / files | Moodle | Assign feedback plugins + files | |
| Workflow state | Moodle | User flags / grade workflow | |
| Blind marking / mappings | Moodle | `assign.blindmarking`, mappings tables | |
| Anonymous marking (partial release) | Moodle | `assign.markinganonymous` | |
| Lock / unlock / extension dates | Moodle | User flags / extensions | |
| Submission content (text + files) | Moodle | Assign submission plugins + file areas | |
| Completion state (user) | Moodle | Completion tables | |
| Completion **configuration** | Moodle | CM completion settings | Write may need `local_espace` |
| Restrict access configuration | Moodle | Availability JSON on CM | Write may need `local_espace` |
| Calendar events (due / grade-by) | Moodle | Calendar (derived from dates) | ESPACE reads via `core_calendar_*` |
| Notifications delivery | Moodle | Moodle notification/message subsystem | ESPACE must not reimplement |
| Permissions / capabilities | Moodle | Roles & capabilities | ESPACE propagates failures |
| AI suggestions (draft text) | **ESPACE** *(future)* | Ephemeral / optional ESPACE store only until teacher accepts | **Never** the graded record |
| AI summary | **ESPACE** *(future)* | Optional ESPACE analytics store | Must not replace Moodle intro/feedback |
| AI feedback draft | **ESPACE** *(future)* | Ephemeral until teacher saves via `mod_assign_save_grade` | Moodle owns published feedback |
| AI analytics | **ESPACE** *(future)* | ESPACE analytics | Derived; Moodle remains academic SoR |

**Owner legend**

| Owner | Meaning |
|-------|---------|
| **Moodle** | System of record; ESPACE reads/writes through Moodle |
| **ESPACE** | Only for future AI/assistive artefacts that are not academic gradebook data |
| **Shared** | Not used for durable academic fields — orchestration only |

---

## Domain Model

The Assignment subsystem is composed of independent educational domains. Each domain has a Moodle owner; ESPACE orchestrates and presents.

```
Assignment
    ↓
Resources
    ↓
Submission
    ↓
Grading
    ↓
Feedback
    ↓
Completion
    ↓
Availability
    ↓
Workflow
```

### Assignment

| | |
|--|--|
| **Responsibilities** | Activity identity, name, description, settings shell, course module lifecycle (create/edit/delete/visibility/move) |
| **Official Moodle owner** | `mod_assign` + course module APIs |
| **ESPACE responsibility** | Authoring UX; forward reads via `mod_assign_get_assignments`; create/update via `local_espace_upsert_module` (`modname=assign`) wrapping Moodle `add_moduleinfo` / `update_moduleinfo` |
| **Future extension points** | Import assistants (AI) that **propose** fields then persist only through Moodle |

### Resources

| | |
|--|--|
| **Responsibilities** | Optional teacher instruction files and activity attachments (PDF, Office, ZIP, media, etc.) |
| **Official Moodle owner** | Moodle file API / `mod_assign` file areas |
| **ESPACE responsibility** | Upload via `core_files_*` drafts; attach through upsert; display download links from Moodle |
| **Future extension points** | Voice / document import that produces Moodle-stored files |

### Submission

| | |
|--|--|
| **Responsibilities** | Student drafts, final submit, file/online text plugins, attempts, team submission |
| **Official Moodle owner** | `mod_assign` submission subsystem + plugins |
| **ESPACE responsibility** | Forward `save_submission`, `submit_for_grading`, `get_submission_status`, `copy_previous_attempt`; never store submissions in ESPACE |
| **Future extension points** | Student AI coach suggestions that the student may paste/edit before Moodle save |

### Grading

| | |
|--|--|
| **Responsibilities** | Points, scales, rubrics, marking guides, gradebook items/categories |
| **Official Moodle owner** | `mod_assign` grading + gradebook + advanced grading |
| **ESPACE responsibility** | Forward `save_grade` / `save_grades` / `submit_grading_form` / `get_grades`; read gradebook via `gradereport_*`; author definitions via official WS if adequate, else `local_espace` wrapping Moodle grading APIs |
| **Future extension points** | AI grading assistant → teacher confirms → Moodle save |

### Feedback

| | |
|--|--|
| **Responsibilities** | Comments, feedback files, (later) annotate PDF / offline worksheet |
| **Official Moodle owner** | Assign feedback plugins |
| **ESPACE responsibility** | Forward plugin data on grade save; file uploads via Moodle drafts |
| **Future extension points** | AI feedback drafts (ESPACE ephemeral) published only through Moodle |

### Completion

| | |
|--|--|
| **Responsibilities** | Completion rules and per-user completion state |
| **Official Moodle owner** | Core completion |
| **ESPACE responsibility** | Read/update status via `core_completion_*`; configure rules via `local_espace` if no official writer |
| **Future extension points** | Adaptive recommendations consuming completion signals (read-only from Moodle) |

### Availability

| | |
|--|--|
| **Responsibilities** | Allow-from / due / cut-off / time limit; restrict-access conditions |
| **Official Moodle owner** | Assign dates + availability API |
| **ESPACE responsibility** | Dates via assignment upsert; restrict-access via `local_espace_set_availability` when needed; calendar read via `core_calendar_*` |
| **Future extension points** | Smart scheduling suggestions (do not bypass Moodle dates) |

### Workflow

| | |
|--|--|
| **Responsibilities** | Marking workflow states, allocation, blind/anonymous marking, locks, extensions, flags |
| **Official Moodle owner** | `mod_assign` workflow / user flags |
| **ESPACE responsibility** | Forward `set_user_flags`, extensions, lock/unlock, revert, reveal identities |
| **Future extension points** | Marker load-balancing suggestions (still stored as Moodle allocations) |

---

## 1. Overview

### Architecture (same pattern as Sections)

```
Flutter (teacher + student UX)
        │
        ▼
ESPACE FastAPI (thin orchestration, JWT, normalisation)
        │
        ├──────────────► Official Moodle WS (preferred)
        │                  mod_assign_*, core_files_*,
        │                  core_grading_*, core_completion_*,
        │                  core_calendar_*, gradereport_*, …
        │
        └──────────────► local_espace (only genuine WS gaps)
                           wraps Moodle internals; never owns data
                                │
                                ▼
                         Moodle Core APIs
                                │
                                ▼
                         Moodle Database (system of record)
```

### Current ESPACE baseline

| Layer | What exists today |
|-------|-------------------|
| FastAPI | Activities authoring API (`/activities`) for assign; activity engine reads; student save/submit partial; `core_files_*` via `/files/draft-itemid` + `/files/upload`; gradebook read. |
| Flutter | Activity Registry + Picker + Assignment Editor (Sprint A). `AssignRenderer` student online text (Sprint B expands). |
| local_espace | `local_espace_upsert_module` + `AssignmentService::upsert` (Sprint A). Section WS complete. |

### Subsystem diagram

```
┌─────────────────────────────────────────────────────────────┐
│                        Flutter                              │
│  Student: view · draft · submit · feedback                  │
│  Teacher: author · inbox · grade · workflow · settings      │
└────────────────────────────┬────────────────────────────────┘
                             │ JWT REST
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                     ESPACE FastAPI                          │
│  Forward official WS · orchestrate local_espace gaps only   │
└───────────────┬─────────────────────────────┬───────────────┘
                │ Official WS                 │ Gap only
                ▼                             ▼
┌──────────────────────────┐    ┌─────────────────────────────┐
│ Moodle mod_assign + core │    │ local_espace → Moodle APIs  │
└─────────────┬────────────┘    └──────────────┬──────────────┘
              └────────────┬───────────────────┘
                           ▼
                    Moodle Database
```

---

## 2. Everything teachers can do

| Capability | Moodle behaviour (ESPACE reuses) |
|------------|----------------------------------|
| Create / edit / delete assignment | Full mod settings + CM lifecycle |
| Description, instructions, optional resources | Text and/or Moodle-stored files |
| Availability dates + optional time limit | Allow from / due / cut-off / grade-by |
| Submission types & settings | Online text, files, comments, drafts, attempts |
| Group / team submissions | Team submit, members, grouping |
| Notifications | Graders / late / student grade notify |
| Grade setup | None / point / scale; category; pass mark |
| Advanced grading | Rubric, marking guide |
| Feedback types | Comments, files, (later) annotate PDF, offline worksheet |
| Grade submissions | Individual / quick / bulk |
| Blind / anonymous marking | Hide identities; reveal; partial release |
| Lock / unlock / revert / extensions | Per-user and bulk |
| Completion & restrict access | Standard activity settings |
| Gradebook / calendar | Automatic Moodle integration |

---

## 3. Everything students can do

| Capability | Moodle behaviour (ESPACE reuses) |
|------------|----------------------------------|
| View assignment | Description, instructions, resources, dates, status |
| Submit online text and/or files | Per enabled plugins |
| Save draft / final submit | Per submission settings |
| Accept submission statement | When required |
| Group collaboration | Shared team submission when enabled |
| View feedback & grades | When Moodle releases them |
| Additional attempts | Manual / until pass |
| Calendar / notifications | Moodle-native |
| Completion | View / submit / grade conditions |

---

## 4. Complete feature inventory

**FastAPI column:** Forward = official WS only · local_espace = justified gap wrapping Moodle internals.

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| View assignment config | Yes | `mod_assign_get_assignments` | Forward | No | Detail screens |
| Submission status | Yes | `mod_assign_get_submission_status` | Forward (exists) | No | Status panel |
| Save draft submission | Yes | `mod_assign_save_submission` | Forward (exists) | No | Text + files |
| Submit for grading | Yes | `mod_assign_submit_for_grading` | Forward (exists) | No | Submit CTA |
| Copy previous attempt | Yes | `mod_assign_copy_previous_attempt` | Forward | No | Resubmit flows |
| List participants | Yes | `list_participants`, `get_participant` | Forward | No | Grading inbox |
| Get submissions | Yes | `mod_assign_get_submissions` | Forward | No | Teacher inbox |
| Get grades | Yes | `mod_assign_get_grades` | Forward | No | Reports |
| Save grade + feedback | Yes | `mod_assign_save_grade` | Forward (partial today) | No | Grader |
| Bulk grades | Yes | `mod_assign_save_grades` | Forward | No | Table |
| Submit grading form | Yes | `mod_assign_submit_grading_form` | Forward | No | Advanced grader |
| Lock / unlock | Yes | `lock_submissions`, `unlock_submissions` | Forward | No | Actions |
| Revert to draft | Yes | `revert_submissions_to_draft` | Forward | No | Actions |
| User flags | Yes | `get_user_flags`, `set_user_flags` | Forward | No | Workflow UI |
| Extensions | Yes | `save_user_extensions` | Forward | No | Dialog |
| Blind mappings / reveal | Yes | `get_user_mappings`, `reveal_identities` | Forward | No | Blind UX |
| View events | Yes | `view_assign`, `view_grading_table`, `view_submission_status` | Forward (optional) | No | Analytics hooks |
| CM hide/show/delete/move | Yes | `core_courseformat_update_course` | Forward (exists) | No | Course editor |
| Full create/edit settings + plugins + resources | Yes (web UI) | **No dedicated upsert WS** | Orchestrate `/activities` | **`local_espace_upsert_module` (assign)** | Authoring (Sprint A ✅) |
| Quick CM shell | Partial | `core_courseformat_new_module` if FEATURE_QUICKCREATE | Legacy `POST /modules` (not Assign path) | Else upsert creates | Avoid for Assign |
| Draft file upload | Yes | `core_files_*` | Forward (exists) | No | Pickers |
| Gradebook read | Yes | `gradereport_*` | Forward (exists) | No | Grades |
| Rubric/guide **read** | Yes | `core_grading_get_definitions` (+ instances) | Forward | No | Display |
| Rubric/guide **author** | Yes (web UI) | Incomplete for full authoring | — | **Likely yes** | Editors (Sprint D) |
| Completion status | Yes | `core_completion_*` | Forward (exists) | No | Badges |
| Completion **config** | Yes (web UI) | No stable full writer | — | **Yes (shared)** | Settings (Sprint F) |
| Restrict access **config** | Yes (web UI) | No stable full writer | — | **Yes (shared)** | Settings (Sprint F) |
| Calendar views | Yes | `core_calendar_*` | Forward (exists) | No | Timeline |
| Notifications delivery | Yes | Moodle notifications | Do not reimplement | No | Prefs only |
| Offline worksheet / zip export | Yes | Web-oriented | — | Optional later | Teacher tools |

---

## 5. Submission types

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Online text | Yes | `save_submission` plugindata | Forward | Config on upsert | Editor |
| File submissions | Yes | Draft itemid → `files_filemanager` | Forward | Config on upsert | Multi-file picker |
| Submission comments | Yes | Plugin data in status/submissions | Forward | Enable on upsert | Comments |
| Max files / size / types | Yes | Plugin config | Read | Write on upsert | Enforce + Moodle |

Teacher instruction resources are **Resources domain**, not submission types. Optional: text only, files only, or both.

---

## 6. Availability

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Allow from / due / cut-off / grade-by | Yes | Fields on get_assignments | Forward | Write via upsert | Date UX |
| Time limit | Yes (admin-enabled) | Field | Forward | Upsert | Timer |
| Per-user extension | Yes | `save_user_extensions` | Forward | No | Dialog |
| Restrict access | Yes | Partial read | — | **Yes (shared)** | Builder |

---

## 7. Grading

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Grade type / max / scale | Yes | Assignment + grade item | Forward | Upsert config | Settings |
| Simple numeric / scale grade | Yes | `save_grade` / `save_grades` | Forward | No | Grader |
| Advanced grading payload | Yes | `advancedgradingdata` / `submit_grading_form` | Forward | No | Rubric/guide UI |
| Hide grader | Yes | Setting | Forward | Upsert | Settings |

---

## 8. Gradebook integration

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Auto grade item | Yes | Created with activity | Moodle-owned | Upsert may set category | — |
| Read grades | Yes | `gradereport_*` | Forward | No | Screens |
| Grade category | Yes | On activity form | Limited list WS | Part of upsert | Dropdown |
| Hidden until released | Yes | Workflow / gradebook | Moodle-owned | No | Respect status |

ESPACE never stores parallel grades.

---

## 9. Rubrics

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Grade with rubric | Yes | Official save APIs | Forward | No | Scorer |
| Read definition | Yes | `core_grading_get_definitions` | Forward | No | Display |
| Author definition | Yes (web UI) | Incomplete | — | **Likely yes** | Editor |

---

## 10. Marking guides

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Grade with guide | Yes | Official save APIs | Forward | No | Scorer |
| Read definition | Yes | `core_grading_get_definitions` | Forward | No | Display |
| Author definition | Yes (web UI) | Incomplete | — | **Likely yes** | Editor |

---

## 11. Grade categories

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| List categories | Yes | `core_grades_*` where available | Forward | Helper if missing | Dropdown |
| Attach assign to category | Yes | On create/update | — | Part of upsert | Settings |

---

## 12. Feedback

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Feedback comments | Yes | `save_grade` plugindata | Forward | No | Comment box |
| Feedback files | Yes | Draft uploads + feedback plugin | Forward | No | Upload |
| Comment inline | Yes | Plugin | Forward where exposed | No | Inline UX |
| Annotate PDF | Yes | Limited REST | Prefer feedback files first | Later if needed | Later |
| Offline worksheet | Yes | Web-oriented | — | Optional | Later |
| Notify students | Yes | Moodle + grade flags | Forward flags | No | Toggle |

---

## 13. Completion

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| User completion status | Yes | `core_completion_*` | Forward | No | UI |
| `completionsubmit` & rules config | Yes | Readable; write incomplete | — | **Yes (shared)** | Settings |
| Manual completion | Yes | Manual update WS | Forward | No | Checkbox |

---

## 14. Permissions

Examples: `mod/assign:view`, `submit`, `grade`, `exportownsubmission`, `revealidentities`, `manageallocations`, `viewblinddetails`, `moodle/course:manageactivities`.

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Enforce capabilities | Yes | Built into every WS | Propagate Moodle errors | Same | Hide unauthorized actions |

ESPACE does not invent a second ACL.

---

## 15. Notifications

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Graders / late / student notify settings | Yes | Assignment settings | Read; write via upsert | Upsert | Toggles |
| Delivery of due/overdue/grade messages | Yes | Moodle notification subsystem | **Reuse only** — never duplicate | No | Prefs / inbox |

---

## 16. Calendar

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Due / grade-by events | Yes (from Moodle dates) | Auto + `core_calendar_*` views | Forward reads | Dates via upsert | Timeline |

---

## 17. Bulk operations

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Bulk lock / unlock / revert / grades / extensions / workflow | Yes | Corresponding `mod_assign_*` | Forward | No | Multi-select |
| Download all submissions | Yes | Web-oriented | — | Optional helper | Export |

---

## 18. Offline grading

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Offline grading worksheet | Yes | Not clean first-class REST | — | Optional | Later |
| Feedback zip re-upload | Yes | Web-oriented | — | Optional | Later |

Deliver only after online grading parity (Sprints C–E).

---

## 19. Workflow

| Feature | Moodle already supports | Official WS/API | FastAPI | local_espace | Flutter UI |
|---------|-------------------------|-----------------|---------|--------------|------------|
| Marking workflow states | Yes | `workflowstate` / flags | Forward | Enable via upsert | Dropdown |
| Marking allocation | Yes | Flags | Forward | Enable via upsert | Allocate |
| Blind marking | Yes | Settings + mappings + reveal | Forward | Upsert setting | Participant UX |
| Marking anonymous | Yes | Setting | Forward | Upsert | Settings |
| Attempt reopen | Yes | Settings + grade allow-another | Forward | Upsert | Settings |

States (Moodle): notmarked → inmarking → markingcompleted → inreview → readyforrelease → released.

---

## 20. Extension points (`local_espace`)

Register **only** when the decision filter reaches justified gap. Plugin must call Moodle internals — never invent academic semantics.

| Extension | Purpose | Why justified |
|-----------|---------|---------------|
| `local_espace_upsert_module` | Create/update activity settings by `modname`. Sprint A: `assign` → AssignmentService::upsert (plugins, intro, attachments via Moodle file APIs) | No official create/update assign WS |
| `local_espace_upsert_grading_definition` | Create/update rubric or marking guide for a CM | Authoring WS incomplete |
| `local_espace_set_completion` | Write activity completion rules | Shared platform gap |
| `local_espace_set_availability` | Write restrict-access conditions | Shared platform gap |
| `local_espace_assign_offline_worksheet` *(optional)* | Worksheet export/import using Moodle APIs | Convenience over web-only flow |
| `local_espace_assign_export_submissions` *(optional)* | Zip export | Convenience |

**Do not register** a separate public `local_espace_upsert_assign` — assign logic stays internal to the module dispatcher.

**Never implement in local_espace:** save submission, submit for grading, save grade, lock/unlock, extensions, reveal identities — official `mod_assign_*` already exist.

---

## Assignment creation — field inventory

ESPACE authoring must expose the same capabilities Moodle’s form does (Sprint A+). All fields remain Moodle-owned.

### General

| Field | Notes |
|-------|-------|
| Name | Required |
| Description (`intro`) + format | Optional rich text |
| Display description on course page | CM flag |
| Additional files (`introattachments`) | **Optional** Moodle files |
| Activity instructions + format | Optional |
| Activity attachments | **Optional** Moodle files |

Valid assignments: **text only**, **attachments only**, or **both**.

Resource types via Moodle files (not a new store): PDF, Word, PowerPoint, ZIP, images, video, audio, and other types allowed by site/course limits.

### Availability

`allowsubmissionsfromdate`, `duedate`, `cutoffdate`, `gradingduedate`, `timelimit` (if enabled).

### Submission types / plugin config

Online text (+ word limit), file submissions (max files/size/types), submission comments.

### Submission settings

`submissiondrafts`, `requiresubmissionstatement`, `attemptreopenmethod`, `maxattempts`.

### Group submission

`teamsubmission`, `requireallteammemberssubmit`, `teamsubmissiongroupingid`, related site defaults.

### Notifications

`sendnotifications`, `sendlatenotifications`, `sendstudentnotifications`.

### Grade

Grade type (none/point/scale), max grade / scale, grade category, grade to pass, grading method (simple/rubric/guide), `blindmarking`, `hidegrader`, `markingworkflow`, `markingallocation`, `markinganonymous`.

### Common module / other

Visibility, ID number, group mode; completion rules; restrict access; tags/competencies optional later.

---

## Official Moodle Web Services to reuse

### mod_assign (Moodle 5.2)

`get_assignments`, `get_submission_status`, `get_submissions`, `get_grades`, `save_submission`, `submit_for_grading`, `copy_previous_attempt`, `save_grade`, `save_grades`, `submit_grading_form`, `list_participants`, `get_participant`, `lock_submissions`, `unlock_submissions`, `revert_submissions_to_draft`, `get_user_flags`, `set_user_flags`, `save_user_extensions`, `get_user_mappings`, `reveal_identities`, `view_assign`, `view_grading_table`, `view_submission_status`.

### Supporting

- Files: `core_files_get_unused_draft_itemid`, `core_files_upload`
- Grading: `core_grading_get_definitions`, `core_grading_get_gradingform_instances`
- Gradebook: `gradereport_user_*`, `gradereport_overview_*`, `core_grades_*` as available
- Completion / calendar / groups / structure: `core_completion_*`, `core_calendar_*`, `core_group_*`, `core_course_get_contents`, `core_courseformat_update_course`, optional `core_courseformat_new_module`

Production Moodle external service must enable the full required `mod_assign_*` set (not only the subset used today).

---

## Features requiring local_espace

| Gap | Sprint | Justification |
|-----|--------|---------------|
| Full assignment create/update | **A ✅** | No official upsert WS → `local_espace_upsert_module` |
| Rubric / marking guide authoring | **D** | If core WS cannot fully author definitions |
| Completion configuration | **F** | Shared gap |
| Restrict access configuration | **F** | Shared gap |
| Offline worksheet / zip export | After E | Optional convenience |

All other operational features → official WS only.

---

## Implementation order (sprints)

Replaces prior phase numbering. Each sprint must preserve the Compatibility Promise.

### Sprint A — Teacher Assignment Authoring ✅

Create · Edit · Delete · Availability dates · Resources (optional attachments) · Submission type config · Grade config · Visibility

- Structural CM ops: official `core_courseformat_update_course`.
- Authoring write: **`local_espace_upsert_module`** (`modname=assign`).
- FastAPI: **`/courses/.../activities`** (+ `/files/draft-itemid`, `/files/upload`).
- Flutter: Activity Registry + Picker + Assignment Editor.
- Round-trip edit with Moodle web UI remains an exit / deploy verification criterion.

### Sprint B — Student Submission

Files · Online text · Submission status · Resubmission / attempts

- Official `mod_assign_save_submission`, `submit_for_grading`, `get_submission_status`, `copy_previous_attempt`.
- Files via Moodle drafts only.

### Sprint C — Teacher Grading

Simple grading · Feedback comments/files · Gradebook integration

- Official `list_participants`, `get_submissions`, `save_grade` / `save_grades`, `get_grades`, `gradereport_*`.

### Sprint D — Advanced Grading

Rubrics · Marking guides · Scales · Categories

- Grade **using** official advanced grading APIs first.
- Author definitions via official WS if sufficient; else `local_espace_upsert_grading_definition`.

### Sprint E — Workflow

Blind marking · Anonymous grading · Extensions · Flags · Locking · Revert · Reveal identities

- Official `mod_assign_*` flags/extensions/lock/unlock/mappings/reveal only.

### Sprint F — Completion, Availability, Notifications, Calendar

Completion config · Restrict access · Notification settings · Calendar presentation

- Dates already Moodle-owned (Sprint A).
- Completion/availability writers via shared `local_espace` gaps if needed.
- Notifications: configure settings; **delivery stays Moodle**.
- Calendar: read Moodle events.

### Sprint G — AI (future only)

See **ESPACE Enhancements** below. No Sprint G work until A–F native parity for touched surfaces.

---

## ESPACE Enhancements (Future Roadmap)

**Not Sprint A–F.** Do not design implementation here. Extension points only.  
These improve UX or add assists; they **must not** replace Moodle-owned academic data. Teacher (or student) confirmation + Moodle persistence required.

| Enhancement | Idea | Moodle relationship |
|-------------|------|---------------------|
| **Assignment Import** | Import from PDF / DOCX / PowerPoint → auto-propose Moodle assignment | Creates Moodle activity via upsert; files stored in Moodle |
| **AI Question Extraction** | Extract tasks/questions from documents into instructions | Writes Moodle intro/activity text only after confirm |
| **AI Rubric Generator** | Propose rubric criteria | Persists via Moodle grading definition APIs / local_espace wrapper |
| **AI Feedback Suggestions** | Draft feedback comments | Ephemeral until saved through `mod_assign_save_grade` |
| **AI Grading Assistant** | Suggest scores / rubric levels | Teacher submits final grade via official WS |
| **Adaptive Recommendations** | Suggest next activities from completion/grades | Read-only Moodle signals; no parallel gradebook |
| **Voice Assignment Creation** | Dictate assignment fields | Same as authoring → Moodle upsert |
| **Student AI Coach** | Study help against assignment brief | Must not submit on behalf of student without explicit action |

Separate from Moodle-native functionality in product language, QA, and roadmaps.

---

## Risks and open decisions

1. **Upsert shape (decided):** Public `local_espace_upsert_module` + internal `AssignmentService`; FastAPI `/activities` with `modname`.  
2. **Quick create:** Abandoned for Assignment authoring; optional legacy `POST /modules` remains for other experiments only.  
3. **Annotate PDF:** Defer; feedback files first.  
4. **WS enablement:** Audit OCI Moodle external service for full `mod_assign_*` coverage before Sprint B/C.  
5. **No ESPACE grade/submission store:** Non-negotiable.

---

## Consistency checklist

- [x] Moodle is system of record for all academic assignment data  
- [x] ESPACE does not replace Moodle logic — only UX and orchestration  
- [x] Every feature answers: Moodle support? Reuse WS? Else justified local_espace?  
- [x] Compatibility Promise is permanent  
- [x] Data Ownership table covers core fields + future AI artefacts  
- [x] Domain model separates responsibilities without parallel models  
- [x] Sprint order is A (author) → B (submit) → C (grade) → D (advanced) → E (workflow) → F (completion/availability/notifications/calendar) → G (AI)  
- [x] AI clearly future-only  

---

## Approval / next step

Sprint A teacher authoring is implemented end-to-end.

**Next:** Deploy/verify on Moodle 5.2.1+, then Sprint B (student submission) only after explicit go-ahead.
