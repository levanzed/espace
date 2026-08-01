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
 * Generic module structural service framework.
 *
 * Official Moodle WS already provide structural ops via:
 * - core_courseformat_update_course (cm_hide/show/delete/duplicate/move)
 * - core_courseformat_new_module (FEATURE_QUICKCREATE only)
 * - core_course_delete_modules
 *
 * Full settings/content upsert is the primary future local_espace gap.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\service;

defined('MOODLE_INTERNAL') || die();

use local_espace\helper\FileHelper;
use local_espace\helper\ModuleHelper;
use local_espace\output\ApiResponse;
use local_espace\permission\CapabilityChecker;
use local_espace\validator\CourseValidator;
use local_espace\validator\ModuleValidator;

/**
 * Shared module service base for typed module services.
 */
final class ModuleService extends BaseService {

    /** @var ModuleValidator */
    private ModuleValidator $modulevalidator;

    /** @var FileHelper */
    private FileHelper $files;

    public function __construct(
        ?CapabilityChecker $permissions = null,
        ?ModuleHelper $modules = null,
        ?CourseValidator $coursevalidator = null,
        ?ModuleValidator $modulevalidator = null,
        ?FileHelper $files = null
    ) {
        parent::__construct($permissions, $modules, $coursevalidator);
        $this->modulevalidator = $modulevalidator ?? new ModuleValidator();
        $this->files = $files ?? new FileHelper();
    }

    /**
     * Describe module capability matrix for ESPACE.
     *
     * @return array
     */
    public function capability_matrix(): array {
        $this->permissions->require_login();
        $this->permissions->require_plugin_enabled();

        return ApiResponse::success('module_capability_matrix', [
            'use_official_ws' => [
                'core_courseformat_update_course',
                'core_courseformat_new_module',
                'core_course_delete_modules',
                'core_course_get_course_module',
                'core_course_get_contents',
            ],
            'local_espace_planned' => [
                'upsert_module_settings_and_content',
            ],
            'allowed_modnames' => ModuleValidator::ALLOWED_MODNAMES,
            'notes' => 'Structural module ops: use official courseformat WS. Content/settings upsert reserved for typed services.',
        ]);
    }

    /**
     * Expose module validator for typed services.
     *
     * @return ModuleValidator
     */
    public function get_module_validator(): ModuleValidator {
        return $this->modulevalidator;
    }

    /**
     * Expose file helper for typed services.
     *
     * @return FileHelper
     */
    public function get_file_helper(): FileHelper {
        return $this->files;
    }
}
