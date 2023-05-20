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

namespace tool_certify\local;

/**
 * Certification catalogue for learners.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalogue {
    /** @var int page number */
    protected $page = 0;
    /** @var int number of certifications per page */
    protected $perpage = 10;
    /** @var ?string search text */
    protected $searchtext = null;

    /**
     * Creates catalogue instance.
     *
     * @param array $request
     */
    public function __construct(array $request) {
        // NOTE: we do not care about CSRF here, because there are no data modifications in Catalogue,
        // we DO want to allow and encourage bookmarking of catalogue URLs.
        if (isset($request['page'])) {
            $page = clean_param($request['page'], PARAM_INT);
            if ($page > 0) {
                $this->page = $page;
            }
        }
        if (isset($request['perpage'])) {
            $perpage = clean_param($request['perpage'], PARAM_INT);
            if ($perpage > 0) {
                $this->perpage = $perpage;
            }
        }
        if (isset($request['searchtext'])) {
            $searchtext = clean_param($request['searchtext'], PARAM_RAW);
            if (\core_text::strlen($searchtext) > 1) {
                $this->searchtext = $searchtext;
            }
        }
    }

    /**
     * Current catalogue URL.
     *
     * @return \moodle_url
     */
    public function get_current_url(): \moodle_url {
        $pageparams = [];
        if ($this->page != 0) {
            $pageparams['page'] = $this->page;
        }
        if ($this->perpage != 10) {
            $pageparams['perpage'] = $this->perpage;
        }
        if ($this->searchtext !== null) {
            $pageparams['searchtext'] = $this->searchtext;
        }
        return new \moodle_url('/admin/tool/certify/catalogue/index.php', $pageparams);
    }

    /**
     * Are we filtering results?
     *
     * @return bool
     */
    public function is_filtering(): bool {
        if ($this->searchtext !== null) {
            return true;
        }
        return false;
    }

    /**
     * Returns page number.
     *
     * @return int
     */
    public function get_page(): int {
        return $this->page;
    }

    /**
     * Returns number of certifications per page.
     *
     * @return int
     */
    public function get_perpage(): int {
        return $this->perpage;
    }

    /**
     * Returns search text.
     *
     * @return string|null
     */
    public function get_searchtext(): ?string {
        return $this->searchtext;
    }

    /**
     * Returns hidden text search params.
     *
     * @return array
     */
    public function get_hidden_search_fields(): array {
        $result = [];
        if ($this->page > 0) {
            $result['page'] = $this->page;
        }
        if ($this->perpage != 10) {
            $result['perpage'] = $this->perpage;
        }
        return $result;
    }

    /**
     * Render certification listing.
     *
     * @return string
     */
    public function render_certifications(): string {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;

        $catalogueoutput = $PAGE->get_renderer('tool_certify', 'catalogue');

        $totalcount = $this->count_certifications();
        $certifications = $this->get_certifications();

        if (!$totalcount && !$this->is_filtering()) {
            return get_string('errornocertifications', 'tool_certify');
        }

        $certificationicon = $OUTPUT->pix_icon('certification', '', 'tool_certify');
        $currenturl = $this->get_current_url();

        $result = '';

        $data = [
            'action' => new \moodle_url('/admin/tool/certify/catalogue/index.php'),
            'inputname' => 'searchtext',
            'searchstring' => get_string('search', 'cohort'),
            'query' => $this->searchtext,
            'hiddenfields' => $this->get_hidden_search_fields(),
            'extraclasses' => 'mb-3'
        ];
        $result .= $OUTPUT->render_from_template('core/search_input', $data);

        if (!$totalcount) {
            $result .= get_string('errornocertifications', 'tool_certify');
            return $result;
        }

        $result .= $OUTPUT->paging_bar($totalcount, $this->page, $this->perpage, $currenturl);
        $result .= '<div class="certifications">';
        $i = 0;
        $count = count($certifications);
        foreach ($certifications as $certification) {
            $assignment = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification->id, 'userid' => $USER->id, 'archived' => 0]);
            $context = \context::instance_by_id($certification->contextid);
            $classes = ['certificationbox', 'clearfix'];
            if ($i % 2 === 0) {
                $classes[] = 'odd';
            } else {
                $classes[] = 'even';
            }
            if ($i == 0) {
                $classes[] = 'first';
            }
            if ($i == $count - 1) {
                $classes[] = 'last';
            }
            $classes = implode(' ', $classes);
            $fullname = format_string($certification->fullname);
            if ($assignment) {
                $url = new \moodle_url('/admin/tool/certify/my/certification.php', ['id' => $certification->id]);
            } else {
                $url = new \moodle_url('/admin/tool/certify/catalogue/certification.php', ['id' => $certification->id]);
            }
            $url = $url->out(true);

            $description = file_rewrite_pluginfile_urls($certification->description, 'pluginfile.php', $context->id, 'tool_certify', 'description', $certification->id);
            $description = format_text($description, $certification->descriptionformat, ['context' => $context]);

            $tagsdiv = '';
            if ($CFG->usetags) {
                $tags = \core_tag_tag::get_item_tags('tool_certify', 'certification', $certification->id);
                if ($tags) {
                    $tagsdiv = $OUTPUT->tag_list($tags, '', 'certification-tags');
                }
            }

            $assignmentinfo = '';
            if ($assignment) {
                $assignmentinfo = assignment::get_status_html($certification, $assignment);
            }

            $certificationimage = '';
            $presentation = (array)json_decode($certification->presentationjson);
            if (!empty($presentation['image'])) {
                $imageurl = \moodle_url::make_file_url("$CFG->wwwroot/pluginfile.php",
                    '/' . $context->id . '/tool_certify/image/' . $certification->id . '/'. $presentation['image'], false);
                $certificationimage = '<div class="float-right certificationimage">' . \html_writer::img($imageurl, '') . '</div>';
            }

            $result .= <<<EOT
