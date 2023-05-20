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

namespace tool_certify\local\source;

use stdClass;

/**
 * Certification self assignment source.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class selfassignment extends base {
    /**
     * Return short type name of source, it is used in database to identify this source.
     *
     * NOTE: this must be unique and ite cannot be changed later
     *
     * @return string
     */
    public static function get_type(): string {
        return 'selfassignment';
    }

    /**
     * Always allow enabling in certification.
     *
     * @param stdClass $certification
     * @return bool
     */
    public static function is_new_allowed(stdClass $certification): bool {
        return true;
    }

    /**
     * Is it possible to manually edit user assignment?
     *
     * @param stdClass $certification
     * @param stdClass $source
     * @param stdClass $assignment
     * @return bool
     */
    public static function assignment_edit_supported(stdClass $certification, stdClass $source, stdClass $assignment): bool {
        return true;
    }

    /**
     * Is it possible to manually delete user assignment?
     *
     * @param stdClass $certification
     * @param stdClass $source
     * @param stdClass $assignment
     * @return bool
     */
    public static function assignment_delete_supported(stdClass $certification, stdClass $source, stdClass $assignment): bool {
        if (!$assignment->archived) {
            return false;
        }
        return true;
    }

    /**
     * Can the user request self-assignment?
     *
     * @param stdClass $certification
     * @param stdClass $source
     * @param int $userid
     * @param string|null $failurereason optional failure reason
     * @return bool
     */
    public static function can_user_request(\stdClass $certification, \stdClass $source, int $userid, ?string &$failurereason = null): bool {
        global $DB;

        if ($source->type !== 'selfassignment') {
            throw new \coding_exception('invalid source parameter');
        }

        if ($certification->archived) {
            return false;
        }

        if ($userid <= 0 || isguestuser($userid)) {
            return false;
        }

        if (!\tool_certify\local\catalogue::is_certification_visible($certification, $userid)) {
            return false;
        }

        if ($DB->record_exists('tool_certify_assignments', ['certificationid' => $certification->id, 'userid' => $userid])) {
            return false;
        }

        $data = (object)json_decode($source->datajson);
        if (isset($data->maxusers)) {
            // Any type of assignments.
            $count = $DB->count_records('tool_certify_assignments', ['certificationid' => $certification->id]);
            if ($count >= $data->maxusers) {
                $failurereason = get_string('source_selfassignment_maxusersreached', 'tool_certify');
                $failurereason = '<em><strong>' . $failurereason . '</strong></em>';
                return false;
            }
        }
        if (isset($data->allowsignup) && !$data->allowsignup) {
            return false;
        }

        return true;
    }

    /**
     * Returns list of actions available in certification catalogue.
     *
     * NOTE: This is intended mainly for students.
     *
     * @param stdClass $certification
     * @param stdClass $source
     * @return string[]
     */
    public static function get_catalogue_actions(\stdClass $certification, \stdClass $source): array {
        global $USER, $DB, $PAGE;

        $failurereason = null;
        if (!self::can_user_request($certification, $source, (int)$USER->id, $failurereason)) {
            if ($failurereason !== null) {
                return [$failurereason];
            } else {
                return [];
            }
        }

        $url = new \moodle_url('/admin/tool/certify/catalogue/source_selfassignment.php', ['sourceid' => $source->id]);
        $button = new \local_openlms\output\dialog_form\button($url, get_string('source_selfassignment_assign', 'tool_certify'));

        /** @var \local_openlms\output\dialog_form\renderer $dialogformoutput */
        $dialogformoutput = $PAGE->get_renderer('local_openlms', 'dialog_form');
        $button = $dialogformoutput->render($button);

        return [$button];
    }

    /**
     * Self-allocate current user to certification.
     *
     * @param int $certificationid
     * @param int $sourceid
     * @return stdClass
     */
    public static function signup(int $certificationid, int $sourceid): stdClass {
        global $DB, $USER;

        $certification = $DB->get_record('tool_certify_certifications', ['id' => $certificationid], '*', MUST_EXIST);
        $source = $DB->get_record('tool_certify_sources',
            ['id' => $sourceid, 'type' => static::get_type(), 'certificationid' => $certification->id], '*', MUST_EXIST);

        $user = $DB->get_record('user', ['id' => $USER->id, 'deleted' => 0], '*', MUST_EXIST);
        $assignment = $DB->get_record('tool_certify_assignments', ['certificationid' => $certification->id, 'userid' => $user->id]);
        if ($assignment) {
            // One assignment per certification only.
            return $assignment;
        }

        $assignment = self::assign_user($certification, $source, $user->id, []);

        \enrol_programs\local\source\certify::sync_certifications($certification->id, $user->id);
        \tool_certify\local\notification_manager::trigger_notifications($certification->id, $user->id);

        return $assignment;
    }

    /**
     * Decode extra source settings.
     *
     * @param stdClass $source
     * @return stdClass
     */
    public static function decode_datajson(stdClass $source): stdClass {
        $source->selfassignment_maxusers = '';
        $source->selfassignment_key = '';
        $source->selfassignment_allowsignup = 1;

        if (isset($source->datajson)) {
            $data = (object)json_decode($source->datajson);
            if (isset($data->maxusers) && $data->maxusers !== '') {
                $source->selfassignment_maxusers = (int)$data->maxusers;
            }
            if (isset($data->key)) {
                $source->selfassignment_key = $data->key;
            }
            if (isset($data->allowsignup)) {
                $source->selfassignment_allowsignup = (int)(bool)$data->allowsignup;
            }
        }

        return $source;
    }

    /**
     * Encode extra source settings.
     *
     * @param stdClass $formdata
     * @return string
     */
    public static function encode_datajson(stdClass $formdata): string {
        $data = ['maxusers' => null, 'key' => null, 'allowsignup' => 1];
        if (isset($formdata->selfassignment_maxusers)
            && trim($formdata->selfassignment_maxusers) !== ''
            && $formdata->selfassignment_maxusers >= 0) {

            $data['maxusers'] = (int)$formdata->selfassignment_maxusers;
        }
        if (isset($formdata->selfassignment_key)
            && trim($formdata->selfassignment_key) !== '') {

            $data['key'] = $formdata->selfassignment_key;
        }
        if (isset($formdata->selfassignment_allowsignup)) {
            $data['allowsignup'] = (int)(bool)$formdata->selfassignment_allowsignup;
        }
        return \tool_certify\local\util::json_encode($data);
    }

    /**
     * Render details about this enabled source in a certification management ui.
     *
     * @param stdClass $certification
     * @param stdClass|null $source
     * @return string
     */
    public static function render_status_details(stdClass $certification, ?stdClass $source): string {
        global $DB;

        $result = parent::render_status_details($certification, $source);

        if ($source) {
            $data = (object)json_decode($source->datajson);
            if (isset($data->key)) {
                $result .= '; ' . get_string('source_selfassignment_keyrequired', 'tool_certify');
            }
            if (isset($data->maxusers)) {
                $count = $DB->count_records('tool_certify_assignments', ['certificationid' => $certification->id, 'sourceid' => $source->id]);
                $a = (object)['count' => $count, 'max' => $data->maxusers];
                $result .= '; ' . get_string('source_selfassignment_maxusers_status', 'tool_certify', $a);
            }
            if (!isset($data->allowsignup) || $data->allowsignup) {
                $result .= '; ' . get_string('source_selfassignment_signupallowed', 'tool_certify');
            } else {
                $result .= '; ' . get_string('source_selfassignment_signupnotallowed', 'tool_certify');
            }
        }

        return $result;
    }
}

