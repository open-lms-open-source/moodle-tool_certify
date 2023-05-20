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
 * Certification assignment test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\assignment
 */
final class assignment_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_get_source_classes() {
        $classes = assignment::get_source_classes();
        $this->assertIsArray($classes);
        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class));
        }
        $this->assertArrayHasKey('manual', $classes);
        $this->assertArrayHasKey('cohort', $classes);
        $this->assertArrayHasKey('selfassignment', $classes);
        $this->assertArrayHasKey('approval', $classes);
    }

    public function test_get_source_names() {
        $names = assignment::get_source_names();
        $this->assertSame('Manual assignment', $names['manual']);
        $this->assertSame('Automatic cohort assignment', $names['cohort']);
        $this->assertSame('Self assignment', $names['selfassignment']);
        $this->assertSame('Requests with approval', $names['approval']);
    }

    public function test_update_user() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();

        $data = [
            'sources' => ['manual' => []],
            'programid1' => $program1->id,
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);
        manual::assign_users($certification->id, $source->id, [$user1->id]);
        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);

        $data = [
            'id' => $assignment->id,
        ];
        assignment::update_user((object)$data);
        $assignment2 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame((array)$assignment, (array)$assignment2);

        $now = time();
        $data = [
            'id' => $assignment->id,
            'timecertifieduntil' => (string)($now + WEEKSECS),
        ];
        assignment::update_user((object)$data);
        $assignment2 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame($data['timecertifieduntil'], $assignment2->timecertifieduntil);
        $assignment2->timecertifieduntil = $assignment->timecertifieduntil;
        $this->assertSame((array)$assignment, (array)$assignment2);

        $data = [
            'id' => $assignment->id,
            'timecertifieduntil' => null,
            'archived' => '1',
        ];
        assignment::update_user((object)$data);
        $assignment2 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame($data['archived'], $assignment2->archived);

        $data = [
            'id' => $assignment->id,
            'archived' => '0',
        ];
        assignment::update_user((object)$data);
        $assignment2 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame((array)$assignment, (array)$assignment2);

        $period = $DB->get_record('tool_certify_periods', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame('1', $period->recertifiable);
        $data = [
            'id' => $assignment->id,
            'stoprecertify' => '1',
        ];
        assignment::update_user((object)$data);
        $period = $DB->get_record('tool_certify_periods', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame('0', $period->recertifiable);

        $data = [
            'id' => $assignment->id,
            'stoprecertify' => '0',
        ];
        assignment::update_user((object)$data);
        $period = $DB->get_record('tool_certify_periods', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame('1', $period->recertifiable);
    }

    public function test_get_status_html() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();

        $now = time();

        $data = [
            'sources' => ['manual' => []],
            'programid1' => $program1->id,
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);
        manual::assign_users($certification->id, $source->id, [$user1->id]);
        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $period = $DB->get_record('tool_certify_periods', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);

        $status = assignment::get_status_html($certification, $assignment);
        $this->assertStringContainsString('Not certified', $status);

        $certification->archived = '1';
        $status = assignment::get_status_html($certification, $assignment);
        $this->assertStringContainsString('Archived', $status);
        $certification->archived = '0';

        $assignment->archived = '1';
        $status = assignment::get_status_html($certification, $assignment);
        $this->assertStringContainsString('Archived', $status);
        $assignment->archived = '0';

        $period->timecertified = $now - HOURSECS;
        $period->timefrom = $now - DAYSECS;
        $period->timeuntil = $now + DAYSECS;
        $DB->update_record('tool_certify_periods', $period);
        $status = assignment::get_status_html($certification, $assignment);
        $this->assertStringContainsString('Valid', $status);

        $period->timerevoked = $now;
        $DB->update_record('tool_certify_periods', $period);
        $status = assignment::get_status_html($certification, $assignment);
        $this->assertStringContainsString('Not certified', $status);

        $period->timerevoked = null;
        $period->timecertified = $now - DAYSECS;
        $period->timefrom = $now - WEEKSECS;
        $period->timeuntil = $now - HOURSECS;
        $DB->update_record('tool_certify_periods', $period);
        $status = assignment::get_status_html($certification, $assignment);
        $this->assertStringContainsString('Expired', $status);

        $assignment->timecertifieduntil = $now + DAYSECS;
        $status = assignment::get_status_html($certification, $assignment);
        $this->assertStringContainsString('Temporary valid', $status);

        $assignment->timecertifieduntil = $now - 1;
        $status = assignment::get_status_html($certification, $assignment);
        $this->assertStringContainsString('Expired', $status);

        $assignment->archived = '1';
        $status = assignment::get_status_html($certification, $assignment);
        $this->assertStringContainsString('Archived', $status);
    }

    public function test_sync_current_status() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();

        $data = [
            'sources' => ['manual' => []],
            'programid1' => $program1->id,
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);
        manual::assign_users($certification->id, $source->id, [$user1->id]);

        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);

        assignment::sync_current_status($assignment);
    }

    public function test_fix_assignment_sources() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();

        $data = [
            'sources' => ['manual' => []],
            'programid1' => $program1->id,
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);
        manual::assign_users($certification->id, $source->id, [$user1->id]);

        assignment::fix_assignment_sources(null, null);
        assignment::fix_assignment_sources($certification->id, null);
        assignment::fix_assignment_sources($certification->id, $user1->id);
        assignment::fix_assignment_sources(null, $user1->id);
    }

    public function test_has_active_assignments() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();

        $data = [
            'sources' => ['manual' => []],
            'programid1' => $program1->id,
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);

        $this->assertFalse(assignment::has_active_assignments($user1->id));

        manual::assign_users($certification->id, $source->id, [$user1->id]);
        $this->assertTrue(assignment::has_active_assignments($user1->id));

        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $assignment = assignment::update_user((object)['id' => $assignment->id, 'archived' => 1]);
        $this->assertFalse(assignment::has_active_assignments($user1->id));

        $assignment = assignment::update_user((object)['id' => $assignment->id, 'archived' => 0]);
        $certification = certification::update_certification_general((object)['id' => $certification->id, 'archived' => 1]);
        $this->assertFalse(assignment::has_active_assignments($user1->id));
    }

    public function test_get_until_html() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $user1 = $this->getDataGenerator()->create_user();

        $now = time();

        $data = [
            'sources' => ['manual' => []],
            'programid1' => $program1->id,
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification->id], '*', MUST_EXIST);

        $this->assertFalse(assignment::has_active_assignments($user1->id));

        manual::assign_users($certification->id, $source->id, [$user1->id]);
        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame('Not set', assignment::get_until_html($certification, $assignment));

        $assignment = assignment::update_user((object)['id' => $assignment->id, 'timecertifieduntil' => $now + DAYSECS]);
        $this->assertSame(userdate($assignment->timecertifieduntil, get_string('strftimedatetimeshort')),
            assignment::get_until_html($certification, $assignment));

        $assignment = assignment::update_user((object)['id' => $assignment->id, 'timecertifieduntil' => null]);
        $period1 = $DB->get_record('tool_certify_periods', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period1->id,
            'timewindowstart' => (string)($now + 1500),
            'timewindowdue' => (string)($now + 2000),
            'timewindowend' => (string)($now + 3000),
            'timefrom' => (string)($now + 1000),
            'timeuntil' => (string)($now + 5000),
            'timecertified' => (string)$now,
            'timerevoked' => (string)$now,
        ];
        $period1 = period::override_dates((object)$dateoverrides);
        $this->assertSame('Not set', assignment::get_until_html($certification, $assignment));

        $dateoverrides = [
            'id' => $period1->id,
            'timewindowstart' => (string)($now + 1500),
            'timewindowdue' => (string)($now + 2000),
            'timewindowend' => (string)($now + 3000),
            'timefrom' => (string)($now + 1000),
            'timeuntil' => (string)($now + 5000),
            'timecertified' => (string)$now,
            'timerevoked' => null,
        ];
        $period1 = period::override_dates((object)$dateoverrides);
        $this->assertSame(userdate($period1->timeuntil, get_string('strftimedatetimeshort')),
            assignment::get_until_html($certification, $assignment));

        $assignment = assignment::update_user((object)['id' => $assignment->id, 'timecertifieduntil' => $now + WEEKSECS]);
        $period1 = period::override_dates((object)$dateoverrides);
        $this->assertSame(userdate($assignment->timecertifieduntil, get_string('strftimedatetimeshort')),
            assignment::get_until_html($certification, $assignment));

        $assignment = assignment::update_user((object)['id' => $assignment->id, 'timecertifieduntil' => null]);
        $dateoverrides = [
            'id' => $period1->id,
            'timewindowstart' => (string)($now + 1500),
            'timewindowdue' => (string)($now + 2000),
            'timewindowend' => (string)($now + 3000),
            'timefrom' => (string)($now + 1000),
            'timeuntil' => (string)($now + 5000),
            'timecertified' => (string)$now,
            'timerevoked' => null,
        ];
        $period1 = period::override_dates((object)$dateoverrides);
        $data = [
            'assignmentid' => $assignment->id,
            'programid' => $program1->id,
            'timewindowstart' => (string)($now + 4000),
            'timewindowdue' => null,
            'timewindowend' => null,
            'timefrom' => (string)($now + 6000),
            'timeuntil' => (string)($now + 7000),
            'timecertified' => null,
            'timerevoked' => null,
        ];
        $period2 = period::add((object)$data);
        $this->assertSame(userdate($period1->timeuntil, get_string('strftimedatetimeshort')),
            assignment::get_until_html($certification, $assignment));

        $dateoverrides = [
            'id' => $period2->id,
            'timecertified' => (string)($now + 1234),
            'timerevoked' => null,
        ];
        $period2 = period::override_dates((object)$dateoverrides);
        $this->assertSame(userdate($period2->timeuntil, get_string('strftimedatetimeshort')),
            assignment::get_until_html($certification, $assignment));

        $dateoverrides = [
            'id' => $period2->id,
            'timerevoked' => $now,
        ];
        $period2 = period::override_dates((object)$dateoverrides);
        $this->assertSame(userdate($period1->timeuntil, get_string('strftimedatetimeshort')),
            assignment::get_until_html($certification, $assignment));
    }
}
