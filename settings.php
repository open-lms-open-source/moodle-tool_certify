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
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var core_renderer $OUTPUT */
/** @var admin_root $ADMIN */

defined('MOODLE_INTERNAL') || die();

// Certifications require programs.
if (enrol_is_enabled('programs')) {
    $ADMIN->add('root', new admin_category('certifications', new lang_string('certifications', 'tool_certify')), 'analytics');
    $ADMIN->add('certifications', new admin_externalpage('certificationsmanagement',
        new lang_string('management', 'tool_certify'),
        new moodle_url("/admin/tool/certify/management/index.php"),
        'tool/certify:view'));
}

// Do not use enrol plugin settings, create a top level management section.
$settings = null;