<div class="$classes" data-certificationid="$certification->id">
  $certificationimage
  <div class="info">
    <h3 class="certificationname"><a class="aalink" href="$url">{$certificationicon}{$fullname}<a/></h3>
  </div>$tagsdiv
  <div class="content">
    <div class="summary">$description</div>
  </div>
  <div class="assignment">$assignmentinfo</div>
</div>
EOT;
            $i++;
        }

        $result .= '</div>';
        $result .= $OUTPUT->paging_bar($totalcount, $this->page, $this->perpage, $currenturl);

        return $result;
    }

    /**
     * Returns visible certifications.
     *
     * @return array
     */
    public function get_certifications(): array {
        global $DB;

        list($sql, $params) = $this->get_certifications_sql();
        return $DB->get_records_sql($sql, $params, $this->page * $this->perpage, $this->perpage);
    }

    /**
     * Returns filtered count of certifications on all pages.
     *
     * @return int
     */
    public function count_certifications(): int {
        global $DB;

        list($sql, $params) = $this->get_certifications_sql();

        $sql = util::convert_to_count_sql($sql);

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Returns SQL to fetch filtered certifications.
     *
     * @return array
     */
    protected function get_certifications_sql(): array {
        global $DB, $USER;

        $params = ['userid1' => $USER->id, 'userid2' => $USER->id];

        $searchwhere = '';
        if (isset($this->searchtext)) {
            // NOTE: We should add better search similar to get_courses_search().
            $concat = $DB->sql_concat_join("' '", ['p.fullname', 'p.description', 'p.idnumber']);
            $searchwhere = 'AND ' . $DB->sql_like("($concat)", ':searchtext', false, false);
            $params['searchtext'] = '%' . $DB->sql_like_escape($this->searchtext) . '%';
        }

        $tenantjoin = "";
        if (tenant::is_active()) {
            $tenantid = \tool_olms_tenant\tenancy::get_tenant_id();
            if ($tenantid) {
                $tenantjoin = "JOIN {context} pc ON pc.id = p.contextid AND (pc.tenantid IS NULL OR pc.tenantid = :tenantid)";
                $params['tenantid'] = $tenantid;
            }
        }

        $sql = "SELECT p.*
                  FROM {tool_certify_certifications} p
             LEFT JOIN {tool_certify_assignments} pa ON pa.certificationid = p.id AND pa.userid = :userid1 AND pa.archived = 0
                  $tenantjoin
                 WHERE p.archived = 0 $searchwhere
                       AND (p.public = 1 OR pa.id IS NOT NULL OR EXISTS (
                            SELECT cm.id
                              FROM {cohort_members} cm
                              JOIN {tool_certify_cohorts} pc ON pc.cohortid = cm.cohortid
                             WHERE cm.userid = :userid2 AND pc.certificationid = p.id))
              ORDER BY p.fullname ASC";

        return [$sql, $params];
    }

    /**
     * Is certification visible for the user?
     *
     * @param \stdClass $certification
     * @param int|null $userid
     */
    public static function is_certification_visible(\stdClass $certification, ?int $userid = null): bool {
        global $DB, $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        if ($certification->archived) {
            return false;
        }

        if (\tool_certify\local\tenant::is_active()) {
            if ($userid == $USER->id) {
                $tenantid = \tool_olms_tenant\tenancy::get_tenant_id();
            } else {
                $tenantid = \tool_olms_tenant\tenant_users::get_user_tenant_id($userid);
            }
            if ($tenantid) {
                $certificationcontext = \context::instance_by_id($certification->contextid);
                $certificationtenantid = \tool_olms_tenant\tenants::get_context_tenant_id($certificationcontext);
                if ($certificationtenantid && $certificationtenantid != $tenantid) {
                    return false;
                }
            }
        }

        if ($certification->public) {
            return true;
        }
        if ($DB->record_exists('tool_certify_assignments', ['certificationid' => $certification->id, 'userid' => $userid, 'archived' => 0])) {
            return true;
        }
        $sql = "SELECT 1
                  FROM {tool_certify_cohorts} c
                  JOIN {cohort_members} cm ON cm.cohortid = c.cohortid AND cm.userid = :userid
                 WHERE c.certificationid = :certificationid";
        $params = ['certificationid' => $certification->id, 'userid' => $userid];
        if ($DB->record_exists_sql($sql, $params)) {
            return true;
        }
        return false;
    }

    /**
     * Returns link to certification catalogue.
     *
     * @return ?\moodle_url null of certifications disabled or user cannot access catalogue
     */
    public static function get_catalogue_url(): ?\moodle_url {
        if (!enrol_is_enabled('programs')) {
            return null;
        }
        if (!isloggedin()) {
            return null;
        }
        if (!has_capability('tool/certify:viewcatalogue', \context_system::instance())) {
            return null;
        }
        return new \moodle_url('/admin/tool/certify/catalogue/index.php');
    }

    /**
     * Returns list of all tags of certifications that user may see or is allocated to.
     *
     * NOTE: not used anywhere, this was intended for tag filtering UI
     *
     * @param ?int $userid
     * @return array [tagid => tagname]
     */
    public function get_used_tags(?int $userid = null): array {
        global $USER, $DB, $CFG;

        if (!$CFG->usetags) {
            return [];
        }

        if ($userid === null) {
            $userid = $USER->id;
        }

        $sql = "SELECT DISTINCT t.id, t.name
                  FROM {tag} t
                  JOIN {tag_instance} tt ON tt.itemtype = 'certification' AND tt.tagid = t.id AND tt.component = 'tool_certify'
                  JOIN {tool_certify_certifications} p ON p.id = tt.itemid
             LEFT JOIN {tool_certify_assignments} pa ON pa.certificationid = p.id AND pa.userid = :userid1 AND pa.archived = 0
                 WHERE p.archived = 0
                       AND (p.public = 1 OR pa.id IS NOT NULL OR EXISTS (
                            SELECT cm.id
                              FROM {cohort_members} cm
                              JOIN {tool_certify_cohorts} pc ON pc.cohortid = cm.cohortid
                             WHERE cm.userid = :userid2 AND pc.certificationid = p.id))
              ORDER BY t.name ASC";
        $params = ['userid1' => $userid, 'userid2' => $userid];

        $menu = $DB->get_records_sql_menu($sql, $params);
        return array_map('format_string', $menu);
    }

    /**
     * Render certifications with a tag that current learner can see.
     *
     * NOTE: this is using only certification.public flag and cohort visibility + allocated certifications
     *
     * @param int $tagid
     * @param bool $exclusive
     * @param int $limitfrom
     * @param int $limitnum
     * @return array ['content' => string, 'totalcount' => int]
     */
    public static function get_tagged_certifications(int $tagid, bool $exclusive, int $limitfrom, int $limitnum): array {
        global $DB, $USER, $OUTPUT;

        // NOTE: When learners browse certifications we ignore the contexts, certifications have a flat structure,
        // then only complication here may be multi-tenancy.

        $sql = "SELECT p.*
                  FROM {tool_certify_certifications} p
                  JOIN {tag_instance} tt ON tt.itemid = p.id AND tt.itemtype = 'certification' AND tt.tagid = :tagid AND tt.component = 'tool_certify'
             LEFT JOIN {tool_certify_assignments} pa ON pa.certificationid = p.id AND pa.userid = :userid1 AND pa.archived = 0
                 WHERE p.archived = 0
                       AND (p.public = 1 OR pa.id IS NOT NULL OR EXISTS (
                             SELECT cm.id
                               FROM {cohort_members} cm
                               JOIN {tool_certify_cohorts} pc ON pc.cohortid = cm.cohortid
                              WHERE cm.userid = :userid2 AND pc.certificationid = p.id))
              ORDER BY p.fullname";
        $countsql = util::convert_to_count_sql($sql);
        $params = ['tagid' => $tagid, 'userid1' => $USER->id, 'userid2' => $USER->id];

        $totalcount = $DB->count_records_sql($countsql, $params);
        $certifications = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);

        $result = [];
        foreach ($certifications as $certification) {
            $fullname = format_string($certification->fullname);
            $url = new \moodle_url('/admin/tool/certify/catalogue/certification.php', ['id' => $certification->id]);
            $icon = $OUTPUT->pix_icon('certification', '', 'tool_certify');
            $result[] = '<div class="certification-link">' . $icon . \html_writer::link($url, $fullname) . '</div>';
        }

        return ['content' => implode('', $result), 'totalcount' => $totalcount];
    }
}
