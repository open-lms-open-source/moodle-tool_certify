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
 * Provides list of candidates for certification assignment.
 *
 * @package     tool_certify
 * @copyright   2023 Open LMS (https://www.openlms.net/)
 * @author      Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class form_source_manual_assign_users extends \local_openlms\external\form_autocomplete_field {
    /**
     * True means returned field data is array, false means value is scalar.
     *
     * @return bool
     */
    public static function is_multi_select_field(): bool {
        return true;
    }

    /**
     * Describes the external function arguments.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW, 'The search query', VALUE_REQUIRED),
            'certificationid' => new external_value(PARAM_INT, 'Certification id', VALUE_REQUIRED),
        ]);
    }

    /**
     * Finds users with the identity matching the given query.
     *
     * @param string $query The search request.
     * @param int $certificationid The certification.
     * @return array
     */
    public static function execute(string $query, int $certificationid): array {
        global $DB, $CFG, $OUTPUT;

        $params = self::validate_parameters(self::execute_parameters(),
            ['query' => $query, 'certificationid' => $certificationid]);
        $query = $params['query'];
        $certificationid = $params['certificationid'];

        $certification = $DB->get_record('tool_certify_certifications', ['id' => $certificationid], '*', MUST_EXIST);

        // Validate context.
        $context = \context::instance_by_id($certification->contextid);
        self::validate_context($context);
        require_capability('tool/certify:assign', $context);

        $hasviewfullnames = has_capability('moodle/site:viewfullnames', $context);

        $fields = \core_user\fields::for_name()->with_identity($context, false);
        $extrafields = $fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]);

        list($searchsql, $searchparams) = users_search_sql($query, 'usr', true, $extrafields);
        list($sortsql, $sortparams) = users_order_by_sql('usr', $query, $context);
        $params = array_merge($searchparams, $sortparams);
        $params['certificationid'] = $certificationid;

        $tenantjoin = "";
        $tenantwhere = "";
        if (\tool_certify\local\tenant::is_active()) {
            $tenantid = \tool_olms_tenant\tenants::get_context_tenant_id($context);
            if ($tenantid) {
                $tenantjoin .= " LEFT JOIN {tool_olms_tenant_user} tu ON tu.userid = usr.id";
                $tenantwhere .= " AND (tu.id IS NULL OR tu.tenantid = :tenantid)";
                $params['tenantid'] = $tenantid;
            }
            $currenttenantid = \tool_olms_tenant\tenancy::get_tenant_id();
            if ($currenttenantid) {
                $tenantjoin .= " JOIN {tool_olms_tenant_user} ctu ON ctu.userid = usr.id";
                $tenantwhere .= " AND ctu.tenantid = :currenttenantid";
                $params['currenttenantid'] = $currenttenantid;
            }
        }

        $additionalfields = $fields->get_sql('usr')->selects;
        $sqlquery = <<<SQL
            SELECT usr.id {$additionalfields}
              FROM {user} usr
         LEFT JOIN {tool_certify_assignments} pa ON (pa.userid = usr.id AND pa.certificationid = :certificationid)
         {$tenantjoin}     
             WHERE pa.id IS NULL AND {$searchsql} {$tenantwhere}
                   AND usr.deleted = 0 AND usr.confirmed = 1
          ORDER BY {$sortsql}
SQL;

        $rs = $DB->get_recordset_sql($sqlquery, $params, 0, $CFG->maxusersperpage + 1);

        $count = 0;
        $list = [];
        $notice = null;

        foreach ($rs as $record) {
            $count++;
            if ($count > $CFG->maxusersperpage) {
                $notice = get_string('toomanyuserstoshow', 'core', $CFG->maxusersperpage);
                break;
            }

            $user = (object) [
                'id' => $record->id,
                'fullname' => fullname($record, $hasviewfullnames),
                'extrafields' => [],
            ];
            foreach ($extrafields as $extrafield) {
                // Sanitize the extra fields to prevent potential XSS exploit.
                $user->extrafields[] = (object) [
                    'name' => $extrafield,
                    'value' => s($record->$extrafield)
                ];
            }
            $list[] = [
                'value' => $record->id,
                'label' => clean_text($OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user)),
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
            global $OUTPUT, $DB;

            $certification = $DB->get_record('tool_certify_certifications', ['id' => $arguments['certificationid']], '*', MUST_EXIST);
            $context = \context::instance_by_id($certification->contextid);

            $error = ''; // This is not pretty, but luckily there is a low chance this will happen.
            if (static::validate_form_value($arguments, $value, $context) !== null) {
                $error = ' (' . get_string('error') .')';
            }

            $fields = \core_user\fields::for_name()->with_identity($context, false);
            $record = \core_user::get_user($value, 'id' . $fields->get_sql()->selects, MUST_EXIST);

            $user = (object) [
                'id' => $record->id,
                'fullname' => fullname($record, has_capability('moodle/site:viewfullnames', $context)),
                'extrafields' => [],
            ];

            foreach ($fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]) as $extrafield) {
                $user->extrafields[] = (object) [
                    'name' => $extrafield,
                    'value' => s($record->$extrafield),
                ];
            }

            return $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user) . $error;
        };
    }

    /**
     * @param array $arguments
     * @param $value
     * @return string|null error message, NULL means value is ok
     */
    public static function validate_form_value(array $arguments, $value, \context $context): ?string {
        global $DB;

        $user = $DB->get_record('user', ['id' => $value, 'deleted' => 0, 'confirmed' => 1]);
        if (!$user) {
            return get_string('error');
        }

        if ($DB->record_exists('tool_certify_assignments', ['certificationid' => $arguments['certificationid'], 'userid' => $user->id])) {
            return get_string('error');
        }

        if (\tool_certify\local\tenant::is_active()) {
            $tenantid = \tool_olms_tenant\tenants::get_context_tenant_id($context);
            if ($tenantid) {
                $usertenantid = \tool_olms_tenant\tenant_users::get_user_tenant_id($user->id);
                if ($usertenantid && $usertenantid != $tenantid) {
                    return get_string('error');
                }
            }
        }

        return null;
    }
}