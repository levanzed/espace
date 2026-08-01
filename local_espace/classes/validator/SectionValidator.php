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
 * Section input validation.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\validator;

defined('MOODLE_INTERNAL') || die();

use local_espace\helper\ModuleHelper;
use moodle_exception;
use section_info;
use stdClass;

/**
 * Validates section identifiers and mutation parameters.
 */
final class SectionValidator {

    /** @var ModuleHelper */
    private ModuleHelper $modules;

    /** @var CourseValidator */
    private CourseValidator $courses;

    public function __construct(
        ?ModuleHelper $modules = null,
        ?CourseValidator $courses = null
    ) {
        $this->modules = $modules ?? new ModuleHelper();
        $this->courses = $courses ?? new CourseValidator();
    }

    /**
     * Validate course + section id pair.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return array{0:stdClass,1:section_info}
     */
    public function require_section(int $courseid, int $sectionid): array {
        $course = $this->courses->require_course($courseid);

        if ($sectionid <= 0) {
            throw new moodle_exception('errorinvalidsectionid', 'local_espace', '', $sectionid);
        }

        $section = $this->modules->get_section_by_id($courseid, $sectionid);

        if ((int) $section->course !== (int) $course->id) {
            throw new moodle_exception('errorsectioncoursemismatch', 'local_espace');
        }

        return [$course, $section];
    }

    /**
     * Validate a rename payload.
     *
     * @param string|null $name
     * @param string|null $summary
     * @return array{name:?string,summary:?string,summaryformat:int}
     */
    public function validate_rename(?string $name, ?string $summary, int $summaryformat = FORMAT_HTML): array {
        if ($name === null && $summary === null) {
            throw new moodle_exception('errorsectionrenamenodata', 'local_espace');
        }

        $result = [
            'name' => null,
            'summary' => null,
            'summaryformat' => $summaryformat,
        ];

        if ($name !== null) {
            // Empty string is allowed: Moodle treats it as default section name.
            $clean = trim($name);
            if (\core_text::strlen($clean) > 255) {
                throw new moodle_exception('errorfieldtoolong', 'local_espace', '', 'name');
            }
            $result['name'] = $clean;
        }

        if ($summary !== null) {
            $result['summary'] = clean_param($summary, PARAM_RAW);
            $allowedformats = [FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN];
            if (!in_array($summaryformat, $allowedformats, true)) {
                throw new moodle_exception('errorinvalidsummaryformat', 'local_espace');
            }
            $result['summaryformat'] = $summaryformat;
        }

        return $result;
    }

    /**
     * Validate a destination section number for move operations.
     *
     * Section 0 (general) cannot be the moved section in most formats;
     * destination must be >= 1 for classic move_section_to().
     *
     * @param stdClass $course
     * @param section_info $section
     * @param int $destination
     * @throws moodle_exception
     */
    public function validate_move(stdClass $course, section_info $section, int $destination): void {
        if ((int) $section->section === 0) {
            throw new moodle_exception('errorcannotmovegeneralsection', 'local_espace');
        }

        if ($destination < 1) {
            throw new moodle_exception('errorinvalidmovedestination', 'local_espace', '', $destination);
        }

        $format = course_get_format($course);
        $options = $format->get_format_options();
        if (array_key_exists('numsections', $options) && $destination > (int) $options['numsections']) {
            throw new moodle_exception('errorinvalidmovedestination', 'local_espace', '', $destination);
        }

        $last = $format->get_last_section_number();
        if ($destination > $last) {
            throw new moodle_exception('errorinvalidmovedestination', 'local_espace', '', $destination);
        }
    }

    /**
     * Validate create position.
     *
     * @param int $position 0 means append
     * @throws moodle_exception
     */
    public function validate_create_position(int $position): void {
        if ($position < 0) {
            throw new moodle_exception('errorinvalidsectionposition', 'local_espace', '', $position);
        }
    }

    /**
     * Validate delete is allowed by format + business rules.
     *
     * @param stdClass $course
     * @param section_info $section
     * @param bool $forcedeleteifnotempty
     * @throws moodle_exception
     */
    public function validate_delete(
        stdClass $course,
        section_info $section,
        bool $forcedeleteifnotempty
    ): void {
        if ((int) $section->section === 0) {
            throw new moodle_exception('errorcannotdeletegeneralsection', 'local_espace');
        }

        if (!$this->modules->can_delete_section($course, $section)) {
            throw new moodle_exception('errorcannotdeletesection', 'local_espace');
        }

        if (!$forcedeleteifnotempty) {
            $sequence = trim((string) $section->sequence);
            if ($sequence !== '') {
                throw new moodle_exception('errorsectionnotempty', 'local_espace');
            }
        }
    }

    /**
     * Validate visibility value.
     *
     * @param int $visible
     * @return int
     * @throws moodle_exception
     */
    public function validate_visibility(int $visible): int {
        if ($visible !== 0 && $visible !== 1) {
            throw new moodle_exception('errorinvalidvisibility', 'local_espace', '', $visible);
        }
        return $visible;
    }
}
