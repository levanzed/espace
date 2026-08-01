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
 * External web service API for course sections.
 *
 * Thin adapter: validate params → service → structured return.
 * No Moodle business logic lives here.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_espace\output\ApiResponse;
use local_espace\service\SectionService;

/**
 * Section external functions for ESPACE FastAPI.
 */
class section extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function create_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'position' => new external_value(
                PARAM_INT,
                'Insert position (0 = append)',
                VALUE_DEFAULT,
                0
            ),
            'name' => new external_value(PARAM_TEXT, 'Optional section name', VALUE_DEFAULT, null),
            'summary' => new external_value(PARAM_RAW, 'Optional section summary HTML', VALUE_DEFAULT, null),
            'summaryformat' => new external_value(PARAM_INT, 'Summary format', VALUE_DEFAULT, FORMAT_HTML),
        ]);
    }

    /**
     * Create a section.
     *
     * @param int $courseid
     * @param int $position
     * @param string|null $name
     * @param string|null $summary
     * @param int $summaryformat
     * @return array
     */
    public static function create(
        int $courseid,
        int $position = 0,
        ?string $name = null,
        ?string $summary = null,
        int $summaryformat = FORMAT_HTML
    ): array {
        [
            'courseid' => $courseid,
            'position' => $position,
            'name' => $name,
            'summary' => $summary,
            'summaryformat' => $summaryformat,
        ] = self::validate_parameters(self::create_parameters(), [
            'courseid' => $courseid,
            'position' => $position,
            'name' => $name,
            'summary' => $summary,
            'summaryformat' => $summaryformat,
        ]);

        self::validate_context(\context_course::instance($courseid));

        $service = new SectionService();
        return $service->create($courseid, $position, $name, $summary, $summaryformat);
    }

    /**
     * @return external_single_structure
     */
    public static function create_returns(): external_single_structure {
        return self::mutation_returns();
    }

    /**
     * @return external_function_parameters
     */
    public static function rename_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'name' => new external_value(PARAM_TEXT, 'New section name', VALUE_DEFAULT, null),
            'summary' => new external_value(PARAM_RAW, 'New summary HTML', VALUE_DEFAULT, null),
            'summaryformat' => new external_value(PARAM_INT, 'Summary format', VALUE_DEFAULT, FORMAT_HTML),
        ]);
    }

    /**
     * Rename / update summary of a section.
     *
     * @param int $courseid
     * @param int $sectionid
     * @param string|null $name
     * @param string|null $summary
     * @param int $summaryformat
     * @return array
     */
    public static function rename(
        int $courseid,
        int $sectionid,
        ?string $name = null,
        ?string $summary = null,
        int $summaryformat = FORMAT_HTML
    ): array {
        [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'name' => $name,
            'summary' => $summary,
            'summaryformat' => $summaryformat,
        ] = self::validate_parameters(self::rename_parameters(), [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'name' => $name,
            'summary' => $summary,
            'summaryformat' => $summaryformat,
        ]);

        self::validate_context(\context_course::instance($courseid));

        $service = new SectionService();
        return $service->rename($courseid, $sectionid, $name, $summary, $summaryformat);
    }

    /**
     * @return external_single_structure
     */
    public static function rename_returns(): external_single_structure {
        return self::mutation_returns();
    }

    /**
     * @return external_function_parameters
     */
    public static function move_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'destination' => new external_value(PARAM_INT, 'Destination section number (>= 1)'),
        ]);
    }

    /**
     * Move a section.
     *
     * @param int $courseid
     * @param int $sectionid
     * @param int $destination
     * @return array
     */
    public static function move(int $courseid, int $sectionid, int $destination): array {
        [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'destination' => $destination,
        ] = self::validate_parameters(self::move_parameters(), [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'destination' => $destination,
        ]);

        self::validate_context(\context_course::instance($courseid));

        $service = new SectionService();
        return $service->move($courseid, $sectionid, $destination);
    }

    /**
     * @return external_single_structure
     */
    public static function move_returns(): external_single_structure {
        return ApiResponse::external_success_structure(new external_single_structure([
            'section' => ApiResponse::section_structure(),
            'from' => new external_value(PARAM_INT, 'Previous section number'),
            'to' => new external_value(PARAM_INT, 'New section number'),
        ]));
    }

    /**
     * @return external_function_parameters
     */
    public static function hide_parameters(): external_function_parameters {
        return self::section_id_parameters();
    }

    /**
     * Hide a section.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return array
     */
    public static function hide(int $courseid, int $sectionid): array {
        ['courseid' => $courseid, 'sectionid' => $sectionid] = self::validate_parameters(
            self::hide_parameters(),
            ['courseid' => $courseid, 'sectionid' => $sectionid]
        );
        self::validate_context(\context_course::instance($courseid));
        return (new SectionService())->hide($courseid, $sectionid);
    }

    /**
     * @return external_single_structure
     */
    public static function hide_returns(): external_single_structure {
        return self::mutation_returns();
    }

    /**
     * @return external_function_parameters
     */
    public static function show_parameters(): external_function_parameters {
        return self::section_id_parameters();
    }

    /**
     * Show a section.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return array
     */
    public static function show(int $courseid, int $sectionid): array {
        ['courseid' => $courseid, 'sectionid' => $sectionid] = self::validate_parameters(
            self::show_parameters(),
            ['courseid' => $courseid, 'sectionid' => $sectionid]
        );
        self::validate_context(\context_course::instance($courseid));
        return (new SectionService())->show($courseid, $sectionid);
    }

    /**
     * @return external_single_structure
     */
    public static function show_returns(): external_single_structure {
        return self::mutation_returns();
    }

    /**
     * @return external_function_parameters
     */
    public static function delete_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'forcedeleteifnotempty' => new external_value(
                PARAM_BOOL,
                'Delete even if section contains modules',
                VALUE_DEFAULT,
                true
            ),
        ]);
    }

    /**
     * Delete a section.
     *
     * @param int $courseid
     * @param int $sectionid
     * @param bool $forcedeleteifnotempty
     * @return array
     */
    public static function delete(
        int $courseid,
        int $sectionid,
        bool $forcedeleteifnotempty = true
    ): array {
        [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'forcedeleteifnotempty' => $forcedeleteifnotempty,
        ] = self::validate_parameters(self::delete_parameters(), [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'forcedeleteifnotempty' => $forcedeleteifnotempty,
        ]);

        self::validate_context(\context_course::instance($courseid));
        return (new SectionService())->delete($courseid, $sectionid, $forcedeleteifnotempty);
    }

    /**
     * @return external_single_structure
     */
    public static function delete_returns(): external_single_structure {
        return ApiResponse::external_success_structure(new external_single_structure([
            'sectionid' => new external_value(PARAM_INT, 'Deleted section id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionnum' => new external_value(PARAM_INT, 'Former section number'),
            'deleted' => new external_value(PARAM_BOOL, 'Deletion succeeded'),
        ]));
    }

    /**
     * @return external_function_parameters
     */
    public static function get_parameters(): external_function_parameters {
        return self::section_id_parameters();
    }

    /**
     * Get one section.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return array
     */
    public static function get(int $courseid, int $sectionid): array {
        ['courseid' => $courseid, 'sectionid' => $sectionid] = self::validate_parameters(
            self::get_parameters(),
            ['courseid' => $courseid, 'sectionid' => $sectionid]
        );
        self::validate_context(\context_course::instance($courseid));
        return (new SectionService())->get($courseid, $sectionid);
    }

    /**
     * @return external_single_structure
     */
    public static function get_returns(): external_single_structure {
        return ApiResponse::external_success_structure(new external_single_structure([
            'section' => ApiResponse::section_structure(),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]));
    }

    /**
     * @return external_function_parameters
     */
    public static function get_list_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * List sections in a course.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_list(int $courseid): array {
        ['courseid' => $courseid] = self::validate_parameters(
            self::get_list_parameters(),
            ['courseid' => $courseid]
        );
        self::validate_context(\context_course::instance($courseid));
        return (new SectionService())->list($courseid);
    }

    /**
     * @return external_single_structure
     */
    public static function get_list_returns(): external_single_structure {
        return ApiResponse::external_success_structure(new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sections' => new external_multiple_structure(ApiResponse::section_structure()),
            'count' => new external_value(PARAM_INT, 'Number of sections'),
        ]));
    }

    /**
     * Shared parameters for courseid + sectionid.
     *
     * @return external_function_parameters
     */
    private static function section_id_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
        ]);
    }

    /**
     * Shared return for mutations that return a section object.
     *
     * @return external_single_structure
     */
    private static function mutation_returns(): external_single_structure {
        return ApiResponse::external_success_structure(new external_single_structure([
            'section' => ApiResponse::section_structure(),
        ]));
    }
}
