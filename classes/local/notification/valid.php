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
 * Certification validity notification.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class valid extends base {
    /**
     * Send notifications.
     *
     * @param stdClass|null $certification
     * @param stdClass|null $user
     * @return void
     */
    public static function notify_users(?stdClass $certification, ?stdClass $user): void {
        global $DB;

        $source = null;
        $assignment = null;
        $loadfunction = function(stdClass $period) use (&$certification, &$source, &$assignment, &$user): void {
            global $DB;
            if (!$assignment || $assignment->userid != $period->userid || $assignment->certificationid != $period->certificationid) {
                $assignment = $DB->get_record('tool_certify_assignments',
                    ['userid' => $period->userid, 'certificationid' => $period->certificationid], '*', MUST_EXIST);
            }
            if (!$source || $source->id != $assignment->sourceid) {
                $source = $DB->get_record('tool_certify_sources', ['id' => $assignment->sourceid], '*', MUST_EXIST);
            }
            if (!$user || $user->id != $period->userid) {
                $user = $DB->get_record('user', ['id' => $period->userid], '*', MUST_EXIST);
            }
            if (!$certification || $certification->id != $period->certificationid) {
                $certification = $DB->get_record('tool_certify_certifications', ['id' => $period->certificationid], '*', MUST_EXIST);
            }
        };

        $params = [];
        $certificationselect = '';
        if ($certification) {
            $certificationselect = "AND cp.certificationid = :certificationid";
            $params['certificationid'] = $certification->id;
        }
        $userselect = '';
        if ($user) {
            $userselect = "AND cp.userid = :userid";
            $params['userid'] = $user->id;
        }
        $params['now1'] = time();
        $params['now2'] = $params['now1'];

        $sql = "SELECT cp.*
                  FROM {tool_certify_periods} cp
                  JOIN {tool_certify_assignments} ca ON ca.userid = cp.userid AND ca.certificationid = cp.certificationid
                  JOIN {user} u ON u.id = ca.userid AND u.deleted = 0 AND u.suspended = 0
                  JOIN {tool_certify_sources} cs ON cs.id = ca.sourceid
                  JOIN {tool_certify_certifications} c ON c.id = ca.certificationid
                  JOIN {local_openlms_notifications} n
                       ON n.component = 'tool_certify' AND n.notificationtype = 'valid' AND n.instanceid = c.id AND n.enabled = 1
             LEFT JOIN {local_openlms_user_notified} un
                       ON un.notificationid = n.id AND un.userid = ca.userid AND un.otherid1 = ca.id AND un.otherid2 = cp.id
                 WHERE un.id IS NULL AND c.archived = 0 AND ca.archived = 0
                       AND cp.timecertified IS NOT NULL AND cp.timerevoked IS NULL
                       AND cp.timefrom <= :now1 AND (cp.timeuntil IS NULL OR cp.timeuntil > :now2)
                       $certificationselect $userselect
              ORDER BY c.id, cs.id, ca.userid";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $period) {
            $loadfunction($period);
            self::notify_assigned_user($certification, $source, $assignment, $period, $user, false);
        }
        $rs->close();
    }
}
