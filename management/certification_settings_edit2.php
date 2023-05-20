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
 * Re-certification management interface.
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
use tool_certify\local\certification;

if (!empty($_SERVER['HTTP_X_LEGACY_DIALOG_FORM_REQUEST'])) {
    define('AJAX_SCRIPT', true);
}

require('../../../../config.php');
require_once($CFG->dirroot . '/lib/formslib.php');

$id = required_param('id', PARAM_INT);

require_login();

$certification = $DB->get_record('tool_certify_certifications', ['id' => $id], '*', MUST_EXIST);
$context = context::instance_by_id($certification->contextid);
require_capability('tool/certify:edit', $context);

$currenturl = new moodle_url('/admin/tool/certify/management/certification_settings_edit2.php', ['id' => $id]);

management::setup_certification_page($currenturl, $context, $certification);

$form = new \tool_certify\local\form\certification_settings_edit2(null, ['certification' => $certification, 'context' => $context]);

$returnurl = new moodle_url('/admin/tool/certify/management/certification_settings.php', ['id' => $certification->id]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    certification::update_certification_settings($data);
    $form->redirect_submitted($returnurl);
}

/** @var \tool_certify\output\management\renderer $managementoutput */
$managementoutput = $PAGE->get_renderer('tool_certify', 'management');

echo $OUTPUT->header();

echo $managementoutput->render_management_certification_tabs($certification, 'periods');

echo $form->render();

echo $OUTPUT->footer();
