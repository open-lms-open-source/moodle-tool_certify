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

namespace tool_certify\local\notification;

use stdClass;

/**
 * Certification assignment notification.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment extends base {
    /**
     * Send notifications.
     *
     * @param stdClass|null $certification
     * @param stdClass|null $user
     * @return void
     */
    public static function notify_users(?stdClass $certification, ?stdClass $user): void {
        // Nothing to do, we notify during assignment.
    }

    /**
     * Send notifications related to assignment.
     *
     * @param stdClass $user
     * @param stdClass $certification
     * @param stdClass $source
     * @param stdClass $assignment
     * @return void
     */
    public static function notify_now(stdClass $user, stdClass $certification, stdClass $source, stdClass $assignment): void {
        self::notify_assigned_user($certification, $source, $assignment, null, $user);
    }
}
