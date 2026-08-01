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
 * Upgrade steps for local_espace.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_espace_upgrade($oldversion) {
    // Initial release stores no custom tables.
    // Future schema changes must be added here with savepoints.

    if ($oldversion < 2026040100) {
        // Ensure default configs exist after upgrade from incomplete installs.
        if (get_config('local_espace', 'enabled') === false) {
            set_config('enabled', 1, 'local_espace');
        }
        if (get_config('local_espace', 'strictpermissions') === false) {
            set_config('strictpermissions', 1, 'local_espace');
        }

        upgrade_plugin_savepoint(true, 2026040100, 'local', 'espace');
    }

    if ($oldversion < 2026080100) {
        // Registers local_espace_upsert_module (Assignment Sprint A).
        // No schema changes — services.php is refreshed on upgrade.
        upgrade_plugin_savepoint(true, 2026080100, 'local', 'espace');
    }

    if ($oldversion < 2026080101) {
        // Moodle 5.2.1 alignment: preserve CM AI fields on module upsert update path.
        upgrade_plugin_savepoint(true, 2026080101, 'local', 'espace');
    }

    return true;
}
