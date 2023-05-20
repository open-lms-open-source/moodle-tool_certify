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

namespace tool_certify\local\notification;

/**
 * Certification notification test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\notification\unassignment
 */
final class unassignment_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_notification() {
        global $DB, $CFG;
        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');
        /** @var \mod_forum_generator $forumgenerator */

        $syscontext = \context_system::instance();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $program = $programgenerator->create_program(['sources' => 'certify', 'archived' => 1]);
        $certification = $generator->create_certification([
            'sources' => 'manual',
            'programid1' => $program->id,
            'contextid' => $syscontext->id,
        ]);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);

        \tool_certify\local\source\manual::assign_users($certification->id, $source->id, [$user1->id, $user2->id, $user3->id], []);
        $assignment1 = $DB->get_record('tool_certify_assignments',
            ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $assignment2 = $DB->get_record('tool_certify_assignments',
            ['userid' => $user2->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $assignment3 = $DB->get_record('tool_certify_assignments',
            ['userid' => $user3->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $assignment3->archived = '1';
        $DB->update_record('tool_certify_assignments', $assignment3);

        $this->setAdminUser();

        $sink = $this->redirectMessages();
        \tool_certify\local\source\manual::unassign_user($certification, $source, $assignment1);
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(0, $messages);

        $notification = $generator->create_certifiction_notification(['certificationid' => $certification->id, 'notificationtype' => 'unassignment']);

        $sink = $this->redirectMessages();
        \tool_certify\local\source\manual::unassign_user($certification, $source, $assignment2);
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame($user2->id, $message->useridto);
        $this->assertSame('Certification un-assignment notification', $message->subject);
        $this->assertStringContainsString('you have been un-assigned from certification', $message->fullmessage);
        $this->assertSame('tool_certify', $message->component);
        $this->assertSame('unassignment_notification', $message->eventtype);
        $this->assertSame("$CFG->wwwroot/admin/tool/certify/catalogue/certification.php?id=$certification->id", $message->contexturl);
        $this->assertSame('1', $message->notification);

        $sink = $this->redirectMessages();
        \tool_certify\local\source\manual::unassign_user($certification, $source, $assignment3);
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame($user3->id, $message->useridto);
        $this->assertSame('Certification un-assignment notification', $message->subject);
        $this->assertStringContainsString('you have been un-assigned from certification', $message->fullmessage);
        $this->assertSame('tool_certify', $message->component);
        $this->assertSame('unassignment_notification', $message->eventtype);
        $this->assertSame("$CFG->wwwroot/admin/tool/certify/catalogue/certification.php?id=$certification->id", $message->contexturl);
        $this->assertSame('1', $message->notification);

        \tool_certify\local\source\manual::assign_users($certification->id, $source->id, [$user1->id], []);
        $assignment1 = $DB->get_record('tool_certify_assignments',
            ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $certification->archived = '1';
        $DB->update_record('tool_certify_certifications', $certification);
        $sink = $this->redirectMessages();
        \tool_certify\local\source\manual::unassign_user($certification, $source, $assignment1);
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(0, $messages);
    }
}
