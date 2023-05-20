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
 * Manual assignment source test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\source\selfassignment
 */
final class selfassignment_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_get_type() {
        $this->assertSame('selfassignment', selfassignment::get_type());
    }

    public function test_is_new_alloved() {
        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        $certification = $generator->create_certification();

        $this->assertTrue(selfassignment::is_new_allowed($certification));
    }

    public function test_can_user_request() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['sources' => ['manual' => [], 'selfassignment' => []], 'public' => 1]);
        $source1m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source1a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'selfassignment'], '*', MUST_EXIST);

        $certification2 = $generator->create_certification(['sources' => ['manual' => [], 'selfassignment' => []]]);
        $source2m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source2a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification2->id, 'type' => 'selfassignment'], '*', MUST_EXIST);

        $certification3 = $generator->create_certification(['sources' => ['manual' => [], 'selfassignment' => []], 'archived' => 1]);
        $source3m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification3->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source3a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification3->id, 'type' => 'selfassignment'], '*', MUST_EXIST);

        $guest = guest_user();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $cohort1 = $this->getDataGenerator()->create_cohort();

        cohort_add_member($cohort1->id, $user1->id);

        $this->assertTrue(selfassignment::can_user_request($certification1, $source1a, $user1->id));

        // Must not be archived.

        $certification1 = certification::update_certification_general((object)['id' => $certification1->id, 'archived' => 1]);
        $this->assertFalse(selfassignment::can_user_request($certification1, $source1a, $user1->id));
        $certification1 = certification::update_certification_general((object)['id' => $certification1->id, 'archived' => 0]);

        // Real user required.

        $this->assertTrue(selfassignment::can_user_request($certification1, $source1a, $user1->id));

        $this->assertFalse(selfassignment::can_user_request($certification1, $source1a, $guest->id));

        $this->assertFalse(selfassignment::can_user_request($certification1, $source1a, 0));

        // Must be visible.

        $certification1 = certification::update_certification_visibility((object)['id' => $certification1->id,
            'public' => 1]);
        $this->assertTrue(selfassignment::can_user_request($certification1, $source1a, $user1->id));

        $certification1 = certification::update_certification_visibility((object)['id' => $certification1->id,
            'public' => 0, 'cohorts' => [$cohort1->id]]);
        $this->assertTrue(selfassignment::can_user_request($certification1, $source1a, $user1->id));

        $certification1 = certification::update_certification_visibility((object)['id' => $certification1->id,
            'public' => 0, 'cohorts' => []]);
        $this->assertFalse(selfassignment::can_user_request($certification1, $source1a, $user1->id));

        $certification1 = certification::update_certification_visibility((object)['id' => $certification1->id,
            'public' => 1, 'cohorts' => [$cohort1->id]]);
        $this->assertTrue(selfassignment::can_user_request($certification1, $source1a, $user1->id));

        // Assigned already.

        manual::assign_users($certification1->id, $source1m->id, [$user1->id]);
        $this->assertFalse(selfassignment::can_user_request($certification1, $source1a, $user1->id));

        // Max users.

        manual::assign_users($certification1->id, $source1m->id, [$user3->id]);
        $this->assertTrue(selfassignment::can_user_request($certification1, $source1a, $user2->id));

        $source1a = selfassignment::update_source((object)[
            'certificationid' => $certification1->id,
            'type' => 'selfassignment',
            'enable' => 1,
            'selfassignment_maxusers' => 2,
        ]);
        $this->assertFalse(selfassignment::can_user_request($certification1, $source1a, $user2->id));

        $source1a = selfassignment::update_source((object)[
            'certificationid' => $certification1->id,
            'type' => 'selfassignment',
            'enable' => 1,
            'selfassignment_maxusers' => 3,
        ]);
        $this->assertTrue(selfassignment::can_user_request($certification1, $source1a, $user2->id));

        // Disabled new assignments.

        $source1a = selfassignment::update_source((object)[
            'certificationid' => $certification1->id,
            'type' => 'selfassignment',
            'enable' => 1,
            'selfassignment_allowsignup' => 0,
        ]);
        $this->assertFalse(selfassignment::can_user_request($certification1, $source1a, $user2->id));
    }

    public function test_signup() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['sources' => ['manual' => [], 'selfassignment' => []], 'public' => 1]);
        $source1m = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'manual'], '*', MUST_EXIST);
        $source1a = $DB->get_record('tool_certify_sources', ['certificationid' => $certification1->id, 'type' => 'selfassignment'], '*', MUST_EXIST);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->setUser($user1);
        $assignment = selfassignment::signup($certification1->id, $source1a->id);
        $this->assertSame($user1->id, $assignment->userid);
        $this->assertSame($certification1->id, $assignment->certificationid);
        $this->assertSame($source1a->id, $assignment->sourceid);

        $assignment2 = selfassignment::signup($certification1->id, $source1a->id);
        $this->assertEquals($assignment, $assignment2);
    }
}
