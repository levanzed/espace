"""ESPACE ↔ Moodle Web Service capability matrix.

Only official Moodle Web Services are used. Where Moodle does not expose a
service, ESPACE must not invent one — instead leave a clean extension point
for a future `local_espace` Moodle plugin.

Legend:
  ✅ Implemented in this sprint via official WS
  🟡 Partial (official WS exists but limited)
  ❌ Not available via official WS → TODO(local_espace)
"""

# ---------------------------------------------------------------------------
# Phase B – Teacher
# ---------------------------------------------------------------------------

# Course management
# ✅ core_course_create_courses
# ✅ core_course_update_courses
# ✅ core_course_delete_courses
# ✅ core_course_duplicate_course
# 🟡 core_backup_* async helpers only (no full archive UX) → TODO(local_espace) for guided backup/restore UI

# Section ops (plugin: local/espace) — COMPLETE
# ✅ local_espace_create_section  ← POST /courses/{id}/sections action=section_add
#    (optional name, summary, summaryformat → Moodle course_sections)
# ✅ local_espace_hide_section    ← action=section_hide
# ✅ local_espace_show_section    ← action=section_show
# ✅ local_espace_rename_section  ← POST /courses/{id}/sections/{section_id}/rename
#    (name and/or summary + summaryformat)
# ✅ local_espace_delete_section  ← action=section_delete
# ✅ local_espace_move_section    ← action=section_move | section_move_after
# ✅ local_espace_get_section     ← used internally for move destination resolution
# Course browse still uses core_course_get_contents (sections + modules + summary)

# Module structural ops
# ✅ cm_hide / cm_show / cm_delete / cm_duplicate / cm_move via core_courseformat_update_course
# 🟡 core_courseformat_new_module — only for mods with FEATURE_QUICKCREATE
# ✅ local_espace_upsert_module — Assignment Sprint A (modname=assign)
#    ← POST /courses/{id}/sections/{section_id}/activities
#    ← PUT  /courses/{id}/activities/{cmid}
#    ← GET  /courses/{id}/activities/{cmid} (read via mod_assign_get_assignments)
# ❌ Full create/edit of quiz/page/url/folder/label/book content & settings
#    TODO(local_espace): local_espace_upsert_module branches for other modnames
# ❌ Availability / restriction editor
#    TODO(local_espace): local_espace_set_availability
# ❌ Completion settings configuration
#    TODO(local_espace): local_espace_set_completion

# ✅ core_files_get_unused_draft_itemid ← GET /files/draft-itemid (on Mobile)
# ✅ Draft upload ← POST /files/upload → Moodle /webservice/upload.php (Mobile App pattern)
# ⚠️ core_files_upload — NOT on moodle_mobile_app; do not use for ESPACE.
#
# Auth: login/token.php service=moodle_mobile_app (per-user token → JWT).
# Deploy: docs/DEPLOYMENT.md
# Post–Sprint A roadmap: docs/ASSIGNMENT_ROADMAP.md (Phase 1 = B1, Phase 2 = B2)
# Sprint B implementation: docs/HANDOFF_ASSIGNMENT_SPRINT_B.md

# ---------------------------------------------------------------------------
# Assignment Sprint B — Student submission (B1) / Teacher grading (B2)
# ---------------------------------------------------------------------------
# Official WS only — no local_espace for submit/grade.
# Handoff: docs/HANDOFF_ASSIGNMENT_SPRINT_B.md
#
# B1 — Student (target)
# 🟡 mod_assign_get_submission_status ← GET /activity/{cmid}/assign/status
#    (+ embedded in GET /activity/{cmid}; plugin fileurls need token — B1 backend)
# 🟡 mod_assign_save_submission     ← POST /activity/{cmid}/assign/submission
# 🟡 mod_assign_submit_for_grading ← POST /activity/{cmid}/assign/submit
# ✅ upload.php                     ← POST /files/upload
# ✅ core_files_get_unused_draft_itemid ← GET /files/draft-itemid
#
# B2 — Teacher (target)
# ❌ mod_assign_list_participants   ← not exposed yet
# ❌ mod_assign_save_grade          ← interactions only; no route yet
# 🟡 mod_assign_get_submission_status (userid) ← teacher view student — B2
# Optional: mod_assign_get_submissions, mod_assign_get_grades
#
# Out of scope Sprint B: rubrics, blind marking, workflow, teams, PDF annotate

# Participants / groups / enrolment / grades / calendar
# ✅ See services in app/services/{participants,academic,grades}.py

# ---------------------------------------------------------------------------
# Phase C – Student
# ---------------------------------------------------------------------------

# Learning browse/view — already covered by course contents + activity engine
# 🟡 Assignment submit flow — mod_assign_save_submission / submit_for_grading
#    (FastAPI partial; Flutter Phase 1: file submit + status UX — ASSIGNMENT_ROADMAP.md)
# ✅ Forum discuss/reply/edit/delete — mod_forum_*
# ✅ Quiz attempt flow — mod_quiz_start/save/process/review
# ✅ Choice — mod_choice_*
# ✅ Feedback / Lesson / Glossary / Wiki / Data / SCORM / H5P — student-facing WS where available
# ✅ Grades — gradereport_user_get_grade_items / grades_table
# ✅ Completion — core_completion_*
# ✅ Calendar — core_calendar_*
# ✅ Messages — core_message_* (conversations + send)

# ---------------------------------------------------------------------------
# Service registration note
# ---------------------------------------------------------------------------
# Some Moodle 5.x courseformat functions are flagged ajax=true. They are still
# official external functions and work over REST when included in the external
# service used by ESPACE. Site admins must enable them on the Moodle service.
