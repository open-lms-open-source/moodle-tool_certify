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
 * Certification management interface.
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
$searchquery = optional_param('search', '', PARAM_RAW);
$sort = optional_param('sort', 'name', PARAM_ALPHANUMEXT);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);
$status = optional_param('status', 0, PARAM_INT);
$perpage = 25;

require_login();

$certification = $DB->get_record('tool_certify_certifications', ['id' => $id], '*', MUST_EXIST);
$context = context::instance_by_id($certification->contextid);
require_capability('tool/certify:view', $context);

$pageparams = ['id' => $certification->id];
if (trim($searchquery) !== '') {
    $pageparams['search'] = $searchquery;
}
if ($page) {
    $pageparams['page'] = $page;
}
if ($sort !== 'name') {
    $pageparams['sort'] = $sort;
}
if ($dir !== 'ASC') {
    $pageparams['dir'] = $dir;
}
if ($status) {
    $pageparams['status'] = $status;
}
$currenturl = new moodle_url('/admin/tool/certify/management/certification_users.php', $pageparams);

management::setup_certification_page($currenturl, $context, $certification);
//$PAGE->set_docs_path("$CFG->wwwroot/admin/tool/certify/documentation.php/certification_assignment.md");

/** @var \local_openlms\output\dialog_form\renderer $dialogformoutput */
$dialogformoutput = $PAGE->get_renderer('local_openlms', 'dialog_form');
/** @var \tool_certify\output\management\renderer $managementoutput */
$managementoutput = $PAGE->get_renderer('tool_certify', 'management');

echo $OUTPUT->header();

echo $managementoutput->render_management_certification_tabs($certification, 'users');

echo '<div class="assignment-filtering">';
// Add search form.
$data = [
    'action' => new moodle_url('/admin/tool/certify/management/certification_users.php'),
    'inputname' => 'search',
    'searchstring' => get_string('search', 'search'),
    'query' => $searchquery,
    'hiddenfields' => [
        (object)['name' => 'id', 'value' => $certification->id],
        (object)['name' => 'sort', 'value' => $sort],
        (object)['name' => 'dir', 'value' => $dir],
        (object)['name' => 'status', 'value' => $status],
    ],
    'extraclasses' => 'mb-3'
];
echo $OUTPUT->render_from_template('core/search_input', $data);
$changestatus = new moodle_url($currenturl);
$statusoptions = [
    0 => get_string('certificationstatus_any', 'tool_certify'),
    1 => get_string('certificationstatus_valid', 'tool_certify'),
    2 => get_string('certificationsarchived', 'tool_certify'),
];
if (!isset($statusoptions[$status])) {
    $status = 0;
}
echo $OUTPUT->single_select($currenturl, 'status', $statusoptions, $status, []);
echo '</div>';
echo '<div class="clearfix"></div>';

$allusernamefields = \core_user\fields::get_name_fields(true);
$userfieldsapi = \core_user\fields::for_identity(\context_system::instance(), false)->with_userpic();
$userfieldssql = $userfieldsapi->get_sql('u', false, 'user', 'userid2', false);
$userfields = $userfieldssql->selects;
$params = $userfieldssql->params;

$orderby = '';
if ($dir === 'ASC') {
    $orderby = ' ASC';
} else {
    $orderby = ' DESC';
}
if ($sort === 'from') {
    $orderby = 'timefrom' . $orderby;
} else if ($sort === 'until') {
    $orderby = 'timeuntil' . $orderby;
} else if ($sort === 'firstname') {
    $orderby = 'firstname' . $orderby . ',  lastname' . $orderby;
} else if ($sort === 'lastname') {
    $orderby = 'lastname' . $orderby . ',  firstname' . $orderby;
} else {
    // Use first name, last name for now.
    $orderby = $allusernamefields[0] . $orderby . ', ' . $allusernamefields[0] . $orderby;
}

$usersearch = '';
if (trim($searchquery) !== '') {
    $searchparam = '%' . $DB->sql_like_escape($searchquery) . '%';
    $conditions = [];
    $fields = ['email', 'idnumber'] + $allusernamefields;
    $cnt = 0;
    foreach ($fields as $field) {
        $conditions[] = $DB->sql_like('u.' . $field, ':usersearch' . $cnt, false);
        $params['usersearch' . $cnt] = $searchparam;
        $cnt++;
    }
    // Let them search for full name too.
    $conditions[] = $DB->sql_like($DB->sql_concat_join("' '", ['u.firstname', 'u.lastname']), ':usersearch' . $cnt, false);
    $params['usersearch' . $cnt] = $searchparam;
    $cnt++;
    $conditions[] = $DB->sql_like($DB->sql_concat_join("' '", ['u.lastname', 'u.firstname']), ':usersearch' . $cnt, false);
    $params['usersearch' . $cnt] = $searchparam;
    $cnt++;
    $usersearch = 'AND (' . implode(' OR ', $conditions) . ')';
}

