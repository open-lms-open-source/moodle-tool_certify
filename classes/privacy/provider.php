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

namespace tool_certify\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Certifications privacy info.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // Transactions store user data.
    \core_privacy\local\metadata\provider,

    // The certifications plugin has user assignments.
    \core_privacy\local\request\plugin\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta-data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'tool_certify_requests',
            [
                'sourceid' => 'privacy:metadata:field:sourceid',
                'userid' => 'privacy:metadata:field:userid',
                'datajson' => 'privacy:metadata:field:datajson',
                'timerequested' => 'privacy:metadata:field:timerequested',
                'timerejected' => 'privacy:metadata:field:timerejected',
                'rejectedby' => 'privacy:metadata:field:rejectedby',
            ],
            'privacy:metadata:table:tool_certify_requests'
        );
        $collection->add_database_table(
            'tool_certify_assignments',
            [
                'certificationid' => 'privacy:metadata:field:certificationid',
                'userid' => 'privacy:metadata:field:userid',
                'archived' => 'privacy:metadata:field:archived',
                'timecertifieduntil' => 'privacy:metadata:field:timecertifieduntil',
            ],
            'privacy:metadata:table:tool_certify_assignments'
        );

        $collection->add_database_table(
            'tool_certify_periods',
            [
                'certificationid' => 'privacy:metadata:field:certificationid',
                'userid' => 'privacy:metadata:field:userid',
                'assignmentid' => 'privacy:metadata:field:assignmentid',
                'programid' => 'privacy:metadata:field:programid',
                'timewindowstart' => 'privacy:metadata:field:timewindowstart',
                'timewindowdue' => 'privacy:metadata:field:timewindowdue',
                'timewindowend' => 'privacy:metadata:field:timewindowend',
                'timecertified' => 'privacy:metadata:field:timecertified',
                'timefrom' => 'privacy:metadata:field:timefrom',
                'timeuntil' => 'privacy:metadata:field:timeuntil',
                'timerevoked' => 'privacy:metadata:field:timerevoked',
            ],
            'privacy:metadata:table:tool_certify_periods'
        );

        $collection->add_database_table(
            'tool_certify_usr_snapshots',
            [
                'certificationid' => 'privacy:metadata:field:certificationid',
                'userid' => 'privacy:metadata:field:userid',
                'assignmentid' => 'privacy:metadata:field:assignmentid',
                'reason' => 'privacy:metadata:field:reason',
                'timesnapshot' => 'privacy:metadata:field:timesnapshot',
                'snapshotby' => 'privacy:metadata:field:snapshotby',
                'explanation' => 'privacy:metadata:field:explanation',
            ],
            'privacy:metadata:table:tool_certify_usr_snapshots'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $sql = "SELECT ctx.id
                  FROM {tool_certify_certifications} c
                  JOIN {tool_certify_assignments} ca ON ca.certificationid = c.id
                  JOIN {context} ctx ON c.contextid = ctx.id
                  JOIN {user} u ON u.id = ca.userid AND u.deleted = 0
                 WHERE u.id = :userid";
        $params = ['userid' => $userid];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        $sql = "SELECT u.id
                  FROM {tool_certify_certifications} c
                  JOIN {tool_certify_assignments} ca ON ca.certificationid = c.id
                  JOIN {context} ctx ON c.contextid = ctx.id
                  JOIN {user} u ON u.id = ca.userid AND u.deleted = 0
                 WHERE ctx.id = :contextid";
        $params = ['contextid' => $context->id];

        $userlist->add_from_sql('id', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT c.contextid, c.fullname, ca.id, ca.certificationid, ca.userid, ca.sourceid, ca.archived, ca.timecreated
                  FROM {tool_certify_certifications} c
                  JOIN {tool_certify_assignments} ca ON ca.certificationid = c.id
                  JOIN {context} ctx ON c.contextid = ctx.id
                  JOIN {user} u ON u.id = ca.userid AND u.deleted = 0
                 WHERE ctx.id {$contextsql} AND u.id = :userid
              ORDER BY ca.id ASC";
        $params = ['userid' => $user->id];
        $params += $contextparams;

        $strassignment = get_string('assignments', 'tool_certify');

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $assignment) {
            // Format dates.
            $assignment->timecreated = \core_privacy\local\request\transform::datetime($assignment->timecreated);

            // Add periods.
            $assignment->periods = [];
            $periods = $DB->get_records('tool_certify_periods',
                ['certificationid' => $assignment->certificationid, 'userid' => $assignment->userid], 'timewindowstart ASC');
            foreach ($periods as $p) {
                $period = new \stdClass();
                $period->programid = $p->programid;
                $period->timewindowstart = \core_privacy\local\request\transform::datetime($p->timewindowstart);
                $period->timewindowdue = \core_privacy\local\request\transform::datetime($p->timewindowdue);
                $period->timewindowend = \core_privacy\local\request\transform::datetime($p->timewindowend);
                $period->timecertified = \core_privacy\local\request\transform::datetime($p->timecertified);
                $period->timefrom = \core_privacy\local\request\transform::datetime($p->timefrom);
                $period->timeuntil = \core_privacy\local\request\transform::datetime($p->timeuntil);
                $period->timerevoked = \core_privacy\local\request\transform::datetime($p->timerevoked);
                $assignment->periods[] = $period;
            }

            // Set user snapshot data.
            $assignment->usersnapshots = [];
            $sql = "SELECT certificationid, userid, reason, timesnapshot, snapshotby, explanation, sourceid,
                           archived, periodsjson
                      FROM {tool_certify_usr_snapshots}
                     WHERE certificationid = :certificationid AND userid = :userid
                  ORDER BY timesnapshot ASC";
            $params = ['certificationid' => $assignment->certificationid, 'userid' => $assignment->userid];

            $snapshots = $DB->get_recordset_sql($sql, $params);
            foreach ($snapshots as $snapshot) {
                $snapshot->timesnapshot = \core_privacy\local\request\transform::datetime($snapshot->timesnapshot);
                $assignment->usersnapshots[] = $snapshot;
            }
            $snapshots->close();

            $certificationcontext = \context::instance_by_id($assignment->contextid);
            unset($assignment->id, $assignment->contextid);
            writer::with_context($certificationcontext)->export_data(
                [$strassignment, $assignment->fullname],
                (object) ['assignment' => $assignment]
            );
        }
        $rs->close();
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        $sql = "SELECT ca.*
                  FROM {tool_certify_certifications} c
                  JOIN {tool_certify_assignments} ca ON ca.certificationid = c.id
                  JOIN {tool_certify_sources} s ON s.id = ca.sourceid AND s.certificationid = c.id
                  JOIN {context} ctx ON c.contextid = ctx.id
                  JOIN {user} u ON u.id = ca.userid AND u.deleted = 0
                 WHERE ctx.id = :contextid
              ORDER BY ca.id ASC, u.id ASC";
        $params = ['contextid' => $context->id];

        $allclasses = \tool_certify\local\assignment::get_source_classes();
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $assignment) {
            $certification = $DB->get_record('tool_certify_certifications', ['id' => $assignment->certificationid]);
            $source = $DB->get_record('tool_certify_sources', ['id' => $assignment->sourceid]);
            if (!isset($allclasses[$source->type])) {
                continue;
            }
            /** @var \tool_certify\local\source\base $coursceclass */
            $coursceclass = $allclasses[$source->type];
            $coursceclass::unassign_user($certification, $source, $assignment);

            $params = ['certificationid' => $assignment->certificationid, 'userid' => $assignment->userid];
            $DB->delete_records('tool_certify_usr_snapshots', $params);
        }
        $rs->close();
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT ca.*
                  FROM {tool_certify_certifications} c
                  JOIN {tool_certify_assignments} ca ON ca.certificationid = c.id
                  JOIN {tool_certify_sources} s ON s.id = ca.sourceid AND s.certificationid = c.id
                  JOIN {context} ctx ON c.contextid = ctx.id
                  JOIN {user} u ON u.id = ca.userid AND u.deleted = 0
                 WHERE u.id = :userid AND ctx.id {$contextsql}
              ORDER BY ca.id ASC";
        $params = ['userid' => $user->id];
        $params += $contextparams;

        $allclasses = \tool_certify\local\assignment::get_source_classes();
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $assignment) {
            $certification = $DB->get_record('tool_certify_certifications', ['id' => $assignment->certificationid]);
            $source = $DB->get_record('tool_certify_sources', ['id' => $assignment->sourceid]);
            if (!isset($allclasses[$source->type])) {
                continue;
            }
            /** @var \tool_certify\local\source\base $coursceclass */
            $coursceclass = $allclasses[$source->type];
            $coursceclass::unassign_user($certification, $source, $assignment);
        }
        $rs->close();

        $params = ['userid' => $user->id];
        $DB->delete_records('tool_certify_usr_snapshots', $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $sql = "SELECT ca.*
                  FROM {tool_certify_certifications} c
                  JOIN {tool_certify_assignments} ca ON ca.certificationid = c.id
                  JOIN {tool_certify_sources} s ON s.id = ca.sourceid AND s.certificationid = c.id
                  JOIN {context} ctx ON c.contextid = ctx.id
                  JOIN {user} u ON u.id = ca.userid AND u.deleted = 0
                 WHERE ctx.id = :contextid AND u.id {$usersql}
              ORDER BY ca.id ASC, u.id ASC";
        $params = ['contextid' => $context->id];
        $params += $userparams;

        $allclasses = \tool_certify\local\assignment::get_source_classes();
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $assignment) {
            $certification = $DB->get_record('tool_certify_certifications', ['id' => $assignment->certificationid]);
            $source = $DB->get_record('tool_certify_sources', ['id' => $assignment->sourceid]);
            if (!isset($allclasses[$source->type])) {
                continue;
            }
            /** @var \tool_certify\local\source\base $coursceclass */
            $coursceclass = $allclasses[$source->type];
            $coursceclass::unassign_user($certification, $source, $assignment);

            $params = ['userid' => $assignment->userid];
            $DB->delete_records('tool_certify_usr_snapshots', $params);
        }
        $rs->close();
    }
}
