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

/**
 * certification management helper test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\management
 */
final class management_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_get_management_url() {
        global $DB;

        $syscontext = \context_system::instance();

        $category1 = $this->getDataGenerator()->create_category([]);
        $catcontext1 = \context_coursecat::instance($category1->id);
        $category2 = $this->getDataGenerator()->create_category([]);
        $catcontext2 = \context_coursecat::instance($category2->id);

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification();
        $certification2 = $generator->create_certification(['contextid' => $catcontext1->id]);
        $certification3 = $generator->create_certification(['contextid' => $catcontext1->id]);
        $certification4 = $generator->create_certification(['contextid' => $catcontext2->id]);

        $admin = get_admin();
        $guest = guest_user();
        $manager = $this->getDataGenerator()->create_user();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        role_assign($managerrole->id, $manager->id, $catcontext2->id);

        $viewer = $this->getDataGenerator()->create_user();
        $viewerroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/certify:view', CAP_ALLOW, $viewerroleid, $syscontext);
        role_assign($viewerroleid, $viewer->id, $catcontext1->id);

        $this->setUser(null);
        $this->assertNull(management::get_management_url());

        $this->setUser($guest);
        $this->assertNull(management::get_management_url());

        $this->setUser($admin);
        $expected = new \moodle_url('/admin/tool/certify/management/index.php');
        $this->assertSame((string)$expected, (string)management::get_management_url());

        $this->setUser($manager);
        $expected = new \moodle_url('/admin/tool/certify/management/index.php', ['contextid' => $catcontext2->id]);
        $this->assertSame((string)$expected, (string)management::get_management_url());