$now = time();
switch ($status) {
    case 1: // Valid.
        $statusselect = "AND (EXISTS (
           SELECT 1 
             FROM {tool_certify_periods} p3
            WHERE p3.allocationid = a.id AND p3.timerevoked IS NULL
                  AND p3.timecertified IS NOT NULL AND p3.timefrom <= $now AND (p3.timeuntil IS NULL OR p3.timeuntil > $now)
           ) OR a.timecertifieduntil > $now) AND a.archived = 0 AND c.archived = 0";
        break;
    case 2: // Archived.
        $statusselect = "AND (a.archived = 1 OR c.archived = 1)";
        break;
    default:
        $statusselect = '';
}

$sql = "SELECT a.*, s.type AS sourcetype, $userfields,
               (SELECT MIN(p1.timefrom)
                  FROM {tool_certify_periods} p1
                 WHERE p1.allocationid = a.id AND p1.timerevoked IS NULL AND p1.timecertified IS NOT NULL) AS timefrom,
               (SELECT MIN(p2.timeuntil)
                  FROM {tool_certify_periods} p2
                 WHERE p2.allocationid = a.id AND p2.timerevoked IS NULL AND p2.timecertified IS NOT NULL
                       AND p2.timefrom <= $now AND (p2.timeuntil IS NULL OR p2.timeuntil > $now)) AS timeuntil
          FROM {tool_certify_assignments} a
          JOIN {tool_certify_certifications} c ON c.id = a.certificationid
     LEFT JOIN {tool_certify_sources} s ON s.id = a.sourceid
          JOIN {user} u ON u.id = a.userid
         WHERE a.certificationid = :certificationid $usersearch $statusselect        
      ORDER BY $orderby";
$params['certificationid'] = $certification->id;
$assignments = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

$sql = "SELECT COUNT(a.id)
          FROM {tool_certify_assignments} a
          JOIN {user} u ON u.id = a.userid
         WHERE a.certificationid = :certificationid $usersearch";
$totalcount = $DB->count_records_sql($sql, $params);

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $currenturl);

$sources = $DB->get_records('tool_certify_sources', ['certificationid' => $certification->id]);
$sourcenames = \tool_certify\local\assignment::get_source_names();
/** @var \tool_certify\local\source\base[] $sourceclasses */ // Type hack.
$sourceclasses = \tool_certify\local\assignment::get_source_classes();
$dateformat = get_string('strftimedatetimeshort');

$data = [];
foreach ($assignments as $assignment) {
    $row = [];
    $source = $sources[$assignment->sourceid];
    $sourceclass = $sourceclasses[$assignment->sourcetype];

    $user = (object)['id' => $assignment->userid];
    username_load_fields_from_object($user, $assignment, 'user', $userfieldsapi::for_userpic()->get_required_fields());
    $userurl = new moodle_url('/admin/tool/certify/management/user_assignment.php', ['id' => $assignment->id]);
    $fullnametext = fullname($user);
    $userpicture = $OUTPUT->user_picture($user, ['alttext' => $fullnametext]);
    $fullname = html_writer::link($userurl, $fullnametext);
    $row[] = $userpicture . $fullname;

    if ($assignment->timefrom) {
        $row[] = userdate($assignment->timefrom, $dateformat);
    } else {
        $row[] = '';
    }
    if (!$assignment->timeuntil) {
        $row[] = '';
    } else if ($assignment->timecertifieduntil && $assignment->timeuntil < $assignment->timecertifieduntil) {
        $row[] = userdate($assignment->timecertifieduntil, $dateformat);
    } else {
        $row[] = userdate($assignment->timeuntil, $dateformat);
    }

    $row[] = \tool_certify\local\assignment::get_status_html($certification, $assignment);

    $cell = $sourcenames[$assignment->sourcetype];
    $actions = [];
    if (has_capability('tool/certify:admin', $context)) {
        if ($sourceclass::assignment_edit_supported($certification, $source, $assignment)) {
            $editformurl = new moodle_url('/admin/tool/certify/management/user_assignment_edit.php', ['id' => $assignment->id]);
            $editaction = new \local_openlms\output\dialog_form\icon($editformurl, 'i/settings', get_string('updateassignment', 'tool_certify'));
            $actions[] = $dialogformoutput->render($editaction);
        }
    }
    if (has_capability('tool/certify:assign', $context)) {
        if ($sourceclass::assignment_delete_supported($certification, $source, $assignment)) {
            $deleteformurl = new moodle_url('/admin/tool/certify/management/user_assignment_delete.php', ['id' => $assignment->id]);
            $deleteaction = new \local_openlms\output\dialog_form\icon($deleteformurl, 'i/delete', get_string('deleteassignment', 'tool_certify'));
            $actions[] = $dialogformoutput->render($deleteaction);
        }
    }
    if ($actions) {
        $cell .= ' ' . implode('', $actions);
    }
    $row[] = $cell;

    $data[] = $row;
}

