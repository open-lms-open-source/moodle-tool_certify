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
 * Certification helper test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\certification
 */
final class certification_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_get_description_editor_options() {
        $syscontext = \context_system::instance();

        $result = certification::get_description_editor_options($syscontext->id);
        $this->assertIsArray($result);
        $this->assertSame(-1, $result['maxfiles']);
        $this->assertSame($syscontext, $result['context']);
    }

    public function test_get_image_filemanager_options() {
        $result = certification::get_image_filemanager_options();
        $this->assertIsArray($result);
        $this->assertSame(1, $result['maxfiles']);
        $this->assertSame(0, $result['subdirs']);
    }

    public function test_add_certification() {
        global $DB;

        $syscontext = \context_system::instance();

        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $expectedperiods = (array)certification::get_periods_defaults();

        $data = [
            'fullname' => 'Certifikace 1',
            'idnumber' => 'c1',
            'contextid' => $syscontext->id,
        ];
        $this->setCurrentTimeStart();
        $certification = certification::add_certification((object)$data);
        $this->assertInstanceOf('stdClass', $certification);
        $this->assertSame((string)$syscontext->id, $certification->contextid);
        $this->assertSame('Certifikace 1', $certification->fullname);
        $this->assertSame('c1', $certification->idnumber);
        $this->assertSame('', $certification->description);
        $this->assertSame('1', $certification->descriptionformat);
        $this->assertSame('[]', $certification->presentationjson);
        $this->assertSame('0', $certification->public);
        $this->assertSame('0', $certification->archived);
        $this->assertSame(null, $certification->programid1);
        $this->assertSame(null, $certification->programid2);
        $this->assertSame(null, $certification->recertify);
        $this->assertSame($expectedperiods, json_decode($certification->periodsjson, true));
        $this->assertTimeCurrent($certification->timecreated);
        $sources = $DB->get_records('tool_certify_sources', ['certificationid' => $certification->id]);
        $this->assertCount(0, $sources);

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();
        $program3 = $programgenerator->create_program();

        $data = [
            'fullname' => 'Certifikace 2',
            'idnumber' => 'c2',
            'description' => 'some desc',
            'descriptionformat' => FORMAT_MARKDOWN,
            'contextid' => $catcontext->id,
            'programid1' => $program1->id,
            'programid2' => $program2->id,
            'recertify' => '98765',
        ];
        $this->setCurrentTimeStart();
        $certification = certification::add_certification((object)$data);
        $this->assertInstanceOf('stdClass', $certification);
        $this->assertSame((string)$catcontext->id, $certification->contextid);
        $this->assertSame('Certifikace 2', $certification->fullname);
        $this->assertSame('c2', $certification->idnumber);
        $this->assertSame('some desc', $certification->description);
        $this->assertSame('4', $certification->descriptionformat);
        $this->assertSame('[]', $certification->presentationjson);
        $this->assertSame('0', $certification->public);
        $this->assertSame('0', $certification->archived);
        $this->assertSame($program1->id, $certification->programid1);
        $this->assertSame($program2->id, $certification->programid2);
        $this->assertSame('98765', $certification->recertify);
        $this->assertSame($expectedperiods, json_decode($certification->periodsjson, true));
        $this->assertTimeCurrent($certification->timecreated);

        $data = [
            'fullname' => 'Certifikace 3',
            'idnumber' => 'c3',
            'contextid' => $catcontext->id,
            'programid1' => $program1->id,
            'programid2' => $program2->id,
            'recertify' => null,
        ];
        $certification = certification::add_certification((object)$data);
        $this->assertSame($program1->id, $certification->programid1);
        $this->assertSame(null, $certification->programid2);
        $this->assertSame(null, $certification->recertify);
    }

    public function test_update_certification_general() {
        $syscontext = \context_system::instance();

        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $expectedperiods = (array)certification::get_periods_defaults();

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();
        $program3 = $programgenerator->create_program();

        $data = [
            'fullname' => 'Certifikace 1',
            'idnumber' => 'c1',
            'contextid' => $syscontext->id,
        ];
        $certification = certification::add_certification((object)$data);

        $data = [
            'id' => $certification->id,
            'fullname' => 'Certifikace 2',
            'idnumber' => 'c2',
            'archived' => '1',
            'public' => '1',
            'description' => 'some desc',
            'descriptionformat' => FORMAT_MARKDOWN,
            'contextid' => $catcontext->id,
            'programid1' => $program1->id,
            'programid2' => $program2->id,
            'recertify' => '98765',
        ];
        $certification2 = certification::update_certification_general((object)$data);
        $this->assertInstanceOf('stdClass', $certification2);
        $this->assertSame((string)$catcontext->id, $certification2->contextid);
        $this->assertSame('Certifikace 2', $certification2->fullname);
        $this->assertSame('c2', $certification2->idnumber);
        $this->assertSame('some desc', $certification2->description);
        $this->assertSame('4', $certification2->descriptionformat);
        $this->assertSame('[]', $certification2->presentationjson);
        $this->assertSame('0', $certification2->public);
        $this->assertSame('1', $certification2->archived);
        $this->assertSame(null, $certification2->programid1);
        $this->assertSame(null, $certification2->programid2);
        $this->assertSame(null, $certification2->recertify);
        $this->assertSame($expectedperiods, json_decode($certification2->periodsjson, true));
        $this->assertSame($certification->timecreated, $certification2->timecreated);
    }

    public function test_update_certification_visibility() {
        global $DB;

        $syscontext = \context_system::instance();

        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $expectedperiods = (array)certification::get_periods_defaults();

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();
        $program3 = $programgenerator->create_program();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();

        $data = [
            'fullname' => 'Certifikace 1',
            'idnumber' => 'c1',
            'contextid' => $syscontext->id,
            'public' => '0',
        ];
        $certification = certification::add_certification((object)$data);

        $data = [
            'id' => $certification->id,
            'fullname' => 'Certifikace 2',
            'idnumber' => 'c2',
            'archived' => '1',
            'public' => '1',
            'description' => 'some desc',
            'descriptionformat' => FORMAT_MARKDOWN,
            'contextid' => $catcontext->id,
            'programid1' => $program1->id,
            'programid2' => $program2->id,
            'recertify' => '98765',
        ];
        $certification2 = certification::update_certification_visibility((object)$data);
        $this->assertInstanceOf('stdClass', $certification2);
        $this->assertSame($certification->contextid, $certification2->contextid);
        $this->assertSame($certification->fullname, $certification2->fullname);
        $this->assertSame($certification->idnumber, $certification2->idnumber);
        $this->assertSame($certification->description, $certification2->description);
        $this->assertSame($certification->descriptionformat, $certification2->descriptionformat);
        $this->assertSame('[]', $certification2->presentationjson);
        $this->assertSame('1', $certification2->public);
        $this->assertSame('0', $certification2->archived);
        $this->assertSame(null, $certification2->programid1);
        $this->assertSame(null, $certification2->programid2);
        $this->assertSame(null, $certification2->recertify);
        $this->assertSame($expectedperiods, json_decode($certification2->periodsjson, true));
        $this->assertSame($certification->timecreated, $certification2->timecreated);

        $data = [
            'id' => $certification->id,
            'cohorts' => [$cohort2->id, $cohort1->id],
            'public' => 0,
        ];
        $certification = certification::update_certification_visibility((object)$data);
        $cs = $DB->get_records('tool_certify_cohorts', ['certificationid' => $certification->id], 'cohortid ASC');
        $this->assertCount(2, $cs);
        $cs = array_values($cs);
        $this->assertSame($cohort1->id, $cs[0]->cohortid);
        $this->assertSame($cohort2->id, $cs[1]->cohortid);

        $data = [
            'id' => $certification->id,
            'cohorts' => [$cohort2->id, $cohort3->id],
            'public' => 0,
        ];
        $certification = certification::update_certification_visibility((object)$data);
        $cs = $DB->get_records('tool_certify_cohorts', ['certificationid' => $certification->id], 'cohortid ASC');
        $this->assertCount(2, $cs);
        $cs = array_values($cs);
        $this->assertSame($cohort2->id, $cs[0]->cohortid);
        $this->assertSame($cohort3->id, $cs[1]->cohortid);
    }

    public function test_update_certification_settings() {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');
        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();
        $program3 = $programgenerator->create_program();

        $defaultperiods = (array)certification::get_periods_defaults();

        $data = [
            'fullname' => 'Certifikace 1',
            'idnumber' => 'c1',
            'contextid' => $catcontext->id,
            'public' => '0',
        ];
        $certification = certification::add_certification((object)$data);

        $data = [
            'id' => $certification->id,
        ];
        $certification = certification::update_certification_settings((object)$data);
        $this->assertInstanceOf('stdClass', $certification);
        $this->assertSame(null, $certification->programid1);
        $this->assertSame(null, $certification->programid2);
        $this->assertSame(null, $certification->recertify);
        $this->assertSame($defaultperiods, json_decode($certification->periodsjson, true));

        $data = [
            'id' => (string)$certification->id,
            'programid1' => $program1->id,
            'resettype1' => certification::RESETTYPE_PURGE,
            'due1' => '9876',
            'valid1' => certification::SINCE_WINDOWDUE,
            'windowend1' => util::get_delay_form_value(['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P7D'], 'days'),
            'expiration1' => util::get_delay_form_value(['since' => certification::SINCE_CERTIFIED, 'delay' => 'P1Y'], 'days'),
        ];
        $certification = certification::update_certification_settings((object)$data);
        $this->assertInstanceOf('stdClass', $certification);
        $this->assertSame($program1->id, $certification->programid1);
        $this->assertSame(null, $certification->programid2);
        $this->assertSame(null, $certification->recertify);
        $periods = (object)json_decode($certification->periodsjson, true);
        $this->assertSame($data['resettype1'], $periods->resettype1);
        $this->assertSame($data['due1'], $periods->due1);
        $this->assertSame($data['valid1'], $periods->valid1);
        $this->assertSame(['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P7D'], $periods->windowend1);
        $this->assertSame(['since' => certification::SINCE_CERTIFIED, 'delay' => 'P1Y'], $periods->expiration1);

        $data2 = [
            'id' => (string)$certification->id,
            'recertify' => (string)DAYSECS,
        ];
        $certification = certification::update_certification_settings((object)$data2);
        $this->assertInstanceOf('stdClass', $certification);
        $this->assertSame($program1->id, $certification->programid1);
        $this->assertSame($program1->id, $certification->programid2);
        $this->assertSame($data2['recertify'], $certification->recertify);
        $periods = (object)json_decode($certification->periodsjson, true);
        $this->assertSame($data['resettype1'], $periods->resettype1);
        $this->assertSame($data['due1'], $periods->due1);
        $this->assertSame($data['valid1'], $periods->valid1);
        $this->assertSame(['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P7D'], $periods->windowend1);
        $this->assertSame(['since' => certification::SINCE_CERTIFIED, 'delay' => 'P1Y'], $periods->expiration1);
        $this->assertSame(null, $periods->grace2);
        $this->assertSame($defaultperiods['resettype2'], $periods->resettype2);
        $this->assertSame($defaultperiods['valid2'], $periods->valid2);
        $this->assertSame($periods->windowend1, $periods->windowend2);
        $this->assertSame($periods->expiration1, $periods->expiration2);

        $data3 = [
            'id' => (string)$certification->id,
            'programid2' => $program2->id,
            'grace2' => '12345',
            'resettype2' => certification::RESETTYPE_UNENROL,
            'valid2' => certification::SINCE_CERTIFIED,
            'windowend2' => util::get_delay_form_value(['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P7D'], 'days'),
            'expiration2' => util::get_delay_form_value(['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P1Y'], 'days'),
        ];
        $certification = certification::update_certification_settings((object)$data3);
        $this->assertInstanceOf('stdClass', $certification);
        $this->assertSame($program1->id, $certification->programid1);
        $this->assertSame($program2->id, $certification->programid2);
        $this->assertSame($data2['recertify'], $certification->recertify);
        $periods = (object)json_decode($certification->periodsjson, true);
        $this->assertSame($data['resettype1'], $periods->resettype1);
        $this->assertSame($data['due1'], $periods->due1);
        $this->assertSame($data['valid1'], $periods->valid1);
        $this->assertSame(['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P7D'], $periods->windowend1);
        $this->assertSame(['since' => certification::SINCE_CERTIFIED, 'delay' => 'P1Y'], $periods->expiration1);
        $this->assertSame($data3['grace2'], (string)$periods->grace2);
        $this->assertSame($data3['resettype2'], $periods->resettype2);
        $this->assertSame($data3['valid2'], $periods->valid2);
        $this->assertSame(['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P7D'], $periods->windowend2);
        $this->assertSame(['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P1Y'], $periods->expiration2);

        $data4 = [
            'id' => (string)$certification->id,
            'recertify' => null,
            'windowend1' => util::get_delay_form_value(['since' => certification::SINCE_NEVER, 'delay' => null], 'days'),
            'expiration1' => util::get_delay_form_value(['since' => certification::SINCE_NEVER, 'delay' => null], 'days'),
        ];
        $certification = certification::update_certification_settings((object)$data4);
        $this->assertInstanceOf('stdClass', $certification);
        $this->assertSame($program1->id, $certification->programid1);
        $this->assertSame(null, $certification->programid2);
        $this->assertSame(null, $certification->recertify);
        $periods = (object)json_decode($certification->periodsjson, true);
        $this->assertSame($data['resettype1'], $periods->resettype1);
        $this->assertSame($data['due1'], $periods->due1);
        $this->assertSame($data['valid1'], $periods->valid1);
        $this->assertSame(['since' => certification::SINCE_NEVER, 'delay' => null], $periods->windowend1);
        $this->assertSame(['since' => certification::SINCE_NEVER, 'delay' => null], $periods->expiration1);
        $this->assertSame($data3['grace2'], (string)$periods->grace2);
        $this->assertSame($data3['resettype2'], $periods->resettype2);
        $this->assertSame($data3['valid2'], $periods->valid2);
        $this->assertSame($periods->windowend1, $periods->windowend2);
        $this->assertSame($periods->expiration1, $periods->expiration2);

        $data5 = [
            'id' => (string)$certification->id,
            'recertify' => (string)WEEKSECS,
            'windowend1' => ['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P6D'],
            'expiration1' => ['since' => certification::SINCE_CERTIFIED, 'delay' => 'P1Y'],
            'windowend2' => ['since' => certification::SINCE_WINDOWDUE, 'delay' => 'P7D'],
            'expiration2' => ['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P2Y'],
        ];
        $certification = certification::update_certification_settings((object)$data5);
        $this->assertInstanceOf('stdClass', $certification);
        $this->assertSame($program1->id, $certification->programid1);
        $this->assertSame($program1->id, $certification->programid2);
        $this->assertSame($data5['recertify'], $certification->recertify);
        $periods = (object)json_decode($certification->periodsjson, true);
        $this->assertSame($data['resettype1'], $periods->resettype1);
        $this->assertSame($data['due1'], $periods->due1);
        $this->assertSame($data['valid1'], $periods->valid1);
        $this->assertSame(['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P6D'], $periods->windowend1);
        $this->assertSame(['since' => certification::SINCE_CERTIFIED, 'delay' => 'P1Y'], $periods->expiration1);
        $this->assertSame($data3['grace2'], (string)$periods->grace2);
        $this->assertSame($data3['resettype2'], $periods->resettype2);
        $this->assertSame($data3['valid2'], $periods->valid2);
        $this->assertSame(['since' => certification::SINCE_WINDOWDUE, 'delay' => 'P7D'], $periods->windowend2);
        $this->assertSame(['since' => certification::SINCE_WINDOWSTART, 'delay' => 'P2Y'], $periods->expiration2);
    }

    public function test_update_certificate() {
        if (!certificate::is_available()) {
            $this->markTestSkipped('tool_certificate not available');
        }

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        /** @var \tool_certificate_generator $certificategenerator */
        $certificategenerator = $this->getDataGenerator()->get_plugin_generator('tool_certificate');

        $certification = $generator->create_certification();
        $this->assertSame(null, $certification->templateid);

        $template = $certificategenerator->create_template(['name' => 't1']);

        $certification = certification::update_certificate($certification->id, $template->get_id());
        $this->assertSame((string)$template->get_id(), $certification->templateid);

        $certification = certification::update_certificate($certification->id, null);
        $this->assertSame(null, $certification->templateid);

        $certification = certification::update_certificate($certification->id, $template->get_id());
        $certification = certification::update_certificate($certification->id, 0);
        $this->assertSame(null, $certification->templateid);
    }

    public function test_delete_certification() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();
        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $category = $this->getDataGenerator()->create_category([]);
        $catcontext = \context_coursecat::instance($category->id);

        $certification1 = $generator->create_certification();

        $data = (object)[
            'fullname' => 'Some other certification',
            'idnumber' => 'SP2',
            'contextid' => $catcontext->id,
            'description' => 'Some desc',
            'descriptionformat' => '2',
            'presentation' => ['some' => 'test'],
            'public' => '1',
            'archived' => '1',
            'sources' => ['manual' => []],
            'cohorts' => [$cohort1->id, $cohort2->name],
            'programid1' => $program1->id,
            'programid2' => $program2->id,
            'recertify' => '77777',
        ];
        $certification2 = $generator->create_certification($data);

        certification::delete_certification($certification2->id);
        $this->assertSame(false, $DB->record_exists('tool_certify_certifications', ['id' => $certification2->id]));
        $this->assertSame(true, $DB->record_exists('tool_certify_certifications', ['id' => $certification1->id]));
    }

    public function test_make_snapshot() {
        global $DB;

        $syscontext = \context_system::instance();
        $data = (object)[
            'fullname' => 'Some certification',
            'idnumber' => 'SP1',
            'contextid' => $syscontext->id,
        ];
        $certification = certification::add_certification($data);
        $this->setAdminUser();
        $admin = get_admin();

        $this->setCurrentTimeStart();
        $DB->delete_records('tool_certify_crt_snapshots', []);
        certification::make_snapshot($certification->id, 'test', 'some explanation');

        $records = $DB->get_records('tool_certify_crt_snapshots', []);
        $this->assertCount(1, $records);

        $record = reset($records);
        $this->assertSame($certification->id, $record->certificationid);
        $this->assertSame('test', $record->reason);
        $this->assertTimeCurrent($record->timesnapshot);
        $this->assertSame($admin->id, $record->snapshotby);
        $this->assertSame('some explanation', $record->explanation);

        certification::delete_certification($certification->id);
        $this->setCurrentTimeStart();
        $DB->delete_records('tool_certify_crt_snapshots', []);
        certification::make_snapshot($certification->id, 'delete', 'some explanation');

        $records = $DB->get_records('tool_certify_crt_snapshots', []);
        $this->assertCount(1, $records);

        $record = reset($records);
        $this->assertSame($certification->id, $record->certificationid);
        $this->assertSame('delete', $record->reason);
        $this->assertTimeCurrent($record->timesnapshot);
        $this->assertSame($admin->id, $record->snapshotby);
        $this->assertSame('some explanation', $record->explanation);
    }

    public function test_pre_course_category_delete() {
        global $DB;

        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        $category = $this->getDataGenerator()->create_category([]);
        $catcontext = \context_coursecat::instance($category->id);
        $syscontext = \context_system::instance();

        $data = (object)[
            'fullname' => 'Some other certification',
            'idnumber' => 'SP',
            'contextid' => $catcontext->id,
        ];
        $certification = $generator->create_certification($data);

        certification::pre_course_category_delete($category->get_db_record());
        $certification = $DB->get_record('tool_certify_certifications', ['id' => $certification->id], '*', MUST_EXIST);
        $this->assertSame((string)$syscontext->id, $certification->contextid);

        $data = (object)[
            'fullname' => 'Some other certification 2',
            'idnumber' => 'SP 2',
            'contextid' => $catcontext->id,
        ];
        $certification2 = $generator->create_certification($data);

        $category->delete_full(false);
        $certification2 = $DB->get_record('tool_certify_certifications', ['id' => $certification2->id], '*', MUST_EXIST);
        $this->assertSame((string)$syscontext->id, $certification2->contextid);
    }

    public function test_get_periods_defaults() {
        $expected = [
            'resettype1' => 2,
            'due1' => null,
            'valid1' => 'certified',
            'windowend1' => ['since' => 'never', 'delay' => null],
            'expiration1' => ['since' => 'never', 'delay' => null],
            'grace2' => null,
            'resettype2' => 2,
            'valid2' => 'windowdue',
            'windowend2' => ['since' => 'never', 'delay' => null],
            'expiration2' => ['since' => 'never', 'delay' => null],
        ];
        $defaults = certification::get_periods_defaults();
        $this->assertInstanceOf(\stdClass::class, $defaults);
        $this->assertSame($expected, (array)$defaults);
    }

    public function test_get_periods_settings() {
        /** @var \tool_certify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certify');

        /** @var \enrol_programs_generator $programgenerator */
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('enrol_programs');

        $perioddefaults = certification::get_periods_defaults();

        $program1 = $programgenerator->create_program();
        $program2 = $programgenerator->create_program();

        $data = (object)[
            'fullname' => 'Some other certification',
            'idnumber' => 'SP',
            'programid1' => $program1->id,
            'programid2' => $program2->id,
            'recertify' => '77777',
        ];
        $certification = $generator->create_certification($data);

        $result = certification::get_periods_settings($certification);
        $this->assertInstanceOf(\stdClass::class, $result);
        $expected = certification::get_periods_defaults();
        $expected->programid1 = $program1->id;
        $expected->programid2 = $program2->id;
        $expected->recertify = $data->recertify;
        $this->assertEquals((array)$expected, (array)$result);

        $certification->programid2 = null;
        $result = certification::get_periods_settings($certification);
        $expected->programid2 = $program1->id;
        $this->assertEquals((array)$expected, (array)$result);

        $this->assertDebuggingNotCalled();

        $periods = json_decode($certification->periodsjson);
        $periods->resettype1 = 'xxxx';
        $certification->periodsjson = util::json_encode($periods);
        $result = certification::get_periods_settings($certification);
        $expected->resettype1 = $perioddefaults->resettype1;
        $this->assertEquals((array)$expected, (array)$result);
        $this->assertDebuggingCalled("invalid resettype1 detected in $certification->id certification");
    }

    public function test_get_resettype_options() {
        $result = certification::get_resettype_options();
        $this->assertIsArray($result);
        $this->assertArrayHasKey(certification::RESETTYPE_NONE, $result);
        $this->assertArrayHasKey(certification::RESETTYPE_DEALLOCATE, $result);
        $this->assertArrayHasKey(certification::RESETTYPE_UNENROL, $result);
        $this->assertArrayHasKey(certification::RESETTYPE_PURGE, $result);
        $this->assertCount(4, $result);
    }

    public function test_get_valid_options() {
        $result = certification::get_valid_options();
        $this->assertIsArray($result);
        $this->assertArrayHasKey(certification::SINCE_CERTIFIED, $result);
        $this->assertArrayHasKey(certification::SINCE_WINDOWSTART, $result);
        $this->assertArrayHasKey(certification::SINCE_WINDOWDUE, $result);
        $this->assertArrayHasKey(certification::SINCE_WINDOWEND, $result);
        $this->assertCount(4, $result);
    }

    public function test_get_windowend_options() {
        $result = certification::get_windowend_options();
        $this->assertIsArray($result);
        $this->assertArrayHasKey(certification::SINCE_NEVER, $result);
        $this->assertArrayHasKey(certification::SINCE_WINDOWSTART, $result);
        $this->assertArrayHasKey(certification::SINCE_WINDOWDUE, $result);
        $this->assertCount(3, $result);
    }

    public function test_get_expiration_options() {
        $result = certification::get_expiration_options();
        $this->assertIsArray($result);
        $this->assertArrayHasKey(certification::SINCE_NEVER, $result);
        $this->assertArrayHasKey(certification::SINCE_CERTIFIED, $result);
        $this->assertArrayHasKey(certification::SINCE_WINDOWSTART, $result);
        $this->assertArrayHasKey(certification::SINCE_WINDOWDUE, $result);
        $this->assertArrayHasKey(certification::SINCE_WINDOWEND, $result);
        $this->assertCount(5, $result);
    }
}