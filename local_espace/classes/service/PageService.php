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
 * Page service framework for local_espace.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\service;

defined('MOODLE_INTERNAL') || die();

use local_espace\output\ApiResponse;

/**
 * Page domain service.
 *
 * Wired into the dependency graph now so future sprints can add methods
 * without changing plugin architecture or constructor signatures.
 */
final class PageService extends BaseService {

    /**
     * Capability matrix for this subsystem.
     *
     * @return array
     */
    public function capability_matrix(): array {
        $this->permissions->require_login();
        $this->permissions->require_plugin_enabled();

        return ApiResponse::success('page_capability_matrix', [
            'use_official_ws' => ["mod_page_get_pages_by_courses", "mod_page_view_page"],
            'local_espace_gap' => 'Page content create/update (intro + content HTML + files)',
            'status' => 'framework_ready',
        ]);
    }
}
