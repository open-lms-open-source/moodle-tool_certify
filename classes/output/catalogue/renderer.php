<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_certify\output\catalogue;

use stdClass;

/**
 * Certification catalogue renderer.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    public function render_certification(\stdClass $certification): string {
        global $CFG, $DB;

        $context = \context::instance_by_id($certification->contextid);
        $fullname = format_string($certification->fullname);
        $certificationicon = $this->output->pix_icon('certification', '', 'tool_certify');

        $description = file_rewrite_pluginfile_urls($certification->description, 'pluginfile.php', $context->id, 'tool_certify', 'description', $certification->id);
        $description = format_text($description, $certification->descriptionformat, ['context' => $context]);

        $tagsdiv = '';
        if ($CFG->usetags) {
            $tags = \core_tag_tag::get_item_tags('tool_certify', 'certification', $certification->id);
            if ($tags) {
                $tagsdiv = $this->output->tag_list($tags, '', 'certification-tags');
            }
        }

        $certificationimage = '';
        $presentation = (array)json_decode($certification->presentationjson);
        if (!empty($presentation['image'])) {
            $imageurl = \moodle_url::make_file_url("$CFG->wwwroot/pluginfile.php",
                '/' . $context->id . '/tool_certify/image/' . $certification->id . '/'. $presentation['image'], false);
            $certificationimage = '<div class="float-right certificationimage">' . \html_writer::img($imageurl, '') . '</div>';
        }

        $result = '';
        $result .= <<<EOT
<div class="certificationbox clearfix" data-certificationid="$certification->id">
  $certificationimage
  <div class="info">
  <div class="info">
    <h2 class="certificationname">{$certificationicon}{$fullname}</h2>
  </div>$tagsdiv
  <div class="content">
    <div class="summary">$description</div>
  </div>
</div>
EOT;

        $result .= '<dl class="row">';
        $result .= '<dt class="col-3">' . get_string('certificationstatus', 'tool_certify') . ':</dt><dd class="col-9">'
            . get_string('errornoassignment', 'tool_certify') . '</dd>';
        $result .= '</dl>';

        $actions = [];
        /** @var \tool_certify\local\source\base[] $sourceclasses */ // Type hack.
        $sourceclasses = \tool_certify\local\assignment::get_source_classes();
        foreach ($sourceclasses as $type => $classname) {
            $source = $DB->get_record('tool_certify_sources', ['certificationid' => $certification->id, 'type' => $type]);
            if (!$source) {
                continue;
            }
            $actions = array_merge($actions, $classname::get_catalogue_actions($certification, $source));
        }

        if ($actions) {
            $result .= '<div class="buttons mb-5">';
            $result .= implode(' ', $actions);
            $result .= '</div>';
        }

        return $result;
    }
}
