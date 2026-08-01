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
 * Course / module helper utilities.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\helper;

defined('MOODLE_INTERNAL') || die();

use cm_info;
use moodle_exception;
use section_info;
use stdClass;

/**
 * Shared helpers around courses, sections, and modules.
 */
final class ModuleHelper {

    /**
     * Ensure course/lib.php is loaded once.
     */
    private function require_course_lib(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
    }

    /**
     * Load a course record or throw.
     *
     * @param int $courseid
     * @return stdClass
     */
    public function get_course(int $courseid): stdClass {
        return get_course($courseid);
    }

    /**
     * Get section_info by section id (not section number).
     *
     * @param int $courseid
     * @param int $sectionid
     * @return section_info
     * @throws moodle_exception
     */
    public function get_section_by_id(int $courseid, int $sectionid): section_info {
        $modinfo = get_fast_modinfo($courseid);
        $section = $modinfo->get_section_info_by_id($sectionid, MUST_EXIST);
        return $section;
    }

    /**
     * Get section_info by relative section number.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @return section_info
     * @throws moodle_exception
     */
    public function get_section_by_number(int $courseid, int $sectionnum): section_info {
        $modinfo = get_fast_modinfo($courseid);
        $section = $modinfo->get_section_info($sectionnum, MUST_EXIST);
        return $section;
    }

    /**
     * Convert section_info / DB record into a serialisable array.
     *
     * @param section_info|stdClass $section
     * @return array
     */
    public function export_section($section): array {
        return [
            'id' => (int) $section->id,
            'course' => (int) $section->course,
            'section' => (int) $section->section,
            'name' => isset($section->name) ? (string) $section->name : null,
            'summary' => isset($section->summary) ? (string) $section->summary : null,
            'summaryformat' => isset($section->summaryformat) ? (int) $section->summaryformat : FORMAT_HTML,
            'visible' => isset($section->visible) ? (int) $section->visible : 1,
            'sequence' => isset($section->sequence) ? (string) $section->sequence : '',
            'timemodified' => isset($section->timemodified) ? (int) $section->timemodified : time(),
        ];
    }

    /**
     * Rebuild course cache after structural mutations.
     *
     * @param int $courseid
     */
    public function rebuild_course_cache(int $courseid): void {
        $this->require_course_lib();
        rebuild_course_cache($courseid, true);
    }

    /**
     * Whether the course format allows deleting the given section.
     *
     * @param stdClass $course
     * @param section_info $section
     * @return bool
     */
    public function can_delete_section(stdClass $course, section_info $section): bool {
        $this->require_course_lib();
        return course_can_delete_section($course, $section);
    }

    /**
     * Get cm_info by cmid.
     *
     * @param int $cmid
     * @return cm_info
     */
    public function get_cm(int $cmid): cm_info {
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        return get_fast_modinfo($course)->get_cm($cmid);
    }
}
