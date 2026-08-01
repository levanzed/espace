<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External service definitions for local_espace.
 *
 * $functions: only local_espace-owned WS (official WS gaps).
 * $services['ESPACE']['functions']: living allowlist of every WS FastAPI may call
 * for shipped features. Grow this list when new features add Moodle call() targets.
 * See docs/ESPACE_WS_ALLOWLIST.md.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_espace_create_section' => [
        'classname'   => 'local_espace\external\section',
        'methodname'  => 'create',
        'description' => 'Create a course section (with optional name/summary).',
        'type'        => 'write',
        'capabilities'=> 'local/espace:managesections',
        'ajax'        => false,
    ],

    'local_espace_rename_section' => [
        'classname'   => 'local_espace\external\section',
        'methodname'  => 'rename',
        'description' => 'Rename a course section and/or update its summary. Fills official WS gap.',
        'type'        => 'write',
        'capabilities'=> 'local/espace:managesections',
        'ajax'        => false,
    ],

    'local_espace_move_section' => [
        'classname'   => 'local_espace\external\section',
        'methodname'  => 'move',
        'description' => 'Move a course section to a destination section number.',
        'type'        => 'write',
        'capabilities'=> 'local/espace:managesections,moodle/course:movesections',
        'ajax'        => false,
    ],

    'local_espace_hide_section' => [
        'classname'   => 'local_espace\external\section',
        'methodname'  => 'hide',
        'description' => 'Hide a course section.',
        'type'        => 'write',
        'capabilities'=> 'local/espace:managesections,moodle/course:sectionvisibility',
        'ajax'        => false,
    ],

    'local_espace_show_section' => [
        'classname'   => 'local_espace\external\section',
        'methodname'  => 'show',
        'description' => 'Show a course section.',
        'type'        => 'write',
        'capabilities'=> 'local/espace:managesections,moodle/course:sectionvisibility',
        'ajax'        => false,
    ],

    'local_espace_delete_section' => [
        'classname'   => 'local_espace\external\section',
        'methodname'  => 'delete',
        'description' => 'Delete a course section.',
        'type'        => 'write',
        'capabilities'=> 'local/espace:managesections',
        'ajax'        => false,
    ],

    'local_espace_get_section' => [
        'classname'   => 'local_espace\external\section',
        'methodname'  => 'get',
        'description' => 'Get a single course section by id.',
        'type'        => 'read',
        'capabilities'=> 'local/espace:managesections',
        'ajax'        => false,
    ],

    'local_espace_list_sections' => [
        'classname'   => 'local_espace\external\section',
        'methodname'  => 'get_list',
        'description' => 'List all sections in a course.',
        'type'        => 'read',
        'capabilities'=> 'local/espace:managesections',
        'ajax'        => false,
    ],

    'local_espace_upsert_module' => [
        'classname'   => 'local_espace\external\module',
        'methodname'  => 'upsert',
        'description' => 'Create or update a course module (Sprint A: modname=assign). Fills official WS gap for full activity settings.',
        'type'        => 'write',
        'capabilities'=> 'local/espace:managemodules,moodle/course:manageactivities',
        'ajax'        => false,
    ],
];

$services = [
    'ESPACE' => [
        // Single supported external service for the ESPACE app (shortname=espace).
        // Official core_*/mod_* names may appear here; do not re-declare them in $functions.
        // Living allowlist — add functions when FastAPI gains new call() targets.
        'functions' => [
            // Authentication / site
            'core_webservice_get_site_info',

            // Course browse (required to reach sections & activities)
            'core_enrol_get_users_courses',
            'core_course_get_contents',
            'core_course_get_user_administration_options',
            'core_course_get_user_navigation_options',

            // Sections (local_espace)
            'local_espace_create_section',
            'local_espace_rename_section',
            'local_espace_move_section',
            'local_espace_hide_section',
            'local_espace_show_section',
            'local_espace_delete_section',
            'local_espace_get_section',
            'local_espace_list_sections',

            // Activity structural ops (hide/show/delete/move cm)
            'core_courseformat_update_course',

            // Assignment Sprint A / Activities authoring
            'local_espace_upsert_module',
            'mod_assign_get_assignments',

            // File drafts / upload (Assignment attachments)
            'core_files_get_unused_draft_itemid',
            'core_files_upload',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'espace',
        'downloadfiles' => 1,
        'uploadfiles' => 1,
    ],
];
