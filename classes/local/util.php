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

use stdClass;

/**
 * Utility class for certifications.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class util {

    /**
     * Encode JSON date in a consistent way.
     *
     * @param $data
     * @return string
     */
    public static function json_encode($data): string {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Parse form data for delay settings.
     *
     * @param string $name
     * @param stdClass $data
     */
    public static function get_submitted_delay(string $name, stdClass $data): string {
        $type = $data->{$name}['timeunit'];
        $value = (int)$data->{$name}['number'];

        if ($value < 0) {
            throw new \coding_exception('Invalid delay value');
        }
        if ($type === 'years') {
            return 'P' . $value . 'Y';
        } else if ($type === 'months') {
            return 'P' . $value . 'M';
        } else if ($type === 'days') {
            return 'P' . $value . 'D';
        } else if ($type === 'hours') {
            return 'PT' . $value . 'H';
        }
        throw new \coding_exception('Invalid delay type');
    }

    /**
     * Prepare current data for delay settings.
     *
     * @param array $value interval string
     * @param string $defaultunit
     * @return array
     */
    public static function get_delay_form_value(array $value, string $defaultunit): array {
        $since = $value['since'];
        $delay = $value['delay'];

        if ($delay === null || $delay === '') {
            return ['since' => $since, 'timeunit' => $defaultunit, 'number' => null];
        }
        if (preg_match('/^P(\d+)Y$/D', $delay, $matches)) {
            return ['since' => $since, 'timeunit' => 'years', 'number' => $matches[1]];
        }
        if (preg_match('/^P(\d+)M$/D', $delay, $matches)) {
            return ['since' => $since, 'timeunit' => 'months', 'number' => $matches[1]];
        }
        if (preg_match('/^P(\d+)D$/D', $delay, $matches)) {
            return ['since' => $since, 'timeunit' => 'days', 'number' => $matches[1]];
        }
        if (preg_match('/^PT(\d+)H$/D', $delay, $matches)) {
            return ['since' => $since, 'timeunit' => 'hours', 'number' => $matches[1]];
        }

        debugging('Unsupported delay format: ' . var_export($delay, true), DEBUG_DEVELOPER);
        return ['since' => $since, 'timeunit' => $defaultunit, 'number' => null];
    }

    /**
     * Normalise delays used in allocation settings.
     *
     * NOTE: for now only simple P1Y, P22M, P22D and PT22H formats are supported,
     *       support for more options may be added later.
     *
     * @param string|null $string
     * @return string|null
     */
    public static function normalise_delay(?string $string): ?string {
        if (trim($string ?? '') === '') {
            return null;
        }

        if (preg_match('/^P\d+Y$/D', $string)) {
            if ($string === 'P0Y') {
                return null;
            }
            return $string;
        }
        if (preg_match('/^P\d+M$/D', $string)) {
            if ($string === 'P0M') {
                return null;
            }
            return $string;
        }
        if (preg_match('/^P\d+D$/D', $string)) {
            if ($string === 'P0D') {
                return null;
            }
            return $string;
        }
        if (preg_match('/^PT\d+H$/D', $string)) {
            if ($string === 'PT0H') {
                return null;
            }
            return $string;
        }

        debugging('Unsupported delay format: ' . $string, DEBUG_DEVELOPER);
        return null;
    }

    /**
     * Format delay that was stored in format of PHP DateInterval
     * to human-readable form.
     *
     * @param string|null $string
     * @return string
     */
    public static function format_interval(?string $string): string {
        if (!$string) {
            return get_string('notset', 'tool_certify');
        }

        $interval = new \DateInterval($string);

        $result = [];
        if ($interval->y) {
            if ($interval->y == 1) {
                $result[] = get_string('numyear', 'core', $interval->y);
            } else {
                $result[] = get_string('numyears', 'core', $interval->y);
            }
        }
        if ($interval->m) {
            if ($interval->m == 1) {
                $result[] = get_string('nummonth', 'core', $interval->m);
            } else {
                $result[] = get_string('nummonths', 'core', $interval->m);
            }
        }
        if ($interval->d) {
            if ($interval->d == 1) {
                $result[] = get_string('numday', 'core', $interval->d);
            } else {
                $result[] = get_string('numdays', 'core', $interval->d);
            }
        }
        if ($interval->h) {
            $result[] = get_string('numhours', 'core', $interval->h);
        }
        if ($interval->i) {
            $result[] = get_string('numminutes', 'core', $interval->i);
        }
        if ($interval->s) {
            $result[] = get_string('numseconds', 'core', $interval->s);
        }

        if ($result) {
            return implode(', ', $result);
        } else {
            return '';
        }
    }

    /**
     * Format duration of interval specified using seconds value.
     *
     * @param int|null $duration seconds
     * @return string
     */
    public static function format_duration(?int $duration): string {
        if ($duration < 0) {
            return get_string('error');
        }
        if (!$duration) {
            return get_string('notset', 'tool_certify');
        }
        $days = intval($duration / DAYSECS);
        $duration = $duration - $days * DAYSECS;
        $hours = intval($duration / HOURSECS);
        $duration = $duration - $hours * HOURSECS;
        $minutes = intval($duration / MINSECS);
        $seconds = $duration - $minutes * MINSECS;

        $interval = 'P';
        if ($days) {
            $interval .= $days . 'D';
        }
        if ($hours || $minutes || $seconds) {
            $interval .= 'T';
            if ($hours) {
                $interval .= $hours . 'H';
            }
            if ($minutes) {
                $interval .= $minutes . 'M';
            }
            if ($seconds) {
                $interval .= $seconds . 'S';
            }
        }

        return self::format_interval($interval);
    }

    /**
     * Convert SELECT query to format suitable for $DB->count_records_sql().
     *
     * @param string $sql
     * @return string
     */
    public static function convert_to_count_sql(string $sql): string {
        $count = null;
        $sql = preg_replace('/^\s*SELECT.*FROM/Uis', "SELECT COUNT('x') FROM", $sql, 1, $count);
        if ($count !== 1) {
            debugging('Cannot convert SELECT query to count compatible form', DEBUG_DEVELOPER);
        }
        // Subqueries should not have ORDER BYs, so this should be safe,
        // worst case there will be a fatal error caused by cutting the query short.
        $sql = preg_replace('/\s*ORDER BY.*$/is', '', $sql);
        return $sql;
    }
}
