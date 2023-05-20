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
 * @copyright  2022 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $CFG */
/** @var stdClass $COURSE */

use tool_certify\local\certification;
use tool_certify\local\management;

if (!empty($_SERVER['HTTP_X_LEGACY_DIALOG_FORM_REQUEST'])) {
    define('AJAX_SCRIPT', true);
}

require('../../../../config.php');
require_once($CFG->dirroot . '/lib/formslib.php');

$contextid = required_param('contextid', PARAM_INT);
$context = context::instance_by_id($contextid);

require_login();
require_capability('tool/certify:edit', $context);

if ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSECAT) {
    throw new moodle_exception('invalidcontext');
}

$currenturl = new moodle_url('/admin/tool/certify/management/certification_add.php', ['contextid' => $context->id]);
management::setup_index_page($currenturl, $context, $context->id);

$certification = new stdClass();
$certification->contextid = $context->id;
$certification->fullname = '';
$certification->idnumber = '';
$certification->description = '';
$certification->descriptionformat = FORMAT_HTML;

$editoroptions = certification::get_description_editor_options($context->id);

$form = new \tool_certify\local\form\certification_add(null, ['data' => $certification, 'editoroptions' => $editoroptions]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/certify/management/index.php', ['contextid' => $context->id]));
}

if ($data = $form->get_data()) {
    $certification = certification::add_certification($data);
    $returlurl = new moodle_url('/admin/tool/certify/management/certification.php', ['id' => $certification->id]);
    $form->redirect_submitted($returlurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addcertification', 'tool_certify'));

echo $form->render();

echo $OUTPUT->footer();
