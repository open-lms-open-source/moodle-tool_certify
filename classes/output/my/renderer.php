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

namespace tool_certify\output\my;

use tool_certify\local\assignment;
use stdClass;

/**
 * My certification renderer.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    public function render_certification(\stdClass $certification): string {
        global $CFG;

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

        return $result;
    }

    public function render_user_assignment(stdClass $certification, stdClass $assignment): string {
        global $DB;

        $result = '';

        $result .= '<dl class="row">';
        $result .= '<dt class="col-3">' . get_string('certificationstatus', 'tool_certify') . ':</dt><dd class="col-9">'
            . assignment::get_status_html($certification, $assignment) . '</dd>';

        if ($certification->recertify && !$certification->archived && !$assignment->archived) {
            $stoprecertify = !$DB->record_exists('tool_certify_periods', [
                'certificationid' => $assignment->certificationid,
                'userid' => $assignment->userid,
                'recertifiable' => 1,
            ]);
            $result .= '<dt class="col-3">' . get_string('stoprecertify', 'tool_certify') . ':</dt><dd class="col-9">'
                . ($stoprecertify ? get_string('yes') : get_string('no')) . '<br />';
        }

        if ($assignment->timecertifieduntil) {
            $result .= '<dt class="col-3">' . get_string('certifieduntiltemporary', 'tool_certify') . ':</dt><dd class="col-9">'
                . userdate($assignment->timecertifieduntil) . '</dd>';
        }
        $result .= '</dl>';

        return $result;
    }

    public function render_user_periods(stdClass $certification, stdClass $assignment): string {
        global $PAGE;
        $result = $this->output->heading(get_string('periods', 'tool_certify'), 4);

        $table = new \tool_certify\table\certification_periods($certification, $assignment, $PAGE->url);
        ob_start();
        $table->out($table->pagesize, false);
        $result .= ob_get_clean();

        return $result;
    }

    /**
     * Returns body of My certifications block.
     *
     * @return string
     */
    public function render_block_content(): string {
        global $DB, $USER;

        $sql = "SELECT ca.*
                  FROM {tool_certify_certifications} c
                  JOIN {tool_certify_assignments} ca ON ca.certificationid = c.id
                 WHERE c.archived = 0 AND ca.archived = 0
                       AND ca.userid = :userid
              ORDER BY c.fullname ASC";
        $params = ['userid' => $USER->id];
        $assignments = $DB->get_records_sql($sql, $params);

        if (!$assignments) {
            return '<em>' . get_string('errornomycertifications', 'tool_certify') . '</em>';
        }

        $certificationicon = $this->output->pix_icon('certification', '', 'tool_certify');
        $strnotset = get_string('notset', 'tool_certify');
        $dateformat = get_string('strftimedatetimeshort');

        foreach ($assignments as $assignment) {
            $row = [];

            $certification = $DB->get_record('tool_certify_certifications', ['id' => $assignment->certificationid]);
            $fullname = $certificationicon . format_string($certification->fullname);
            $detailurl = new \moodle_url('/admin/tool/certify/my/certification.php', ['id' => $certification->id]);
            $fullname = \html_writer::link($detailurl, $fullname);
            $row[] = $fullname;

            $row[] = assignment::get_status_html($certification, $assignment);

            $row[] = assignment::get_until_html($certification, $assignment);

            $data[] = $row;
        }

        $table = new \html_table();
        $table->head = [
            get_string('certificationname', 'tool_certify'),
            get_string('certificationstatus', 'tool_certify'),
            get_string('untildate', 'tool_certify'),
        ];
        $table->attributes['class'] = 'admintable generaltable';
        $table->data = $data;
        return \html_writer::table($table);
    }

    /**
     * Returns footer of My certifications block.
     *
     * @return string
     */
    public function render_block_footer(): string {
        $url = \tool_certify\local\catalogue::get_catalogue_url();
        if ($url) {
            return '<div class="float-right">' . \html_writer::link($url, get_string('catalogue', 'tool_certify')) . '</div>';
        }
        return '';
    }
}
