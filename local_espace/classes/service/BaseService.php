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
 * Abstract base for ESPACE service classes.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\service;

defined('MOODLE_INTERNAL') || die();

use local_espace\helper\ModuleHelper;
use local_espace\permission\CapabilityChecker;
use local_espace\validator\CourseValidator;

/**
 * Shared dependencies for all domain services.
 */
abstract class BaseService {

    /** @var CapabilityChecker */
    protected CapabilityChecker $permissions;

    /** @var ModuleHelper */
    protected ModuleHelper $modules;

    /** @var CourseValidator */
    protected CourseValidator $coursevalidator;

    public function __construct(
        ?CapabilityChecker $permissions = null,
        ?ModuleHelper $modules = null,
        ?CourseValidator $coursevalidator = null
    ) {
        $this->permissions = $permissions ?? new CapabilityChecker();
        $this->modules = $modules ?? new ModuleHelper();
        $this->coursevalidator = $coursevalidator ?? new CourseValidator();
    }
}
