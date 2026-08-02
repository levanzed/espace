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
 * External API for Quiz Studio publish (Phase 0).
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_espace\output\ApiResponse;
use local_espace\service\QuizService;

/**
 * Quiz external adapter.
 */
class quiz extends external_api {

  /**
   * @return external_function_parameters
   */
  public static function publish_parameters(): external_function_parameters {
    return new external_function_parameters([
      'courseid' => new external_value(PARAM_INT, 'Course id'),
      'sectionid' => new external_value(PARAM_INT, 'Section id (course_sections.id)'),
      'payload' => self::payload_structure(),
    ]);
  }

  /**
   * @return external_single_structure
   */
  private static function payload_structure(): external_single_structure {
    return new external_single_structure([
      'title' => new external_value(PARAM_TEXT, 'Quiz title'),
      'intro' => new external_single_structure([
        'format' => new external_value(PARAM_ALPHA, 'html|plain|markdown|moodle', VALUE_DEFAULT, 'html'),
        'text' => new external_value(PARAM_RAW, 'Intro HTML/text', VALUE_DEFAULT, ''),
      ], 'Quiz intro', VALUE_DEFAULT, []),
      'questions' => new external_multiple_structure(
        new external_single_structure([
          'type' => new external_value(PARAM_ALPHANUMEXT, 'multiple_choice|short_answer'),
          'mark' => new external_value(PARAM_FLOAT, 'Question mark', VALUE_DEFAULT, 1),
          'stem' => new external_single_structure([
            'format' => new external_value(PARAM_ALPHA, 'Text format', VALUE_DEFAULT, 'html'),
            'text' => new external_value(PARAM_RAW, 'Question stem'),
          ]),
          'choices' => new external_multiple_structure(
            new external_single_structure([
              'text' => new external_single_structure([
                'format' => new external_value(PARAM_ALPHA, 'Text format', VALUE_DEFAULT, 'plain'),
                'text' => new external_value(PARAM_RAW, 'Choice text'),
              ]),
              'correct' => new external_value(PARAM_BOOL, 'Whether this choice is correct', VALUE_DEFAULT, false),
            ]),
            'MCQ choices',
            VALUE_DEFAULT,
            []
          ),
          'answers' => new external_multiple_structure(
            new external_single_structure([
              'text' => new external_value(PARAM_RAW, 'Accepted answer text'),
              'fraction' => new external_value(PARAM_FLOAT, 'Grade fraction', VALUE_DEFAULT, 1),
            ]),
            'Short answer accepted answers',
            VALUE_DEFAULT,
            []
          ),
          'case_sensitive' => new external_value(PARAM_BOOL, 'Case sensitive short answer', VALUE_DEFAULT, false),
        ]),
        'Questions in quiz order'
      ),
    ], 'ESPACE quiz publish payload');
  }

  /**
   * @param int $courseid
   * @param int $sectionid
   * @param array $payload
   * @return array
   */
  public static function publish(int $courseid, int $sectionid, array $payload): array {
    [
      'courseid' => $courseid,
      'sectionid' => $sectionid,
      'payload' => $payload,
    ] = self::validate_parameters(self::publish_parameters(), [
      'courseid' => $courseid,
      'sectionid' => $sectionid,
      'payload' => $payload,
    ]);

    self::validate_context(\context_course::instance($courseid));

    return (new QuizService())->publish($courseid, $sectionid, $payload);
  }

  /**
   * @return external_single_structure
   */
  public static function publish_returns(): external_single_structure {
    return ApiResponse::external_success_structure(new external_single_structure([
      'cm' => ApiResponse::cm_structure(),
      'courseid' => new external_value(PARAM_INT, 'Course id'),
      'quizid' => new external_value(PARAM_INT, 'Quiz instance id'),
      'questions' => new external_multiple_structure(
        new external_single_structure([
          'index' => new external_value(PARAM_INT, 'Index in request'),
          'type' => new external_value(PARAM_ALPHANUMEXT, 'ESPACE question type'),
          'questionid' => new external_value(PARAM_INT, 'Moodle question id'),
        ])
      ),
    ]));
  }

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
        'use_official_ws' => new external_multiple_structure(
          new external_value(PARAM_RAW, 'WS name')
        ),
        'local_espace_gap' => new external_value(PARAM_RAW, 'Gap description', VALUE_OPTIONAL),
        'status' => new external_value(PARAM_ALPHANUMEXT, 'Framework status', VALUE_OPTIONAL),
      ]),
      'warnings' => new external_multiple_structure(
        new external_value(PARAM_RAW, 'Warning'),
        'Warnings',
        VALUE_DEFAULT,
        []
      ),
      'timemodified' => new external_value(PARAM_INT, 'Timestamp'),
    ]);
  }
}
