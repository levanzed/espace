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
 * Course service framework (mutations deferred — official WS covers most cases).
 *
 * Official Moodle WS already provide:
 * - core_course_create_courses
 * - core_course_update_courses
 * - core_course_delete_courses
 * - core_course_duplicate_course
 *
 * This class exists so FastAPI / future sprints can extend only true gaps
 * without restructuring the plugin.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\service;

defined('MOODLE_INTERNAL') || die();

use local_espace\output\ApiResponse;

/**
 * Course-level extension service.
 */
final class CourseService extends BaseService {

    /**
     * Describe which course operations belong to official WS vs local_espace.
     *
     * @return array
     */
    public function capability_matrix(): array {
        $this->permissions->require_login();
        $this->permissions->require_plugin_enabled();

        return ApiResponse::success('course_capability_matrix', [
            'use_official_ws' => [
                'core_course_create_courses',
                'core_course_update_courses',
                'core_course_delete_courses',
                'core_course_duplicate_course',
                'core_course_get_courses',
                'core_course_get_contents',
                'core_course_get_user_administration_options',
            ],
            'local_espace' => [],
            'notes' => 'No local_espace course mutation endpoints in this release. Use official WS.',
        ]);
    }
}
