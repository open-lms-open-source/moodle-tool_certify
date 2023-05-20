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

namespace tool_certify\task;

/**
 * Certification cron task.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron extends \core\task\scheduled_task {

    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcron', 'tool_certify');
    }

    /**
     * Run task for all program cron stuff.
     */
    public function execute() {
        if (!enrol_is_enabled('programs')) {
            return;
        }

        \tool_certify\local\assignment::fix_assignment_sources(null, null);
        \enrol_programs\local\source\certify::sync_certifications(null, null);

        \tool_certify\local\notification_manager::trigger_notifications(null, null);

        \tool_certify\local\period::process_recertifications(null, null);

        \tool_certify\local\certificate::cron();
    }
}
