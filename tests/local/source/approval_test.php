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
 * Approval assignment source test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\source\approval
 */
final class approval_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_get_type() {
        $this->assertSame('approval', approval::get_type());
    }

    public function test_is_new_alloved() {
        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        $certification = $generator->create_certification();

        $this->assertTrue(approval::is_new_allowed($certification));
    }

    public function test_can_user_request() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['sources' => ['manual' => [], 'approval' => []], 'public' => 1]);
        $source1m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source1a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'approval'], '*', MUST_EXIST);

        $certification2 = $generator->create_certification(['sources' => ['manual' => [], 'approval' => []]]);
        $source2m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source2a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'approval'], '*', MUST_EXIST);

        $certification3 = $generator->create_certification(['sources' => ['manual' => [], 'approval' => []], 'archived' => 1]);
        $source3m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification3->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source3a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification3->id, 'type' => 'approval'], '*', MUST_EXIST);

        $guest = guest_user();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $cohort1 = $this->getDataGenerator()->create_cohort();

        cohort_add_member($cohort1->id, $user1->id);

        $this->assertTrue(approval::can_user_request($certification1, $source1a, $user1->id));

        // Must not be archived.

        $certification1 = certification::update_certification_general((object)['id' => $certification1->id, 'archived' => 1]);
        $this->assertFalse(approval::can_user_request($certification1, $source1a, $user1->id));
        $certification1 = certification::update_certification_general((object)['id' => $certification1->id, 'archived' => 0]);

        // Real user required.

        $this->assertTrue(approval::can_user_request($certification1, $source1a, $user1->id));

        $this->assertFalse(approval::can_user_request($certification1, $source1a, $guest->id));

        $this->assertFalse(approval::can_user_request($certification1, $source1a, 0));

        // Must be visible.

        $certification1 = certification::update_certification_visibility((object)['id' => $certification1->id,
            'public' => 1]);
        $this->assertTrue(approval::can_user_request($certification1, $source1a, $user1->id));

        $certification1 = certification::update_certification_visibility((object)['id' => $certification1->id,
            'public' => 0, 'cohorts' => [$cohort1->id]]);
        $this->assertTrue(approval::can_user_request($certification1, $source1a, $user1->id));

        $certification1 = certification::update_certification_visibility((object)['id' => $certification1->id,
            'public' => 0, 'cohorts' => []]);
        $this->assertFalse(approval::can_user_request($certification1, $source1a, $user1->id));

        $certification1 = certification::update_certification_visibility((object)['id' => $certification1->id,
            'public' => 1, 'cohorts' => [$cohort1->id]]);
        $this->assertTrue(approval::can_user_request($certification1, $source1a, $user1->id));

        // Assigned already.

        manual::assign_users($certification1->id, $source1m->id, [$user1->id]);
        $this->assertFalse(approval::can_user_request($certification1, $source1a, $user1->id));

        // Not rejected or pending.

        $this->assertTrue(approval::can_user_request($certification1, $source1a, $user2->id));
        $this->setUser($user2);

        $request = approval::request($certification1->id, $source1a->id);
        $this->assertFalse(approval::can_user_request($certification1, $source1a, $user2->id));

        approval::reject_request($request->id, 'oh well');
        $this->assertFalse(approval::can_user_request($certification1, $source1a, $user2->id));

        approval::delete_request($request->id);
        $this->assertTrue(approval::can_user_request($certification1, $source1a, $user2->id));

        // Disabled requests.

        $source1a = approval::update_source((object)[
            'certificationid' => $certification1->id,
            'type' => 'approval',
            'enable' => 1,
            'approval_allowrequest' => 0,
        ]);
        $this->assertFalse(approval::can_user_request($certification1, $source1a, $user2->id));
    }

    public function test_request() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['sources' => ['manual' => [], 'approval' => []], 'public' => 1]);
        $source1m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source1a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'approval'], '*', MUST_EXIST);

        $certification2 = $generator->create_certification(['sources' => ['manual' => [], 'approval' => []]]);
        $source2m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source2a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'approval'], '*', MUST_EXIST);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->setUser($user1);
        $request = approval::request($certification1->id, $source1a->id);
        $this->assertSame($source1a->id, $request->sourceid);
        $this->assertSame($user1->id, $request->userid);

        $request = approval::request($certification1->id, $source1a->id);
        $this->assertNull($request);
    }

    public function test_approve_request() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['sources' => ['manual' => [], 'approval' => []], 'public' => 1]);
        $source1m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source1a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'approval'], '*', MUST_EXIST);

        $certification2 = $generator->create_certification(['sources' => ['manual' => [], 'approval' => []]]);
        $source2m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source2a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'approval'], '*', MUST_EXIST);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $request = approval::request($certification1->id, $source1a->id);

        $this->setUser($user2);
        $assignment = approval::approve_request($request->id);
        $this->assertSame($certification1->id, $assignment->certificationid);
        $this->assertSame($source1a->id, $assignment->sourceid);
        $this->assertSame($user1->id, $assignment->userid);
        $this->assertFalse($DB->record_exists('tool_certify_requests', ['sourceid' => $source1a->id, 'userid' => $user1->id]));
    }

    public function test_reject_request() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['sources' => ['manual' => [], 'approval' => []], 'public' => 1]);
        $source1m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source1a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'approval'], '*', MUST_EXIST);

        $certification2 = $generator->create_certification(['sources' => ['manual' => [], 'approval' => []]]);
        $source2m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source2a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'approval'], '*', MUST_EXIST);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $request = approval::request($certification1->id, $source1a->id);

        $this->setUser($user2);
        $this->setCurrentTimeStart();
        approval::reject_request($request->id, 'sorry mate');
        $request = $DB->get_record('tool_certify_requests', ['sourceid' => $source1a->id, 'userid' => $user1->id]);
        $this->assertSame($source1a->id, $request->sourceid);
        $this->assertSame($user1->id, $request->userid);
        $this->assertTimeCurrent($request->timerejected);
        $this->assertFalse(approval::can_user_request($certification1, $source1a, $user1->id));
    }

    public function test_delete_request() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['sources' => ['manual' => [], 'approval' => []], 'public' => 1]);
        $source1m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source1a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'approval'], '*', MUST_EXIST);

        $certification2 = $generator->create_certification(['sources' => ['manual' => [], 'approval' => []]]);
        $source2m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source2a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'approval'], '*', MUST_EXIST);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $request = approval::request($certification1->id, $source1a->id);

        $this->setUser($user2);
        approval::delete_request($request->id);
        $this->assertTrue(approval::can_user_request($certification1, $source1a, $user1->id));
    }
}
