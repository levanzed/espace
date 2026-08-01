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
 * Library callbacks for local_espace.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Whether the ESPACE local plugin web services are enabled.
 *
 * @return bool
 */
function local_espace_is_enabled(): bool {
    return (bool) get_config('local_espace', 'enabled');
}

/**
 * Assert that the plugin is enabled or throw.
 *
 * @throws moodle_exception
 */
function local_espace_require_enabled(): void {
    if (!local_espace_is_enabled()) {
        throw new moodle_exception('errorplugindisabled', 'local_espace');
    }
}

/**
 * Extend navigation for course (no UI required for API-only plugin).
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_espace_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    // API-only plugin. Intentionally no course navigation nodes.
}

/**
 * Serve plugin files if ever needed for future module helpers.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_espace_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
): bool {
    // No public file areas in the initial release.
    return false;
}
