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

use tool_certify\local\management;
use tool_certify\local\certification;

if (!empty($_SERVER['HTTP_X_LEGACY_DIALOG_FORM_REQUEST'])) {
    define('AJAX_SCRIPT', true);
}

require('../../../../config.php');
require_once($CFG->dirroot . '/lib/formslib.php');

$certificationid = required_param('certificationid', PARAM_INT);
$type = required_param('type', PARAM_ALPHANUMEXT);

require_login();

$certification = $DB->get_record('tool_certify_certifications', ['id' => $certificationid], '*', MUST_EXIST);
$source = $DB->get_record('tool_certify_sources', ['certificationid' => $certification->id, 'type' => $type]);
$context = context::instance_by_id($certification->contextid);
require_capability('tool/certify:edit', $context);

$currenturl = new moodle_url('/admin/tool/certify/management/source_edit.php', ['id' => $certification->id]);
$returnurl = new moodle_url('/admin/tool/certify/management/certification_assignment.php', ['id' => $certification->id]);

/** @var \tool_certify\local\source\base[] $sourceclasses */
$sourceclasses = \tool_certify\local\assignment::get_source_classes();
if (!isset($sourceclasses[$type])) {
    throw new coding_exception('Invalid source type');
}
$sourceclass = $sourceclasses[$type];

management::setup_certification_page($currenturl, $context, $certification);

if ($source) {
    if (!$sourceclass::is_update_allowed($certification)) {
        redirect($returnurl);
    }
    $source->enable = 1;
    $source->hasassignments = $DB->record_exists('tool_certify_assignments', ['sourceid' => $source->id]);
} else {
    if (!$sourceclass::is_new_allowed($certification)) {
        redirect($returnurl);
    }
    $source = new stdClass();
    $source->id = null;
    $source->type = $type;
    $source->certificationid = $certification->id;
    $source->enable = 0;
    $source->hasassignments = false;
}
$source = $sourceclass::decode_datajson($source);

$formclass = $sourceclass::get_edit_form_class();
$form = new $formclass(null, ['source' => $source, 'certification' => $certification, 'context' => $context]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    tool_certify\local\source\base::update_source($data);
    $form->redirect_submitted($returnurl);
}

/** @var \tool_certify\output\management\renderer $managementoutput */
$managementoutput = $PAGE->get_renderer('tool_certify', 'management');

echo $OUTPUT->header();

echo $managementoutput->render_management_certification_tabs($certification, 'assignment');

echo $form->render();

echo $OUTPUT->footer();
