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
 * Program management interface.
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

$contextid = optional_param('contextid', 0, PARAM_INT);
$archived = optional_param('archived', 0, PARAM_BOOL);
$page = optional_param('page', 0, PARAM_INT);
$searchquery = optional_param('search', '', PARAM_RAW);
$sort = optional_param('sort', 'fullname', PARAM_ALPHANUMEXT);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);
$perpage = 25;

if ($contextid) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
} else {
    $context = context_system::instance();
}

require_login();
require_capability('tool/certify:view', $context);

if ($context->contextlevel == CONTEXT_SYSTEM) {
    $category = null;
} else if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', ['id' => $context->instanceid], '*', MUST_EXIST);
} else {
    throw new moodle_exception('invalidcontext');
}

$pageparams = [];
if ($page > 0) {
    $pageparams['page'] = $page;
}
if (trim($searchquery) !== '') {
    $pageparams['search'] = $searchquery;
}
if ($contextid) {
    $pageparams['contextid'] = $contextid;
}
if ($archived) {
    $pageparams['archived'] = 1;
}
if ($sort !== 'fullname') {
    $pageparams['sort'] = $sort;
}
if ($dir !== 'ASC') {
    $pageparams['dir'] = $dir;
}

$currenturl = new moodle_url('/admin/tool/certify/management/index.php', $pageparams);

management::setup_index_page($currenturl, $context, $contextid);

/** @var \local_openlms\output\dialog_form\renderer $dialogformoutput */
$dialogformoutput = $PAGE->get_renderer('local_openlms', 'dialog_form');

echo $OUTPUT->header();

// Allow category switching.

$contextoptions = management::get_used_contexts_menu($context);

echo '<div class="certification-category-selector float-right">';
$changecontexturl = new moodle_url($currenturl);
$changecontexturl->remove_all_params();
echo $OUTPUT->single_select($changecontexturl, 'contextid', $contextoptions, $contextid, [], 'certificationcategoryselect',
    ['label' => '<span class="accesshide">' . get_string('selectcategory', 'tool_certify') . '</span>']);
echo '</div>';

$taburl = new moodle_url($currenturl);
$taburl->remove_params(['archived']);
$taburl->remove_params(['search']);
$tabs[] = new tabobject('active', $taburl, get_string('certificationsactive', 'tool_certify'));
$tabs[] = new tabobject('archived', new moodle_url($taburl, ['archived' => 1]), get_string('certificationsarchived', 'tool_certify'));
echo $OUTPUT->render(new \tabtree($tabs, ($archived ? 'archived' : 'active')));

if (!$archived && has_capability('tool/certify:edit', $context)) {
    $addurl = new moodle_url('/admin/tool/certify/management/certification_add.php', ['contextid' => $context->id]);
    $addbutton = new local_openlms\output\dialog_form\button($addurl, get_string('addcertification', 'tool_certify'));
    $addbutton->set_after_submit($addbutton::AFTER_SUBMIT_REDIRECT);
    $button = $dialogformoutput->render($addbutton);
    echo '<div class="buttons float-right">';
    echo $button;
    echo '</div>';
}

// Add search form.
$data = [
    'action' => new moodle_url('/admin/tool/certify/management/index.php'),
    'inputname' => 'search',
    'searchstring' => get_string('search', 'search'),
    'query' => $searchquery,
    'hiddenfields' => [
        (object)['name' => 'contextid', 'value' => $contextid],
        (object)['name' => 'archived', 'value' => $archived],
        (object)['name' => 'sort', 'value' => $sort],
        (object)['name' => 'dir', 'value' => $dir],
    ],
    'extraclasses' => 'mb-3'
];
echo $OUTPUT->render_from_template('core/search_input', $data);

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

if ($contextid) {
    $certificationsinfo = management::fetch_certifications($context, (bool)$archived, $searchquery, $page, $perpage, $orderby);
} else {
    $certificationsinfo = management::fetch_certifications(null, (bool)$archived, $searchquery, $page, $perpage, $orderby);
}

echo $OUTPUT->paging_bar($certificationsinfo['totalcount'], $page, $perpage, $currenturl);

$data = [];

$certificationicon = $OUTPUT->pix_icon('certification', '', 'tool_certify');

foreach ($certificationsinfo['certifications'] as $certification) {
    $pcontext = context::instance_by_id($certification->contextid, MUST_EXIST);
    $row = [];
    if (!$contextid) {
        $row[] = html_writer::link(new moodle_url('/admin/tool/certify/management/index.php',
            ['contextid' => $pcontext->id]), $pcontext->get_context_name(false));
    }
    $fullname = $certificationicon . format_string($certification->fullname);
    if (has_capability('tool/certify:view', $pcontext)) {
        $detailurl = new moodle_url('/admin/tool/certify/management/certification.php', ['id' => $certification->id]);
        $fullname = html_writer::link($detailurl, $fullname);
    }
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
    $assignmentcount = $DB->count_records('tool_certify_assignments', ['certificationid' => $certification->id]);
    if (has_capability('tool/certify:view', $pcontext)) {
        $detailurl = new moodle_url('/admin/tool/certify/management/certification_users.php', ['id' => $certification->id]);
        $assignmentcount = html_writer::link($detailurl, $assignmentcount);
    }
    $row[] = $assignmentcount;
    $public = ($certification->public ? get_string('yes') : get_string('no'));
    if (has_capability('tool/certify:view', $pcontext)) {
        $detailurl = new moodle_url('/admin/tool/certify/management/certification_visibility.php', ['id' => $certification->id]);
        $public = html_writer::link($detailurl, $public);
    }
    $row[] = $public;
    $data[] = $row;
}

if (!$certificationsinfo['totalcount']) {
    echo get_string('errornocertifications', 'tool_certify');

} else {
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
    $columns[] = get_string('assignments', 'tool_certify');
    $columns[] = get_string('public', 'tool_certify');

    $table = new html_table();
    $table->head = $columns;
    if (!$contextid) {
        array_unshift($table->head, get_string('category'));
    }
    $table->id = 'management_certifications';
    $table->attributes['class'] = 'admintable generaltable';
    $table->data = $data;
    echo html_writer::table($table);
}

echo $OUTPUT->paging_bar($certificationsinfo['totalcount'], $page, $perpage, $currenturl);

echo $OUTPUT->footer();
