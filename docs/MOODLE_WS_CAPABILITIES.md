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

# Section management
# ✅ section_add / section_delete / section_hide / section_show / section_move_after
#    via core_courseformat_update_course
# ❌ section rename — no dedicated stable WS for section name/summary
#    TODO(local_espace): local_espace_update_section

# Module structural ops
# ✅ cm_hide / cm_show / cm_delete / cm_duplicate / cm_move via core_courseformat_update_course
# 🟡 core_courseformat_new_module — only for mods with FEATURE_QUICKCREATE
# ❌ Full create/edit of resource/page/url/folder/label/book content & settings
#    TODO(local_espace): local_espace_upsert_module
# ❌ Availability / restriction editor
#    TODO(local_espace): local_espace_set_availability
# ❌ Completion settings configuration
#    TODO(local_espace): local_espace_set_completion

# Participants / groups / enrolment / files / grades / calendar
# ✅ See services in app/services/{participants,groups,files,grades,calendar}.py

# ---------------------------------------------------------------------------
# Phase C – Student
# ---------------------------------------------------------------------------

# Learning browse/view — already covered by course contents + activity engine
# ✅ Assignment submit flow — mod_assign_save_submission / submit_for_grading
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
