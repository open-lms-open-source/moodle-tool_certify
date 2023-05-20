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

namespace tool_certify\local\source;

use tool_certify\local\certification;

/**
 * Certification manual assignment source test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\source\manual
 */
final class manual_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_get_type() {
        $this->assertSame('manual', manual::get_type());
    }

    public function test_get_name() {
        $this->assertSame('Manual assignment', manual::get_name());
    }

    public function test_is_new_allowed() {
        $certification = new \stdClass();
        $this->assertSame(true, manual::is_new_allowed($certification));
    }

    public function test_is_update_allowed() {
        $certification = new \stdClass();
        $this->assertSame(true, manual::is_update_allowed($certification));
    }

    public function test_fix_assignments() {
        $result = manual::fix_assignments(null, null);
        $this->assertFalse($result);
    }

    public function test_get_extra_management_tabs() {
        $certification = new \stdClass();
        $this->assertSame([], manual::get_extra_management_tabs($certification));
    }

    public function test_assignment_edit_supported() {
        $certification = new \stdClass();
        $source = new \stdClass();
        $assignment = new \stdClass();
        $result = manual::assignment_edit_supported($certification, $source, $assignment);
        $this->assertTrue($result);
    }

    public function test_assignment_delete_supported() {
        $certification = new \stdClass();
        $source = new \stdClass();
        $assignment = new \stdClass();

        $assignment->archived = '0';
        $result = manual::assignment_delete_supported($certification, $source, $assignment);
        $this->assertTrue($result);

        $assignment->archived = '1';
        $result = manual::assignment_delete_supported($certification, $source, $assignment);
        $this->assertTrue($result);
    }

    public function test_is_assignment_possible() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();

        $data = [
            'sources' => ['manual' => []],
            'programid1' => $program1->id,
            'periods_due1' => DAYSECS,
            'periods_windowend1' => ['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P2D'],
            'periods_expiration1' => ['since' => certification::SINCE_NEVER, 'delay' => null],
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);

        $result = manual::is_assignment_possible($certification, $source);
        $this->assertTrue($result);

        $certification->archived = '1';
        $result = manual::is_assignment_possible($certification, $source);
        $this->assertFalse($result);
        $certification->archived = '0';

        $certification->programid1 = null;
        $result = manual::is_assignment_possible($certification, $source);
        $this->assertFalse($result);
    }

    public function test_get_catalogue_actions() {
        $certification = new \stdClass();
        $source = new \stdClass();
        $this->assertSame([], manual::get_catalogue_actions($certification, $source));
    }

    public function test_decode_datajson() {
        $source = new \stdClass();
        $this->assertSame($source, manual::decode_datajson($source));
    }

    public function test_encode_datajson() {
        $formdata = new \stdClass();
        $this->assertSame('[]', manual::encode_datajson($formdata));
    }

    public function test_get_management_certification_users_buttons() {
        global $DB;

        $category = $this->getDataGenerator()->create_category([]);
        $catcontext = \context_coursecat::instance($category->id);
        $syscontext = \context_system::instance();

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $editorroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/certify:assign', CAP_ALLOW, $editorroleid, $syscontext);
        role_assign($editorroleid, $user1->id, $catcontext->id);

        $data = [
            'sources' => ['manual' => []],
            'programid1' => $program1->id,
            'contextid' => $catcontext->id,
            'periods_due1' => DAYSECS,
            'periods_windowend1' => ['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P2D'],
            'periods_expiration1' => ['since' => certification::SINCE_NEVER, 'delay' => null],
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);

        $this->setUser($user2);
        $result = manual::get_management_certification_users_buttons($certification, $source);
        $this->assertCount(0, $result);

        $this->setUser($user1);
        $result = manual::get_management_certification_users_buttons($certification, $source);
        $this->assertCount(1, $result);

        $certification->archived = '1';
        $result = manual::get_management_certification_users_buttons($certification, $source);
        $this->assertCount(0, $result);
        $certification->archived = '0';
    }

    public function test_update_source() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $data = [
            'sources' => ['manual' => []],
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);

        $data = [
            'certificationid' => $certification->id,
            'type' => 'manual',
            'enable' => 0,
        ];
        $source = manual::update_source((object)$data);
        $this->assertSame(null, $source);

        $data = [
            'certificationid' => $certification->id,
            'type' => 'manual',
            'enable' => 1,
        ];
        $source = manual::update_source((object)$data);
        $this->assertSame($certification->id, $source->certificationid);
        $this->assertSame('manual', $source->type);
    }

    public function test_assign_users() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $data = [
            'sources' => ['manual' => []],
            'programid1' => $program1->id,
            'periods_due1' => DAYSECS,
            'periods_windowend1' => ['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P2D'],
            'periods_expiration1' => ['since' => certification::SINCE_NEVER, 'delay' => null],
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);

        $this->setCurrentTimeStart();
        manual::assign_users($certification->id, $source->id, [$user1->id]);
        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame($source->id, $assignment->sourceid);
        $this->assertSame('[]', $assignment->sourcedatajson);
        $this->assertSame('0', $assignment->archived);
        $this->assertSame(null, $assignment->timecertifieduntil);
        $this->assertSame('[]', $assignment->evidencejson);
        $this->assertTimeCurrent($assignment->timecreated);
        $period = $DB->get_record('tool_certify_periods', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame($program1->id, $period->programid);
        $this->assertTimeCurrent($period->timewindowstart);
        $this->assertSame((string)($period->timewindowstart + DAYSECS), $period->timewindowdue);
        $this->assertSame((string)($period->timewindowstart + 2 * DAYSECS), $period->timewindowend);
        $this->assertSame(null, $period->allocationid);
        $this->assertSame(null, $period->timecertified);
        $this->assertSame(null, $period->timefrom);
        $this->assertSame(null, $period->timeuntil);
        $this->assertSame(null, $period->timerevoked);
        $this->assertSame('1', $period->first);
        $this->assertSame('1', $period->recertifiable);

        $now = time();
        $this->setCurrentTimeStart();
        manual::assign_users($certification->id, $source->id, [$user2->id], [
            'timewindowstart' => $now - DAYSECS,
            'timewindowdue' => null,
            'timewindowend' => $now + DAYSECS
        ]);
        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame($source->id, $assignment->sourceid);
        $this->assertSame('[]', $assignment->sourcedatajson);
        $this->assertSame('0', $assignment->archived);
        $this->assertSame(null, $assignment->timecertifieduntil);
        $this->assertSame('[]', $assignment->evidencejson);
        $this->assertTimeCurrent($assignment->timecreated);
        $period = $DB->get_record('tool_certify_periods', ['userid' => $user2->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame($program1->id, $period->programid);
        $this->assertSame((string)($now - DAYSECS), $period->timewindowstart);
        $this->assertSame(null, $period->timewindowdue);
        $this->assertSame((string)($now + DAYSECS), $period->timewindowend);
        $this->assertSame(null, $period->allocationid);
        $this->assertSame(null, $period->timecertified);
        $this->assertSame(null, $period->timefrom);
        $this->assertSame(null, $period->timeuntil);
        $this->assertSame(null, $period->timerevoked);
        $this->assertSame('1', $period->first);
        $this->assertSame('1', $period->recertifiable);

        $data = [
            'sources' => ['manual' => []],
            'programid1' => null,
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);

        manual::assign_users($certification->id, $source->id, [$user1->id]);
        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame($source->id, $assignment->sourceid);
        $this->assertSame('[]', $assignment->sourcedatajson);
        $this->assertSame('0', $assignment->archived);
        $this->assertSame(null, $assignment->timecertifieduntil);
        $this->assertSame('[]', $assignment->evidencejson);
        $this->assertTimeCurrent($assignment->timecreated);
        $period = $DB->get_record('tool_certify_periods', ['userid' => $user1->id, 'certificationid' => $certification->id]);
        $this->assertFalse($period);

        $now = time();
        manual::assign_users($certification->id, $source->id, [$user2->id], [
            'timecertifieduntil' => $now + WEEKSECS,
        ]);
        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame($source->id, $assignment->sourceid);
        $this->assertSame('[]', $assignment->sourcedatajson);
        $this->assertSame('0', $assignment->archived);
        $this->assertSame((string)($now + WEEKSECS), $assignment->timecertifieduntil);
        $this->assertSame('[]', $assignment->evidencejson);
        $this->assertTimeCurrent($assignment->timecreated);
        $period = $DB->get_record('tool_certify_periods', ['userid' => $user2->id, 'certificationid' => $certification->id]);
        $this->assertFalse($period);
    }

    public function test_unassign_user() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $data = [
            'sources' => ['manual' => []],
            'programid1' => $program1->id,
            'periods_due1' => DAYSECS,
            'periods_windowend1' => ['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P2D'],
            'periods_expiration1' => ['since' => certification::SINCE_NEVER, 'delay' => null],
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->setCurrentTimeStart();
        manual::assign_users($certification->id, $source->id, [$user1->id, $user2->id]);
        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertCount(1, $DB->get_records('tool_certify_periods', ['userid' => $user1->id, 'certificationid' => $certification->id]));

        manual::unassign_user($certification, $source, $assignment);
        $this->assertCount(0, $DB->get_records('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id]));
        $this->assertCount(0, $DB->get_records('tool_certify_periods', ['userid' => $user1->id, 'certificationid' => $certification->id]));
        $this->assertCount(1, $DB->get_records('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification->id]));
        $this->assertCount(1, $DB->get_records('tool_certify_periods', ['userid' => $user2->id, 'certificationid' => $certification->id]));
    }

    public function test_render_status_details() {
        $certification = new \stdClass();
        $source = new \stdClass();
        $this->assertSame('Active', manual::render_status_details($certification, $source));
        $this->assertSame('Inactive', manual::render_status_details($certification, null));
    }

    public function test_render_status() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $category = $this->getDataGenerator()->create_category([]);
        $catcontext = \context_coursecat::instance($category->id);
        $syscontext = \context_system::instance();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $editorroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/certify:edit', CAP_ALLOW, $editorroleid, $syscontext);
        role_assign($editorroleid, $user1->id, $catcontext->id);

        $data = [
            'contextid' => $catcontext->id,
            'sources' => ['manual' => []],
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);

        $this->setUser($user2);
        $this->assertSame('Active', manual::render_status($certification, $source));
        $this->assertSame('Inactive', manual::render_status($certification, null));

        $this->setUser($user1);
        $this->assertStringStartsWith('Active', manual::render_status($certification, $source));
        $this->assertStringContainsString('"Update Manual assignment"', manual::render_status($certification, $source));
        $this->assertStringStartsWith('Inactive', manual::render_status($certification, null));
        $this->assertStringContainsString('"Update Manual assignment"', manual::render_status($certification, null));
    }

    public function test_get_assigner() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $admin = get_admin();

        $data = [
            'sources' => ['manual' => []],
            'programid1' => $program1->id,
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);
        manual::assign_users($certification->id, $source->id, [$user1->id]);
        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);

        $this->setUser(null);
        $result = manual::get_assigner($certification, $source, $assignment);
        $this->assertSame($admin->id, $result->id);

        $this->setUser($user2);
        $result = manual::get_assigner($certification, $source, $assignment);
        $this->assertSame($user2->id, $result->id);

        $this->setGuestUser();
        $result = manual::get_assigner($certification, $source, $assignment);
        $this->assertSame($admin->id, $result->id);
    }
}
