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
 * Event: section visibility changed via local_espace.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Triggered after a section is hidden or shown.
 */
class section_visibility_changed extends section_base {

    /**
     * @return void
     */
    protected function init(): void {
        parent::init();
        $this->data['crud'] = 'u';
    }

    /**
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventsectionvisibilitychanged', 'local_espace');
    }

    /**
     * @return string
     */
    public function get_description(): string {
        $visible = $this->other['visible'] ?? '?';
        return "The user with id '{$this->userid}' changed visibility of course section id '{$this->objectid}' " .
            "to '{$visible}' in course id '{$this->other['courseid']}' via local_espace.";
    }

    /**
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/course/view.php', ['id' => $this->other['courseid']]);
    }
}
