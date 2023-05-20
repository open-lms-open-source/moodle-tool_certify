<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_certify\local;

use tool_certify\local\source\manual;
use enrol_programs\local\program;
use enrol_programs\local\allocation;

/**
 * Program helper test.
 *
 * @group      openlms
 * @package    enrol_programs
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
final class event_observer_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * @return void
     *
     * @covers \enrol_programs\local\event_observer::group_deleted()
     */
    public function test_program_completed() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $user1 = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course([]);
        $course1context = \context_course::instance($course1->id);
        $program1 = $programgenerator->create_program(['sources' => ['certify' => []]]);
        $top1 = program::load_content($program1->id);
        $item1x1 = $top1->append_course($top1, $course1->id);

        $data = [
            'programid1' => $program1->id,
            'sources' => ['manual' => []],
        ];
        $certification1 = $generator->create_certification($data);
        $source1 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification1->id], '*', MUST_EXIST);
        manual::assign_users($certification1->id, $source1->id, [$user1->id]);
        $allocation = $DB->get_record('enrol_programs_allocations', ['programid' => $program1->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $this->assertTrue(is_enrolled($course1context, $user1));

        $period1 = $DB->get_record('tool_certify_periods',
            ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $this->assertSame(null, $period1->timecertified);

        $this->setCurrentTimeStart();
        $data = (object)[
            'allocationid' => $allocation->id,
            'timecompleted' => time() - 10,
            'itemid' => $item1x1->get_id(),
            'evidencetimecompleted' => null,
        ];
        allocation::update_item_completion($data);
        $period1 = $DB->get_record('tool_certify_periods',
            ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $this->assertTimeCurrent($period1->timecertified);
    }
}
