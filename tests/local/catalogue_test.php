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
 * certification catalogue test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\catalogue
 */
final class catalogue_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_get_current_url() {
        $catalogue = new catalogue([]);
        $this->assertSame('https://www.example.com/moodle/admin/tool/certify/catalogue/index.php',
            $catalogue->get_current_url()->out(false));

        $catalogue = new catalogue(['searchtext' => '']);
        $this->assertSame('https://www.example.com/moodle/admin/tool/certify/catalogue/index.php',
            $catalogue->get_current_url()->out(false));

        $catalogue = new catalogue(['page' => 10, 'searchtext' => 'abc']);
        $this->assertSame('https://www.example.com/moodle/admin/tool/certify/catalogue/index.php?page=10&searchtext=abc',
            $catalogue->get_current_url()->out(false));

        $catalogue = new catalogue(['page' => 10, 'searchtext' => 'abc', 'perpage' => 12]);
        $this->assertSame('https://www.example.com/moodle/admin/tool/certify/catalogue/index.php?page=10&perpage=12&searchtext=abc',
            $catalogue->get_current_url()->out(false));
    }

    public function test_is_filtering() {
        $catalogue = new catalogue([]);
        $this->assertFalse($catalogue->is_filtering());

        $catalogue = new catalogue(['searchtext' => '']);
        $this->assertFalse($catalogue->is_filtering());

        $catalogue = new catalogue(['page' => 2, 'perpage' => 11]);
        $this->assertFalse($catalogue->is_filtering());

        $catalogue = new catalogue(['page' => 10, 'searchtext' => 'abc']);
        $this->assertTrue($catalogue->is_filtering());
    }

    public function test_get_page() {
        $catalogue = new catalogue([]);
        $this->assertSame(0, $catalogue->get_page());

        $catalogue = new catalogue(['page' => '10', 'searchtext' => 'abc']);
        $this->assertSame(10, $catalogue->get_page());
    }

    public function test_get_perpage() {
        $catalogue = new catalogue([]);
        $this->assertSame(10, $catalogue->get_perpage());

        $catalogue = new catalogue(['page' => '10', 'searchtext' => 'abc', 'perpage' => 14]);
        $this->assertSame(14, $catalogue->get_perpage());
    }

    public function test_get_search_text() {
        $catalogue = new catalogue(['page' => '10', 'searchtext' => 'abc']);
        $this->assertSame('abc', $catalogue->get_searchtext());

        $catalogue = new catalogue([]);
        $this->assertSame(null, $catalogue->get_searchtext());

        $catalogue = new catalogue(['page' => '10', 'searchtext' => '']);
        $this->assertSame(null, $catalogue->get_searchtext());

        $catalogue = new catalogue(['page' => '10', 'searchtext' => 'a']);
        $this->assertSame(null, $catalogue->get_searchtext());
    }

    public function test_get_hidden_search_fields() {
        $catalogue = new catalogue([]);
        $this->assertSame([], $catalogue->get_hidden_search_fields());

        $catalogue = new catalogue(['searchtext' => '']);
        $this->assertSame([], $catalogue->get_hidden_search_fields());

        $catalogue = new catalogue(['page' => 2, 'perpage' => 11]);
        $this->assertSame(['page' => 2, 'perpage' => 11], $catalogue->get_hidden_search_fields());

        $catalogue = new catalogue(['page' => 10, 'searchtext' => 'abc']);
        $this->assertSame(['page' => 10], $catalogue->get_hidden_search_fields());
    }

    public function test_get_certifications() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $category1 = $this->getDataGenerator()->create_category([]);
        $catcontext1 = \context_coursecat::instance($category1->id);
        $category2 = $this->getDataGenerator()->create_category([]);
        $catcontext2 = \context_coursecat::instance($category2->id);

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['public' => 1]);
        $certification2 = $generator->create_certification(['idnumber' => 'pokus', 'cohorts' => [$cohort1->id, $cohort2->id]]);
        $certification3 = $generator->create_certification(['public' => 1, 'archived' => 1, 'cohorts' => [$cohort1->id, $cohort2->id], 'sources' => ['manual' => []]]);
        $source3 = $DB->get_record('tool_certify_sources', ['certificationid' => $certification3->id, 'type' => 'manual'], '*', MUST_EXIST);
        $certification4 = $generator->create_certification(['contextid' => $catcontext1->id, 'cohorts' => [$cohort1->id]]);
        $certification5 = $generator->create_certification(['contextid' => $catcontext1->id, 'archived' => 1, 'cohorts' => [$cohort2->id]]);
        $certification6 = $generator->create_certification(['contextid' => $catcontext2->id, 'sources' => ['manual' => []]]);
        $source6 = $DB->get_record('tool_certify_sources', ['certificationid' => $certification6->id, 'type' => 'manual'], '*', MUST_EXIST);

        cohort_add_member($cohort1->id, $user2->id);
        cohort_add_member($cohort1->id, $user3->id);
        \tool_certify\local\source\manual::assign_users($certification3->id, $source3->id, [$user3->id]);
        \tool_certify\local\source\manual::assign_users($certification6->id, $source6->id, [$user3->id]);

        $this->setUser($user1);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id], array_keys($certifications));
        $this->assertSame(1, $catalogue->count_certifications());

        $this->setUser($user2);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id, (int)$certification2->id, (int)$certification4->id], array_keys($certifications));
        $this->assertSame(3, $catalogue->count_certifications());

        $this->setUser($user3);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id, (int)$certification2->id, (int)$certification4->id, (int)$certification6->id], array_keys($certifications));
        $this->assertSame(4, $catalogue->count_certifications());

        $this->setUser($user3);
        $catalogue = new catalogue(['page' => 1, 'perpage' => 2]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification4->id, (int)$certification6->id], array_keys($certifications));
        $this->assertSame(4, $catalogue->count_certifications());
    }

    public function test_get_certifications_tenant() {
        global $DB;

        if (!\tool_certify\local\tenant::is_available()) {
            $this->markTestSkipped('tenant support not available');
        }

        \tool_olms_tenant\tenants::activate_tenants();

        /** @var \tool_olms_tenant_generator $generator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_olms_tenant');

        $tenant1 = $tenantgenerator->create_tenant();
        $tenant2 = $tenantgenerator->create_tenant();

        $user1 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);
        $user2 = $this->getDataGenerator()->create_user(['tenantid' => $tenant2->id]);
        $user3 = $this->getDataGenerator()->create_user();

        $catcontext1 = \context_coursecat::instance($tenant1->categoryid);
        $catcontext2 = \context_coursecat::instance($tenant2->categoryid);

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['public' => 1]);
        $certification2 = $generator->create_certification(['idnumber' => 'pokus', 'cohorts' => [$cohort1->id, $cohort2->id]]);
        $certification3 = $generator->create_certification(['public' => 1, 'archived' => 1, 'cohorts' => [$cohort1->id, $cohort2->id], 'sources' => ['manual' => []]]);
        $certification4 = $generator->create_certification(['cohorts' => [$cohort1->id]]);
        $certification5 = $generator->create_certification(['archived' => 1, 'cohorts' => [$cohort2->id]]);
        $certification6 = $generator->create_certification(['sources' => ['manual' => []]]);

        cohort_add_member($cohort1->id, $user2->id);
        cohort_add_member($cohort1->id, $user3->id);
        $source3 = $DB->get_record('tool_certify_sources', ['certificationid' => $certification3->id, 'type' => 'manual'], '*', MUST_EXIST);
        \tool_certify\local\source\manual::assign_users($certification3->id, $source3->id, [$user3->id]);
        $source6 = $DB->get_record('tool_certify_sources', ['certificationid' => $certification6->id, 'type' => 'manual'], '*', MUST_EXIST);
        \tool_certify\local\source\manual::assign_users($certification6->id, $source6->id, [$user3->id]);

        $this->setUser($user1);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id], array_keys($certifications));
        $this->assertSame(1, $catalogue->count_certifications());

        \tool_olms_tenant\tenancy::force_tenant_id($tenant2->id);
        $this->setUser($user1);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id], array_keys($certifications));
        $this->assertSame(1, $catalogue->count_certifications());
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();

        \tool_olms_tenant\tenancy::force_tenant_id(null);
        $this->setUser($user1);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id], array_keys($certifications));
        $this->assertSame(1, $catalogue->count_certifications());
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();

        $this->setUser($user2);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id, (int)$certification2->id, (int)$certification4->id], array_keys($certifications));
        $this->assertSame(3, $catalogue->count_certifications());

        $this->setUser($user3);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id, (int)$certification2->id, (int)$certification4->id, (int)$certification6->id], array_keys($certifications));
        $this->assertSame(4, $catalogue->count_certifications());

        $certification1->contextid = $catcontext1->id;
        $certification1 = certification::update_certification_general($certification1);
        $certification2->contextid = $catcontext1->id;
        $certification2 = certification::update_certification_general($certification2);
        $certification3->contextid = $catcontext1->id;
        $certification3 = certification::update_certification_general($certification3);
        $certification4->contextid = $catcontext1->id;
        $certification4 = certification::update_certification_general($certification4);
        $certification5->contextid = $catcontext1->id;
        $certification5 = certification::update_certification_general($certification5);
        $certification6->contextid = $catcontext1->id;
        $certification6 = certification::update_certification_general($certification6);

        $this->setUser($user1);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id], array_keys($certifications));
        $this->assertSame(1, $catalogue->count_certifications());

        \tool_olms_tenant\tenancy::force_tenant_id($tenant2->id);
        $this->setUser($user1);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([], array_keys($certifications));
        $this->assertSame(0, $catalogue->count_certifications());
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();

        \tool_olms_tenant\tenancy::force_tenant_id(null);
        $this->setUser($user1);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id], array_keys($certifications));
        $this->assertSame(1, $catalogue->count_certifications());
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();

        $this->setUser($user2);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([], array_keys($certifications));
        $this->assertSame(0, $catalogue->count_certifications());

        $this->setUser($user3);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id, (int)$certification2->id, (int)$certification4->id, (int)$certification6->id], array_keys($certifications));
        $this->assertSame(4, $catalogue->count_certifications());

        $certification1->contextid = $catcontext2->id;
        $certification1 = certification::update_certification_general($certification1);
        $certification2->contextid = $catcontext2->id;
        $certification2 = certification::update_certification_general($certification2);
        $certification3->contextid = $catcontext2->id;
        $certification3 = certification::update_certification_general($certification3);
        $certification4->contextid = $catcontext2->id;
        $certification4 = certification::update_certification_general($certification4);
        $certification5->contextid = $catcontext2->id;
        $certification5 = certification::update_certification_general($certification5);
        $certification6->contextid = $catcontext2->id;
        $certification6 = certification::update_certification_general($certification6);

        $this->setUser($user1);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([], array_keys($certifications));
        $this->assertSame(0, $catalogue->count_certifications());

        \tool_olms_tenant\tenancy::force_tenant_id($tenant2->id);
        $this->setUser($user1);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id], array_keys($certifications));
        $this->assertSame(1, $catalogue->count_certifications());
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();

        \tool_olms_tenant\tenancy::force_tenant_id(null);
        $this->setUser($user1);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id], array_keys($certifications));
        $this->assertSame(1, $catalogue->count_certifications());
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();

        $this->setUser($user2);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id, (int)$certification2->id, (int)$certification4->id], array_keys($certifications));
        $this->assertSame(3, $catalogue->count_certifications());

        $this->setUser($user3);
        $catalogue = new catalogue([]);
        $certifications = $catalogue->get_certifications();
        $this->assertSame([(int)$certification1->id, (int)$certification2->id, (int)$certification4->id, (int)$certification6->id], array_keys($certifications));
        $this->assertSame(4, $catalogue->count_certifications());

    }

    public function test_is_certification_visible() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $category1 = $this->getDataGenerator()->create_category([]);
        $catcontext1 = \context_coursecat::instance($category1->id);
        $category2 = $this->getDataGenerator()->create_category([]);
        $catcontext2 = \context_coursecat::instance($category2->id);

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['fullname' => 'hokus', 'public' => 1]);
        $certification2 = $generator->create_certification(['idnumber' => 'pokus', 'cohorts' => [$cohort1->id, $cohort2->id]]);
        $certification3 = $generator->create_certification(['public' => 1, 'archived' => 1, 'cohorts' => [$cohort1->id, $cohort2->id], 'sources' => ['manual' => []]]);
        $source3 = $DB->get_record('tool_certify_sources', ['certificationid' => $certification3->id, 'type' => 'manual'], '*', MUST_EXIST);
        $certification4 = $generator->create_certification(['contextid' => $catcontext1->id, 'cohorts' => [$cohort1->id]]);
        $certification5 = $generator->create_certification(['contextid' => $catcontext1->id, 'archived' => 1, 'cohorts' => [$cohort2->id]]);
        $certification6 = $generator->create_certification(['contextid' => $catcontext2->id, 'sources' => ['manual' => []]]);
        $source6 = $DB->get_record('tool_certify_sources', ['certificationid' => $certification6->id, 'type' => 'manual'], '*', MUST_EXIST);

        cohort_add_member($cohort1->id, $user2->id);
        cohort_add_member($cohort1->id, $user3->id);
        \tool_certify\local\source\manual::assign_users($certification3->id, $source3->id, [$user3->id]);
        \tool_certify\local\source\manual::assign_users($certification6->id, $source6->id, [$user3->id]);

        $this->setUser($user1);
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification2, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification4, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user1->id));
        $this->assertTrue(catalogue::is_certification_visible($certification1));
        $this->assertFalse(catalogue::is_certification_visible($certification2));
        $this->assertFalse(catalogue::is_certification_visible($certification3));
        $this->assertFalse(catalogue::is_certification_visible($certification4));
        $this->assertFalse(catalogue::is_certification_visible($certification5));
        $this->assertFalse(catalogue::is_certification_visible($certification6));

        $this->assertTrue(catalogue::is_certification_visible($certification1, $user2->id));
        $this->assertTrue(catalogue::is_certification_visible($certification2, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user2->id));
        $this->assertTrue(catalogue::is_certification_visible($certification4, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user2->id));

        $this->assertTrue(catalogue::is_certification_visible($certification1, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification2, $user3->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification4, $user3->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification6, $user3->id));
    }

    public function test_is_certification_visible_tenant() {
        global $DB;

        if (!\tool_certify\local\tenant::is_available()) {
            $this->markTestSkipped('tenant support not available');
        }

        \tool_olms_tenant\tenants::activate_tenants();

        /** @var \tool_olms_tenant_generator $generator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_olms_tenant');

        $tenant1 = $tenantgenerator->create_tenant();
        $tenant2 = $tenantgenerator->create_tenant();

        $user1 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);
        $user2 = $this->getDataGenerator()->create_user(['tenantid' => $tenant2->id]);
        $user3 = $this->getDataGenerator()->create_user();

        $catcontext1 = \context_coursecat::instance($tenant1->categoryid);
        $catcontext2 = \context_coursecat::instance($tenant2->categoryid);

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['fullname' => 'hokus', 'public' => 1]);
        $certification2 = $generator->create_certification(['idnumber' => 'pokus', 'cohorts' => [$cohort1->id, $cohort2->id]]);
        $certification3 = $generator->create_certification(['public' => 1, 'archived' => 1, 'cohorts' => [$cohort1->id, $cohort2->id], 'sources' => ['manual' => []]]);
        $certification4 = $generator->create_certification(['cohorts' => [$cohort1->id]]);
        $certification5 = $generator->create_certification(['archived' => 1, 'cohorts' => [$cohort2->id]]);
        $certification6 = $generator->create_certification(['sources' => ['manual' => []]]);
        cohort_add_member($cohort1->id, $user2->id);
        cohort_add_member($cohort1->id, $user3->id);
        $source3 = $DB->get_record('tool_certify_sources', ['certificationid' => $certification3->id, 'type' => 'manual'], '*', MUST_EXIST);
        \tool_certify\local\source\manual::assign_users($certification3->id, $source3->id, [$user3->id]);
        $source6 = $DB->get_record('tool_certify_sources', ['certificationid' => $certification6->id, 'type' => 'manual'], '*', MUST_EXIST);
        \tool_certify\local\source\manual::assign_users($certification6->id, $source6->id, [$user3->id]);

        $this->setUser($user1);
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification2, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification4, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user1->id));
        $this->assertTrue(catalogue::is_certification_visible($certification1));
        $this->assertFalse(catalogue::is_certification_visible($certification2));
        $this->assertFalse(catalogue::is_certification_visible($certification3));
        $this->assertFalse(catalogue::is_certification_visible($certification4));
        $this->assertFalse(catalogue::is_certification_visible($certification5));
        $this->assertFalse(catalogue::is_certification_visible($certification6));
        \tool_olms_tenant\tenancy::force_tenant_id($tenant2->id);
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification2, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification4, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user1->id));
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();
        \tool_olms_tenant\tenancy::force_tenant_id(null);
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification2, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification4, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user1->id));
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user2->id));
        $this->assertTrue(catalogue::is_certification_visible($certification2, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user2->id));
        $this->assertTrue(catalogue::is_certification_visible($certification4, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user2->id));
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification2, $user3->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification4, $user3->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification6, $user3->id));

        $certification1->contextid = $catcontext1->id;
        $certification1 = certification::update_certification_general($certification1);
        $certification2->contextid = $catcontext1->id;
        $certification2 = certification::update_certification_general($certification2);
        $certification3->contextid = $catcontext1->id;
        $certification3 = certification::update_certification_general($certification3);
        $certification4->contextid = $catcontext1->id;
        $certification4 = certification::update_certification_general($certification4);
        $certification5->contextid = $catcontext1->id;
        $certification5 = certification::update_certification_general($certification5);
        $certification6->contextid = $catcontext1->id;
        $certification6 = certification::update_certification_general($certification6);

        $this->setUser($user1);
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification2, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification4, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user1->id));
        $this->assertTrue(catalogue::is_certification_visible($certification1));
        $this->assertFalse(catalogue::is_certification_visible($certification2));
        $this->assertFalse(catalogue::is_certification_visible($certification3));
        $this->assertFalse(catalogue::is_certification_visible($certification4));
        $this->assertFalse(catalogue::is_certification_visible($certification5));
        $this->assertFalse(catalogue::is_certification_visible($certification6));
        \tool_olms_tenant\tenancy::force_tenant_id($tenant2->id);
        $this->assertFalse(catalogue::is_certification_visible($certification1, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification2, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification4, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user1->id));
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();
        \tool_olms_tenant\tenancy::force_tenant_id(null);
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification2, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification4, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user1->id));
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();
        $this->assertFalse(catalogue::is_certification_visible($certification1, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification2, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification4, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user2->id));
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification2, $user3->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification4, $user3->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification6, $user3->id));

        $certification1->contextid = $catcontext2->id;
        $certification1 = certification::update_certification_general($certification1);
        $certification2->contextid = $catcontext2->id;
        $certification2 = certification::update_certification_general($certification2);
        $certification3->contextid = $catcontext2->id;
        $certification3 = certification::update_certification_general($certification3);
        $certification4->contextid = $catcontext2->id;
        $certification4 = certification::update_certification_general($certification4);
        $certification5->contextid = $catcontext2->id;
        $certification5 = certification::update_certification_general($certification5);
        $certification6->contextid = $catcontext2->id;
        $certification6 = certification::update_certification_general($certification6);

        $this->setUser($user1);
        $this->assertFalse(catalogue::is_certification_visible($certification1, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification2, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification4, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification1));
        $this->assertFalse(catalogue::is_certification_visible($certification2));
        $this->assertFalse(catalogue::is_certification_visible($certification3));
        $this->assertFalse(catalogue::is_certification_visible($certification4));
        $this->assertFalse(catalogue::is_certification_visible($certification5));
        $this->assertFalse(catalogue::is_certification_visible($certification6));
        \tool_olms_tenant\tenancy::force_tenant_id($tenant2->id);
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification2, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification4, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user1->id));
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();
        \tool_olms_tenant\tenancy::force_tenant_id(null);
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification2, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification4, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user1->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user1->id));
        \tool_olms_tenant\tenancy::clear_forced_tenant_id();
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user2->id));
        $this->assertTrue(catalogue::is_certification_visible($certification2, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user2->id));
        $this->assertTrue(catalogue::is_certification_visible($certification4, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user2->id));
        $this->assertFalse(catalogue::is_certification_visible($certification6, $user2->id));
        $this->assertTrue(catalogue::is_certification_visible($certification1, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification2, $user3->id));
        $this->assertFalse(catalogue::is_certification_visible($certification3, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification4, $user3->id));
        $this->assertFalse(catalogue::is_certification_visible($certification5, $user3->id));
        $this->assertTrue(catalogue::is_certification_visible($certification6, $user3->id));
    }

    public function test_get_catalogue_url() {
        $this->setUser(null);
        $this->assertNull(catalogue::get_catalogue_url());

        $this->setUser(guest_user());
        $this->assertNull(catalogue::get_catalogue_url());

        $this->setUser(get_admin());
        $expected = new \moodle_url('/admin/tool/certify/catalogue/index.php');
        $this->assertSame((string)$expected, (string)catalogue::get_catalogue_url());

        $viewer = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);
        $expected = new \moodle_url('/admin/tool/certify/catalogue/index.php');
        $this->assertSame((string)$expected, (string)catalogue::get_catalogue_url());

        $syscontext = \context_system::instance();
        $viewerroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/certify:viewcatalogue', CAP_PROHIBIT, $viewerroleid, $syscontext);
        role_assign($viewerroleid, $viewer->id, $syscontext->id);
        $this->setUser($viewer);
        $this->assertNull(catalogue::get_catalogue_url());
    }

    public function test_get_tagged_certifications() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $category1 = $this->getDataGenerator()->create_category([]);
        $catcontext1 = \context_coursecat::instance($category1->id);
        $category2 = $this->getDataGenerator()->create_category([]);
        $catcontext2 = \context_coursecat::instance($category2->id);

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $certification1 = $generator->create_certification(['fullname' => 'hokus', 'public' => 1]);
        $certification2 = $generator->create_certification(['idnumber' => 'pokus', 'cohorts' => [$cohort1->id, $cohort2->id]]);
        $certification3 = $generator->create_certification(['public' => 1, 'archived' => 1, 'cohorts' => [$cohort1->id, $cohort2->id], 'sources' => ['manual' => []]]);
        $source3 = $DB->get_record('tool_certify_sources', ['certificationid' => $certification3->id, 'type' => 'manual'], '*', MUST_EXIST);
        $certification4 = $generator->create_certification(['contextid' => $catcontext1->id, 'cohorts' => [$cohort1->id]]);
        $certification5 = $generator->create_certification(['contextid' => $catcontext1->id, 'archived' => 1, 'cohorts' => [$cohort2->id]]);
        $certification6 = $generator->create_certification(['contextid' => $catcontext2->id, 'sources' => ['manual' => []]]);
        $source6 = $DB->get_record('tool_certify_sources', ['certificationid' => $certification6->id, 'type' => 'manual'], '*', MUST_EXIST);

        cohort_add_member($cohort1->id, $user2->id);
        cohort_add_member($cohort1->id, $user3->id);
        \tool_certify\local\source\manual::assign_users($certification3->id, $source3->id, [$user3->id]);
        \tool_certify\local\source\manual::assign_users($certification6->id, $source6->id, [$user3->id]);

        $this->setUser($user1);

        // Just make sure there are no fatal errors in sql, behat will test the logic.
        $html = catalogue::get_tagged_certifications(1, true, 0, 1);
        $html = catalogue::get_tagged_certifications(2, false, 1, 3);
    }
}
