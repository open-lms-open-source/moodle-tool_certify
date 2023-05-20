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
 * Confirm self-assignment to certification.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $CFG */
/** @var stdClass $COURSE */
/** @var stdClass $USER */

if (!empty($_SERVER['HTTP_X_LEGACY_DIALOG_FORM_REQUEST'])) {
    define('AJAX_SCRIPT', true);
}

require('../../../../config.php');

$sourceid = required_param('sourceid', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/admin/tool/certify/catalogue/source_selfassignment.php', ['sourceid' => $sourceid]));

require_login();
require_capability('tool/certify:viewcatalogue', context_system::instance());

if (!enrol_is_enabled('programs')) {
    redirect(new moodle_url('/'));
}

$source = $DB->get_record('tool_certify_sources', ['id' => $sourceid, 'type' => 'selfassignment'], '*', MUST_EXIST);
$certification = $DB->get_record('tool_certify_certifications', ['id' => $source->certificationid], '*', MUST_EXIST);
$certificationcontext = context::instance_by_id($certification->contextid);

$PAGE->set_heading(get_string('catalogue', 'tool_certify'));
$PAGE->navigation->override_active_url(new moodle_url('/admin/tool/certify/catalogue/index.php'));
$PAGE->set_title(get_string('catalogue', 'tool_certify'));
$PAGE->navbar->add(format_string($certification->fullname));

if (!\tool_certify\local\source\selfassignment::can_user_request($certification, $source, $USER->id)) {
    redirect(new moodle_url('/admin/tool/certify/catalogue/index.php'));
}

$returnurl = new moodle_url('/admin/tool/certify/catalogue/certification.php', ['id' => $certification->id]);

$form = new tool_certify\local\form\source_selfassignment(null, ['source' => $source, 'certification' => $certification]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    tool_certify\local\source\selfassignment::signup($certification->id, $source->id);
    $form->redirect_submitted($returnurl);
}

/** @var \tool_certify\output\catalogue\renderer $catalogueoutput */
$catalogueoutput = $PAGE->get_renderer('tool_certify', 'catalogue');

echo $OUTPUT->header();

echo $catalogueoutput->render_certification($certification);

echo $form->render();

echo $OUTPUT->footer();
