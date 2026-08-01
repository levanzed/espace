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
 * Structured API response helpers for local_espace external functions.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\output;

defined('MOODLE_INTERNAL') || die();

use core_external\external_single_structure;
use core_external\external_value;

/**
 * Consistent response envelope for ESPACE FastAPI consumption.
 */
final class ApiResponse {

    /**
     * Build a success payload.
     *
     * @param string $operation
     * @param array $data
     * @param array $warnings
     * @return array
     */
    public static function success(string $operation, array $data = [], array $warnings = []): array {
        return [
            'status' => 'ok',
            'operation' => $operation,
            'data' => $data,
            'warnings' => array_values($warnings),
            'timemodified' => time(),
        ];
    }

    /**
     * External structure describing the success envelope with a free-form data object.
     *
     * Callers should typically define their own tighter return structure.
     * This helper documents the shared envelope fields.
     *
     * @param external_single_structure $datastructure
     * @return external_single_structure
     */
    public static function external_success_structure(external_single_structure $datastructure): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'ok'),
            'operation' => new external_value(PARAM_ALPHANUMEXT, 'Operation name'),
            'data' => $datastructure,
            'warnings' => new \core_external\external_multiple_structure(
                new external_value(PARAM_RAW, 'Warning message'),
                'Warnings',
                VALUE_DEFAULT,
                []
            ),
            'timemodified' => new external_value(PARAM_INT, 'Unix timestamp'),
        ]);
    }

    /**
     * Section record structure returned by section operations.
     *
     * @return external_single_structure
     */
    public static function section_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Section id'),
            'course' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'name' => new external_value(PARAM_RAW, 'Section name', VALUE_OPTIONAL),
            'summary' => new external_value(PARAM_RAW, 'Section summary HTML', VALUE_OPTIONAL),
            'summaryformat' => new external_value(PARAM_INT, 'Summary format', VALUE_OPTIONAL),
            'visible' => new external_value(PARAM_INT, 'Visibility (1/0)'),
            'sequence' => new external_value(PARAM_RAW, 'Module sequence', VALUE_OPTIONAL),
            'timemodified' => new external_value(PARAM_INT, 'Last modified', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Course module record structure returned by module upsert operations.
     *
     * @return external_single_structure
     */
    public static function cm_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course module id'),
            'course' => new external_value(PARAM_INT, 'Course id'),
            'module' => new external_value(PARAM_INT, 'Modules table id'),
            'instance' => new external_value(PARAM_INT, 'Activity instance id'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'modname' => new external_value(PARAM_PLUGIN, 'Module plugin name'),
            'name' => new external_value(PARAM_RAW, 'Activity name'),
            'visible' => new external_value(PARAM_INT, 'Visibility (1/0)'),
            'idnumber' => new external_value(PARAM_RAW, 'CM idnumber', VALUE_OPTIONAL),
        ]);
    }
}
