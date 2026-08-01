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
 * External API shell for Quiz.
 *
 * No web service functions are registered for this class in this release.
 * The class exists so the namespace layout matches the architecture contract.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_espace\service\QuizService;

/**
 * Quiz external adapter.
 */
class quiz extends external_api {

    /**
     * Return the capability matrix for this subsystem.
     *
     * @return external_function_parameters
     */
    public static function capability_matrix_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * @return array
     */
    public static function capability_matrix(): array {
        self::validate_parameters(self::capability_matrix_parameters(), []);
        return (new QuizService())->capability_matrix();
    }

    /**
     * @return external_single_structure
     */
    public static function capability_matrix_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'ok'),
            'operation' => new external_value(PARAM_ALPHANUMEXT, 'Operation'),
            'data' => new external_single_structure([
                'use_official_ws' => new \core_external\external_multiple_structure(
                    new external_value(PARAM_RAW, 'WS name')
                ),
                'local_espace_gap' => new external_value(PARAM_RAW, 'Gap description', VALUE_OPTIONAL),
                'local_espace_planned' => new \core_external\external_multiple_structure(
                    new external_value(PARAM_RAW, 'Planned function'),
                    'Planned',
                    VALUE_OPTIONAL
                ),
                'allowed_modnames' => new \core_external\external_multiple_structure(
                    new external_value(PARAM_PLUGIN, 'modname'),
                    'Allowed modules',
                    VALUE_OPTIONAL
                ),
                'notes' => new external_value(PARAM_RAW, 'Notes', VALUE_OPTIONAL),
                'status' => new external_value(PARAM_ALPHANUMEXT, 'Framework status', VALUE_OPTIONAL),
            ]),
            'warnings' => new \core_external\external_multiple_structure(
                new external_value(PARAM_RAW, 'Warning'),
                'Warnings',
                VALUE_DEFAULT,
                []
            ),
            'timemodified' => new external_value(PARAM_INT, 'Timestamp'),
        ]);
    }
}
