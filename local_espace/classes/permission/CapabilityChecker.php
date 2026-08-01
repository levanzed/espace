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
 * Capability and context permission checks for local_espace.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\permission;

defined('MOODLE_INTERNAL') || die();

use context;
use context_course;
use context_module;
use context_system;
use moodle_exception;
use require_login_exception;
use required_capability_exception;

/**
 * Centralised permission gate for all ESPACE local web services.
 */
final class CapabilityChecker {

    /**
     * Require an authenticated session / valid token user.
     *
     * @throws require_login_exception
     */
    public function require_login(): void {
        require_login(null, false, null, false, true);
    }

    /**
     * Require the plugin to be enabled in admin settings.
     *
     * @throws moodle_exception
     */
    public function require_plugin_enabled(): void {
        local_espace_require_enabled();
    }

    /**
     * Require system-level use capability.
     *
     * @throws required_capability_exception
     */
    public function require_system_use(): void {
        require_capability('local/espace:use', context_system::instance());
    }

    /**
     * Require course context and validate access.
     *
     * @param int $courseid
     * @return context_course
     * @throws moodle_exception
     */
    public function require_course_context(int $courseid): context_course {
        $course = get_course($courseid);
        $context = context_course::instance($course->id);
        require_login($course, false, null, false, true);
        return $context;
    }

    /**
     * Require one or more capabilities in a context.
     *
     * When strictpermissions is enabled, ALL listed capabilities are required.
     * Otherwise ANY listed capability is sufficient.
     *
     * @param array $capabilities
     * @param context $context
     * @throws required_capability_exception
     * @throws moodle_exception
     */
    public function require_capabilities(array $capabilities, context $context): void {
        if (empty($capabilities)) {
            throw new moodle_exception('errornocapabilities', 'local_espace');
        }

        $strict = (bool) get_config('local_espace', 'strictpermissions');

        if ($strict) {
            foreach ($capabilities as $capability) {
                require_capability($capability, $context);
            }
            return;
        }

        if (!has_any_capability($capabilities, $context)) {
            // Throw using the first capability for a consistent Moodle exception.
            require_capability(reset($capabilities), $context);
        }
    }

    /**
     * Require section management capability set in a course.
     *
     * @param int $courseid
     * @return context_course
     */
    public function require_section_management(int $courseid): context_course {
        $this->require_login();
        $this->require_plugin_enabled();
        $this->require_system_use();

        $context = $this->require_course_context($courseid);

        $this->require_capabilities([
            'local/espace:managesections',
            'moodle/course:update',
        ], $context);

        return $context;
    }

    /**
     * Require move-section capability in addition to section management.
     *
     * @param int $courseid
     * @return context_course
     */
    public function require_section_move(int $courseid): context_course {
        $context = $this->require_section_management($courseid);
        $this->require_capabilities(['moodle/course:movesections'], $context);
        return $context;
    }

    /**
     * Require section visibility capability in addition to section management.
     *
     * @param int $courseid
     * @return context_course
     */
    public function require_section_visibility(int $courseid): context_course {
        $context = $this->require_section_management($courseid);
        $this->require_capabilities(['moodle/course:sectionvisibility'], $context);
        return $context;
    }

    /**
     * Require module management capabilities.
     *
     * @param int $courseid
     * @return context_course
     */
    public function require_module_management(int $courseid): context_course {
        $this->require_login();
        $this->require_plugin_enabled();
        $this->require_system_use();

        $context = $this->require_course_context($courseid);
        $this->require_capabilities([
            'local/espace:managemodules',
            'moodle/course:manageactivities',
        ], $context);

        return $context;
    }

    /**
     * Require module context for an existing course module.
     *
     * @param int $cmid
     * @return array{0:\cm_info,1:context_module,2:\stdClass}
     */
    public function require_module_context(int $cmid): array {
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cmid);
        $context = context_module::instance($cminfo->id);
        require_login($course, false, $cminfo, false, true);
        return [$cminfo, $context, $course];
    }
}
