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
 * Certification plugin lib functions.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function tool_certify_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSECAT) {
        send_file_not_found();
    }

    if ($filearea !== 'description' && $filearea !== 'image') {
        send_file_not_found();
    }

    $certificationid = (int)array_shift($args);

    $certification = $DB->get_record('tool_certify_certifications', ['id' => $certificationid]);
    if (!$certification) {
        send_file_not_found();
    }
    if (!has_capability('tool/certify:view', $context)
        && !\tool_certify\local\catalogue::is_certification_visible($certification)
    ) {
        send_file_not_found();
    }

    $filename = array_pop($args);
    $filepath = implode('/', $args) . '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'tool_certify', $filearea, $certificationid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 60 * 60, 0, $forcedownload, $options);
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 */
function tool_certify_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $USER;

    if (!enrol_is_enabled('programs')) {
        return;
    }

    if ($USER->id == $user->id) {
        if (\tool_certify\local\assignment::has_active_assignments($USER->id)) {
            $link = get_string('mycertifications', 'tool_certify');
            $url = new moodle_url('/admin/tool/certify/my/index.php');
            $node = new core_user\output\myprofile\node('miscellaneous', 'toolcertify_certifications', $link, null, $url);
            $tree->add_node($node);
        }
    }
}

/**
 * Hook called before a course category is deleted.
 *
 * @param \stdClass $category The category record.
 */
function tool_certify_pre_course_category_delete(\stdClass $category) {
    \tool_certify\local\certification::pre_course_category_delete($category);
}

/**
 * Map icons for font-awesome themes.
 */
function tool_certify_get_fontawesome_icon_map() {
    return [
        'tool_certify:catalogue' => 'fa-cubes',
        'tool_certify:certification' => 'fa-certificate',
        'tool_certify:mycertifications' => 'fa-certificate',
        'tool_certify:requestapprove' => 'fa-check-square-o',
        'tool_certify:requestreject' => 'fa-times-rectangle-o',
    ];
}
