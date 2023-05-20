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
 * Certification tenant util test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\tenant
 */
final class tenant_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_is_available() {
        if (file_exists(__DIR__ . '/../../../olms_tenant/version.php')) {
            $this->assertTrue(tenant::is_available());
        } else {
            $this->assertFalse(tenant::is_available());
        }
    }

    public function test_is_active() {
        if (!tenant::is_available()) {
            $this->markTestSkipped('no tenants');
        }
        $this->assertFalse(tenant::is_active());

        \tool_olms_tenant\tenants::activate_tenants();
        $this->assertTrue(tenant::is_active());
    }
}