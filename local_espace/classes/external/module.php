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
 * External web service API for module upsert (Activity architecture).
 *
 * Thin adapter: validate params → ModuleService → structured return.
 * Public entry: local_espace_upsert_module (dispatch by modname).
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_espace\output\ApiResponse;
use local_espace\service\ModuleService;

/**
 * Module external functions for ESPACE FastAPI.
 */
class module extends external_api {

    /**
     * Parameters for local_espace_upsert_module.
     *
     * @return external_function_parameters
     */
    public static function upsert_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'modname' => new external_value(PARAM_PLUGIN, 'Moodle module plugin name (e.g. assign)'),
            'sectionid' => new external_value(
                PARAM_INT,
                'Section id (required for create; ignored on update)',
                VALUE_DEFAULT,
                0
            ),
            'cmid' => new external_value(
                PARAM_INT,
                'Course module id (0 = create; >0 = update)',
                VALUE_DEFAULT,
                0
            ),
            'settings' => self::settings_structure(),
        ]);
    }

    /**
     * Shared settings structure for Sprint A assign (other modnames later).
     *
     * @return external_single_structure
     */
    private static function settings_structure(): external_single_structure {
        return new external_single_structure([
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'intro' => new external_value(PARAM_RAW, 'Description HTML', VALUE_DEFAULT, ''),
            'introformat' => new external_value(PARAM_INT, 'Intro format', VALUE_DEFAULT, FORMAT_HTML),
            'introattachments' => new external_value(
                PARAM_INT,
                'Draft itemid for intro attachments (optional)',
                VALUE_OPTIONAL
            ),
            'allowsubmissionsfromdate' => new external_value(
                PARAM_INT,
                'Allow submissions from (unix)',
                VALUE_OPTIONAL
            ),
            'duedate' => new external_value(PARAM_INT, 'Due date (unix)', VALUE_OPTIONAL),
            'cutoffdate' => new external_value(PARAM_INT, 'Cut-off date (unix)', VALUE_OPTIONAL),
            'gradingduedate' => new external_value(PARAM_INT, 'Remind me to grade by (unix)', VALUE_OPTIONAL),
            'onlinetext_enabled' => new external_value(
                PARAM_INT,
                'Enable online text submission (0/1)',
                VALUE_OPTIONAL
            ),
            'file_enabled' => new external_value(PARAM_INT, 'Enable file submission (0/1)', VALUE_OPTIONAL),
            'maxfiles' => new external_value(PARAM_INT, 'Max uploaded files', VALUE_OPTIONAL),
            'maxsizebytes' => new external_value(
                PARAM_INT,
                'Max upload size in bytes (0 = course/site default)',
                VALUE_OPTIONAL
            ),
            'grade_type' => new external_value(PARAM_ALPHA, 'Grade type: none|point|scale', VALUE_OPTIONAL),
            'grade' => new external_value(PARAM_FLOAT, 'Maximum grade (point type)', VALUE_OPTIONAL),
            'scaleid' => new external_value(PARAM_INT, 'Scale id (scale type)', VALUE_OPTIONAL),
            'gradecat' => new external_value(PARAM_INT, 'Grade category id', VALUE_OPTIONAL),
            'visible' => new external_value(PARAM_INT, 'CM visibility (1/0)', VALUE_OPTIONAL),
        ], 'Module settings payload');
    }

    /**
     * Create or update a module (Sprint A: assign only).
     *
     * @param int $courseid
     * @param string $modname
     * @param int $sectionid
     * @param int $cmid
     * @param array $settings
     * @return array
     */
    public static function upsert(
        int $courseid,
        string $modname,
        int $sectionid = 0,
        int $cmid = 0,
        ?array $settings = null
    ): array {
        [
            'courseid' => $courseid,
            'modname' => $modname,
            'sectionid' => $sectionid,
            'cmid' => $cmid,
            'settings' => $settings,
        ] = self::validate_parameters(self::upsert_parameters(), [
            'courseid' => $courseid,
            'modname' => $modname,
            'sectionid' => $sectionid,
            'cmid' => $cmid,
            'settings' => $settings ?? [],
        ]);

        self::validate_context(\context_course::instance($courseid));

        // Drop null optional keys so validators treat them as absent.
        $clean = [];
        foreach ($settings as $key => $value) {
            if ($value !== null) {
                $clean[$key] = $value;
            }
        }

        return (new ModuleService())->upsert($courseid, $modname, $sectionid, $cmid, $clean);
    }

    /**
     * @return external_single_structure
     */
    public static function upsert_returns(): external_single_structure {
        return ApiResponse::external_success_structure(new external_single_structure([
            'cm' => ApiResponse::cm_structure(),
            'modname' => new external_value(PARAM_PLUGIN, 'Module plugin name'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]));
    }
}
