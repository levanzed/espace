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
 * Section domain service — production implementation.
 *
 * Uses Moodle core course APIs exclusively:
 * - course_create_section()
 * - course_update_section()
 * - move_section_to()
 * - set_section_visible()
 * - course_delete_section()
 * - rebuild_course_cache()
 *
 * Official Moodle WS gaps covered by this service:
 * - Section rename / summary edit (no dedicated stable REST function)
 * - Explicit create with optional name in one call
 *
 * Structural hide/show/delete/move also exist via core_courseformat_update_course;
 * this service provides a stable ESPACE-facing API with validation, events,
 * and consistent response shape for FastAPI.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\service;

defined('MOODLE_INTERNAL') || die();

use local_espace\event\section_created;
use local_espace\event\section_deleted;
use local_espace\event\section_moved;
use local_espace\event\section_updated;
use local_espace\event\section_visibility_changed;
use local_espace\helper\ModuleHelper;
use local_espace\output\ApiResponse;
use local_espace\permission\CapabilityChecker;
use local_espace\validator\CourseValidator;
use local_espace\validator\SectionValidator;
use moodle_exception;
use stdClass;

require_once($GLOBALS['CFG']->dirroot . '/course/lib.php');

/**
 * Full section lifecycle management for ESPACE.
 */
final class SectionService extends BaseService {

    /** @var SectionValidator */
    private SectionValidator $sectionvalidator;

    public function __construct(
        ?CapabilityChecker $permissions = null,
        ?ModuleHelper $modules = null,
        ?CourseValidator $coursevalidator = null,
        ?SectionValidator $sectionvalidator = null
    ) {
        parent::__construct($permissions, $modules, $coursevalidator);
        $this->sectionvalidator = $sectionvalidator ?? new SectionValidator($this->modules, $this->coursevalidator);
    }

    /**
     * Create a course section.
     *
     * @param int $courseid
     * @param int $position 0 = append to end
     * @param string|null $name Optional name applied after creation
     * @param string|null $summary Optional summary HTML
     * @param int $summaryformat
     * @return array ApiResponse envelope
     */
    public function create(
        int $courseid,
        int $position = 0,
        ?string $name = null,
        ?string $summary = null,
        int $summaryformat = FORMAT_HTML
    ): array {
        $context = $this->permissions->require_section_management($courseid);
        $course = $this->coursevalidator->require_course($courseid);
        $this->sectionvalidator->validate_create_position($position);

        $format = course_get_format($course);
        $last = $format->get_last_section_number();
        $max = $format->get_max_sections();
        if ($last >= $max) {
            throw new moodle_exception('errormaxsections', 'local_espace', '', $max);
        }

        $created = course_create_section($course, $position, false);
        $this->modules->rebuild_course_cache($course->id);

        // Reload as section_info after cache rebuild.
        $section = $this->modules->get_section_by_id($course->id, (int) $created->id);

        if ($name !== null || $summary !== null) {
            $data = $this->sectionvalidator->validate_rename($name, $summary, $summaryformat);
            $update = [];
            if ($data['name'] !== null) {
                $update['name'] = $data['name'];
            }
            if ($data['summary'] !== null) {
                $update['summary'] = $data['summary'];
                $update['summaryformat'] = $data['summaryformat'];
            }
            if (!empty($update)) {
                course_update_section($course, $section, $update);
                $this->modules->rebuild_course_cache($course->id);
                $section = $this->modules->get_section_by_id($course->id, (int) $section->id);
            }
        }

        $exported = $this->modules->export_section($section);

        $event = section_created::create([
            'context' => $context,
            'objectid' => $exported['id'],
            'other' => [
                'courseid' => $course->id,
                'sectionnum' => $exported['section'],
            ],
        ]);
        $event->trigger();

        return ApiResponse::success('create_section', ['section' => $exported]);
    }

    /**
     * Rename a section and/or update its summary.
     *
     * @param int $courseid
     * @param int $sectionid
     * @param string|null $name
     * @param string|null $summary
     * @param int $summaryformat
     * @return array
     */
    public function rename(
        int $courseid,
        int $sectionid,
        ?string $name = null,
        ?string $summary = null,
        int $summaryformat = FORMAT_HTML
    ): array {
        $context = $this->permissions->require_section_management($courseid);
        [$course, $section] = $this->sectionvalidator->require_section($courseid, $sectionid);
        $data = $this->sectionvalidator->validate_rename($name, $summary, $summaryformat);

        $update = [];
        if ($data['name'] !== null) {
            $update['name'] = $data['name'];
        }
        if ($data['summary'] !== null) {
            $update['summary'] = $data['summary'];
            $update['summaryformat'] = $data['summaryformat'];
        }

        course_update_section($course, $section, $update);
        $this->modules->rebuild_course_cache($course->id);
        $section = $this->modules->get_section_by_id($course->id, $sectionid);
        $exported = $this->modules->export_section($section);

        $event = section_updated::create([
            'context' => $context,
            'objectid' => $exported['id'],
            'other' => [
                'courseid' => $course->id,
                'sectionnum' => $exported['section'],
                'fields' => array_keys($update),
            ],
        ]);
        $event->trigger();

        return ApiResponse::success('rename_section', ['section' => $exported]);
    }

