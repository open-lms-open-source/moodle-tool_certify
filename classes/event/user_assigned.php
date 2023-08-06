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

/**
 * User assigned event.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_assigned extends \core\event\base {
    /**
     * Helper for event creation.
     *
     * @param \stdClass $assignment
     * @param \stdClass $certification
     *
     * @return user_assigned|static
     */
    public static function create_from_assignment(\stdClass $assignment, \stdClass $certification) {
        $context = \context::instance_by_id($certification->contextid);
        $data = array(
            'context' => $context,
            'objectid' => $assignment->id,
            'relateduserid' => $assignment->userid,
            'other' => ['certificationid' => $certification->id]
        );
        /** @var static $event */
        $event = self::create($data);
        $event->add_record_snapshot('tool_certify_assignments', $assignment);
        $event->add_record_snapshot('tool_certify_certifications', $certification);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->relateduserid' was assigned to certification with id '$this->objectid'";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_user_assigned', 'tool_certify');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/admin/tool/certify/management/user_assignment.php', ['id' => $this->objectid]);
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'tool_certify_assignments';
    }
}
