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

namespace tool_certify\event;

use tool_certify\local\certification;

/**
 * Certification event test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\event\certification_created
 */
final class certification_created_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_add_certification() {
        $syscontext = \context_system::instance();
        $data = (object)[
            'fullname' => 'Some certification',
            'idnumber' => 'SP1',
            'contextid' => $syscontext->id,
        ];
        $admin = get_admin();

        $this->setAdminUser();
        $sink = $this->redirectEvents();
        $certification = certification::add_certification($data);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(certification_created::class, $event);
        $this->assertEquals($syscontext->id, $event->contextid);
        $this->assertSame($certification->id, $event->objectid);
        $this->assertSame('c', $event->crud);
        $this->assertSame($event::LEVEL_OTHER, $event->edulevel);
        $this->assertSame('tool_certify_certifications', $event->objecttable);
        $this->assertSame('Certification created', $event::get_name());
        $description = $event->get_description();
        $certificationurl = new \moodle_url('/admin/tool/certify/management/certification.php', ['id' => $certification->id]);
        $this->assertSame($certificationurl->out(false), $event->get_url()->out(false));
    }
}
