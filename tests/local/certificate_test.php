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

/**
 * Certification certificate util test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\certificate
 */
final class certificate_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_is_available() {
        if (get_config('tool_certificate', 'version')) {
            $this->assertTrue(certificate::is_available());
        } else {
            $this->assertFalse(certificate::is_available());
        }
    }

    public function test_issue() {
        global $DB;

        if (!certificate::is_available()) {
            $this->markTestSkipped('tool_certificate not available');
        }

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');
        /** @var \tool_certificate_generator $certificategenerator */
        $certificategenerator = $this->getDataGenerator()->get_plugin_generator('tool_certificate');

        $now = time();

        $template1 = $certificategenerator->create_template(['name' => 't1']);
        $program1 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $data = [
            'programid1' => $program1->id,
            'sources' => ['manual' => []],
        ];
        $certification1 = $generator->create_certification($data);
        $source1 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $certification1 = certification::update_certificate($certification1->id, $template1->get_id());
        manual::assign_users($certification1->id, $source1->id, [$user1->id]);
        $period1 = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification1->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period1->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)$now,
        ];
        $period1 = period::override_dates((object)$dateoverrides);
        $this->assertSame(null, $period1->certificateissueid);

        $this->assertTrue(certificate::issue($period1->id));
        $period1 = $DB->get_record('tool_certify_periods', ['id' => $period1->id], '*', MUST_EXIST);
        $issue1 = $DB->get_record('tool_certificate_issues', ['id' => $period1->certificateissueid], '*', MUST_EXIST);
        $this->assertSame((string)$template1->get_id(), $issue1->templateid);

        $this->assertFalse(certificate::issue($period1->id));

        manual::assign_users($certification1->id, $source1->id, [$user2->id]);
        $period2 = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification1->id, 'userid' => $user2->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period2->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now + 1000),
            'timeuntil' => (string)($now + 2000),
            'timecertified' => (string)$now,
        ];
        $period2 = period::override_dates((object)$dateoverrides);
        $this->assertTrue(certificate::issue($period2->id));
        $period2 = $DB->get_record('tool_certify_periods', ['id' => $period2->id], '*', MUST_EXIST);
        $issue2 = $DB->get_record('tool_certificate_issues', ['id' => $period2->certificateissueid], '*', MUST_EXIST);
        $this->assertSame((string)$template1->get_id(), $issue2->templateid);

        manual::assign_users($certification1->id, $source1->id, [$user3->id]);
        $period3 = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification1->id, 'userid' => $user3->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period3->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 3000),
            'timecertified' => (string)$now,
            'timerevoked' => (string)$now,
        ];
        $period3 = period::override_dates((object)$dateoverrides);
        $this->assertFalse(certificate::issue($period3->id));

        $dateoverrides = [
            'id' => $period3->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 3000),
            'timecertified' => null,
            'timerevoked' => null,
        ];
        $period3 = period::override_dates((object)$dateoverrides);
        $this->assertFalse(certificate::issue($period3->id));

        $dateoverrides = [
            'id' => $period3->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 3000),
            'timecertified' => (string)$now,
        ];
        $certification1 = certification::update_certificate($certification1->id, null);
        $period3 = period::override_dates((object)$dateoverrides);
        $this->assertFalse(certificate::issue($period3->id));

        $this->assertFalse(certificate::issue(99999999));
    }

    public function test_revoke() {
        global $DB;

        if (!certificate::is_available()) {
            $this->markTestSkipped('tool_certificate not available');
        }

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');
        /** @var \tool_certificate_generator $certificategenerator */
        $certificategenerator = $this->getDataGenerator()->get_plugin_generator('tool_certificate');

        $now = time();

        $template1 = $certificategenerator->create_template(['name' => 't1']);
        $program1 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();

        $data = [
            'programid1' => $program1->id,
            'sources' => ['manual' => []],
        ];
        $certification1 = $generator->create_certification($data);
        $source1 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $certification1 = certification::update_certificate($certification1->id, $template1->get_id());
        manual::assign_users($certification1->id, $source1->id, [$user1->id]);
        $period1 = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification1->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $assignment1 = $DB->get_record('tool_certify_assignments',
            ['certificationid' => $certification1->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period1->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)$now,
        ];
        $period1 = period::override_dates((object)$dateoverrides);
        $this->assertSame(null, $period1->certificateissueid);

        $this->assertTrue(certificate::issue($period1->id));
        $period1 = $DB->get_record('tool_certify_periods', ['id' => $period1->id], '*', MUST_EXIST);
        $this->assertNotEmpty($period1->certificateissueid);

        certificate::revoke($period1->id);
        $this->assertFalse($DB->record_exists('tool_certificate_issues', ['id' => $period1->certificateissueid]));
        $period1 = $DB->get_record('tool_certify_periods', ['id' => $period1->id], '*', MUST_EXIST);
        $this->assertSame(null, $period1->certificateissueid);

        $this->assertTrue(certificate::issue($period1->id));
        $period1 = $DB->get_record('tool_certify_periods', ['id' => $period1->id], '*', MUST_EXIST);
        $this->assertNotEmpty($period1->certificateissueid);
        $dateoverrides = [
            'id' => $period1->id,
            'timerevoked' => (string)$now,
        ];
        $period1x = period::override_dates((object)$dateoverrides);
        $this->assertFalse($DB->record_exists('tool_certificate_issues', ['id' => $period1->certificateissueid]));
        $this->assertSame(null, $period1x->certificateissueid);

        $dateoverrides = [
            'id' => $period1->id,
            'timerevoked' => null,
        ];
        $period1 = period::override_dates((object)$dateoverrides);
        $this->assertTrue(certificate::issue($period1->id));
        $period1 = $DB->get_record('tool_certify_periods', ['id' => $period1->id], '*', MUST_EXIST);
        $this->assertNotEmpty($period1->certificateissueid);

        period::delete($period1->id);;
        $this->assertFalse($DB->record_exists('tool_certify_periods', ['id' => $period1->id]));
        $this->assertFalse($DB->record_exists('tool_certificate_issues', ['id' => $period1->certificateissueid]));

        $data = [
            'certificationid' => $certification1->id,
            'userid' => $user1->id,
            'programid' => $program1->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)$now,
        ];
        $period1 = period::add((object)$data);
        $this->assertTrue(certificate::issue($period1->id));
        $period1 = $DB->get_record('tool_certify_periods', ['id' => $period1->id], '*', MUST_EXIST);

        manual::unassign_user($certification1, $source1, $assignment1);
        $this->assertFalse($DB->record_exists('tool_certify_periods', ['id' => $period1->id]));
        $this->assertFalse($DB->record_exists('tool_certificate_issues', ['id' => $period1->certificateissueid]));
    }

    public function test_template_deleted() {
        global $DB;

        if (!certificate::is_available()) {
            $this->markTestSkipped('tool_certificate not available');
        }

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');
        /** @var \tool_certificate_generator $certificategenerator */
        $certificategenerator = $this->getDataGenerator()->get_plugin_generator('tool_certificate');

        $now = time();

        $template1 = $certificategenerator->create_template(['name' => 't1']);
        $program1 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();

        $data = [
            'programid1' => $program1->id,
            'sources' => ['manual' => []],
        ];
        $certification1 = $generator->create_certification($data);
        $source1 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $certification1 = certification::update_certificate($certification1->id, $template1->get_id());
        manual::assign_users($certification1->id, $source1->id, [$user1->id]);
        $period1 = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification1->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $assignment1 = $DB->get_record('tool_certify_assignments',
            ['certificationid' => $certification1->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period1->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)$now,
        ];
        $period1 = period::override_dates((object)$dateoverrides);
        $this->assertTrue(certificate::issue($period1->id));
        $period1 = $DB->get_record('tool_certify_periods', ['id' => $period1->id], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('tool_certificate_issues', ['id' => $period1->certificateissueid]));

        $template1->delete();
        $this->assertFalse($DB->record_exists('tool_certificate_issues', ['id' => $period1->certificateissueid]));
        $period1 = $DB->get_record('tool_certify_periods', ['id' => $period1->id], '*', MUST_EXIST);
        $this->assertSame(null, $period1->certificateissueid);
    }

    public function test_cron() {
        global $DB;

        certificate::cron();

        if (!certificate::is_available()) {
            $this->markTestSkipped('tool_certificate not available');
        }

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');
        /** @var \tool_certificate_generator $certificategenerator */
        $certificategenerator = $this->getDataGenerator()->get_plugin_generator('tool_certificate');

        $now = time();

        $template1 = $certificategenerator->create_template(['name' => 't1']);
        $program1 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        $user5 = $this->getDataGenerator()->create_user();
        $user6 = $this->getDataGenerator()->create_user();

        $data = [
            'programid1' => $program1->id,
            'sources' => ['manual' => []],
        ];
        $certification1 = $generator->create_certification($data);
        $certification2 = $generator->create_certification($data);
        $source1 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $source2 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $certification1 = certification::update_certificate($certification1->id, $template1->get_id());
        $certification2 = certification::update_certificate($certification2->id, $template1->get_id());

        manual::assign_users($certification1->id, $source1->id, [$user1->id, $user2->id, $user3->id, $user4->id, $user5->id]);
        manual::assign_users($certification2->id, $source2->id, [$user6->id]);
        $period1 = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification1->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $period2 = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification1->id, 'userid' => $user2->id], '*', MUST_EXIST);
        $period3 = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification1->id, 'userid' => $user3->id], '*', MUST_EXIST);
        $period4 = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification1->id, 'userid' => $user4->id], '*', MUST_EXIST);
        $period5 = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification1->id, 'userid' => $user5->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period1->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period1 = period::override_dates((object)$dateoverrides);
        $dateoverrides = [
            'id' => $period2->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now + 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period2 = period::override_dates((object)$dateoverrides);
        $dateoverrides = [
            'id' => $period3->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now + 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now + 100),
        ];
        $period3 = period::override_dates((object)$dateoverrides);
        $dateoverrides = [
            'id' => $period4->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
            'timerevoked' => $now,
        ];
        $period4 = period::override_dates((object)$dateoverrides);
        $dateoverrides = [
            'id' => $period5->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period5 = period::override_dates((object)$dateoverrides);
        $assignment5 = $DB->get_record('tool_certify_assignments',
            ['certificationid' => $certification1->id, 'userid' => $user5->id], '*', MUST_EXIST);
        $assignment5 = assignment::update_user((object)['id' => $assignment5->id, 'archived' => 1]);
        $period6 = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification2->id, 'userid' => $user6->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period6->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)$now,
        ];
        $period6 = period::override_dates((object)$dateoverrides);
        $certification2 = certification::update_certification_general((object)['id' => $certification2->id, 'archived' => 1]);

        certificate::cron();

        $period1 = $DB->get_record('tool_certify_periods', ['id' => $period1->id], '*', MUST_EXIST);
        $period2 = $DB->get_record('tool_certify_periods', ['id' => $period2->id], '*', MUST_EXIST);
        $period3 = $DB->get_record('tool_certify_periods', ['id' => $period3->id], '*', MUST_EXIST);
        $period4 = $DB->get_record('tool_certify_periods', ['id' => $period4->id], '*', MUST_EXIST);
        $period5 = $DB->get_record('tool_certify_periods', ['id' => $period5->id], '*', MUST_EXIST);
        $period6 = $DB->get_record('tool_certify_periods', ['id' => $period6->id], '*', MUST_EXIST);

        $this->assertNotEmpty($period1->certificateissueid);
        $this->assertNotEmpty($period2->certificateissueid);
        $this->assertNotEmpty($period3->certificateissueid);
        $this->assertNull($period4->certificateissueid);
        $this->assertNull($period5->certificateissueid);
        $this->assertNull($period6->certificateissueid);

        certificate::cron();

        $period1x = $DB->get_record('tool_certify_periods', ['id' => $period1->id], '*', MUST_EXIST);
        $period2x = $DB->get_record('tool_certify_periods', ['id' => $period2->id], '*', MUST_EXIST);
        $period3x = $DB->get_record('tool_certify_periods', ['id' => $period3->id], '*', MUST_EXIST);
        $period4 = $DB->get_record('tool_certify_periods', ['id' => $period4->id], '*', MUST_EXIST);
        $period5 = $DB->get_record('tool_certify_periods', ['id' => $period5->id], '*', MUST_EXIST);
        $period6 = $DB->get_record('tool_certify_periods', ['id' => $period6->id], '*', MUST_EXIST);
        $this->assertSame($period1->certificateissueid, $period1x->certificateissueid);
        $this->assertSame($period2->certificateissueid, $period2x->certificateissueid);
        $this->assertSame($period3->certificateissueid, $period3x->certificateissueid);
        $this->assertNull($period4->certificateissueid);
        $this->assertNull($period5->certificateissueid);
        $this->assertNull($period6->certificateissueid);

        // Test removing of invalid certificates
        $DB->delete_records('tool_certificate_issues', ['id' => $period1->certificateissueid]);
        $DB->set_field('tool_certify_periods', 'timerevoked', $now, ['id' => $period2->id]);
        $DB->set_field('tool_certify_periods', 'certificateissueid', '9999999999', ['id' => $period3->certificateissueid]);

        certificate::cron();

        $period1x = $DB->get_record('tool_certify_periods', ['id' => $period1->id], '*', MUST_EXIST);
        $period2x = $DB->get_record('tool_certify_periods', ['id' => $period2->id], '*', MUST_EXIST);
        $period3x = $DB->get_record('tool_certify_periods', ['id' => $period3->id], '*', MUST_EXIST);
        $period4 = $DB->get_record('tool_certify_periods', ['id' => $period4->id], '*', MUST_EXIST);
        $period5 = $DB->get_record('tool_certify_periods', ['id' => $period5->id], '*', MUST_EXIST);
        $period6 = $DB->get_record('tool_certify_periods', ['id' => $period6->id], '*', MUST_EXIST);
        $this->assertNotEmpty($period1x->certificateissueid);
        $this->assertNotEquals($period1->certificateissueid, $period1x->certificateissueid);
        $this->assertTrue($DB->record_exists('tool_certificate_issues', ['id' => $period1x->certificateissueid]));
        $this->assertSame(null, $period2x->certificateissueid);
        $this->assertFalse($DB->record_exists('tool_certificate_issues', ['id' => $period2->certificateissueid]));
        $this->assertTrue($DB->record_exists('tool_certificate_issues', ['id' => $period3x->certificateissueid]));
        $this->assertNull($period4->certificateissueid);
        $this->assertNull($period5->certificateissueid);
        $this->assertNull($period6->certificateissueid);

        $DB->delete_records('tool_certify_periods', ['id' => $period1->id]);
        certificate::cron();
        $this->assertFalse($DB->record_exists('tool_certificate_issues', ['id' => $period1->certificateissueid]));

        $dateoverrides = [
            'id' => $period4->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
            'timerevoked' => null,
        ];
        $period4 = period::override_dates((object)$dateoverrides);
        (new \tool_certify\task\cron())->execute();
        $period2 = $DB->get_record('tool_certify_periods', ['id' => $period2->id], '*', MUST_EXIST);
        $period3 = $DB->get_record('tool_certify_periods', ['id' => $period3->id], '*', MUST_EXIST);
        $period4 = $DB->get_record('tool_certify_periods', ['id' => $period4->id], '*', MUST_EXIST);
        $period5 = $DB->get_record('tool_certify_periods', ['id' => $period5->id], '*', MUST_EXIST);
        $period6 = $DB->get_record('tool_certify_periods', ['id' => $period6->id], '*', MUST_EXIST);
        $this->assertNull($period2->certificateissueid);
        $this->assertNotEmpty($period3->certificateissueid);
        $this->assertNotEmpty($period4->certificateissueid);
        $this->assertNull($period5->certificateissueid);
        $this->assertNull($period6->certificateissueid);
    }
}
