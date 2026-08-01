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
 * Module input validation (framework for future module services).
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\validator;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;

/**
 * Validates module names and identifiers for future module subsystems.
 */
final class ModuleValidator {

    /**
     * Official Moodle module plugin names accepted by ESPACE for future create/edit.
     */
    public const ALLOWED_MODNAMES = [
        'assign',
        'book',
        'choice',
        'data',
        'feedback',
        'folder',
        'forum',
        'glossary',
        'h5pactivity',
        'label',
        'lesson',
        'page',
        'quiz',
        'resource',
        'scorm',
        'url',
        'wiki',
        'workshop',
    ];

    /**
     * Validate modname against the allow-list.
     *
     * @param string $modname
     * @return string
     * @throws moodle_exception
     */
    public function require_modname(string $modname): string {
        $clean = clean_param($modname, PARAM_PLUGIN);
        if ($clean === '' || !in_array($clean, self::ALLOWED_MODNAMES, true)) {
            throw new moodle_exception('errorinvalidmodname', 'local_espace', '', $modname);
        }

        if (!is_readable($GLOBALS['CFG']->dirroot . '/mod/' . $clean . '/version.php')) {
            throw new moodle_exception('errormodulenotinstalled', 'local_espace', '', $clean);
        }

        return $clean;
    }

    /**
     * Validate positive cmid.
     *
     * @param int $cmid
     * @return int
     * @throws moodle_exception
     */
    public function require_cmid(int $cmid): int {
        if ($cmid <= 0) {
            throw new moodle_exception('errorinvalidcmid', 'local_espace', '', $cmid);
        }
        return $cmid;
    }

    /**
     * Modnames that local_espace_upsert_module can create/update today.
     *
     * Sprint A: assign only. Later sprints add quiz/page/forum branches.
     */
    public const UPSERT_SUPPORTED_MODNAMES = [
        'assign',
    ];

    /**
     * Validate modname is installed and currently upsert-supported.
     *
     * @param string $modname
     * @return string
     * @throws moodle_exception
     */
    public function require_upsert_modname(string $modname): string {
        $clean = $this->require_modname($modname);
        if (!in_array($clean, self::UPSERT_SUPPORTED_MODNAMES, true)) {
            throw new moodle_exception('errormoduleupsertunsupported', 'local_espace', '', $clean);
        }
        return $clean;
    }
}
