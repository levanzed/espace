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

    if ($oldversion < 2026080102) {
        // Fix FORMAT_* string vs int strict in_array in Assignment/Section validators.
        upgrade_plugin_savepoint(true, 2026080102, 'local', 'espace');
    }

    if ($oldversion < 2026080103) {
        // Fix prepare_new_moduleinfo_data unpack (returns 5-tuple; 5th is stdClass moduleinfo).
        upgrade_plugin_savepoint(true, 2026080103, 'local', 'espace');
    }

    if ($oldversion < 2026080104) {
        // ESPACE built-in service: include core_files_upload + draft itemid.
        upgrade_plugin_savepoint(true, 2026080104, 'local', 'espace');
    }

    if ($oldversion < 2026080105) {
        // Single-service model: minimal living allowlist for Auth/Sections/Assign Sprint A/Files.
        // Remove Mobile auto-membership from local_espace function definitions.
        upgrade_plugin_savepoint(true, 2026080105, 'local', 'espace');
    }

    if ($oldversion < 2026080106) {
        // Restore Mobile service membership for local_espace WS (app auth = moodle_mobile_app).
        upgrade_plugin_savepoint(true, 2026080106, 'local', 'espace');
    }

    if ($oldversion < 2026080107) {
        // 1.1.5 (auth experiment) cleared services=>[MOODLE_OFFICIAL_MOBILE_SERVICE], which
        // deleted local_espace_* from moodle_mobile_app via external_update_descriptions.
        // 1.1.6 restored the services.php declaration, but plugin-only upgrades update the
        // external_functions.services column without always re-injecting into built-in
        // services. Force both steps so Mobile tokens can call local_espace_* again.
        external_update_descriptions('local_espace');
        external_update_services();
        upgrade_plugin_savepoint(true, 2026080107, 'local', 'espace');
    }

    if ($oldversion < 2026080108) {
        // Fix namespaced resolution of core grade_item in AssignmentService (update path).
        upgrade_plugin_savepoint(true, 2026080108, 'local', 'espace');
    }

    return true;
}
