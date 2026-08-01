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
 * Course input validation.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\validator;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;
use stdClass;

/**
 * Validates course-level identifiers and payloads.
 */
final class CourseValidator {

    /**
     * Validate a positive course id and return the course.
     *
     * @param int $courseid
     * @return stdClass
     * @throws moodle_exception
     */
    public function require_course(int $courseid): stdClass {
        if ($courseid <= 0) {
            throw new moodle_exception('errorinvalidcourseid', 'local_espace', '', $courseid);
        }

        $course = get_course($courseid);
        if (!$course || empty($course->id)) {
            throw new moodle_exception('errorinvalidcourseid', 'local_espace', '', $courseid);
        }

        return $course;
    }

    /**
     * Validate a non-empty trimmed course shortname/fullname style string.
     *
     * @param string $value
     * @param string $field
     * @param int $maxlen
     * @return string
     * @throws moodle_exception
     */
    public function require_nonempty_string(string $value, string $field, int $maxlen = 254): string {
        $clean = trim($value);
        if ($clean === '') {
            throw new moodle_exception('errorrequiredfield', 'local_espace', '', $field);
        }
        if (\core_text::strlen($clean) > $maxlen) {
            throw new moodle_exception('errorfieldtoolong', 'local_espace', '', $field);
        }
        return $clean;
    }
}
