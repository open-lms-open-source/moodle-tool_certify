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

namespace tool_certify\external;

/**
 * External API for form program selection.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 * @covers \tool_certify\external\form_certification_periods_programid
 */
final class form_certification_periods_programid_test extends \advanced_testcase {
    public function setUp(): void {
        global $CFG;
        require_once("$CFG->dirroot/lib/externallib.php");
        $this->resetAfterTest();
    }

    public function test_execute() {
        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $syscontext = \context_system::instance();
        $category1 = $this->getDataGenerator()->create_category([]);
        $catcontext1 = \context_coursecat::instance($category1->id);

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $program1 = $programgenerator->create_program([
            'fullname' => 'hokus',
            'idnumber' => 'p1',
            'description' => 'some desc 1',
            'descriptionformat' => FORMAT_MARKDOWN,
            'public' => 1,
            'archived' => 0,
            'contextid' => $syscontext->id,
            'sources' => ['certify' => []],
            'cohorts' => [$cohort1->id],
        ]);
        $program2 = $programgenerator->create_program([
            'fullname' => 'pokus',
            'idnumber' => 'p2',
            'description' => '<b>some desc 2</b>',
            'descriptionformat' => FORMAT_HTML,
            'public' => 0,
            'archived' => 0,
            'contextid' => $catcontext1->id,
            'sources' => ['certify' => [], 'cohort' => []],
            'cohorts' => [$cohort1->id, $cohort2->id],
        ]);
        $program3 = $programgenerator->create_program([
            'fullname' => 'Prog3',
            'idnumber' => 'p3',
            'public' => 1,
            'archived' => 1,
            'contextid' => $syscontext->id,
            'sources' => ['certify' => []]
        ]);
        $program4 = $programgenerator->create_program([
            'fullname' => 'Prog4',
            'idnumber' => 'p4',
            'public' => 1,
            'archived' => 0,
            'contextid' => $syscontext->id,
            'sources' => ['manual' => []]
        ]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $editorroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/certify:edit', CAP_ALLOW, $editorroleid, $syscontext);
        assign_capability('enrol/programs:addtocertifications', CAP_ALLOW, $editorroleid, $syscontext);
        role_assign($editorroleid, $user1->id, $catcontext1->id);

        $certification1 = $generator->create_certification([
            'contextid' => $syscontext->id,
        ]);
        $certification2 = $generator->create_certification([
            'contextid' => $catcontext1->id,
        ]);

        $this->setAdminUser();
        $response = form_certification_periods_programid::execute ('', $certification1->id);
        $results = form_certification_periods_programid::clean_returnvalue(form_certification_periods_programid::execute_returns(), $response);
        $this->assertSame(null, $results['notice']);
        $this->assertCount(2, $results['list']);
        $this->assertSame($program1->id, $results['list'][0]['value']);
        $this->assertSame($program2->id, $results['list'][1]['value']);

        $response = form_certification_periods_programid::execute ('hoku', $certification1->id);
        $results = form_certification_periods_programid::clean_returnvalue(form_certification_periods_programid::execute_returns(), $response);
        $this->assertSame(null, $results['notice']);
        $this->assertCount(1, $results['list']);
        $this->assertSame($program1->id, $results['list'][0]['value']);

        $this->setUser($user1);
        $response = form_certification_periods_programid::execute ('', $certification2->id);
        $results = form_certification_periods_programid::clean_returnvalue(form_certification_periods_programid::execute_returns(), $response);
        $this->assertSame(null, $results['notice']);
        $this->assertCount(1, $results['list']);
        $this->assertSame($program2->id, $results['list'][0]['value']);

        $this->setUser($user1);
        try {
            form_certification_periods_programid::execute ('', $certification1->id);
            $this->fail('Exception excepted');
        } catch (\moodle_exception $ex) {
            $this->assertInstanceOf(\required_capability_exception::class, $ex);
            $this->assertSame('Sorry, but you do not currently have permissions to do that (Update certifications).',
                $ex->getMessage());
        }
    }

    public function test_execute_tenant() {
        if (!\tool_certify\local\tenant::is_available()) {
            $this->markTestSkipped('tenant support not available');
        }

        \tool_olms_tenant\tenants::activate_tenants();

        /** @var \tool_olms_tenant_generator $generator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_olms_tenant');

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $tenant1 = $tenantgenerator->create_tenant();
        $tenant1context = \context_tenant::instance($tenant1->id);
        $tenant1catcontext = \context_coursecat::instance($tenant1->categoryid);
        $tenant2 = $tenantgenerator->create_tenant();
        $tenant2context = \context_tenant::instance($tenant2->id);
        $tenant2catcontext = \context_coursecat::instance($tenant2->categoryid);

        $syscontext = \context_system::instance();

        $program0 = $programgenerator->create_program([
            'public' => 1,
            'archived' => 0,
            'contextid' => $syscontext->id,
            'sources' => ['certify' => []],
        ]);
        $program1 = $programgenerator->create_program([
            'public' => 1,
            'archived' => 0,
            'contextid' => $tenant1catcontext->id,
            'sources' => ['certify' => []],
        ]);
        $program2 = $programgenerator->create_program([
            'public' => 1,
            'archived' => 0,
            'contextid' => $tenant2catcontext->id,
            'sources' => ['certify' => []],
        ]);

        $user0 = $this->getDataGenerator()->create_user(['tenantid' => 0]);
        $user1 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);
        $user2 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);

        $editorroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/certify:edit', CAP_ALLOW, $editorroleid, $syscontext);
        assign_capability('enrol/programs:addtocertifications', CAP_ALLOW, $editorroleid, $syscontext);
        role_assign($editorroleid, $user0->id, $syscontext->id);
        role_assign($editorroleid, $user1->id, $syscontext->id);
        role_assign($editorroleid, $user2->id, $syscontext->id);

        $certification0 = $generator->create_certification([
            'contextid' => $syscontext->id,
        ]);
        $certification1 = $generator->create_certification([
            'contextid' => $tenant1catcontext->id,
        ]);
        $certification2 = $generator->create_certification([
            'contextid' => $tenant2catcontext->id,
        ]);

        $this->setAdminUser();
        $response = form_certification_periods_programid::execute ('', $certification0->id);
        $results = form_certification_periods_programid::clean_returnvalue(form_certification_periods_programid::execute_returns(), $response);
        $this->assertSame(null, $results['notice']);
        $this->assertCount(3, $results['list']);
        $this->assertSame($program0->id, $results['list'][0]['value']);
        $this->assertSame($program1->id, $results['list'][1]['value']);
        $this->assertSame($program2->id, $results['list'][2]['value']);

        $this->setAdminUser();
        $response = form_certification_periods_programid::execute ('', $certification1->id);
        $results = form_certification_periods_programid::clean_returnvalue(form_certification_periods_programid::execute_returns(), $response);
        $this->assertSame(null, $results['notice']);
        $this->assertCount(2, $results['list']);
        $this->assertSame($program0->id, $results['list'][0]['value']);
        $this->assertSame($program1->id, $results['list'][1]['value']);

        $this->setUser($user1);
        $response = form_certification_periods_programid::execute ('', $certification0->id);
        $results = form_certification_periods_programid::clean_returnvalue(form_certification_periods_programid::execute_returns(), $response);
        $this->assertSame(null, $results['notice']);
        $this->assertCount(2, $results['list']);
        $this->assertSame($program0->id, $results['list'][0]['value']);
        $this->assertSame($program1->id, $results['list'][1]['value']);

        $this->setUser($user1);
        try {
            form_certification_periods_programid::execute ('', $certification2->id);
            $this->fail('Exception excepted');
        } catch (\moodle_exception $ex) {
            $this->assertInstanceOf(\required_capability_exception::class, $ex);
            $this->assertSame('Sorry, but you do not currently have permissions to do that (Update certifications).',
                $ex->getMessage());
        }
    }
}
