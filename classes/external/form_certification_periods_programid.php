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

namespace tool_certify\external;

use external_function_parameters;
use external_value;

/**
 * Provides list of program candidates for certification.
 *
 * @package     tool_certify
 * @copyright   2023 Open LMS (https://www.openlms.net/)
 * @author      Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class form_certification_periods_programid extends \local_openlms\external\form_autocomplete_field {
    const MAX_RESULTS = 20;

    /**
     * True means returned field data is array, false means value is scalar.
     *
     * @return bool
     */
    public static function is_multi_select_field(): bool {
        return false;
    }

    /**
     * Describes the external function arguments.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW, 'The search query', VALUE_REQUIRED),
            'certificationid' => new external_value(PARAM_INT, 'certification id', VALUE_REQUIRED),
        ]);
    }

    /**
     * Finds candidate programs for given certification.
     *
     * @param string $query The search request.
     * @param int $certificationid The certification.
     * @return array
     */
    public static function execute(string $query, int $certificationid): array {
        global $DB;

        $parameters = self::validate_parameters(self::execute_parameters(),
            ['query' => $query, 'certificationid' => $certificationid]);
        $query = $parameters['query'];
        $certificationid = $parameters['certificationid'];

        $certification = $DB->get_record('tool_certify_certifications', ['id' => $certificationid], '*', MUST_EXIST);

        // Validate context.
        $context = \context::instance_by_id($certification->contextid);
        self::validate_context($context);
        require_capability('tool/certify:edit', $context);

        list($searchsql, $params) = \enrol_programs\local\management::get_program_search_query(null, $query, 'p');

        $tenantselect = '';
        if (\tool_certify\local\tenant::is_active()) {
            $certificationtenantid = $DB->get_field('context', 'tenantid', ['id' => $context->id]);
            if ($certificationtenantid) {
                $tenantselect = "AND (ctx.tenantid = :tenantid OR ctx.tenantid IS NULL)";
                $params['tenantid'] = $certificationtenantid;
            }
        }

        $sqlquery = <<<SQL
            SELECT p.*
              FROM {enrol_programs_programs} p
              JOIN {enrol_programs_sources} s ON s.programid = p.id and s.type = 'certify'
              JOIN {context} ctx ON ctx.id = p.contextid    
             WHERE p.archived = 0 AND $searchsql
                   $tenantselect
          ORDER BY p.fullname ASC
SQL;

        $rs = $DB->get_recordset_sql($sqlquery, $params);

        $count = 0;
        $list = [];
        $notice = null;

        foreach ($rs as $program) {
            $context = \context::instance_by_id($program->contextid);
            if (!has_capability('enrol/programs:addtocertifications', $context)) {
                continue;
            }
            $count++;
            if ($count > self::MAX_RESULTS) {
                $notice = get_string('toomanyrecords', 'local_openlms', self::MAX_RESULTS);
                break;
            }

            $list[] = [
                'value' => $program->id,
                'label' => format_text($program->fullname),
            ];
        }
        $rs->close();

        return [
            'notice' => $notice,
            'list' => $list,
        ];
    }

    /**
     * Return function that return label for given value.
     *
     * @param array $arguments
     * @return callable
     */
    public static function get_label_callback(array $arguments): callable {
        return function($value) use ($arguments): string {
            global $DB;

            if (!$value) {
                return '';
            }

            $certification = $DB->get_record('tool_certify_certifications', ['id' => $arguments['certificationid']], '*', MUST_EXIST);
            $context = \context::instance_by_id($certification->contextid);

            $error = '';
            if (self::validate_form_value($arguments, $value, $context) !== null) {
                $error = ' (' . get_string('error') .')';
            }

            $program = $DB->get_record('enrol_programs_programs', ['id' => $value]);
            if ($program) {
                return format_string($program->fullname) . $error;
            } else {
                return trim($error);
            }
        };
    }

    /**
     * Is valid value?
     *
     * @param array $arguments
     * @param $value
     * @return string|null error message, NULL means value is ok
     */
    public static function validate_form_value(array $arguments, $value): ?string {
        global $DB;

        if (!$value) {
            return null;
        }

        $program = $DB->get_record('enrol_programs_programs', ['id' => $value]);
        if (!$program) {
            return get_string('error');
        }

        $certification = $DB->get_record('tool_certify_certifications', ['id' => $arguments['certificationid']], '*', MUST_EXIST);
        if ($program->id == $certification->programid1 || $program->id == $certification->programid2) {
            // Current value is always ok.
            return null;
        }

        return null;
    }
}
