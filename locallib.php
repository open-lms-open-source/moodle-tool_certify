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
 * Less often used lib files for certification enrolment plugin.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/lib.php');

/**
 * Returns certifications tagged with a specified tag.
 *
 * @param core_tag_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged
 *      with this tag are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *      to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return core_tag\output\tagindex
 */
function tool_certify_get_tagged_certifications($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0) {
    // NOTE: When learners browse certifications we ignore the contexts, certifications have a flat structure,
    // then only complication here may be multi-tenancy.

    $perpage = $exclusivemode ? 20 : 5;

    $result = \tool_certify\local\catalogue::get_tagged_certifications($tag->id, $exclusivemode, $page * $perpage, $perpage);

    $content = $result['content'];
    $totalpages = ceil($result['totalcount'] / $perpage);

    return new core_tag\output\tagindex($tag, 'tool_certify', 'certification', $content,
        $exclusivemode, 0, 0, 1, $page, $totalpages);
}
