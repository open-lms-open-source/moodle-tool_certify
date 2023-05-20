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
 * My certifications.
 *
 * @package    tool_certify
 * @copyright  2022 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $CFG */
/** @var stdClass $USER */

require('../../../../config.php');

$sort = optional_param('sort', 'fullname', PARAM_ALPHANUMEXT);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);

require_login();

$usercontext = context_user::instance($USER->id);
$PAGE->set_context($usercontext);

if (!enrol_is_enabled('programs')) {
    redirect(new moodle_url('/'));
}
if (isguestuser()) {
    redirect(new moodle_url('/admin/tool/certify/catalogue/index.php'));
}

$pageparams = [];
if ($sort !== 'fullname') {
    $pageparams['sort'] = $sort;
}
if ($dir !== 'ASC') {
    $pageparams['dir'] = $dir;
}

$currenturl = new moodle_url('/admin/tool/certify/my/index.php', $pageparams);

$title = get_string('mycertifications', 'tool_certify');
$PAGE->navigation->extend_for_user($USER);
$PAGE->set_title($title);
$PAGE->set_url($currenturl);
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('profile'), new moodle_url('/user/profile.php', ['id' => $USER->id]));
$PAGE->navbar->add($title);

$buttons = [];
$manageurl = \tool_certify\local\management::get_management_url();
if ($manageurl) {
    $buttons[] = html_writer::link($manageurl, get_string('management', 'tool_certify'), ['class' => 'btn btn-secondary']);
}
$catalogueurl = \tool_certify\local\catalogue::get_catalogue_url();
if ($catalogueurl) {
    $buttons[] = html_writer::link($catalogueurl, get_string('catalogue', 'tool_certify'), ['class' => 'btn btn-secondary']);
}
$buttons = implode('&nbsp;', $buttons);
$PAGE->set_button($buttons . $PAGE->button);

echo $OUTPUT->header();

if ($sort === 'idnumber') {
    $orderby = 'idnumber';
} else {
    $orderby = 'fullname';
}
if ($dir === 'ASC') {
    $orderby .= ' ASC';
} else {
    $orderby .= ' DESC';
}

$sql = "SELECT p.*
          FROM {tool_certify_certifications} p
          JOIN {tool_certify_assignments} pa ON pa.certificationid = p.id
         WHERE p.archived = 0 AND pa.archived = 0
               AND pa.userid = :userid
      ORDER BY $orderby";
$params = ['userid' => $USER->id];
$certifications = $DB->get_records_sql($sql, $params);

if (!$certifications) {
    echo get_string('errornomycertifications', 'tool_certify');
    echo $OUTPUT->footer();
    die;
}

$data = [];

$certificationicon = $OUTPUT->pix_icon('certification', '', 'tool_certify');
$dateformat = get_string('strftimedatetimeshort');
$strnotset = get_string('notset', 'tool_certify');

foreach ($certifications as $certification) {
    $assignment = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification->id, 'userid' => $USER->id]);
    $pcontext = context::instance_by_id($certification->contextid);
    $row = [];
    $fullname = $certificationicon . format_string($certification->fullname);
    $detailurl = new moodle_url('/admin/tool/certify/my/certification.php', ['id' => $certification->id]);
    $fullname = html_writer::link($detailurl, $fullname);
    if ($CFG->usetags) {
        $tags = core_tag_tag::get_item_tags('tool_certify', 'certification', $certification->id);
        if ($tags) {
            $fullname .= '<br />' . $OUTPUT->tag_list($tags, '', 'certification-tags');
        }
    }

    $row[] = $fullname;
    $row[] = s($certification->idnumber);
    $description = file_rewrite_pluginfile_urls($certification->description, 'pluginfile.php', $pcontext->id, 'tool_certify', 'description', $certification->id);
    $row[] = format_text($description, $certification->descriptionformat, ['context' => $pcontext]);

    $row[] = \tool_certify\local\assignment::get_until_html($certification, $assignment);

    $row[] = \tool_certify\local\assignment::get_status_html($certification, $assignment);

    $data[] = $row;
}

$columns = [];

$column = get_string('certificationname', 'tool_certify');
$columndir = ($dir === "ASC" ? "DESC" : "ASC");
$columnicon = ($dir === "ASC" ? "sort_asc" : "sort_desc");
$columnicon = $OUTPUT->pix_icon('t/' . $columnicon, get_string(strtolower($columndir)), 'core',
    ['class' => 'iconsort']);
$changeurl = new moodle_url($currenturl);
$changeurl->param('sort', 'fullname');
$changeurl->param('dir', $columndir);
$column = html_writer::link($changeurl, $column);
if ($sort === 'fullname') {
    $column .= $columnicon;
}
$columns[] = $column;

$column = get_string('idnumber');
$columndir = ($dir === "ASC" ? "DESC" : "ASC");
$columnicon = ($dir === "ASC" ? "sort_asc" : "sort_desc");
$columnicon = $OUTPUT->pix_icon('t/' . $columnicon, get_string(strtolower($columndir)), 'core',
    ['class' => 'iconsort']);
$changeurl = new moodle_url($currenturl);
$changeurl->param('sort', 'idnumber');
$changeurl->param('dir', $columndir);
$column = html_writer::link($changeurl, $column);
if ($sort === 'idnumber') {
    $column .= $columnicon;
}
$columns[] = $column;

$columns[] = get_string('description');

$columns[] = get_string('untildate', 'tool_certify');

$columns[] = get_string('certificationstatus', 'tool_certify');

$table = new html_table();
$table->head = $columns;
$table->id = 'my_certifications';
$table->attributes['class'] = 'generaltable';
$table->data = $data;
echo html_writer::table($table);

echo $OUTPUT->footer();
