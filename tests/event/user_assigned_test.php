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

namespace tool_certify\event;

use tool_certify\local\certification;

/**
 * Certification event test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\event\user_assigned
 */
final class user_assigned_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_assign_user() {
        global $DB;
        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');
        /** @var \mod_forum_generator $forumgenerator */

        $syscontext = \context_system::instance();
        $user = $this->getDataGenerator()->create_user();
        $program = $programgenerator->create_program(['sources' => 'certify', 'archived' => 1]);
        $certification = $generator->create_certification([
            'sources' => 'manual',
            'programid1' => $program->id,
            'contextid' => $syscontext->id,
        ]);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);

        $this->setAdminUser();
        $sink = $this->redirectEvents();
        \tool_certify\local\source\manual::assign_users($certification->id, $source->id, [$user->id], []);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $assignment = $DB->get_record('tool_certify_assignments',
            ['userid' => $user->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertInstanceOf(user_assigned::class, $event);
        $this->assertEquals($syscontext->id, $event->contextid);
        $this->assertSame($assignment->id, $event->objectid);
        $this->assertSame($user->id, $event->relateduserid);
        $this->assertSame('c', $event->crud);
        $this->assertSame($event::LEVEL_OTHER, $event->edulevel);
        $this->assertSame('tool_certify_assignments', $event->objecttable);
        $this->assertSame('User assigned to certification', $event::get_name());
        $description = $event->get_description();
        $certificationurl = new \moodle_url('/admin/tool/certify/management/user_assignment.php', ['id' => $assignment->id]);
        $this->assertSame($certificationurl->out(false), $event->get_url()->out(false));
    }
}
