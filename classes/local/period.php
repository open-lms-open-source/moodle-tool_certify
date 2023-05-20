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
 * Certification period helper.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class period {
    /**
     * For first and canrecerfity flags for user certification periods.
     * @param int $certificationid
     * @param int $userid
     * @return void
     */
    protected static function fix_flags(int $certificationid, int $userid): void {
        global $DB;

        $records = $DB->get_records('tool_certify_periods',
            ['certificationid' => $certificationid, 'userid' => $userid], 'timewindowstart ASC');
        $first = false;
        $hasrecertify = false;
        foreach ($records as $record) {
            if ($record->recertifiable) {
                $hasrecertify = true;
            }
            if ($first || $record->timerevoked) {
                if ($record->first) {
                    $DB->set_field('tool_certify_periods', 'first', 0, ['id' => $record->id]);
                }
            } else {
                $first = true;
                if (!$record->first) {
                    $DB->set_field('tool_certify_periods', 'first', 1, ['id' => $record->id]);
                }
            }
        }

        if ($hasrecertify) {
            // Only move to the flag to the end, do not add it if not present in at least one period.
            $last = false;
            $records = array_reverse($records);
            foreach ($records as $record) {
                if ($last || $record->timerevoked) {
                    if ($record->recertifiable) {
                        $DB->set_field('tool_certify_periods', 'recertifiable', 0, ['id' => $record->id]);
                    }
                } else {
                    $last = true;
                    if (!$record->recertifiable) {
                        $DB->set_field('tool_certify_periods', 'recertifiable', 1, ['id' => $record->id]);
                    }
                }
            }
        }
    }

    /**
     * Returns default dates for new period.
     *
     * @param stdClass $certification
     * @param int $userid
     * @param array $dateoverrides
     * @return array
     */
    public static function get_default_dates(stdClass $certification, int $userid, array $dateoverrides): array {
        global $DB;

        $now = time();

        $firstperiod = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification->id, 'userid' => $userid, 'first' => 1]);
        $recertperiod = $DB->get_record('tool_certify_periods',
            ['certificationid' => $certification->id, 'userid' => $userid, 'recertifiable' => 1]);
        $continuation = false;

        if (empty($dateoverrides['timewindowstart'])) {
            $dateoverrides['timewindowstart'] = $now;
            if ($firstperiod && $recertperiod) {
                if ($certification->recertify && $recertperiod->timeuntil) {
                    $dateoverrides['timewindowstart'] = $recertperiod->timeuntil - $certification->recertify;
                    $continuation = true;
                }
            }
        }

        $result = [
            'timewindowstart' => $dateoverrides['timewindowstart'],
            'timewindowdue' => null,
            'timewindowend' => null,
            'timefrom' => null,
            'timeuntil' => null,
        ];

        $settings = certification::get_periods_settings($certification);

        if (array_key_exists('timewindowdue', $dateoverrides)) {
            $result['timewindowdue'] = $dateoverrides['timewindowdue'];
        } else {
            if ($continuation) {
                $result['timewindowdue'] = $recertperiod->timeuntil;
            } else {
                if ($settings->due1 !== null) {
                    $result['timewindowdue'] = $result['timewindowstart'] + $settings->due1;
                }
            }
        }

        if (array_key_exists('timewindowend', $dateoverrides)) {
            $result['timewindowend'] = $dateoverrides['timewindowend'];
        } else {
            if ($firstperiod) {
                $windowend = $settings->windowend2;
            } else {
                $windowend = $settings->windowend1;
            }
            if ($windowend['since'] === certification::SINCE_NEVER) {
                $result['timewindowend'] = null;
            } else if ($windowend['since'] === certification::SINCE_WINDOWSTART) {
                $iterval = util::normalise_delay($windowend['delay']);
                $d = new \DateTime('@' . $result['timewindowstart']);
                $d->add(new \DateInterval($iterval));
                $result['timewindowend'] = $d->getTimestamp();
            } else if ($windowend['since'] === certification::SINCE_WINDOWDUE && $result['timewindowdue']) {
                $iterval = util::normalise_delay($windowend['delay']);
                $d = new \DateTime('@' . $result['timewindowdue']);
                $d->add(new \DateInterval($iterval));
                $result['timewindowend'] = $d->getTimestamp();
            }
        }

        if (array_key_exists('timefrom', $dateoverrides)) {
            $result['timefrom'] = $dateoverrides['timefrom'];
        } else {
            if ($firstperiod) {
                $valid = $settings->valid2;
            } else {
                $valid = $settings->valid1;
            }
            if ($valid === certification::SINCE_WINDOWSTART) {
                $result['timefrom'] = $result['timewindowstart'];
            } else if ($valid === certification::SINCE_WINDOWDUE && $result['timewindowdue']) {
                $result['timefrom'] = $result['timewindowdue'];
            } else if ($valid === certification::SINCE_WINDOWEND && $result['timewindowend']) {
                $result['timefrom'] = $result['timewindowend'];
            }
        }

        if (array_key_exists('timeuntil', $dateoverrides)) {
            $result['timeuntil'] = $dateoverrides['timeuntil'];
        } else {
            if ($firstperiod) {
                $expiration = $settings->expiration2;
            } else {
                $expiration = $settings->expiration1;
            }
            if ($expiration['since'] === certification::SINCE_NEVER || $expiration['since'] === certification::SINCE_CERTIFIED) {
                $result['timeuntil'] = null;
            } else if ($expiration['since'] === certification::SINCE_WINDOWSTART) {
                $iterval = util::normalise_delay($expiration['delay']);
                $d = new \DateTime('@' . $result['timewindowstart']);
                $d->add(new \DateInterval($iterval));
                $result['timeuntil'] = $d->getTimestamp();
            } else if ($expiration['since'] === certification::SINCE_WINDOWDUE && $result['timewindowdue']) {
                $iterval = util::normalise_delay($expiration['delay']);
                $d = new \DateTime('@' . $result['timewindowdue']);
                $d->add(new \DateInterval($iterval));
                $result['timeuntil'] = $d->getTimestamp();
            } else if ($expiration['since'] === certification::SINCE_WINDOWEND && $result['timewindowend']) {
                $iterval = util::normalise_delay($expiration['delay']);
                $d = new \DateTime('@' . $result['timewindowend']);
                $d->add(new \DateInterval($iterval));
                $result['timeuntil'] = $d->getTimestamp();
            }
        }

        foreach ($result as $k => $v) {
            if ($v === null) {
                continue;
            }
            $result[$k] = (int)$v;
        }

        return $result;
    }

    /**
     * Add period.
     *
     * @param stdClass $data
     * @return stdClass assignment record
     */
    public static function add(stdClass $data): stdClass {
        global $DB;

        if (isset($data->assignmentid)) {
            $assignment = $DB->get_record('tool_certify_assignments', ['id' => $data->assignmentid], '*', MUST_EXIST);
            $certification = $DB->get_record('tool_certify_certifications', ['id' => $assignment->certificationid], '*', MUST_EXIST);
            $user = $DB->get_record('user', ['id' => $assignment->userid, 'deleted' => 0], '*', MUST_EXIST);
            $userid = $assignment->userid;
        } else {
            if (!property_exists($data, 'certificationid')) {
                throw new \invalid_parameter_exception('either assignmentid or certificationid value is required');
            }
            if (!property_exists($data, 'userid')) {
                throw new \invalid_parameter_exception('either userid and assignmentid values are required');
            }
            $certification = $DB->get_record('tool_certify_certifications', ['id' => $data->certificationid], '*', MUST_EXIST);
            $user = $DB->get_record('user', ['id' => $data->userid, 'deleted' => 0], '*', MUST_EXIST);
            $assignment = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification->id, 'userid' => $user->id]);
            if (!$assignment) {
                $assignment = null;
            }
            $userid = $user->id;
        }
        if (!property_exists($data, 'programid')) {
            throw new \invalid_parameter_exception('programid value is required');
        }
        if ($data->programid) {
            $program = $DB->get_record('enrol_programs_programs', ['id' => $data->programid], '*', MUST_EXIST);
            $programid = $program->id;
            unset($program);
        } else {
            // Special case - might be some historic data with non-existing program.
            $programid = null;
        }

        $record = new stdClass();
        $record->certificationid = $certification->id;
        $record->userid = $userid;
        $record->programid = $programid;

        $datefields = ['timewindowstart', 'timewindowdue', 'timewindowend', 'timecertified', 'timefrom', 'timeuntil', 'timerevoked'];

        foreach ($datefields as $field) {
            if (!property_exists($data, $field)) {
                $record->$field = null;
                continue;
            }
            if ($data->$field > 0) {
                $record->$field = $data->$field;
            } else {
                $record->$field = null;
            }
        }

        $record->first = 0;
        $record->recertifiable = 0;
        if (!isset($record->timerevoked)) {
            if (!$DB->record_exists('tool_certify_periods',
                ['certificationid' => $certification->id, 'userid' => $user->id, 'timerevoked' => null])
            ) {
                $record->first = 1;
                $record->recertifiable = 1; // Ignore if it is actually enabled in settings, they might enable it later.
            } else if ($DB->record_exists('tool_certify_periods',
                ['certificationid' => $certification->id, 'userid' => $user->id, 'recertifiable' => 1])
            ) {
                // Duplicates will be removed when fixing flags.
                $record->recertifiable = 1;
            }
        }

        // Check dates are valid.
        if ($record->timewindowstart <= 0) {
            throw new \invalid_parameter_exception('timewindowstart invalid');
        }
        if ($record->timewindowdue && $record->timewindowdue <= $record->timewindowstart) {
            throw new \invalid_parameter_exception('timewindowdue invalid');
        }
        if ($record->timewindowend && $record->timewindowend <= $record->timewindowstart) {
            throw new \invalid_parameter_exception('timewindowend invalid');
        }
        if ($record->timewindowdue && $record->timewindowend && $record->timewindowend < $record->timewindowdue) {
            throw new \invalid_parameter_exception('timewindowend invalid');
        }
        if ($record->timefrom && $record->timeuntil && $record->timefrom >= $record->timeuntil) {
            throw new \invalid_parameter_exception('timeuntil invalid');
        }
        if ($record->timecertified && !$record->timefrom) {
            throw new \invalid_parameter_exception('timefrom required');
        }

        $record->evidencejson = \tool_certify\local\util::json_encode([]);

        $trans = $DB->start_delegated_transaction();

        $id = $DB->insert_record('tool_certify_periods', $record);

        self::fix_flags($record->certificationid, $record->userid);

        $trans->allow_commit();

        \enrol_programs\local\source\certify::sync_certifications($record->certificationid, $record->userid);

        return $DB->get_record('tool_certify_periods', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Add first certification period if there is a programid1.
     *
     * NOTE: this is called when new assignment is created
     *
     * @param stdClass $assignment
     * @param array $dateoverrides
     * @return null|stdClass assignment record
     */
    public static function add_first(stdClass $assignment, array $dateoverrides): ?stdClass {
        global $DB;

        $certification = $DB->get_record('tool_certify_certifications', ['id' => $assignment->certificationid], '*', MUST_EXIST);

        if ($DB->record_exists('tool_certify_periods', ['certificationid' => $certification->id, 'userid' => $assignment->userid])) {
            // This should not happen for now, we delete periods when unassigning.
            return null;
        }

        if (!$certification->programid1) {
            // This should not happen.
            return null;
        }

        $now = time();

        $data = new stdClass();
        $data->assignmentid = $assignment->id;
        $data->programid = $certification->programid1;

        if (empty($dateoverrides['timewindowstart'])) {
            $dateoverrides['timewindowstart'] = $now;
        }
        $defaultdates = self::get_default_dates($certification, $assignment->userid, $dateoverrides);
        foreach ($defaultdates as $k => $v) {
            $data->$k = $v;
        }

        // Fix dates if necessary - we cannot ask users now to fix dates.
        if ($data->timewindowdue && $data->timewindowdue <= $data->timewindowstart) {
            $data->timewindowdue = $data->timewindowstart + 1;
        }
        if ($data->timewindowend && $data->timewindowend <= $data->timewindowstart) {
            $data->timewindowend = $data->timewindowstart + 1;
        }
        if ($data->timewindowdue && $data->timewindowend && $data->timewindowend < $data->timewindowdue) {
            $data->timewindowdue = $data->timewindowend;
        }
        if ($data->timefrom && $data->timeuntil && $data->timefrom > $data->timeuntil) {
            $data->timeuntil = $data->timefrom + 1;
        }

        if (isset($dateoverrides['timecertified'])) {
            $data->timecertified = $dateoverrides['timecertified'];
        }

        return self::add($data);
    }

    /**
     * Override period dates.
     *
     * @param stdClass $data
     * @return stdClass
     */
    public static function override_dates(stdClass $data): stdClass {
        global $DB;

        $record = $DB->get_record('tool_certify_periods', ['id' => $data->id], '*', MUST_EXIST);
        $oldrecord = clone($record);
        $datefields = ['timewindowstart', 'timewindowdue', 'timewindowend', 'timecertified', 'timefrom', 'timeuntil', 'timerevoked'];

        foreach ($datefields as $field) {
            if (!property_exists($data, $field)) {
                continue;
            }
            if ($data->$field > 0) {
                $record->$field = $data->$field;
            } else {
                $record->$field = null;
            }
        }

        // Check dates are valid.
        if ($record->timewindowstart <= 0) {
            throw new \invalid_parameter_exception('timewindowstart invalid');
        }
        if ($record->timewindowdue && $record->timewindowdue <= $record->timewindowstart) {
            throw new \invalid_parameter_exception('timewindowdue invalid');
        }
        if ($record->timewindowend && $record->timewindowend <= $record->timewindowstart) {
            throw new \invalid_parameter_exception('timewindowend invalid');
        }
        if ($record->timewindowdue && $record->timewindowend && $record->timewindowend < $record->timewindowdue) {
            throw new \invalid_parameter_exception('timewindowend invalid');
        }
        if ($record->timefrom && $record->timeuntil && $record->timefrom > $record->timeuntil) {
            throw new \invalid_parameter_exception('timeuntil invalid');
        }
        if ($record->timecertified && !$record->timefrom) {
            throw new \invalid_parameter_exception('timefrom required');
        }

        $trans = $DB->start_delegated_transaction();

        $DB->update_record('tool_certify_periods', $record);
        self::fix_flags($record->certificationid, $record->userid);

        if (!$oldrecord->timerevoked && $record->timerevoked && $record->certificateissueid) {
            certificate::revoke($record->id);
        }

        $trans->allow_commit();

        \enrol_programs\local\source\certify::sync_certifications($record->certificationid, $record->userid);

        return $DB->get_record('tool_certify_periods', ['id' => $record->id], '*', MUST_EXIST);
    }

    public static function update_recertifiable(stdClass $assignment, bool $stoprecertify): void {
        global $DB;
        if ($stoprecertify) {
            $DB->set_field('tool_certify_periods', 'recertifiable', 0,
                ['certificationid' => $assignment->certificationid, 'userid' => $assignment->userid]);
        } else {
            $DB->set_field('tool_certify_periods', 'recertifiable', 1,
                ['certificationid' => $assignment->certificationid, 'userid' => $assignment->userid, 'timerevoked' => null]);
        }
        self::fix_flags($assignment->certificationid, $assignment->userid);
    }

    /**
     * Delete period.
     *
     * @param int $periodid
     * @return void
     */
    public static function delete(int $periodid): void {
        global $DB;

        $record = $DB->get_record('tool_certify_periods', ['id' => $periodid]);
        if (!$record) {
            return;
        }

        $trans = $DB->start_delegated_transaction();

        if ($record->certificateissueid) {
            certificate::revoke($periodid);
        }

        \tool_certify\local\notification_manager::delete_period_notifications($record);

        $DB->delete_records('tool_certify_periods', ['id' => $record->id]);
        self::fix_flags($record->certificationid, $record->userid);

        $trans->allow_commit();

        \enrol_programs\local\source\certify::sync_certifications($record->certificationid, $record->userid);
    }

    /**
     * Called from event observer.
     *
     * @param stdClass $program
     * @param stdClass $allocation
     * @return void
     */
    public static function program_completed(stdClass $program, stdClass $allocation): void {
        global $DB;

        if ($program->id != $allocation->programid) {
            throw new \coding_exception('program mismatch');
        }

        $now = time();
        $sql = "SELECT p.*
                  FROM {tool_certify_periods} p
                  JOIN {tool_certify_assignments} a ON a.userid = p.userid AND a.certificationid = p.certificationid
                  JOIN {tool_certify_certifications} c ON c.id = p.certificationid
                  JOIN {user} u ON u.id = p.userid
                 WHERE a.archived = 0 AND c.archived = 0 AND u.deleted = 0                    
                       AND p.timecertified IS NULL AND p.timerevoked IS NULL
                       AND p.timewindowstart <= $now AND (p.timewindowend IS NULL OR p.timewindowend > $now)
                       AND p.allocationid = :allocationid AND p.programid = :programid";
        $params= [
            'allocationid' => $allocation->id,
            'programid' => $program->id,
        ];
        $period = $DB->get_record_sql($sql, $params);

        if (!$period) {
            return;
        }

        $certification = $DB->get_record('tool_certify_certifications',
            ['id' => $period->certificationid], '*', MUST_EXIST);
        $assignment = $DB->get_record('tool_certify_assignments',
            ['certificationid' => $period->certificationid, 'userid' => $period->userid], '*', MUST_EXIST);

        $settings = certification::get_periods_settings($certification);

        $period->timecertified = $now;
        if ($period->first) {
            $valid = $settings->valid1;
            $expiration = $settings->expiration1;
        } else {
            $valid = $settings->valid2;
            $expiration = $settings->expiration2;
        }
        if ($period->timefrom === null) {
            if ($valid === certification::SINCE_CERTIFIED) {
                $period->timefrom = $period->timecertified;
            } else if ($valid === certification::SINCE_WINDOWSTART) {
                $period->timefrom = $period->timewindowstart;
            } else if ($valid === certification::SINCE_WINDOWDUE && $period->timewindowdue) {
                $period->timefrom = $period->timewindowdue;
            } else if ($valid === certification::SINCE_WINDOWEND && $period->timewindowend) {
                $period->timefrom = $period->timewindowend;
            }
        }
        if (!$period->timefrom) {
            // Value is required.
            $period->timefrom = $period->timecertified;
        }
        if ($period->timeuntil === null) {
            if ($expiration['since'] === certification::SINCE_NEVER) {
                $period->timeuntil = null;
            } else if ($expiration['since'] === certification::SINCE_CERTIFIED) {
                $iterval = util::normalise_delay($expiration['delay']);
                $d = new \DateTime('@' . $period->timecertified);
                $d->add(new \DateInterval($iterval));
                $period->timeuntil = $d->getTimestamp();
            } else if ($expiration['since'] === certification::SINCE_WINDOWSTART) {
                $iterval = util::normalise_delay($expiration['delay']);
                $d = new \DateTime('@' . $period->timewindowstart);
                $d->add(new \DateInterval($iterval));
                $period->timeuntil = $d->getTimestamp();
            } else if ($expiration['since'] === certification::SINCE_WINDOWDUE && $period->timewindowdue) {
                $iterval = util::normalise_delay($expiration['delay']);
                $d = new \DateTime('@' . $period->timewindowdue);
                $d->add(new \DateInterval($iterval));
                $period->timeuntil = $d->getTimestamp();
            } else if ($expiration['since'] === certification::SINCE_WINDOWEND && $period->timewindowend) {
                $iterval = util::normalise_delay($expiration['delay']);
                $d = new \DateTime('@' . $period->timewindowend);
                $d->add(new \DateInterval($iterval));
                $period->timeuntil = $d->getTimestamp();
            }
        }
        if ($period->timeuntil && $period->timeuntil <= $period->timefrom) {
            // Until value cannot be invalid.
            $period->timeuntil = $period->timefrom + 1;
        }

        $DB->update_record('tool_certify_periods', $period);

        \tool_certify\event\user_certified::create_from_period($certification, $assignment, $period)->trigger();

        if (certificate::is_available() && $certification->templateid) {
            // Make sure the certificate is generated asap.
            $asynctask = new \tool_certify\task\trigger_certificate();
            $asynctask->set_blocking(false);
            $asynctask->set_custom_data('');
            $asynctask->set_userid(get_admin()->id);
            \core\task\manager::queue_adhoc_task($asynctask, true);
        }
    }

    /**
     * Returns period status as fancy HTML.
     *
     * @param stdClass $certification
     * @param stdClass|null $assignment
     * @param stdClass $period
     * @return string
     */
    public static function get_status_html(stdClass $certification, ?stdClass $assignment, stdClass $period): string {
        $now = time();

        if (!$assignment || $certification->archived || $assignment->archived) {
            return '<span class="badge badge-dark">' . get_string('periodstatus_archived', 'tool_certify') . '</span>';
        }

        if ($period->timerevoked) {
            return '<span class="badge badge-danger">' . get_string('periodstatus_revoked', 'tool_certify') . '</span>';
        }

        if ($period->timecertified) {
            if (!$period->timeuntil || $period->timeuntil > $now) {
                return '<span class="badge badge-success">' . get_string('periodstatus_certified', 'tool_certify') . '</span>';
            } else {
                return '<span class="badge badge-light">' . get_string('periodstatus_expired', 'tool_certify') . '</span>';
            }
        }

        if ($period->timewindowend && $period->timewindowend < $now) {
            return '<span class="badge badge-danger">' . get_string('periodstatus_failed', 'tool_certify') . '</span>';
        }

        if ($period->timewindowdue && $period->timewindowdue < $now) {
            return '<span class="badge badge-danger">' . get_string('periodstatus_overdue', 'tool_certify') . '</span>';
        }

        if ($period->timewindowstart > $now) {
            return '<span class="badge badge-light">' . get_string('periodstatus_future', 'tool_certify') . '</span>';
        }

        return '<span class="badge badge-warning">' . get_string('periodstatus_pending', 'tool_certify') . '</span>';
    }

    public static function get_windowstart_html(stdClass $certification, ?stdClass $assignment, stdClass $period, bool $short = false): string {
        if ($short) {
            $format = get_string('strftimedatetimeshort');
        } else {
            $format = '';
        }
        // Must be always set!
        return userdate($period->timewindowstart, $format);
    }

    public static function get_windowdue_html(stdClass $certification, ?stdClass $assignment, stdClass $period, bool $short = false): string {
        if (!$period->timewindowdue) {
            return get_string('notset', 'tool_certify');
        }
        if ($short) {
            $format = get_string('strftimedatetimeshort');
        } else {
            $format = '';
        }
        return userdate($period->timewindowdue, $format);
    }

    public static function get_windowend_html(stdClass $certification, ?stdClass $assignment, stdClass $period, bool $short = false): string {
        if (!$period->timewindowend) {
            return get_string('notset', 'tool_certify');
        }
        if ($short) {
            $format = get_string('strftimedatetimeshort');
        } else {
            $format = '';
        }
        return userdate($period->timewindowend, $format);
    }

    public static function get_from_html(stdClass $certification, ?stdClass $assignment, stdClass $period, bool $short = false): string {
        if ($short) {
            $format = get_string('strftimedatetimeshort');
        } else {
            $format = '';
        }
        if ($period->timefrom) {
            return userdate($period->timefrom, $format);
        }
        if ($period->timecertified) {
            // This should not happen.
            return get_string('never', 'tool_certify');
        }

        $settings = certification::get_periods_settings($certification);
        if ($period->first) {
            $valid = $settings->valid1;
        } else {
            $valid = $settings->valid2;
        }
        $options = certification::get_valid_options();
        return $options[$valid];
    }

    public static function get_until_html(stdClass $certification, ?stdClass $assignment, stdClass $period, bool $short = false): string {
        if ($short) {
            $format = get_string('strftimedatetimeshort');
        } else {
            $format = '';
        }
        if ($period->timeuntil) {
            return userdate($period->timeuntil, $format);
        }
        if ($period->timecertified) {
            return get_string('never', 'tool_certify');
        }

        $settings = certification::get_periods_settings($certification);
        if ($period->first) {
            $since = $settings->expiration1['since'];
            $delay = $settings->expiration1['delay'];
        } else {
            $since = $settings->expiration2['since'];
            $delay = $settings->expiration2['delay'];
        }

        if ($since === certification::SINCE_NEVER) {
            return get_string('never', 'tool_certify');
        }
        $options = certification::get_expiration_options();
        $a = new stdClass();
        $a->delay = util::format_interval($delay);
        $a->after = $options[$since];
        return get_string('delayafter', 'tool_certify', $a);
    }

    /**
     * Returns period status as fancy HTML.
     *
     * @param stdClass $certification
     * @param stdClass|null $assignment
     * @param stdClass $period
     * @param bool $short
     * @return string
     */
    public static function get_recertify_html(stdClass $certification, ?stdClass $assignment, stdClass $period, bool $short = false): string {
        if (!$certification->recertify || !$period->recertifiable || $certification->archived || !$assignment || $assignment->archived) {
            return get_string('no');
        }
        if ($period->timeuntil) {
            if ($short) {
                $format = get_string('strftimedatetimeshort');
            } else {
                $format = '';
            }
            return userdate($period->timeuntil - $certification->recertify, $format);
        } else {
            return get_string('recertifyifexpired', 'tool_certify');
        }
    }

    /**
     * Check if new recertification periods should be created.
     *
     * NOTE: this is called from cron and user certification assignment page.
     *
     * @param int|null $certificationid
     * @param int|null $userid
     * @return void
     */
    public static function process_recertifications(?int $certificationid, ?int $userid): void {
        global $DB;

        $params = [];
        $params['now'] = time();
        $params['cutoff'] = time() - DAYSECS * 90; // Do not create recertifications for old periods.

        if ($certificationid) {
            $params['certificationid'] = $certificationid;
            $certificationselect = "AND cp.certificationid = :certificationid";
        } else {
            $certificationselect = "";
        }
        if ($userid) {
            $params['userid'] = $userid;
            $userselect = "AND cp.userid = :userid";
        } else {
            $userselect = "";
        }

        $sql = "SELECT cp.id
                  FROM {tool_certify_periods} cp
                  JOIN {tool_certify_certifications} c ON c.id = cp.certificationid AND c.archived = 0
                  JOIN {tool_certify_assignments} ca ON ca.certificationid = c.id AND ca.userid = cp.userid AND ca.archived = 0                      
                  JOIN {user} u ON u.id = cp.userid AND u.deleted = 0
                  JOIN {enrol_programs_programs} p ON p.id = c.programid2 AND p.archived = 0 
                 WHERE cp.timerevoked IS NULL AND cp.recertifiable = 1 AND cp.timecertified IS NOT NULL
                       AND cp.timeuntil - c.recertify < :now
                       AND cp.timeuntil > :cutoff
                       $certificationselect $userselect
              ORDER BY cp.id ASC";
        $periods = $DB->get_records_sql($sql, $params);
        foreach ($periods as $period) {
            $period = $DB->get_record('tool_certify_periods', ['id' => $period->id]);
            if (!$period || isset($period->timerevoked) || !$period->recertifiable || !$period->timecertified || !$period->timeuntil) {
                continue;
            }
            $certification = $DB->get_record('tool_certify_certifications', ['id' => $period->certificationid]);
            if (!$certification || !isset($certification->recertify)) {
                continue;
            }
            $assignment = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification->id, 'userid' => $period->userid]);
            if (!$assignment || $assignment->archived) {
                continue;
            }
            $periodsettings = certification::get_periods_settings($certification);
            $dates = [
                'timewindowstart' => $period->timeuntil - $certification->recertify,
                'timewindowdue' => $period->timeuntil,
            ]; // Do not rely on guessing in get_default_dates()...
            if ($period->timewindowstart >= $dates['timewindowstart']) {
                // Wrong settings for recertification!!!
                $DB->set_field('tool_certify_periods', 'recertifiable', 0, ['id' => $period->id]);
                continue;
            }
            $dates = period::get_default_dates($certification, $period->userid, $dates);
            $dates['certificationid'] = $certification->id;
            $dates['userid'] = $period->userid;
            $dates['programid'] = $certification->programid2;

            $trans = $DB->start_delegated_transaction();
            period::add((object)$dates);
            if (isset($periodsettings->grace2) && $periodsettings->grace2 > 0) {
                $graceuntil = $period->timeuntil + $periodsettings->grace2;
                if ($graceuntil > time() && $graceuntil > (int)$assignment->timecertifieduntil) {
                    $DB->set_field('tool_certify_assignments', 'timecertifieduntil', $graceuntil, ['id' => $assignment->id]);
                }
            }
            $trans->allow_commit();
        }
    }
}
