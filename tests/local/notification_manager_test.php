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
final class notification_manager_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_get_all_types() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $user1 = $this->getDataGenerator()->create_user();
        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['programid1' => $program1->id, 'sources' => ['manual' => []]]);
        $source1 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification1->id], '*', MUST_EXIST);
        manual::assign_users($certification1->id, $source1->id, [$user1->id]);
        $assignment1 = $DB->get_record('tool_certify_assignments',
            ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $period1 = $DB->get_record('tool_certify_periods',
            ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);

        // Let's call all methods to make sure there are no missing strings and fatal errors.,
        // the actual returned values need to be tested elsewhere.

        $types = notification_manager::get_all_types();
        /** @var class-string<notification\base> $classname */
        foreach($types as $type => $classname) {
            $this->assertSame('tool_certify', $classname::get_component());
            $this->assertSame($type, $classname::get_notificationtype());
            $classname::get_provider();
            $classname::get_name();
            $classname::get_description();
            $classname::get_default_subject();
            $classname::get_default_body();
            $this->assertSame(-10, $classname::get_notifier($certification1, $assignment1)->id);
            $classname::get_period_placeholders($certification1, $source1, $assignment1, $period1, $user1);
            $classname::get_assignment_placeholders($certification1, $source1, $assignment1, $user1);
            $generator->create_certifiction_notification(['notificationtype' => $type, 'certificationid' => $certification1->id]);
            $classname::notify_users(null, null);
            $classname::notify_users($program1, $user1);
            $classname::delete_period_notifications($assignment1);
            $classname::delete_assignment_notifications($assignment1);
        }
    }

    public function test_get_candidate_types() {
        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['programid1' => $program1->id, 'sources' => ['manual' => []]]);

        $alltypes = notification_manager::get_all_types();
        $candidates = notification_manager::get_candidate_types($certification1->id);
        foreach ($candidates as $type => $name) {
            $this->assertIsString($name);
            $this->assertArrayHasKey($type, $alltypes);
        }
        $this->assertArrayHasKey('assignment', $candidates);

        $generator->create_certifiction_notification(['notificationtype' => 'assignment', 'certificationid' => $certification1->id]);
        $candidates = notification_manager::get_candidate_types($certification1->id);
        $this->assertArrayNotHasKey('assignment', $candidates);
    }

    public function test_get_instance_context() {
        $category1 = $this->getDataGenerator()->create_category();
        $catcontext1 = \context_coursecat::instance($category1->id);

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['contextid' => $catcontext1->id, 'programid1' => $program1->id, 'sources' => ['manual' => []]]);


        $context = notification_manager::get_instance_context($certification1->id);
        $this->assertInstanceOf(\context::class, $context);
        $this->assertEquals($certification1->contextid, $context->id);
    }

    public function test_can_view() {
        $syscontext = \context_system::instance();
        $category1 = $this->getDataGenerator()->create_category();
        $catcontext1 = \context_coursecat::instance($category1->id);

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['contextid' => $catcontext1->id, 'programid1' => $program1->id, 'sources' => ['manual' => []]]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $viewerroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/certify:view', CAP_ALLOW, $viewerroleid, $syscontext);
        role_assign($viewerroleid, $user1->id, $catcontext1->id);

        $this->setUser($user1);
        $this->assertTrue(notification_manager::can_view($certification1->id));

        $this->setUser($user2);
        $this->assertFalse(notification_manager::can_view($certification1->id));

        $this->setAdminUser();
        $this->assertTrue(notification_manager::can_view($certification1->id));
    }

    public function test_can_manage() {
        $syscontext = \context_system::instance();
        $category1 = $this->getDataGenerator()->create_category();
        $catcontext1 = \context_coursecat::instance($category1->id);

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['contextid' => $catcontext1->id, 'programid1' => $program1->id, 'sources' => ['manual' => []]]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $viewerroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/certify:edit', CAP_ALLOW, $viewerroleid, $syscontext);
        role_assign($viewerroleid, $user1->id, $catcontext1->id);

        $this->setUser($user1);
        $this->assertTrue(notification_manager::can_manage($certification1->id));

        $this->setUser($user2);
        $this->assertFalse(notification_manager::can_manage($certification1->id));

        $this->setAdminUser();
        $this->assertTrue(notification_manager::can_manage($certification1->id));
    }

    public function test_get_instance_name() {
        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['fullname' => 'Pokus', 'programid1' => $program1->id, 'sources' => ['manual' => []]]);

        $this->assertSame('Pokus', notification_manager::get_instance_name($certification1->id));
    }

    public function test_get_instance_management_url() {
        $syscontext = \context_system::instance();
        $category1 = $this->getDataGenerator()->create_category();
        $catcontext1 = \context_coursecat::instance($category1->id);

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['contextid' => $catcontext1->id, 'programid1' => $program1->id, 'sources' => ['manual' => []]]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $viewerroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/certify:view', CAP_ALLOW, $viewerroleid, $syscontext);
        role_assign($viewerroleid, $user1->id, $catcontext1->id);

        $this->setUser($user1);
        $this->assertSame('https://www.example.com/moodle/admin/tool/certify/management/certification_notifications.php?id=' . $certification1->id,
            notification_manager::get_instance_management_url($certification1->id)->out(false));

        $this->setUser($user2);
        $this->assertSame(null, notification_manager::get_instance_management_url($certification1->id));

        $this->setAdminUser();
        $this->assertSame('https://www.example.com/moodle/admin/tool/certify/management/certification_notifications.php?id=' . $certification1->id,
            notification_manager::get_instance_management_url($certification1->id)->out(false));
    }

    public function test_trigger_notifications() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['fullname' => 'Pokus', 'programid1' => $program1->id, 'sources' => ['manual' => []]]);

        $user1 = $this->getDataGenerator()->create_user();
        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['programid1' => $program1->id, 'sources' => ['manual' => []]]);
        $source1 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification1->id], '*', MUST_EXIST);
        manual::assign_users($certification1->id, $source1->id, [$user1->id]);
        $assignment1 = $DB->get_record('tool_certify_assignments',
            ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);

        $types = notification_manager::get_all_types();
        /** @var class-string<notification\base> $classname */
        foreach ($types as $type => $classname) {
            $generator->create_certifiction_notification(['notificationtype' => $type, 'certificationid' => $certification1->id]);
        }

        notification_manager::trigger_notifications(null, null);
        notification_manager::trigger_notifications($certification1->id, $user1->id);
    }

    public function test_delete_assignment_notifications() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['fullname' => 'Pokus', 'programid1' => $program1->id, 'sources' => ['manual' => []]]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $now = time();

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $program2 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['programid1' => $program1->id, 'sources' => ['manual' => []]]);
        $certification2 = $generator->create_certification(['programid1' => $program2->id, 'sources' => ['manual' => []]]);
        $source1 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $source2 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification2->id], '*', MUST_EXIST);

        $generator->create_certifiction_notification(['notificationtype' => 'assignment', 'certificationid' => $certification1->id]);
        $generator->create_certifiction_notification(['notificationtype' => 'valid', 'certificationid' => $certification1->id]);
        $generator->create_certifiction_notification(['notificationtype' => 'assignment', 'certificationid' => $certification2->id]);

        $this->assertCount(0, $DB->get_records('local_openlms_user_notified', []));

        manual::assign_users($certification1->id, $source1->id, [$user1->id]);
        $period1x1 = $DB->get_record('tool_certify_periods',
            ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period1x1->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period1x1 = period::override_dates((object)$dateoverrides);
        \tool_certify\local\notification\valid::notify_users(null, null);
        $assignment1 = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification1->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));

        manual::assign_users($certification1->id, $source1->id, [$user2->id]);
        $period1x2 = $DB->get_record('tool_certify_periods',
            ['userid' => $user2->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period1x2->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period1x2 = period::override_dates((object)$dateoverrides);
        \tool_certify\local\notification\valid::notify_users(null, null);
        $assignment2 = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification1->id, 'userid' => $user2->id], '*', MUST_EXIST);
        $this->assertCount(4, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));

        manual::assign_users($certification2->id, $source2->id, [$user1->id]);
        $period2x1 = $DB->get_record('tool_certify_periods',
            ['userid' => $user1->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period2x1->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period2x1 = period::override_dates((object)$dateoverrides);
        \tool_certify\local\notification\valid::notify_users(null, null);
        $assignment3 = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification2->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $this->assertCount(5, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(3, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));

        notification_manager::delete_assignment_notifications($assignment3);
        $this->assertCount(4, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));

        notification_manager::delete_assignment_notifications($assignment1);
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(0, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));

        notification_manager::delete_assignment_notifications($assignment2);
        $this->assertCount(0, $DB->get_records('local_openlms_user_notified', []));
    }

    public function test_delete_period_notifications() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['fullname' => 'Pokus', 'programid1' => $program1->id, 'sources' => ['manual' => []]]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $now = time();

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $program2 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['programid1' => $program1->id, 'sources' => ['manual' => []]]);
        $certification2 = $generator->create_certification(['programid1' => $program2->id, 'sources' => ['manual' => []]]);
        $source1 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $source2 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification2->id], '*', MUST_EXIST);

        $generator->create_certifiction_notification(['notificationtype' => 'assignment', 'certificationid' => $certification1->id]);
        $generator->create_certifiction_notification(['notificationtype' => 'valid', 'certificationid' => $certification1->id]);
        $generator->create_certifiction_notification(['notificationtype' => 'assignment', 'certificationid' => $certification2->id]);

        $this->assertCount(0, $DB->get_records('local_openlms_user_notified', []));

        manual::assign_users($certification1->id, $source1->id, [$user1->id]);
        $period1x1 = $DB->get_record('tool_certify_periods',
            ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period1x1->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period1x1 = period::override_dates((object)$dateoverrides);
        \tool_certify\local\notification\valid::notify_users(null, null);
        $assignment1 = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification1->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));

        manual::assign_users($certification1->id, $source1->id, [$user2->id]);
        $period1x2 = $DB->get_record('tool_certify_periods',
            ['userid' => $user2->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period1x2->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period1x2 = period::override_dates((object)$dateoverrides);
        \tool_certify\local\notification\valid::notify_users(null, null);
        $assignment2 = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification1->id, 'userid' => $user2->id], '*', MUST_EXIST);
        $this->assertCount(4, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));

        manual::assign_users($certification2->id, $source2->id, [$user1->id]);
        $period2x1 = $DB->get_record('tool_certify_periods',
            ['userid' => $user1->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period2x1->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period2x1 = period::override_dates((object)$dateoverrides);
        \tool_certify\local\notification\valid::notify_users(null, null);
        $assignment3 = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification2->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $this->assertCount(5, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(3, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));

        notification_manager::delete_period_notifications($period1x1);
        $this->assertCount(4, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));

        notification_manager::delete_assignment_notifications($period1x2);
        $this->assertCount(4, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));

        notification_manager::delete_assignment_notifications($period2x1);
        $this->assertCount(4, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));
    }

    public function test_delete_certification_notifications() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['fullname' => 'Pokus', 'programid1' => $program1->id, 'sources' => ['manual' => []]]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $now = time();

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $program2 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['programid1' => $program1->id, 'sources' => ['manual' => []]]);
        $certification2 = $generator->create_certification(['programid1' => $program2->id, 'sources' => ['manual' => []]]);
        $source1 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $source2 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification2->id], '*', MUST_EXIST);

        $generator->create_certifiction_notification(['notificationtype' => 'assignment', 'certificationid' => $certification1->id]);
        $generator->create_certifiction_notification(['notificationtype' => 'valid', 'certificationid' => $certification1->id]);
        $generator->create_certifiction_notification(['notificationtype' => 'assignment', 'certificationid' => $certification2->id]);

        $this->assertCount(0, $DB->get_records('local_openlms_user_notified', []));

        manual::assign_users($certification1->id, $source1->id, [$user1->id]);
        $period1x1 = $DB->get_record('tool_certify_periods',
            ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period1x1->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period1x1 = period::override_dates((object)$dateoverrides);
        \tool_certify\local\notification\valid::notify_users(null, null);
        $assignment1 = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification1->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));

        manual::assign_users($certification1->id, $source1->id, [$user2->id]);
        $period1x2 = $DB->get_record('tool_certify_periods',
            ['userid' => $user2->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period1x2->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period1x2 = period::override_dates((object)$dateoverrides);
        \tool_certify\local\notification\valid::notify_users(null, null);
        $assignment2 = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification1->id, 'userid' => $user2->id], '*', MUST_EXIST);
        $this->assertCount(4, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));

        manual::assign_users($certification2->id, $source2->id, [$user1->id]);
        $period2x1 = $DB->get_record('tool_certify_periods',
            ['userid' => $user1->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $dateoverrides = [
            'id' => $period2x1->id,
            'timewindowstart' => (string)($now - 1500),
            'timefrom' => (string)($now - 1000),
            'timeuntil' => (string)($now + 1000),
            'timecertified' => (string)($now - 10),
        ];
        $period2x1 = period::override_dates((object)$dateoverrides);
        \tool_certify\local\notification\valid::notify_users(null, null);
        $assignment3 = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification2->id, 'userid' => $user1->id], '*', MUST_EXIST);
        $this->assertCount(5, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(3, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(2, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));

        notification_manager::delete_certification_notifications($certification1);
        $this->assertCount(1, $DB->get_records('local_openlms_user_notified', []));
        $this->assertCount(1, $DB->get_records('local_openlms_user_notified', ['userid' => $user1->id]));
        $this->assertCount(0, $DB->get_records('local_openlms_user_notified', ['userid' => $user2->id]));

        notification_manager::delete_certification_notifications($certification2);
        $this->assertCount(0, $DB->get_records('local_openlms_user_notified', []));
    }

    public function test_get_timenotified() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['fullname' => 'Pokus', 'programid1' => $program1->id, 'sources' => ['manual' => []]]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $program1 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $program2 = $programgenerator->create_program(['fullname' => 'hokus', 'sources' => ['certify' => []]]);
        $certification1 = $generator->create_certification(['programid1' => $program1->id, 'sources' => ['manual' => []]]);
        $certification2 = $generator->create_certification(['programid1' => $program2->id, 'sources' => ['manual' => []]]);
        $source1 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $source2 = $DB->get_record('tool_certify_sources',
            ['type' => 'manual', 'certificationid' => $certification2->id], '*', MUST_EXIST);

        $generator->create_certifiction_notification(['notificationtype' => 'assignment', 'certificationid' => $certification1->id]);
        $generator->create_certifiction_notification(['notificationtype' => 'valid', 'certificationid' => $certification1->id]);
        $generator->create_certifiction_notification(['notificationtype' => 'assignment', 'certificationid' => $certification2->id]);

        $this->assertCount(0, $DB->get_records('local_openlms_user_notified', []));

        $this->setCurrentTimeStart();

        manual::assign_users($certification1->id, $source1->id, [$user1->id, $user2->id]);
        manual::assign_users($certification2->id, $source2->id, [$user1->id]);

        $this->assertTimeCurrent(notification_manager::get_timenotified($user1->id, $certification1->id, 'assignment'));
        $this->assertTimeCurrent(notification_manager::get_timenotified($user1->id, $certification2->id, 'assignment'));
        $this->assertTimeCurrent(notification_manager::get_timenotified($user2->id, $certification1->id, 'assignment'));
        $this->assertNull(notification_manager::get_timenotified($user1->id, $certification1->id, 'valid'));
        $this->assertNull(notification_manager::get_timenotified($user1->id, $certification2->id, 'valid'));
        $this->assertNull(notification_manager::get_timenotified($user2->id, $certification1->id, 'valid'));
    }
}
