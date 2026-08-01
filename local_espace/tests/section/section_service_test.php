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
 * Unit tests for SectionService.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

use local_espace\service\SectionService;
use local_espace\validator\SectionValidator;

/**
 * @covers \local_espace\service\SectionService
 * @covers \local_espace\validator\SectionValidator
 */
final class section_service_test extends \advanced_testcase {

    /**
     * Reset after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('enabled', 1, 'local_espace');
        set_config('strictpermissions', 1, 'local_espace');
    }

    /**
     * Create a course with an editing teacher.
     *
     * @return array{0:\stdClass,1:\stdClass}
     */
    private function create_course_with_teacher(): array {
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        return [$course, $teacher];
    }

    /**
     * @return void
     */
    public function test_create_section_appends(): void {
        [$course] = $this->create_course_with_teacher();
        $service = new SectionService();

        $result = $service->create((int) $course->id, 0, 'Unit section', '<p>Summary</p>');

        $this->assertSame('ok', $result['status']);
        $this->assertSame('create_section', $result['operation']);
        $this->assertArrayHasKey('section', $result['data']);
        $this->assertSame('Unit section', $result['data']['section']['name']);
        $this->assertGreaterThan(0, $result['data']['section']['id']);
    }

    /**
     * @return void
     */
    public function test_rename_section(): void {
        [$course] = $this->create_course_with_teacher();
        $service = new SectionService();
        $created = $service->create((int) $course->id, 0, 'Old name');
        $sectionid = $created['data']['section']['id'];

        $renamed = $service->rename((int) $course->id, $sectionid, 'New name', null);

        $this->assertSame('ok', $renamed['status']);
        $this->assertSame('New name', $renamed['data']['section']['name']);
    }

    /**
     * @return void
     */
    public function test_hide_and_show_section(): void {
        [$course] = $this->create_course_with_teacher();
        $service = new SectionService();
        $created = $service->create((int) $course->id, 0, 'Visibility');
        $sectionid = $created['data']['section']['id'];

        $hidden = $service->hide((int) $course->id, $sectionid);
        $this->assertSame(0, $hidden['data']['section']['visible']);

        $shown = $service->show((int) $course->id, $sectionid);
        $this->assertSame(1, $shown['data']['section']['visible']);
    }

    /**
     * @return void
     */
    public function test_move_section(): void {
        [$course] = $this->create_course_with_teacher();
        $service = new SectionService();
        $created = $service->create((int) $course->id, 0, 'Movable');
        $sectionid = $created['data']['section']['id'];
        $from = $created['data']['section']['section'];

        // Move toward section 1 when possible.
        $destination = max(1, $from - 1);
        if ($destination === $from) {
            $destination = min($from + 1, 3);
        }

        $moved = $service->move((int) $course->id, $sectionid, $destination);
        $this->assertSame('ok', $moved['status']);
        $this->assertSame($destination, $moved['data']['to']);
    }

    /**
     * @return void
     */
    public function test_delete_section(): void {
        [$course] = $this->create_course_with_teacher();
        $service = new SectionService();
        $created = $service->create((int) $course->id, 0, 'Disposable');
        $sectionid = $created['data']['section']['id'];

        $deleted = $service->delete((int) $course->id, $sectionid, true);
        $this->assertTrue($deleted['data']['deleted']);

        $this->expectException(\moodle_exception::class);
        $service->get((int) $course->id, $sectionid);
    }

    /**
     * @return void
     */
    public function test_cannot_delete_general_section(): void {
        [$course] = $this->create_course_with_teacher();
        $modinfo = get_fast_modinfo($course);
        $general = $modinfo->get_section_info(0);
        $service = new SectionService();

        $this->expectException(\moodle_exception::class);
        $service->delete((int) $course->id, (int) $general->id, true);
    }

    /**
     * @return void
     */
    public function test_list_sections(): void {
        [$course] = $this->create_course_with_teacher();
        $service = new SectionService();
        $service->create((int) $course->id, 0, 'Listed');

        $list = $service->list((int) $course->id);
        $this->assertSame('ok', $list['status']);
        $this->assertGreaterThanOrEqual(2, $list['data']['count']);
    }

    /**
     * @return void
     */
    public function test_validator_rejects_empty_rename(): void {
        $validator = new SectionValidator();
        $this->expectException(\moodle_exception::class);
        $validator->validate_rename(null, null);
    }

    /**
     * @return void
     */
    public function test_plugin_disabled_blocks_operations(): void {
        [$course] = $this->create_course_with_teacher();
        set_config('enabled', 0, 'local_espace');
        $service = new SectionService();

        $this->expectException(\moodle_exception::class);
        $service->create((int) $course->id, 0, 'Should fail');
    }
}
