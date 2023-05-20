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

namespace tool_certify\table;

use stdClass;
use moodle_url;
use tool_certify\local\period;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Periods for given assignment.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment_periods extends \table_sql {

    const DEFAULT_PERPAGE = 99;

    /** @var stdClass */
    public $assignment;
    /** @var stdClass */
    public $certification;
    /** @var \context */
    public $context;
    /** @var string */
    public $search = '';

    public function __construct(stdClass $certification, stdClass $assignment, moodle_url $url) {
        parent::__construct('tool_certify_assignment_periods');

        $this->assignment = $assignment;
        $this->certification = $certification;
        $this->context = \context::instance_by_id($certification->contextid);

        $page = optional_param('page', 0, PARAM_INT);

        $params = [];
        if ($page > 0) {
            $params['page'] = $page;
            $this->currpage = $page;
        }
        $baseurl = new moodle_url($url, $params);
        $this->define_baseurl($baseurl);
        $this->pagesize = self::DEFAULT_PERPAGE;

        $this->collapsible(false);
        $this->sortable(false, 'timewindowstart', SORT_DESC);
        $this->pageable(false);
        $this->is_downloadable(false);

        $columns = [
            'timewindowstart',
            'timewindowend',
            'program',
            'timeuntil',
        ];
        if ($this->certification->recertify) {
            $columns[] = 'recertify';
        }
        $columns[] = 'status';
        $headers = [
            get_string('windowstartdate', 'tool_certify'),
            get_string('windowenddate', 'tool_certify'),
            get_string('program', 'enrol_programs'),
            get_string('untildate', 'tool_certify'),
        ];
        if ($this->certification->recertify) {
            $headers[] = get_string('recertify', 'tool_certify');
        }
        $headers[] = get_string('periodstatus', 'tool_certify');

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->set_attribute('id', 'tool_certify_assignment_periods_table');

        $params = ['assignmentid' => $this->assignment->id];

        $sql = "SELECT p.*, pr.fullname AS programfullname, pr.contextid as programcontextid
                  FROM {tool_certify_periods} p
                  JOIN {tool_certify_assignments} a ON a.userid = p.userid AND a.certificationid = p.certificationid
             LEFT JOIN {enrol_programs_programs} pr ON pr.id = p.programid
                 WHERE a.id = :assignmentid";

        $this->set_sql("*", "($sql) xperiod", '1=1', $params);
    }

    /**
     * Display program name.
     *
     * @param stdClass $period
     * @return string html used to display the plan name
     */
    public function col_program(stdClass $period): string {
        global $DB, $USER;
        if ($period->programfullname !== null) {
            $name = format_string($period->programfullname);
            $context = \context::instance_by_id($period->programcontextid, IGNORE_MISSING);
            if ($context && has_capability('enrol/programs:view', $context)) {
                if ($period->allocationid && $DB->record_exists('enrol_programs_allocations', ['id' => $period->allocationid])) {
                    $url = new moodle_url('/enrol/programs/management/user_allocation.php', ['id' => $period->allocationid]);
                } else {
                    $url = new moodle_url('/enrol/programs/management/program.php', ['id' => $period->programid]);
                }
                $name = \html_writer::link($url, $name);
            }
            return $name;
        }
        return get_string('notset', 'tool_certify');
    }

    /**
     * Display the window start date linked to period details page.
     *
     * @param stdClass $period
     * @return string html used to display the plan name
     */
    public function col_timewindowstart(stdClass $period): string {
        $start = period::get_windowstart_html($this->certification, $this->assignment, $period, true);
        $url = new moodle_url('/admin/tool/certify/management/period.php', ['id' => $period->id]);
        return \html_writer::link($url, $start);
    }

    /**
     * Display the until date.
     *
     * @param stdClass $period
     * @return string html used to display the plan name
     */
    public function col_timewindowend(stdClass $period): string {
        return period::get_windowend_html($this->certification, $this->assignment, $period, true);
    }

    /**
     * Display the until date.
     *
     * @param stdClass $period
     * @return string html used to display the plan name
     */
    public function col_timeuntil(stdClass $period): string {
        return period::get_until_html($this->certification, $this->assignment, $period, true);
    }

    /**
     * Display status.
     *
     * @param stdClass $period
     * @return string html used to display the plan name
     */
    public function col_status(stdClass $period): string {
        return period::get_status_html($this->certification, $this->assignment, $period);
    }

    /**
     * Display recertify date.
     *
     * @param stdClass $period
     * @return string html used to display the plan name
     */
    public function col_recertify(stdClass $period): string {
        return period::get_recertify_html($this->certification, $this->assignment, $period, true);
    }

    public function print_nothing_to_display(): void {
        // Get rid of ugly H2 heading.
        echo '<em>' . get_string('nothingtodisplay') . '</em>';
    }
}
