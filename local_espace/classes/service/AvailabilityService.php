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
 * Availability service framework for local_espace.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\service;

defined('MOODLE_INTERNAL') || die();

use local_espace\output\ApiResponse;

/**
 * Availability domain service.
 *
 * Wired into the dependency graph now so future sprints can add methods
 * without changing plugin architecture or constructor signatures.
 */
final class AvailabilityService extends BaseService {

    /**
     * Capability matrix for this subsystem.
     *
     * @return array
     */
    public function capability_matrix(): array {
        $this->permissions->require_login();
        $this->permissions->require_plugin_enabled();

        return ApiResponse::success('availability_capability_matrix', [
            'use_official_ws' => ["None \u2014 availability conditions have no dedicated stable write WS"],
            'local_espace_gap' => 'Set availability conditions JSON on cm/section',
            'status' => 'framework_ready',
        ]);
    }
}
