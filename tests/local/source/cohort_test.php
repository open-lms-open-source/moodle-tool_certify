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

/**
 * Certification cohort assignment source test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\source\cohort
 */
final class cohort_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_get_type() {
        $this->assertSame('cohort', cohort::get_type());
    }

    public function test_get_name() {
        $this->assertSame('Automatic cohort assignment', cohort::get_name());
    }

    public function test_is_new_allowed() {
        $certification = new \stdClass();
        $this->assertSame(true, cohort::is_new_allowed($certification));
    }

    public function test_is_update_allowed() {
        $certification = new \stdClass();
        $this->assertSame(true, cohort::is_update_allowed($certification));
    }

    public function test_fix_assignments() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user2->id);
        cohort_add_member($cohort2->id, $user1->id);
        cohort_add_member($cohort2->id, $user2->id);
        cohort_add_member($cohort3->id, $user1->id);

        $data = [
            'sources' => ['cohort' => ['cohorts' => [$cohort1->id, $cohort2->id]]],
            'programid1' => $program1->id,
        ];
        $certification1 = $generator->create_certification($data);
        $data = [
            'sources' => ['cohort' => ['cohorts' => [$cohort2->id]]],
            'programid1' => $program2->id,
        ];
        $certification2 = $generator->create_certification($data);
        $assignment11 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $assignment12 = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $assignment21 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $assignment22 = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $this->assertFalse($DB->record_exists('tool_certify_assignments', ['userid' => $user3->id, 'certificationid' => $certification1->id]));
        $this->assertFalse($DB->record_exists('tool_certify_assignments', ['userid' => $user3->id, 'certificationid' => $certification2->id]));

        // Use low level DB edits to prevent cohort events interfering with tests.

        $DB->delete_records('tool_certify_assignments', ['id' => $assignment11->id]);
        cohort::fix_assignments($certification1->id, $user2->id);
        $this->assertFalse($DB->record_exists('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification1->id]));

        cohort::fix_assignments($certification2->id, $user1->id);
        $this->assertFalse($DB->record_exists('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification1->id]));

        cohort::fix_assignments(null, $user2->id);
        $this->assertFalse($DB->record_exists('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification1->id]));

        cohort::fix_assignments($certification2->id, null);
        $this->assertFalse($DB->record_exists('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification1->id]));

        cohort::fix_assignments($certification1->id, $user1->id);
        $assignment11 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $assignment12 = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $assignment21 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $assignment22 = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);

        $DB->set_field('tool_certify_assignments', 'archived', 1, ['id' => $assignment11->id]);
        cohort::fix_assignments($certification1->id, $user1->id);
        $assignment11 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $this->assertSame('0', $assignment11->archived);
        $assignment12 = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $this->assertSame('0', $assignment12->archived);
        $assignment21 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $this->assertSame('0', $assignment21->archived);
        $assignment22 = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $this->assertSame('0', $assignment22->archived);

        $DB->delete_records('cohort_members', ['cohortid' => $cohort2->id]);
        cohort::fix_assignments(null, null);
        $assignment11 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $this->assertSame('0', $assignment11->archived);
        $assignment12 = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $this->assertSame('0', $assignment12->archived);
        $assignment21 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $this->assertSame('1', $assignment21->archived);
        $assignment22 = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $this->assertSame('1', $assignment22->archived);
        $this->assertFalse($DB->record_exists('tool_certify_assignments', ['userid' => $user3->id, 'certificationid' => $certification1->id]));
        $this->assertFalse($DB->record_exists('tool_certify_assignments', ['userid' => $user3->id, 'certificationid' => $certification2->id]));

        $DB->insert_record('cohort_members', ['cohortid' => $cohort1->id, 'userid' => $user3->id]);
        cohort::fix_assignments(null, null);
        $assignment11 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $this->assertSame('0', $assignment11->archived);
        $assignment12 = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $this->assertSame('0', $assignment12->archived);
        $assignment13 = $DB->get_record('tool_certify_assignments', ['userid' => $user3->id, 'certificationid' => $certification1->id], '*', MUST_EXIST);
        $this->assertSame('0', $assignment13->archived);
        $assignment21 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $this->assertSame('1', $assignment21->archived);
        $assignment22 = $DB->get_record('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification2->id], '*', MUST_EXIST);
        $this->assertSame('1', $assignment22->archived);
        $this->assertFalse($DB->record_exists('tool_certify_assignments', ['userid' => $user3->id, 'certificationid' => $certification2->id]));
    }

    public function test_get_extra_management_tabs() {
        $certification = new \stdClass();
        $this->assertSame([], cohort::get_extra_management_tabs($certification));
    }

    public function test_assignment_edit_supported() {
        $certification = new \stdClass();
        $source = new \stdClass();
        $assignment = new \stdClass();
        $result = cohort::assignment_edit_supported($certification, $source, $assignment);
        $this->assertTrue($result);
    }

    public function test_assignment_delete_supported() {
        $certification = new \stdClass();
        $source = new \stdClass();
        $assignment = new \stdClass();

        $assignment->archived = '0';
        $result = cohort::assignment_delete_supported($certification, $source, $assignment);
        $this->assertFalse($result);

        $assignment->archived = '1';
        $result = cohort::assignment_delete_supported($certification, $source, $assignment);
        $this->assertTrue($result);
    }

    public function test_get_catalogue_actions() {
        $certification = new \stdClass();
        $source = new \stdClass();
        $this->assertSame([], cohort::get_catalogue_actions($certification, $source));
    }

    public function test_decode_datajson() {
        $source = new \stdClass();
        $this->assertSame($source, cohort::decode_datajson($source));
    }

    public function test_encode_datajson() {
        $formdata = new \stdClass();
        $this->assertSame('[]', cohort::encode_datajson($formdata));
    }

    public function test_get_management_certification_users_buttons() {
        $certification = new \stdClass();
        $source = new \stdClass();

        $result = cohort::get_management_certification_users_buttons($certification, $source);
        $this->assertCount(0, $result);
    }

    public function test_update_source() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();
        $cohort4 = $this->getDataGenerator()->create_cohort();

        $data = [
            'sources' => ['cohort' => []],
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'cohort', 'certificationid' => $certification->id], '*', MUST_EXIST);

        $data = [
            'certificationid' => $certification->id,
            'type' => 'cohort',
            'enable' => 0,
        ];
        $source = cohort::update_source((object)$data);
        $this->assertSame(null, $source);

        $data = [
            'certificationid' => $certification->id,
            'type' => 'cohort',
            'enable' => 1,
            'cohorts' => []
        ];
        $source = cohort::update_source((object)$data);
        $this->assertSame($certification->id, $source->certificationid);
        $this->assertSame('cohort', $source->type);

        $data = [
            'certificationid' => $certification->id,
            'type' => 'cohort',
            'enable' => 1,
            'cohorts' => [$cohort1->id, $cohort3->id],
        ];
        $source = cohort::update_source((object)$data);
        $this->assertSame($certification->id, $source->certificationid);
        $this->assertSame('cohort', $source->type);
        $cohorts = $DB->get_records_menu('tool_certify_src_cohorts', ['sourceid' => $source->id], '', 'cohortid, id');
        $this->assertCount(2, $cohorts);
        $this->assertArrayHasKey($cohort1->id, $cohorts);
        $this->assertArrayHasKey($cohort3->id, $cohorts);

        $data = [
            'certificationid' => $certification->id,
            'type' => 'cohort',
            'enable' => 1,
            'cohorts' => [$cohort2->id, $cohort3->id],
        ];
        $source = cohort::update_source((object)$data);
        $this->assertSame($certification->id, $source->certificationid);
        $this->assertSame('cohort', $source->type);
        $cohorts = $DB->get_records_menu('tool_certify_src_cohorts', ['sourceid' => $source->id], '', 'cohortid, id');
        $this->assertCount(2, $cohorts);
        $this->assertArrayHasKey($cohort2->id, $cohorts);
        $this->assertArrayHasKey($cohort3->id, $cohorts);

        $data = [
            'certificationid' => $certification->id,
            'type' => 'cohort',
            'enable' => 0,
            'cohorts' => [$cohort2->id, $cohort3->id],
        ];
        $source = cohort::update_source((object)$data);
        $this->assertSame(null, $source);
        $cohorts = $DB->get_records_menu('tool_certify_src_cohorts', [], '', 'cohortid, id');
        $this->assertCount(0, $cohorts);
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
        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user2->id);

        $data = [
            'sources' => ['cohort' => ['cohorts' => [$cohort1->id, $cohort2->id]]],
            'programid1' => $program1->id,
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'cohort', 'certificationid' => $certification->id], '*', MUST_EXIST);
        $assignment1 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $assignment2 = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertCount(2, $DB->get_records('tool_certify_periods', ['certificationid' => $certification->id]));

        cohort::unassign_user($certification, $source, $assignment1);
        $this->assertCount(0, $DB->get_records('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id]));
        $this->assertCount(0, $DB->get_records('tool_certify_periods', ['userid' => $user1->id, 'certificationid' => $certification->id]));
        $this->assertCount(1, $DB->get_records('tool_certify_assignments', ['userid' => $user2->id, 'certificationid' => $certification->id]));
        $this->assertCount(1, $DB->get_records('tool_certify_periods', ['userid' => $user2->id, 'certificationid' => $certification->id]));
    }

    public function test_render_status_details() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $data = [
            'sources' => [],
            ];
        $certification = $generator->create_certification($data);
        $this->assertSame('Inactive', cohort::render_status_details($certification, null));

        $data = [
            'sources' => ['cohort' => ['cohorts' => []]],
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'cohort', 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame('Active', cohort::render_status_details($certification, $source));

        $data = [
            'sources' => ['cohort' => ['cohorts' => [$cohort1->id, $cohort2->id]]],
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'cohort', 'certificationid' => $certification->id], '*', MUST_EXIST);
        $this->assertSame('Active (Cohort 1, Cohort 2)', cohort::render_status_details($certification, $source));
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
            'sources' => ['cohort' => []],
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'cohort', 'certificationid' => $certification->id], '*', MUST_EXIST);

        $this->setUser($user2);
        $this->assertSame('Active', cohort::render_status($certification, $source));
        $this->assertSame('Inactive', cohort::render_status($certification, null));

        $this->setUser($user1);
        $this->assertStringStartsWith('Active', cohort::render_status($certification, $source));
        $this->assertStringContainsString('"Update Automatic cohort assignment"', cohort::render_status($certification, $source));
        $this->assertStringStartsWith('Inactive', cohort::render_status($certification, null));
        $this->assertStringContainsString('"Update Automatic cohort assignment"', cohort::render_status($certification, null));
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
        $cohort1 = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort1->id, $user1->id);

        $data = [
            'sources' => ['cohort' => ['cohorts' => [$cohort1->id]]],
            'programid1' => $program1->id,
        ];
        $certification = $generator->create_certification($data);
        $source = $DB->get_record('tool_certify_sources',
            ['type' => 'cohort', 'certificationid' => $certification->id], '*', MUST_EXIST);
        $assignment = $DB->get_record('tool_certify_assignments', ['userid' => $user1->id, 'certificationid' => $certification->id], '*', MUST_EXIST);

        $this->setUser(null);
        $result = cohort::get_assigner($certification, $source, $assignment);
        $this->assertSame($admin->id, $result->id);

        $this->setUser($user2);
        $result = cohort::get_assigner($certification, $source, $assignment);
        $this->assertSame($admin->id, $result->id);

        $this->setGuestUser();
        $result = cohort::get_assigner($certification, $source, $assignment);
        $this->assertSame($admin->id, $result->id);
    }
}
