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
 * Certification browsing for learners.
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

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

$syscontext = context_system::instance();

$PAGE->set_url(new moodle_url('/admin/tool/certify/catalogue/certification.php', ['id' => $id]));
$PAGE->set_context(context_system::instance());

require_login();
require_capability('tool/certify:viewcatalogue', context_system::instance());

if (!enrol_is_enabled('programs')) {
    redirect(new moodle_url('/'));
}

$certification = $DB->get_record('tool_certify_certifications', ['id' => $id]);
if (!$certification || $certification->archived) {
    if ($certification) {
        $context = context::instance_by_id($certification->contextid);
    } else {
        $context = context_system::instance();
    }
    if (has_capability('tool/certify:view', $context)) {
        if ($certification) {
            redirect(new moodle_url('/admin/tool/certify/management/certification.php', ['id' => $certification->id]));
        } else {
            redirect(new moodle_url('/admin/tool/certify/management/index.php'));
        }
    } else {
        redirect(new moodle_url('/admin/tool/certify/catalogue/index.php'));
    }
}
$certificationcontext = context::instance_by_id($certification->contextid);

$assignment = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification->id, 'userid' => $USER->id]);
if ($assignment && !$assignment->archived) {
    redirect(new moodle_url('/admin/tool/certify/my/certification.php', ['id' => $id]));
}

if (!\tool_certify\local\catalogue::is_certification_visible($certification)) {
    if (has_capability('tool/certify:view', $certificationcontext)) {
        redirect(new moodle_url('/admin/tool/certify/management/certification.php', ['id' => $certification->id]));
    } else {
        redirect(new moodle_url('/admin/tool/certify/catalogue/index.php'));
    }
}

if (has_capability('tool/certify:view', $certificationcontext)) {
    $manageurl = new moodle_url('/admin/tool/certify/management/certification.php', ['id' => $certification->id]);
    $button = html_writer::link($manageurl, get_string('management', 'tool_certify'), ['class' => 'btn btn-secondary']);
    $PAGE->set_button($button . $PAGE->button);
}

/** @var \tool_certify\output\catalogue\renderer $catalogueoutput */
$catalogueoutput = $PAGE->get_renderer('tool_certify', 'catalogue');

$PAGE->set_heading(get_string('catalogue', 'tool_certify'));
$PAGE->navigation->override_active_url(new moodle_url('/admin/tool/certify/catalogue/index.php'));
$PAGE->set_title(get_string('catalogue', 'tool_certify'));
$PAGE->navbar->add(format_string($certification->fullname));

echo $OUTPUT->header();

echo $catalogueoutput->render_certification($certification);

echo $OUTPUT->footer();
