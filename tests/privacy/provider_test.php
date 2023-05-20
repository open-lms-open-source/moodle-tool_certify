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

namespace tool_certify\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use stdClass;

/**
 * Privacy provider tests for tool_certify.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var stdClass A user who is not enrolled in any certification. */
    protected $user0;

    /** @var stdClass A user who is only enrolled in certification1. */
    protected $user1;

    /** @var stdClass A user who is only enrolled in certification2. */
    protected $user2;

    /** @var stdClass A user who is enrolled in certifications 1 and 2. */
    protected $user3;

    /** @var stdClass A test certification. */
    protected $certification1;

    /** @var stdClass A test certification. */
    protected $certification2;

    public function tearDown(): void {
        $this->user0 = null;
        $this->user1 = null;
        $this->user2 = null;
        $this->user3 = null;
        $this->certification1 = null;
        $this->certification2 = null;
        parent::tearDown();
    }

    protected function set_instance_vars(): void {
        global $DB;

        $this->resetAfterTest();

        $syscontext = \context_system::instance();
        $coursecategorycontext = \context_coursecat::instance(1);
        $generator = $this->getDataGenerator();

        // Create users.
        $this->user0 = $generator->create_user();
        $this->user1 = $generator->create_user();
        $this->user2 = $generator->create_user();
        $this->user3 = $generator->create_user();

        /** @var \tool_certify_generator $certificationgenerator */
        $certificationgenerator = $generator->get_plugin_generator('tool_certify');

        // Set up and assign users to certifications.
        $data = (object)[
            'fullname' => 'Some certification',
            'idnumber' => 'SP1',
            'contextid' => $syscontext->id,
            'sources' => ['manual' => []],
        ];
        $this->certification1 = $certificationgenerator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources', ['certificationid' => $this->certification1->id, 'type' => 'manual']);
        \tool_certify\local\source\manual::assign_users($this->certification1->id, $source->id, [$this->user1->id, $this->user3->id]);

        $data = (object)[
            'fullname' => 'Another certification',
            'idnumber' => 'AP1',
            'contextid' => $coursecategorycontext->id,
            'sources' => ['manual' => []],
        ];
        $this->certification2 = $certificationgenerator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources', ['certificationid' => $this->certification2->id, 'type' => 'manual']);
        \tool_certify\local\source\manual::assign_users($this->certification2->id, $source->id, [$this->user2->id, $this->user3->id]);
    }

    /**
     * Check that a certification context is returned if there is any user data for this user.
     */
    public function test_get_contexts_for_userid() {
        $this->set_instance_vars();
        $this->assertEmpty(provider::get_contexts_for_userid($this->user0->id));
        // Check that we only get back one context for user1.
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertCount(1, $contextlist);
        // Check that the context is returned is the expected.
        $certificationcontext = \context::instance_by_id($this->certification1->contextid);
        $this->assertEquals($certificationcontext->id, $contextlist->get_contextids()[0]);

        // Check that we get 2 contexts for user3.
        $contextlist = provider::get_contexts_for_userid($this->user3->id);
        $this->assertCount(2, $contextlist);
    }

    /**
     * Test that user data is exported correctly.
     */
    public function test_export_user_data() {
        $this->set_instance_vars();
        $certification1context = \context::instance_by_id($this->certification1->contextid);
        $certification2context = \context::instance_by_id($this->certification2->contextid);

        // Get contexts containing user data.
        $contextlist1 = provider::get_contexts_for_userid($this->user1->id);
        $this->assertEquals(1, $contextlist1->count());

        $approvedcontextlist1 = new approved_contextlist(
            $this->user1,
            'tool_certify',
            $contextlist1->get_contextids()
        );

        // Export for the approved contexts.
        provider::export_user_data($approvedcontextlist1);

        $strassignment = get_string('assignments', 'tool_certify');

        // Verify we have content in certification 1 for user1.
        $writer = writer::with_context($certification1context);
        $certificationdata = $writer->get_data([$strassignment, $this->certification1->fullname]);
        $this->assertNotEmpty($certificationdata);
        // Verify we have usrsnapshot data in certification 1 for user1.
        $this->assertNotEmpty($certificationdata->assignment->usersnapshots);

        // Verify we have nothing in certification 2 for user1.
        $writer = writer::with_context($certification2context);
        $this->assertEmpty($writer->get_data([$strassignment, $this->certification2->fullname]));
    }

    /**
     * Test deleting all user data for a specific context.
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $this->set_instance_vars();

        $certification1context = \context::instance_by_id($this->certification1->contextid);

        // Get all user assignments.
        $userassignments = $DB->get_records('tool_certify_assignments', []);
        $this->assertCount(4, $userassignments);
        // Delete everything for the first certification context.
        provider::delete_data_for_all_users_in_context($certification1context);
        // Get all user assignments match with this context.
        $userassignments = $DB->get_records('tool_certify_assignments', ['certificationid' => $this->certification1->id]);
        $this->assertCount(0, $userassignments);
        // Check for tool_certify_certs_issues and tool_certify_usr_snapshots.
        $snapshots = $DB->get_records('tool_certify_usr_snapshots', ['certificationid' => $this->certification1->id]);
        $this->assertCount(0, $snapshots);

        // Get all user assignments match with this context, check count of assignments from other contexts.
        $userassignments = $DB->get_records('tool_certify_assignments', []);
        $this->assertCount(2, $userassignments);
    }

    /**
     * This should work identical to the above test.
     */
    public function test_delete_data_for_user() {
        global $DB;
        $this->set_instance_vars();

        $certification1context = \context::instance_by_id($this->certification1->contextid);
        $certification2context = \context::instance_by_id($this->certification2->contextid);
        // Get all user enrolments.
        $userenrolments = $DB->get_records('tool_certify_assignments', []);
        $this->assertCount(4, $userenrolments);
        // Get all user enrolments match with user1.
        $userenrolments = $DB->get_records('tool_certify_assignments', ['userid' => $this->user3->id]);
        $this->assertCount(2, $userenrolments);
        // Check for tool_certify_usr_snapshots with user3.
        $snapshots = $DB->get_records('tool_certify_usr_snapshots', ['userid' => $this->user3->id]);
        $this->assertCount(2, $snapshots);

        // Delete everything for the user3 in the context.
        $approvedlist = new approved_contextlist($this->user3, 'tool_certify', [$certification1context->id, $certification2context->id]);
        provider::delete_data_for_user($approvedlist);
        // Get all user enrolments match with user3.
        $userenrolments = $DB->get_records('tool_certify_assignments', ['userid' => $this->user3->id]);
        $this->assertCount(0, $userenrolments);
        // Check for tool_certify_usr_snapshots with user3.
        $snapshots = $DB->get_records('tool_certify_usr_snapshots', ['userid' => $this->user3->id]);
        $this->assertCount(0, $snapshots);
        // Check for tool_certify_requests with user3.
        $requests = $DB->get_records('tool_certify_requests', ['userid' => $this->user3->id]);
        $this->assertCount(0, $requests);

        // Get all user enrolments accounts.
        $userenrolments = $DB->get_records('tool_certify_assignments', []);
        $this->assertCount(2, $userenrolments);
        // Check for tool_certify_usr_snapshots.
        $snapshots = $DB->get_records('tool_certify_usr_snapshots', ['userid' => $this->user1->id]);
        $this->assertCount(1, $snapshots);
    }

    /**
     * Test that only users within a certification context are fetched.
     */
    public function test_get_users_in_context() {
        global $DB;
        $component = 'tool_certify';
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $usercontext = \context_user::instance($user->id);

        $data = (object)[
            'fullname' => 'Get User certification',
            'idnumber' => 'GU1',
            'contextid' => \context_system::instance()->id,
            'sources' => ['manual' => []],
        ];
        $certification = $this->getDataGenerator()->get_plugin_generator('tool_certify')->create_certification($data);

        $certificationcontext = \context::instance_by_id($certification->contextid);

        $userlist1 = new \core_privacy\local\request\userlist($certificationcontext, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(0, $userlist1);

        // assign user to certification.
        $source = $DB->get_record('tool_certify_sources', ['certificationid' => $certification->id, 'type' => 'manual']);
        \tool_certify\local\source\manual::assign_users($certification->id, $source->id, [$user->id]);

        // The list of users within the certification context should contain user.
        provider::get_users_in_context($userlist1);
        $this->assertCount(1, $userlist1);

        $userids = $userlist1->get_userids();
        $this->assertContains((int)$user->id, $userids);

        // The list of users within the user context should be empty.
        $userlist2 = new \core_privacy\local\request\userlist($usercontext, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(0, $userlist2);
    }

    /**
     * Test that data for users in approved userlist is deleted.
     */
    public function test_delete_data_for_users() {
        global $DB;
        $this->set_instance_vars();

        $component = 'tool_certify';
        $certification1context = \context::instance_by_id($this->certification1->contextid);
        $certification2context = \context::instance_by_id($this->certification2->contextid);

        $userlist1 = new \core_privacy\local\request\userlist($certification1context, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(2, $userlist1);

        $userlist2 = new \core_privacy\local\request\userlist($certification2context, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(2, $userlist2);

        // Convert $userlist1 into an approved_contextlist.
        $approvedlist1 = new approved_userlist($certification1context, $component, $userlist1->get_userids());
        // Delete using delete_data_for_user.
        provider::delete_data_for_users($approvedlist1);
        // Re-fetch users in $certification1context.
        $userlist1 = new \core_privacy\local\request\userlist($certification1context, $component);
        provider::get_users_in_context($userlist1);
        // The user data in $certification1context should be deleted.
        $this->assertCount(0, $userlist1);
        // Check for tool_certify_usr_snapshots with user3.
        $snapshots = $DB->get_records('tool_certify_usr_snapshots', ['userid' => $this->user3->id]);
        $this->assertCount(0, $snapshots);
        // Check for tool_certify_requests with user3.
        $requests = $DB->get_records('tool_certify_requests', ['userid' => $this->user3->id]);
        $this->assertCount(0, $requests);

        // Re-fetch users in $certification2context.
        $userlist2 = new \core_privacy\local\request\userlist($certification2context, $component);
        provider::get_users_in_context($userlist2);
        // The user data in $certification2context should be still present.
        $this->assertCount(2, $userlist2);
        // Check for tool_certify_usr_snapshots.
        $snapshots = $DB->get_records('tool_certify_usr_snapshots', ['userid' => $this->user2->id]);
        $this->assertCount(1, $snapshots);

        // Convert $userlist2 into an approved_contextlist in the system context.
        $approvedlist2 = new approved_userlist($certification2context, $component, $userlist2->get_userids());
        // Delete using delete_data_for_user.
        provider::delete_data_for_users($approvedlist2);
        // Re-fetch users in $certification1context.
        $userlist2 = new \core_privacy\local\request\userlist($certification2context, $component);
        provider::get_users_in_context($userlist2);
        // The user data in systemcontext should not be deleted.
        $this->assertCount(0, $userlist2);
    }
}
