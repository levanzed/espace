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
 * Admin settings for local_espace.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_espace',
        get_string('pluginname', 'local_espace')
    );

    $settings->add(new admin_setting_configcheckbox(
        'local_espace/enabled',
        get_string('settingenabled', 'local_espace'),
        get_string('settingenabled_desc', 'local_espace'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_espace/strictpermissions',
        get_string('settingstrictpermissions', 'local_espace'),
        get_string('settingstrictpermissions_desc', 'local_espace'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'local_espace/architectureheading',
        get_string('settingarchitectureheading', 'local_espace'),
        get_string('settingarchitectureheading_desc', 'local_espace')
    ));

    $ADMIN->add('localplugins', $settings);
}