if (!$totalcount) {
    echo get_string('errornoassignments', 'tool_certify');

} else {
    $columns = [];

    $firstname = get_string('firstname');
    $columndir = ($dir === "ASC" ? "DESC" : "ASC");
    $columnicon = ($dir === "ASC" ? "sort_asc" : "sort_desc");
    $columnicon = $OUTPUT->pix_icon('t/' . $columnicon, get_string(strtolower($columndir)), 'core',
        ['class' => 'iconsort']);
    $changeurl = new moodle_url($currenturl);
    $changeurl->param('sort', 'firstname');
    $changeurl->param('dir', $columndir);
    $firstname = html_writer::link($changeurl, $firstname);
    $lastname = get_string('lastname');
    $changeurl = new moodle_url($currenturl);
    $changeurl->param('sort', 'lastname');
    $changeurl->param('dir', $columndir);
    $lastname = html_writer::link($changeurl, $lastname);
    if ($sort === 'firstname') {
        $firstname .= $columnicon;
    } else if ($sort === 'lastname') {
        $lastname .= $columnicon;
    }
    $columns[] = $firstname . ' / ' . $lastname;

    $column = get_string('fromdate', 'tool_certify');
    $columndir = ($dir === "ASC" ? "DESC" : "ASC");
    $columnicon = ($dir === "ASC" ? "sort_asc" : "sort_desc");
    $columnicon = $OUTPUT->pix_icon('t/' . $columnicon, get_string(strtolower($columndir)), 'core',
        ['class' => 'iconsort']);
    $changeurl = new moodle_url($currenturl);
    $changeurl->param('sort', 'from');
    $changeurl->param('dir', $columndir);
    $column = html_writer::link($changeurl, $column);
    if ($sort === 'from') {
        $column .= $columnicon;
    }
    $columns[] = $column;

    $column = get_string('untildate', 'tool_certify');
    $columndir = ($dir === "ASC" ? "DESC" : "ASC");
    $columnicon = ($dir === "ASC" ? "sort_asc" : "sort_desc");
    $columnicon = $OUTPUT->pix_icon('t/' . $columnicon, get_string(strtolower($columndir)), 'core',
        ['class' => 'iconsort']);
    $changeurl = new moodle_url($currenturl);
    $changeurl->param('sort', 'until');
    $changeurl->param('dir', $columndir);
    $column = html_writer::link($changeurl, $column);
    if ($sort === 'until') {
        $column .= $columnicon;
    }
    $columns[] = $column;

    $columns[] = get_string('certificationstatus', 'tool_certify');
    $columns[] = get_string('source', 'tool_certify');

    $table = new html_table();
    $table->head = $columns;
    $table->id = 'certification_assignments';
    $table->attributes['class'] = 'admintable generaltable';
    $table->data = $data;
    echo html_writer::table($table);
}

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $currenturl);

$buttons = [];

foreach ($sourceclasses as $sourceclass) {
    $sourcetype = $sourceclass::get_type();
    $sourcerecord = $DB->get_record('tool_certify_sources', ['certificationid' => $certification->id, 'type' => $sourcetype]);
    if (!$sourcerecord) {
        continue;
    }
    $buttons = array_merge_recursive($buttons,  $sourceclass::get_management_certification_users_buttons($certification, $sourcerecord));
}

if ($buttons) {
    $buttons = implode(' ', $buttons);
    echo $OUTPUT->box($buttons, 'buttons');
}

echo $OUTPUT->footer();