    /**
     * Move a section to a destination section number.
     *
     * @param int $courseid
     * @param int $sectionid
     * @param int $destination Relative section number (>= 1)
     * @return array
     */
    public function move(int $courseid, int $sectionid, int $destination): array {
        $context = $this->permissions->require_section_move($courseid);
        [$course, $section] = $this->sectionvalidator->require_section($courseid, $sectionid);
        $this->sectionvalidator->validate_move($course, $section, $destination);

        $from = (int) $section->section;
        $ok = move_section_to($course, $from, $destination, false);
        if (!$ok) {
            throw new moodle_exception('errormovesectionfailed', 'local_espace');
        }

        // move_section_to already rebuilds cache; refresh export.
        $section = $this->modules->get_section_by_id($course->id, $sectionid);
        $exported = $this->modules->export_section($section);

        $event = section_moved::create([
            'context' => $context,
            'objectid' => $exported['id'],
            'other' => [
                'courseid' => $course->id,
                'from' => $from,
                'to' => $exported['section'],
            ],
        ]);
        $event->trigger();

        return ApiResponse::success('move_section', [
            'section' => $exported,
            'from' => $from,
            'to' => $exported['section'],
        ]);
    }

    /**
     * Hide a section.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return array
     */
    public function hide(int $courseid, int $sectionid): array {
        return $this->set_visibility($courseid, $sectionid, 0);
    }

    /**
     * Show a section.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return array
     */
    public function show(int $courseid, int $sectionid): array {
        return $this->set_visibility($courseid, $sectionid, 1);
    }

    /**
     * Delete a section.
     *
     * @param int $courseid
     * @param int $sectionid
     * @param bool $forcedeleteifnotempty
     * @return array
     */
    public function delete(
        int $courseid,
        int $sectionid,
        bool $forcedeleteifnotempty = true
    ): array {
        $context = $this->permissions->require_section_management($courseid);
        [$course, $section] = $this->sectionvalidator->require_section($courseid, $sectionid);
        $this->sectionvalidator->validate_delete($course, $section, $forcedeleteifnotempty);

        $exported = $this->modules->export_section($section);
        $deleted = course_delete_section($course, $section, $forcedeleteifnotempty, false);
        if (!$deleted) {
            throw new moodle_exception('errordeletesectionfailed', 'local_espace');
        }

        $this->modules->rebuild_course_cache($course->id);

        $event = section_deleted::create([
            'context' => $context,
            'objectid' => $exported['id'],
            'other' => [
                'courseid' => $course->id,
                'sectionnum' => $exported['section'],
                'name' => $exported['name'],
            ],
        ]);
        $event->trigger();

        return ApiResponse::success('delete_section', [
            'sectionid' => $exported['id'],
            'courseid' => $course->id,
            'sectionnum' => $exported['section'],
            'deleted' => true,
        ]);
    }

    /**
     * Get a single section by id.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return array
     */
    public function get(int $courseid, int $sectionid): array {
        $this->permissions->require_section_management($courseid);
        [$course, $section] = $this->sectionvalidator->require_section($courseid, $sectionid);
        return ApiResponse::success('get_section', [
            'section' => $this->modules->export_section($section),
            'courseid' => (int) $course->id,
        ]);
    }

    /**
     * List all sections in a course.
     *
     * @param int $courseid
     * @return array
     */
    public function list(int $courseid): array {
        $this->permissions->require_section_management($courseid);
        $course = $this->coursevalidator->require_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $sections[] = $this->modules->export_section($section);
        }
        return ApiResponse::success('list_sections', [
            'courseid' => (int) $course->id,
            'sections' => $sections,
            'count' => count($sections),
        ]);
    }

    /**
     * Internal visibility setter shared by hide/show.
     *
     * @param int $courseid
     * @param int $sectionid
     * @param int $visible
     * @return array
     */
    private function set_visibility(int $courseid, int $sectionid, int $visible): array {
        $context = $this->permissions->require_section_visibility($courseid);
        [$course, $section] = $this->sectionvalidator->require_section($courseid, $sectionid);
        $visible = $this->sectionvalidator->validate_visibility($visible);

        set_section_visible($course->id, (int) $section->section, $visible);
        $this->modules->rebuild_course_cache($course->id);
        $section = $this->modules->get_section_by_id($course->id, $sectionid);
        $exported = $this->modules->export_section($section);

        $event = section_visibility_changed::create([
            'context' => $context,
            'objectid' => $exported['id'],
            'other' => [
                'courseid' => $course->id,
                'sectionnum' => $exported['section'],
                'visible' => $visible,
            ],
        ]);
        $event->trigger();

        $operation = $visible ? 'show_section' : 'hide_section';
        return ApiResponse::success($operation, ['section' => $exported]);
    }
}
