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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

require_login();

$certification = $DB->get_record('tool_certify_certifications', ['id' => $id], '*', MUST_EXIST);
$context = context::instance_by_id($certification->contextid);
require_capability('tool/certify:view', $context);

$currenturl = new moodle_url('/admin/tool/certify/management/certification_notifications.php', ['id' => $id]);

management::setup_certification_page($currenturl, $context, $certification);

/** @var \local_openlms\output\dialog_form\renderer $dialogformoutput */
$dialogformoutput = $PAGE->get_renderer('local_openlms', 'dialog_form');
/** @var \tool_certify\output\management\renderer $managementoutput */
$managementoutput = $PAGE->get_renderer('tool_certify', 'management');

echo $OUTPUT->header();

echo $managementoutput->render_management_certification_tabs($certification, 'notifications');

echo $OUTPUT->heading(get_string('notifications', 'tool_certify'), 2, ['h3']);
echo \tool_certify\local\notification_manager::render_notifications($certification->id);

if ($certification->programid1) {
    $program1 = $DB->get_record('enrol_programs_programs', ['id' => $certification->programid1]);
    if ($program1) {
        echo $OUTPUT->heading(format_string($program1->fullname), 2, ['h3']);
        echo \enrol_programs\local\notification_manager::render_notifications($certification->programid1, 'enrol_programs_1_notifications');
    }
}
if (isset($certification->recertify) && $certification->programid2 && $certification->programid2 != $certification->programid1) {
    $program2 = $DB->get_record('enrol_programs_programs', ['id' => $certification->programid2]);
    if ($program2) {
        echo $OUTPUT->heading(format_string($program2->fullname), 2, ['h3']);
        echo \enrol_programs\local\notification_manager::render_notifications($certification->programid2, 'enrol_programs_2_notifications');
    }
}

echo $OUTPUT->footer();
