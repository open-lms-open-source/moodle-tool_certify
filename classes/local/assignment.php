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
 * Certification assignment helper.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment {
    /**
     * Returns list of all source classes present.
     *
     * @return string[] type => classname
     */
    public static function get_source_classes(): array {
        // Note: in theory this could be extended to load arbitrary classes.
        $types = [
            source\manual::get_type() => source\manual::class,
            source\cohort::get_type() => source\cohort::class,
            source\selfassignment::get_type() => source\selfassignment::class,
            source\approval::get_type() => source\approval::class,
        ];
        return $types;
    }

    /**
     * Returns list of all source names.
     *
     * @return string[] type => source name
     */
    public static function get_source_names(): array {
        /** @var source\base[] $classes */ // Type hack.
        $classes = self::get_source_classes();

        $result = [];
        foreach ($classes as $class) {
            $result[$class::get_type()] = $class::get_name();
        }

        return $result;
    }

    /**
     * Manually update user assignment data including temporary certification.
     *
     * @param stdClass $data
     * @return stdClass assignment record
     */
    public static function update_user(stdClass $data): stdClass {
        global $DB;

        $record = $DB->get_record('tool_certify_assignments', ['id' => $data->id], '*', MUST_EXIST);

        $trans = $DB->start_delegated_transaction();

        if (property_exists($data, 'timecertifieduntil')) {
            $record->timecertifieduntil = $data->timecertifieduntil;
            if (!$record->timecertifieduntil) {
                $record->timecertifieduntil = null;
            }
        }
        if (property_exists($data, 'archived')) {
            $record->archived = (int)(bool)$data->archived;
        }

        $DB->update_record('tool_certify_assignments', $record);
        $record = $DB->get_record('tool_certify_assignments', ['id' => $record->id], '*', MUST_EXIST);

        if (property_exists($data, 'stoprecertify')) {
            period::update_recertifiable($record, (bool)$data->stoprecertify);
        }

        assignment::make_snapshot($record->certificationid, $record->userid, 'assignment_edit');

        $trans->allow_commit();

        \enrol_programs\local\source\certify::sync_certifications($record->certificationid, $record->userid);

        notification_manager::trigger_notifications($record->certificationid, $record->userid);

        return $DB->get_record('tool_certify_assignments', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Returns valid until/expiration date as HTML.
     *
     * @param stdClass $certification
     * @param stdClass $assignment
     * @return string
     */
    public static function get_until_html(stdClass $certification, stdClass $assignment): string {
        global $DB;

        if ($certification->id != $assignment->certificationid) {
            throw new \coding_exception('invalid parameters');
        }

        $sql = "SELECT cp.*
                  FROM {tool_certify_periods} cp
                  JOIN {tool_certify_assignments} ca ON ca.userid = cp.userid AND ca.certificationid = cp.certificationid
                 WHERE cp.timerevoked IS NULL and cp.timecertified IS NOT NULL
                       AND ca.id = :assignmentid";
        $periods = $DB->get_records_sql($sql, ['assignmentid' => $assignment->id]);
        if (!$periods) {
            if ($assignment->timecertifieduntil) {
                return userdate($assignment->timecertifieduntil, get_string('strftimedatetimeshort'));
            } else {
                return get_string('notset', 'tool_certify');
            }
        }

        $until = null;
        foreach ($periods as $period) {
            if ($period->timeuntil === null) {
                $until = null;
                break;
            }
            if ($period->timeuntil > $until) {
                $until = $period->timeuntil;
            }
        }
        if ($until === null) {
            return get_string('notset', 'tool_certify');
        }
        if ($assignment->timecertifieduntil && $assignment->timecertifieduntil > $until) {
            return userdate($assignment->timecertifieduntil, get_string('strftimedatetimeshort'));
        } else {
            return userdate($until, get_string('strftimedatetimeshort'));
        }
    }

    /**
     * Returns completion status as fancy HTML.
     *
     * @param stdClass $certification
     * @param stdClass $assignment
     * @return string
     */
    public static function get_status_html(stdClass $certification, stdClass $assignment): string {
        global $DB;

        $now = time();

        if ($certification->archived || $assignment->archived) {
            return '<span class="badge badge-dark">' . get_string('certificationstatus_archived', 'tool_certify') . '</span>';
        }

        $select = "certificationid = :certificationid AND userid = :userid AND timerevoked IS NULL AND timecertified IS NOT NULL AND timefrom <= $now AND (timeuntil IS NULL OR timeuntil > $now)";
        $params = ['certificationid' => $assignment->certificationid, 'userid' => $assignment->userid];
        if ($DB->record_exists_select('tool_certify_periods', $select, $params)) {
            return '<span class="badge badge-success">' . get_string('certificationstatus_valid', 'tool_certify') . '</span>';
        }

        if ($assignment->timecertifieduntil && $assignment->timecertifieduntil > $now) {
            return '<span class="badge badge-success">' . get_string('certificationstatus_temporary', 'tool_certify') . '</span>';
        }

        $select = "certificationid = :certificationid AND userid = :userid AND timerevoked IS NULL AND timecertified IS NOT NULL AND timeuntil < $now";
        $params = ['certificationid' => $assignment->certificationid, 'userid' => $assignment->userid];
        if ($DB->record_exists_select('tool_certify_periods', $select, $params)) {
            return '<span class="badge badge-light">' . get_string('certificationstatus_expired', 'tool_certify') . '</span>';
        }

        return '<span class="badge badge-light">' . get_string('certificationstatus_notcertified', 'tool_certify') . '</span>';
    }

    /**
     * Make sure current program status adn certification completion are up-to-date.
     *
     * @param stdClass $assignment
     * @return stdClass
     */
    public static function sync_current_status(stdClass $assignment): stdClass {
        global $DB;

        if ($assignment->archived) {
            return $assignment;
        }

        $now = time();
        $params = [
            'now1' => $now,
            'now2' => $now,
            'certificationid' => $assignment->certificationid,
            'userid' => $assignment->userid,
        ];

        $sql = "SELECT cp.*
                  FROM {tool_certify_periods} cp
                  JOIN {enrol_programs_programs} p ON p.id = cp.programid
                 WHERE cp.timewindowstart < :now1 AND (cp.timewindowend IS NULL OR cp.timewindowend > :now2)                      
                       AND cp.certificationid = :certificationid AND cp.userid = :userid
                       AND cp.timecertified IS NULL AND cp.timerevoked IS NULL";
        $periods = $DB->get_records_sql($sql, $params);
        foreach ($periods as $period) {
            \enrol_programs\local\allocation::fix_user_enrolments($period->programid, $period->userid);
        }

        \tool_certify\local\assignment::fix_assignment_sources($assignment->certificationid, $assignment->userid);
        \enrol_programs\local\source\certify::sync_certifications($assignment->certificationid, $assignment->userid);

        return $DB->get_record('tool_certify_assignments', ['id' => $assignment->id], '*', MUST_EXIST);
    }

    /**
     * Ask sources to fix their assignments.
     *
     * This is expected to be called from cron and when
     * certification settings are updated.
     *
     * @param int|null $certificationid
     * @param int|null $userid
     * @return void
     */
    public static function fix_assignment_sources(?int $certificationid, ?int $userid): void {
        $sources = self::get_source_classes();
        foreach ($sources as $source) {
            /** @var source\base $source */
            $source::fix_assignments($certificationid, $userid);
        }
    }

    /**
     * Does user have any active certifications?
     *
     * @param int $userid
     * @return bool
     */
    public static function has_active_assignments(int $userid): bool {
        global $DB;

        $sql = "SELECT 1
                  FROM {tool_certify_certifications} c
                  JOIN {tool_certify_assignments} ca ON ca.certificationid = c.id
                 WHERE c.archived = 0 AND ca.archived = 0 AND ca.userid = :userid";

        return $DB->record_exists_sql($sql, ['userid' => $userid]);
    }

    /**
     * Make a full user periods and assignment snapshot.
     *
     * @param int $certificationid
     * @param int $userid
     * @param string $reason snapshot reason type
     * @param string|null $explanation
     * @return \stdClass|null assignment record or null if not exists
     */
    public static function make_snapshot(int $certificationid, int $userid, string $reason, ?string $explanation = null): ?stdClass {
        global $DB, $USER;

        $assignment = $DB->get_record('tool_certify_assignments', ['certificationid' => $certificationid, 'userid' => $userid]);
        if (!$assignment) {
            $assignment = null;
            $assignmentid = null;
        } else {
            $assignmentid = $assignment->id;
        }

        $data = new stdClass();
        $data->certificationid = $certificationid;
        $data->userid = $userid;
        $data->assignmentid = $assignmentid;
        $data->reason = $reason;
        $data->timesnapshot = time();
        if ($USER->id > 0) {
            $data->snapshotby = $USER->id;
        }
        $data->explanation = $explanation;

        if ($assignment) {
            foreach ((array)$assignment as $k => $v) {
                if ($k === 'id' || $k === 'timecreated') {
                    continue;
                }
                $data->{$k} = $v;
            }
        }

        $sql = "SELECT p.*
                  FROM {tool_certify_periods} p
                 WHERE p.userid = :userid AND p.certificationid = :certificationid
              ORDER BY p.id ASC";
        $data->periodsjson = util::json_encode($DB->get_records_sql($sql, ['certificationid' => $certificationid, 'userid' => $userid]));

        $DB->insert_record('tool_certify_usr_snapshots', $data);

        return $assignment;
    }
}