        $this->setUser($viewer);
        $expected = new \moodle_url('/admin/tool/certify/management/index.php', ['contextid' => $catcontext1->id]);
        $this->assertSame((string)$expected, (string)management::get_management_url());
    }

    public function test_fetch_certifications() {
        $category1 = $this->getDataGenerator()->create_category([]);
        $catcontext1 = \context_coursecat::instance($category1->id);
        $category2 = $this->getDataGenerator()->create_category([]);
        $catcontext2 = \context_coursecat::instance($category2->id);

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['fullname' => 'hokus']);
        $certification2 = $generator->create_certification(['idnumber' => 'pokus']);
        $certification3 = $generator->create_certification();
        $certification4 = $generator->create_certification(['contextid' => $catcontext1->id]);
        $certification5 = $generator->create_certification(['contextid' => $catcontext1->id]);
        $certification6 = $generator->create_certification(['contextid' => $catcontext2->id]);

        $certification3 = certification::update_certification_general((object)['id' => $certification3->id, 'archived' => 1]);
        $certification5 = certification::update_certification_general((object)['id' => $certification5->id, 'archived' => 1]);

        $result = management::fetch_certifications(null, false, '', 0, 100, 'id ASC');
        $this->assertCount(2, $result);
        $this->assertCount(4, $result['certifications']);
        $this->assertSame(4, $result['totalcount']);
        $certifications = $result['certifications'];
        $this->assertArrayHasKey($certification1->id, $certifications);
        $this->assertArrayHasKey($certification2->id, $certifications);
        $this->assertArrayHasKey($certification4->id, $certifications);
        $this->assertArrayHasKey($certification6->id, $certifications);

        $result = management::fetch_certifications(null, false, 'hokus', 0, 100, 'id ASC');
        $this->assertCount(2, $result);
        $this->assertCount(1, $result['certifications']);
        $this->assertSame(1, $result['totalcount']);
        $certifications = $result['certifications'];
        $this->assertArrayHasKey($certification1->id, $certifications);

        $result = management::fetch_certifications(null, false, 'okus', 0, 100, 'id ASC');
        $this->assertCount(2, $result);
        $this->assertCount(2, $result['certifications']);
        $this->assertSame(2, $result['totalcount']);
        $certifications = $result['certifications'];
        $this->assertArrayHasKey($certification1->id, $certifications);
        $this->assertArrayHasKey($certification2->id, $certifications);

        $result = management::fetch_certifications(null, true, '', 0, 100, 'id ASC');
        $this->assertCount(2, $result);
        $this->assertCount(2, $result['certifications']);
        $this->assertSame(2, $result['totalcount']);
        $certifications = $result['certifications'];
        $this->assertArrayHasKey($certification3->id, $certifications);
        $this->assertArrayHasKey($certification5->id, $certifications);

        $result = management::fetch_certifications($catcontext1, false, '', 0, 100, 'id ASC');
        $this->assertCount(2, $result);
        $this->assertCount(1, $result['certifications']);
        $this->assertSame(1, $result['totalcount']);
        $certifications = $result['certifications'];
        $this->assertArrayHasKey($certification4->id, $certifications);

        $result = management::fetch_certifications(null, false, '', 1, 2, 'id ASC');
        $this->assertCount(2, $result);
        $this->assertCount(2, $result['certifications']);
        $this->assertSame(4, $result['totalcount']);
        $certifications = $result['certifications'];
        $this->assertArrayHasKey($certification4->id, $certifications);
        $this->assertArrayHasKey($certification6->id, $certifications);

        $result = management::fetch_certifications(null, false, '', 3, 1, 'id ASC');
        $this->assertCount(2, $result);
        $this->assertCount(1, $result['certifications']);
        $this->assertSame(4, $result['totalcount']);
        $certifications = $result['certifications'];
        $this->assertArrayHasKey($certification6->id, $certifications);
    }

    public function test_get_used_contexts_menu() {
        global $DB;

        $syscontext = \context_system::instance();
        $category1 = $this->getDataGenerator()->create_category([]);
        $catcontext1 = \context_coursecat::instance($category1->id);
        $category2 = $this->getDataGenerator()->create_category([]);
        $catcontext2 = \context_coursecat::instance($category2->id);
        $category3 = $this->getDataGenerator()->create_category([]);
        $catcontext3 = \context_coursecat::instance($category3->id);

        $user = $this->getDataGenerator()->create_user();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);
        role_assign($managerrole->id, $user->id, $catcontext1);
        role_assign($managerrole->id, $user->id, $catcontext3);
        // Undo work hackery.
        $userrole = $DB->get_record('role', ['shortname' => 'user'], '*', MUST_EXIST);
        assign_capability('moodle/category:viewcourselist', CAP_ALLOW, $managerrole->id, $syscontext->id);
        $coursecatcache = \cache::make('core', 'coursecat');
        $coursecatcache->purge();

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification();
        $certification2 = $generator->create_certification();
        $certification3 = $generator->create_certification();
        $certification4 = $generator->create_certification(['contextid' => $catcontext1->id]);
        $certification5 = $generator->create_certification(['contextid' => $catcontext1->id]);
        $certification6 = $generator->create_certification(['contextid' => $catcontext2->id]);

        $this->setAdminUser();
        $expected = [
            0 => 'All certifications (6)',
            $syscontext->id => 'System (3)',
            $catcontext1->id => $category1->name . ' (2)',
            $catcontext2->id => $category2->name . ' (1)',
        ];
        $contexts = management::get_used_contexts_menu($syscontext);
        $this->assertSame($expected, $contexts);

        $expected = [
            0 => 'All certifications (6)',
            $syscontext->id => 'System (3)',
            $catcontext1->id => $category1->name . ' (2)',
            $catcontext2->id => $category2->name . ' (1)',
            $catcontext3->id => $category3->name,
        ];
        $contexts = management::get_used_contexts_menu($catcontext3);
        $this->assertSame($expected, $contexts);

        $this->setUser($user);
        $coursecatcache->purge();

        $expected = [
            $catcontext1->id => $category1->name . ' (2)',
        ];
        $contexts = management::get_used_contexts_menu($catcontext1);
        $this->assertSame($expected, $contexts);

        $expected = [
            $catcontext1->id => $category1->name . ' (2)',
            $catcontext3->id => $category3->name,
        ];
        $contexts = management::get_used_contexts_menu($catcontext3);
        $this->assertSame($expected, $contexts);
    }

    public function test_fetch_current_cohorts_menu() {
        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $cohort1 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort A']);
        $cohort2 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort B']);
        $cohort3 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort C']);

        $certification1 = $generator->create_certification();
        $certification2 = $generator->create_certification();
        $certification3 = $generator->create_certification();

        certification::update_certification_visibility((object)[
            'id' => $certification1->id,
            'public' => 0,
            'cohorts' => [$cohort1->id, $cohort2->id]
        ]);
        certification::update_certification_visibility((object)[
            'id' => $certification2->id,
            'public' => 1,
            'cohorts' => [$cohort3->id]
        ]);

        $expected = [
            $cohort1->id => $cohort1->name,
            $cohort2->id => $cohort2->name,
        ];
        $menu = management::fetch_current_cohorts_menu($certification1->id);
        $this->assertSame($expected, $menu);

        $menu = management::fetch_current_cohorts_menu($certification3->id);
        $this->assertSame([], $menu);
    }

    public function test_setup_index_page() {
        global $PAGE;

        $syscontext = \context_system::instance();

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification();
        $user = $this->getDataGenerator()->create_user();

        $PAGE = new \moodle_page();
        management::setup_index_page(
            new \moodle_url('/admin/tool/certify/management/index.php'),
            $syscontext,
            0
        );

        $this->setUser($user);
        $PAGE = new \moodle_page();
        management::setup_index_page(
            new \moodle_url('/admin/tool/certify/management/index.php'),
            $syscontext,
            $syscontext->id
        );
    }

    public function test_setup_certification_page() {
        global $PAGE;

        $syscontext = \context_system::instance();

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification();
        $user = $this->getDataGenerator()->create_user();

        $PAGE = new \moodle_page();
        management::setup_certification_page(
            new \moodle_url('/admin/tool/certify/management/new.php'),
            $syscontext,
            $certification1
        );

        $this->setUser($user);
        $PAGE = new \moodle_page();
        management::setup_certification_page(
            new \moodle_url('/admin/tool/certify/management/new.php'),
            $syscontext,
            $certification1
        );
    }
}
