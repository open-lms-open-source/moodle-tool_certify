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

/**
 * certification management interface.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $CFG */
/** @var stdClass $COURSE */

use tool_certify\local\management;

require('../../../../config.php');
require_once($CFG->dirroot . '/lib/formslib.php');

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 25;

require_login();

$certification = $DB->get_record('tool_certify_certifications', ['id' => $id], '*', MUST_EXIST);
$context = context::instance_by_id($certification->contextid);
require_capability('tool/certify:view', $context);

$pageparams = ['id' => $certification->id];
if ($page) {
    $pageparams['page'] = $page;
}
$currenturl = new moodle_url('/admin/tool/certify/management/source_approval_requests.php', $pageparams);

management::setup_certification_page($currenturl, $context, $certification);

/** @var \local_openlms\output\dialog_form\renderer $dialogformoutput */
$dialogformoutput = $PAGE->get_renderer('local_openlms', 'dialog_form');
/** @var \tool_certify\output\management\renderer $managementoutput */
$managementoutput = $PAGE->get_renderer('tool_certify', 'management');

echo $OUTPUT->header();

echo $managementoutput->render_management_certification_tabs($certification, 'requests');

$userfieldsapi = \core_user\fields::for_identity(\context_system::instance(), false)->with_userpic();
$userfieldssql = $userfieldsapi->get_sql('u', false, 'user', 'userid2', false);
$userfields = $userfieldssql->selects;
$sql = "SELECT r.*, $userfields, pa.id AS assignmentid
          FROM {tool_certify_requests} r
          JOIN {tool_certify_sources} s ON s.id = r.sourceid AND s.type = 'approval'
          JOIN {tool_certify_certifications} p ON p.id = s.certificationid
          JOIN {user} u ON u.id = r.userid
     LEFT JOIN {tool_certify_assignments} pa ON pa.certificationid = p.id AND pa.userid = r.userid
         WHERE p.id = :certificationid
      ORDER BY r.timerequested DESC";
$params = $userfieldssql->params;
$params['certificationid'] = $certification->id;
$requests = $DB->get_records_sql($sql, ['certificationid' => $certification->id]);

$sql = "SELECT COUNT(r.id)
          FROM {tool_certify_requests} r
          JOIN {tool_certify_sources} s ON s.id = r.sourceid AND s.type = 'approval'
          JOIN {tool_certify_certifications} p ON p.id = s.certificationid
          JOIN {user} u ON u.id = r.userid
         WHERE p.id = :certificationid";
$totalcount = $DB->count_records_sql($sql, ['certificationid' => $certification->id]);

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $currenturl);

$dateformat = get_string('strftimedatetimeshort');
$canassign = has_capability('tool/certify:assign', $context);

$data = [];
foreach ($requests as $request) {
    $row = [];
    $user = (object)['id' => $request->userid];
    username_load_fields_from_object($user, $request, 'user', $userfieldsapi::for_userpic()->get_required_fields());
    if ($request->assignmentid) {
        $userurl = new moodle_url('/admin/tool/certify/management/user_assignment.php', ['id' => $request->assignmentid]);
    } else {
        $userurl = new moodle_url('/user/view.php', ['id' => $request->id]);
    }
    $fullname = fullname($user);
    $userpicture = $OUTPUT->user_picture($user, ['alttext' => $fullname]);
    $fullname = html_writer::link($userurl, $fullname);
    $row[] = $userpicture . $fullname;

    $row[] = userdate($request->timerequested, $dateformat);
    if ($request->timerejected) {
        $row[] = userdate($request->timerejected, $dateformat);
    } else {
        $row[] = '';
    }

    $actions = [];

    if ($canassign) {
        if (!$request->timerejected && !$request->assignmentid) {
            $approveurl = new moodle_url('/admin/tool/certify/management/source_approval_approve.php', ['id' => $request->id]);
            $approveaction = new \local_openlms\output\dialog_form\icon($approveurl,
                'requestapprove', get_string('source_approval_requestapprove', 'tool_certify'), 'tool_certify');
            $actions[] = $dialogformoutput->render($approveaction);
        }
        if (!$request->timerejected) {
            $rejecturl = new moodle_url('/admin/tool/certify/management/source_approval_reject.php', ['id' => $request->id]);
            $rejectaction = new \local_openlms\output\dialog_form\icon($rejecturl,
                'requestreject', get_string('source_approval_requestreject', 'tool_certify'), 'tool_certify');
            $actions[] = $dialogformoutput->render($rejectaction);
        }
        if ($request->timerejected || $request->assignmentid) {
            $deleteurl = new moodle_url('/admin/tool/certify/management/source_approval_delete.php', ['id' => $request->id]);
            $deleteaction = new \local_openlms\output\dialog_form\icon($deleteurl,
                'i/delete', get_string('source_approval_requestdelete', 'tool_certify'));
            $actions[] = $dialogformoutput->render($deleteaction);
        }
    }
    $row[] = implode('', $actions);

    $data[] = $row;
}

if (!$totalcount) {
    echo get_string('errornorequests', 'tool_certify');

} else {
    $table = new html_table();
    $table->head = [
        get_string('fullnameuser'),
        get_string('source_approval_daterequested', 'tool_certify'),
        get_string('source_approval_daterejected', 'tool_certify'),
        get_string('actions'),
    ];
    $table->id = 'certification_requests';
    $table->attributes['class'] = 'admintable generaltable';
    $table->data = $data;
    echo html_writer::table($table);
}

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $currenturl);

echo $OUTPUT->footer();
