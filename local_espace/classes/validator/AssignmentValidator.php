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
 * Assignment authoring input validation (Sprint A).
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\validator;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;

/**
 * Validates Sprint A assignment authoring settings.
 *
 * Does not invent Moodle semantics — only checks types/ranges and required fields
 * before calling Moodle modlib / assign APIs.
 */
final class AssignmentValidator {

    /** Supported grade types (Moodle modgrade). */
    public const GRADE_TYPES = ['none', 'point', 'scale'];

    /**
     * Validate and normalise assignment settings for upsert.
     *
     * @param array $settings Raw settings (associative).
     * @param bool $iscreate True when creating a new assignment.
     * @return array Normalised settings.
     * @throws moodle_exception
     */
    public function validate_settings(array $settings, bool $iscreate): array {
        $out = [];

        $name = isset($settings['name']) ? trim((string) $settings['name']) : '';
        if ($name === '') {
            throw new moodle_exception('errorrequiredfield', 'local_espace', '', 'name');
        }
        if (\core_text::strlen($name) > 255) {
            throw new moodle_exception('errorfieldtoolong', 'local_espace', '', 'name');
        }
        $out['name'] = $name;

        $out['intro'] = isset($settings['intro']) ? (string) $settings['intro'] : '';
        // Moodle FORMAT_* are strings ('0','1','2','4') in 5.2.1 weblib.php.
        // Cast needle to int for storage; compare with intval(haystack) + strict
        // (core uses loose in_array; either works — avoid int vs string strict miss).
        $introformat = isset($settings['introformat']) ? (int) $settings['introformat'] : (int) FORMAT_HTML;
        $allowedformats = array_map('intval', [FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN, FORMAT_MARKDOWN]);
        if (!in_array($introformat, $allowedformats, true)) {
            throw new moodle_exception('errorinvalidsummaryformat', 'local_espace');
        }
        $out['introformat'] = $introformat;

        foreach (['allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'gradingduedate'] as $datefield) {
            $datevalue = $this->optional_nonneg_int($settings, $datefield, $iscreate ? 0 : null);
            if ($datevalue !== null) {
                $out[$datefield] = $datevalue;
            }
        }

        $this->validate_date_order(array_merge([
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
            'cutoffdate' => 0,
            'gradingduedate' => 0,
        ], $out));

        $onlinetext = $this->optional_bool01($settings, 'onlinetext_enabled', $iscreate ? 1 : null);
        $fileenabled = $this->optional_bool01($settings, 'file_enabled', $iscreate ? 1 : null);
        if ($onlinetext !== null) {
            $out['onlinetext_enabled'] = $onlinetext;
        }
        if ($fileenabled !== null) {
            $out['file_enabled'] = $fileenabled;
        }

        $maxfiles = $this->optional_nonneg_int($settings, 'maxfiles', null);
        if ($maxfiles !== null) {
            $out['maxfiles'] = $maxfiles;
        }
        $maxsize = $this->optional_nonneg_int($settings, 'maxsizebytes', null);
        if ($maxsize !== null) {
            $out['maxsizebytes'] = $maxsize;
        }

        $gradetype = isset($settings['grade_type']) ? clean_param((string) $settings['grade_type'], PARAM_ALPHA) : null;
        if ($gradetype !== null && $gradetype !== '') {
            if (!in_array($gradetype, self::GRADE_TYPES, true)) {
                throw new moodle_exception('errorinvalidgradetype', 'local_espace', '', $gradetype);
            }
            $out['grade_type'] = $gradetype;
        } else if ($iscreate) {
            $out['grade_type'] = 'point';
        }

        if (array_key_exists('grade', $settings) && $settings['grade'] !== null && $settings['grade'] !== '') {
            $out['grade'] = (float) $settings['grade'];
            if ($out['grade'] < 0) {
                throw new moodle_exception('errorinvalidgrade', 'local_espace');
            }
        } else if ($iscreate && ($out['grade_type'] ?? '') === 'point') {
            $out['grade'] = 100.0;
        }

        $scaleid = $this->optional_nonneg_int($settings, 'scaleid', null);
        if ($scaleid !== null) {
            $out['scaleid'] = $scaleid;
        }
        $gradecat = $this->optional_nonneg_int($settings, 'gradecat', null);
        if ($gradecat !== null) {
            $out['gradecat'] = $gradecat;
        }

        if (($out['grade_type'] ?? null) === 'scale') {
            if (empty($out['scaleid'])) {
                throw new moodle_exception('errorrequiredfield', 'local_espace', '', 'scaleid');
            }
        }

        if (array_key_exists('visible', $settings) && $settings['visible'] !== null && $settings['visible'] !== '') {
            $visible = (int) $settings['visible'];
            if ($visible !== 0 && $visible !== 1) {
                throw new moodle_exception('errorinvalidvisibility', 'local_espace', '', $visible);
            }
            $out['visible'] = $visible;
        } else if ($iscreate) {
            $out['visible'] = 1;
        }

        // introattachments: only when explicitly provided.
        $introattachments = $this->optional_nonneg_int($settings, 'introattachments', null);
        if ($introattachments !== null) {
            $out['introattachments'] = $introattachments;
        }

        return $out;
    }

    /**
     * @param array $settings
     * @throws moodle_exception
     */
    private function validate_date_order(array $settings): void {
        $allow = (int) ($settings['allowsubmissionsfromdate'] ?? 0);
        $due = (int) ($settings['duedate'] ?? 0);
        $cutoff = (int) ($settings['cutoffdate'] ?? 0);
        $gradingdue = (int) ($settings['gradingduedate'] ?? 0);

        if ($allow && $due && $due <= $allow) {
            throw new moodle_exception('errordateorder', 'local_espace', '', 'duedate <= allowsubmissionsfromdate');
        }
        if ($allow && $cutoff && $cutoff < $allow) {
            throw new moodle_exception('errordateorder', 'local_espace', '', 'cutoffdate < allowsubmissionsfromdate');
        }
        if ($allow && $gradingdue && $gradingdue < $allow) {
            throw new moodle_exception('errordateorder', 'local_espace', '', 'gradingduedate < allowsubmissionsfromdate');
        }
    }

    /**
     * @param array $settings
     * @param string $key
     * @param int|null $default
     * @return int|null
     * @throws moodle_exception
     */
    private function optional_nonneg_int(array $settings, string $key, ?int $default): ?int {
        if (!array_key_exists($key, $settings) || $settings[$key] === null || $settings[$key] === '') {
            return $default;
        }
        $value = (int) $settings[$key];
        if ($value < 0) {
            throw new moodle_exception('errorinvalidinteger', 'local_espace', '', $key);
        }
        return $value;
    }

    /**
     * @param array $settings
     * @param string $key
     * @param int|null $default 0/1 or null if absent
     * @return int|null
     */
    private function optional_bool01(array $settings, string $key, ?int $default): ?int {
        if (!array_key_exists($key, $settings) || $settings[$key] === null || $settings[$key] === '') {
            return $default;
        }
        return !empty($settings[$key]) ? 1 : 0;
    }
}
