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
 * Certification util test.
 *
 * @group      openlms
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_certify\local\util
 */
final class util_test extends \advanced_testcase {
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_json_encode() {
        $this->assertSame('{"abc":"\\\\šk\"\'"}', util::json_encode(['abc' => '\šk"\'']));
    }

    public function test_get_submitted_delay() {
        $data = (object)[
            'test1' => ['timeunit' => 'years', 'number' => 2],
            'test2' => ['timeunit' => 'months', 'number' => 1],
            'test3' => ['timeunit' => 'days', 'number' => 3],
            'test4' => ['timeunit' => 'hours', 'number' => 8],
            'test5' => ['timeunit' => 'hours', 'number' => -1],
        ];

        $delay = util::get_submitted_delay('test1', $data);
        $this->assertSame('P2Y', $delay);

        $delay = util::get_submitted_delay('test2', $data);
        $this->assertSame('P1M', $delay);

        $delay = util::get_submitted_delay('test3', $data);
        $this->assertSame('P3D', $delay);

        $delay = util::get_submitted_delay('test4', $data);
        $this->assertSame('PT8H', $delay);

        try {
            util::get_submitted_delay('test5', $data);
            $this->fail('exception expected');
        } catch (\moodle_exception $ex) {
            $this->assertInstanceOf(\coding_exception::class, $ex);
            $this->assertSame('Coding error detected, it must be fixed by a programmer: Invalid delay value', $ex->getMessage());
        }
    }

    public function test_get_delay_form_value() {
        $data = ['since' => certification::SINCE_CERTIFIED, 'delay' => 'P3M'];
        $expected = ['since' => 'certified', 'timeunit' => 'months', 'number' => '3'];
        $this->assertSame($expected, util::get_delay_form_value($data, 'hours'));

        $data = ['since' => certification::SINCE_WINDOWDUE, 'delay' => 'P2D'];
        $expected = ['since' => 'windowdue', 'timeunit' => 'days', 'number' => '2'];
        $this->assertSame($expected, util::get_delay_form_value($data, 'hours'));

        $data = ['since' => certification::SINCE_WINDOWDUE, 'delay' => 'PT3H'];
        $expected = ['since' => 'windowdue', 'timeunit' => 'hours', 'number' => '3'];
        $this->assertSame($expected, util::get_delay_form_value($data, 'hours'));

        $data = ['since' => certification::SINCE_WINDOWDUE, 'delay' => ''];
        $expected = ['since' => 'windowdue', 'timeunit' => 'hours', 'number' => null];
        $this->assertSame($expected, util::get_delay_form_value($data, 'hours'));

        $data = ['since' => certification::SINCE_WINDOWSTART, 'delay' => ''];
        $expected = ['since' => 'windowstart', 'timeunit' => 'days', 'number' => null];
        $this->assertSame($expected, util::get_delay_form_value($data, 'days'));

        $this->assertDebuggingNotCalled();
        $data = ['since' => certification::SINCE_WINDOWSTART, 'delay' => 'X2'];
        $expected = ['since' => 'windowstart', 'timeunit' => 'days', 'number' => null];
        $this->assertSame($expected, util::get_delay_form_value($data, 'days'));
        $this->assertDebuggingCalled('Unsupported delay format: \'X2\'');
    }

    public function test_normalise_delay() {
        $this->assertSame('P1M', util::normalise_delay('P1M'));
        $this->assertSame('P99D', util::normalise_delay('P99D'));
        $this->assertSame('PT9H', util::normalise_delay('PT9H'));
        $this->assertDebuggingNotCalled();
        $this->assertSame(null, util::normalise_delay(''));
        $this->assertSame(null, util::normalise_delay(null));
        $this->assertSame(null, util::normalise_delay('P0M'));
        $this->assertDebuggingNotCalled();

        $this->assertSame(null, util::normalise_delay('P9X'));
        $this->assertDebuggingCalled();
        $this->assertSame(null, util::normalise_delay('P1M1D'));
        $this->assertDebuggingCalled();
    }

    public function test_format_interval() {
        $this->assertSame('2 months', util::format_interval('P2M'));
        $this->assertSame('2 days', util::format_interval('P2D'));
        $this->assertSame('2 hours', util::format_interval('PT2H'));
        $this->assertSame('1 month, 2 days, 3 hours', util::format_interval('P1M2DT3H'));
        $this->assertSame('Not set', util::format_interval(''));
        $this->assertSame('Not set', util::format_interval(null));
    }

    public function test_format_duration() {
        $this->assertSame('2 days', util::format_duration(DAYSECS * 2));
        $this->assertSame('38 days, 4 hours, 35 seconds', util::format_duration(DAYSECS * 3 + HOURSECS * 4 + WEEKSECS * 5 + 35));
        $this->assertSame('Not set', util::format_duration(null));
        $this->assertSame('Not set', util::format_duration(0));
        $this->assertSame('Error', util::format_duration(DAYSECS * -1));
    }

    public function test_convert_to_count_sql() {
        $sql = 'SELECT *
                  FROM {user}
              ORDER BY id';
        $expected = 'SELECT COUNT(\'x\') FROM {user}';
        $this->assertSame($expected, util::convert_to_count_sql($sql));
    }
}