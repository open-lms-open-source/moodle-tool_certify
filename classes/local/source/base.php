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

use tool_certify\local\assignment;

use stdClass;

/**
 * Certification source abstraction.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Return short type name of source, it is used in database to identify this source.
     *
     * NOTE: this must be unique and ite cannot be changed later
     *
     * @return string
     */
    public static function get_type(): string {
        throw new \coding_exception('cannot be called on base class');
    }

    /**
     * Returns name of the source.
     *
     * @return string
     */
    public static function get_name(): string {
        $type = static::get_type();
        return get_string('source_' . $type, 'tool_certify');
    }

    /**
     * Can a new source of this type be added to certifications?
     *
     * NOTE: Existing enabled sources in certifications cannot be deleted/hidden
     * if there are any assigned users to certification.
     *
     * @param stdClass $certification
     * @return bool
     */
    public static function is_new_allowed(stdClass $certification): bool {
        return true;
    }

    /**
     * Can existing source of this type be updated or deleted to certifications?
     *
     * NOTE: Existing enabled sources in certifications cannot be deleted/hidden
     * if there are any assigned users to certification.
     *
     * @param stdClass $certification
     * @return bool
     */
    public static function is_update_allowed(stdClass $certification): bool {
        return true;
    }

    /**
     * Make sure users are assigned properly.
     *
     * This is expected to be called from cron and when
     * certification assignment settings are updated.
     *
     * @param int|null $certificationid
     * @param int|null $userid
     * @return bool true if anything updated
     */
    public static function fix_assignments(?int $certificationid, ?int $userid): bool {
        return false;
    }

    /**
     * Return extra tab for managing the source data in certification.
     *
     * @param stdClass $certification
     * @return array
     */
    public static function get_extra_management_tabs(stdClass $certification): array {
        return [];
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
        return false;
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
        return false;
    }

    /**
     * assignment related buttons for certification management page.
     *
     * @param stdClass $certification
     * @param stdClass $source
     * @return array
     */
    public static function get_management_certification_users_buttons(stdClass $certification, stdClass $source): array {
        return [];
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
    public static function get_catalogue_actions(stdClass $certification, stdClass $source): array {
        return [];
    }

    /**
     * Update source details.
     *
     * @param stdClass $data
     * @return stdClass|null assignment source
     */
    final public static function update_source(stdClass $data): ?stdClass {
        global $DB;

        /** @var base[] $sourceclasses */
        $sourceclasses = assignment::get_source_classes();
        if (!isset($sourceclasses[$data->type])) {
            throw new \coding_exception('Invalid source type');
        }
        $sourcetype = $data->type;
        $sourceclass = $sourceclasses[$sourcetype];

        $certification = $DB->get_record('tool_certify_certifications', ['id' => $data->certificationid], '*', MUST_EXIST);
        $source = $DB->get_record('tool_certify_sources', ['type' => $sourcetype, 'certificationid' => $certification->id]);
        if ($source) {
            $oldsource = clone($source);
        } else {
            $source = null;
            $oldsource = null;
        }
        if ($source && $source->type !== $data->type) {
            throw new \coding_exception('Invalid source type');
        }

        if ($data->enable) {
            if ($source) {
                $source->datajson = $sourceclass::encode_datajson($data);
                $source->auxint1 = $data->auxint1 ?? null;
                $source->auxint2 = $data->auxint2 ?? null;
                $source->auxint3 = $data->auxint3 ?? null;
                $DB->update_record('tool_certify_sources', $source);
            } else {
                $source = new stdClass();
                $source->certificationid = $data->certificationid;
                $source->type = $sourcetype;
                $source->datajson = $sourceclass::encode_datajson($data);
                $source->auxint1 = $data->auxint1 ?? null;
                $source->auxint2 = $data->auxint2 ?? null;
                $source->auxint3 = $data->auxint3 ?? null;
                $source->id = $DB->insert_record('tool_certify_sources', $source);
            }
            $source = $DB->get_record('tool_certify_sources', ['id' => $source->id], '*', MUST_EXIST);
        } else {
            if ($source) {
                if ($DB->record_exists('tool_certify_assignments', ['sourceid' => $source->id])) {
                    throw new \coding_exception('Cannot delete source with assignments');
                }
                $DB->delete_records('tool_certify_requests', ['sourceid' => $source->id]);
                $DB->delete_records('tool_certify_src_cohorts', ['sourceid' => $source->id]);
                $DB->delete_records('tool_certify_sources', ['id' => $source->id]);
                $source = null;
            }
        }
        $sourceclass::after_update($oldsource, $data, $source);

        \tool_certify\local\certification::make_snapshot($certification->id, 'update_source');

        $sourceclass::fix_assignments($certification->id, null);
        \enrol_programs\local\source\certify::sync_certifications($certification->id, null);

        return $source;
    }

    /**
     * assign user to certification.
     *
     * @param stdClass $certification
     * @param stdClass $source
     * @param int $userid
     * @param array $sourcedata
     * @param array $dateoverrides
     * @return stdClass user assignment record
     */
    final protected static function assign_user(stdClass $certification, stdClass $source, int $userid, array $sourcedata, array $dateoverrides = []): stdClass {
        global $DB;

        if ($userid <= 0 || isguestuser($userid)) {
            throw new \coding_exception('Only real users can be assigned to certifications');
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'confirmed' => 1], '*', MUST_EXIST);

        $now = time();

        $record = new stdClass();
        $record->certificationid = $certification->id;
        $record->userid = $userid;
        $record->sourceid = $source->id;
        $record->sourcedatajson = \tool_certify\local\util::json_encode($sourcedata);
        $record->archived = 0;
        $record->timecertifieduntil = null;
        if (isset($dateoverrides['timecertifieduntil']) && $dateoverrides['timecertifieduntil'] > 0) {
            $record->timecertifieduntil = $dateoverrides['timecertifieduntil'];
        }
        $record->evidencejson = \tool_certify\local\util::json_encode([]);
        $record->timecreated = $now;

        $trans = $DB->start_delegated_transaction();

        $record->id = $DB->insert_record('tool_certify_assignments', $record);
        $assignment = $DB->get_record('tool_certify_assignments', ['id' => $record->id], '*', MUST_EXIST);

        \tool_certify\local\period::add_first($assignment, $dateoverrides);

        assignment::make_snapshot($assignment->certificationid, $assignment->userid, 'assignment');

        $trans->allow_commit();

        $event = \tool_certify\event\user_assigned::create_from_assignment($assignment, $certification);
        $event->trigger();

        \tool_certify\local\notification\assignment::notify_now($user, $certification, $source, $assignment);

        return $assignment;
    }

    /**
     * Unassign user from a certification.
     *
     * @param stdClass $certification
     * @param stdClass $source
     * @param stdClass $assignment
     * @return void
     */
    public static function unassign_user(stdClass $certification, stdClass $source, stdClass $assignment): void {
        global $DB;

        if (static::get_type() !== $source->type || $certification->id != $assignment->certificationid || $certification->id != $source->certificationid) {
            throw new \coding_exception('invalid parameters');
        }
        $user = $DB->get_record('user', ['id' => $assignment->userid]);

        $trans = $DB->start_delegated_transaction();

        if ($user) {
            \tool_certify\local\notification\unassignment::notify_now($user, $certification, $source, $assignment);
        }
        \tool_certify\local\notification_manager::delete_assignment_notifications($assignment);

        $periods = $DB->get_records('tool_certify_periods', ['certificationid' => $assignment->certificationid, 'userid' => $assignment->userid]);
        foreach ($periods as $period) {
            if ($period->certificateissueid) {
                \tool_certify\local\certificate::revoke($period->id);
            }
        }

        $DB->delete_records('tool_certify_periods',
            ['certificationid' => $assignment->certificationid, 'userid' => $assignment->userid]);
        $DB->delete_records('tool_certify_assignments', ['id' => $assignment->id]);

        $trans->allow_commit();

        $event = \tool_certify\event\user_unassigned::create_from_assignment($assignment, $certification);
        $event->trigger();
    }

    /**
     * Decode extra source settings.
     *
     * @param stdClass $source
     * @return stdClass
     */
    public static function decode_datajson(stdClass $source): stdClass {
        // Override if necessary.
        return $source;
    }

    /**
     * Encode extra source settings.
     * @param stdClass $formdata
     * @return string
     */
    public static function encode_datajson(stdClass $formdata): string {
        // Override if necessary.
        return \tool_certify\local\util::json_encode([]);
    }

    /**
     * Callback method for source updates.
     *
     * @param stdClass|null $oldsource
     * @param stdClass $data
     * @param stdClass|null $source
     * @return void
     */
    public static function after_update(?stdClass $oldsource, stdClass $data, ?stdClass $source): void {
        // Override if necessary.
    }

    /**
     * Returns class for editing of source settings in certification.
     *
     * @return string
     */
    public static function get_edit_form_class(): string {
        $type = static::get_type();
        $class = "tool_certify\\local\\form\source_{$type}_edit";
        if (!class_exists($class)) {
            throw new \coding_exception('source edit class not found, either override get_edit_form_class or add class: ' . $class);
        }
        return $class;
    }

    /**
     * Render details about this enabled source in a certification management ui.
     *
     * @param stdClass $certification
     * @param stdClass|null $source
     * @return string
     */
    public static function render_status_details(stdClass $certification, ?stdClass $source): string {
        return ($source ? get_string('active') : get_string('inactive'));
    }

    /**
     * Render basic status of the certification source.
     *
     * @param stdClass $certification
     * @param stdClass|null $source
     * @return string
     */
    public static function render_status(stdClass $certification, ?stdClass $source): string {
        global $PAGE;

        $type = static::get_type();

        if ($source && $source->type !== $type) {
            throw new \coding_exception('Invalid source type');
        }

        /** @var \local_openlms\output\dialog_form\renderer $dialogformoutput */
        $dialogformoutput = $PAGE->get_renderer('local_openlms', 'dialog_form');

        $result = static::render_status_details($certification, $source);

        $context = \context::instance_by_id($certification->contextid);
        if (has_capability('tool/certify:edit', $context) && static::is_update_allowed($certification)) {
            $label = get_string('updatesource', 'tool_certify', static::get_name());
            $editurl = new \moodle_url('/admin/tool/certify/management/certification_source_edit.php', ['certificationid' => $certification->id, 'type' => $type]);
            $editbutton = new \local_openlms\output\dialog_form\icon($editurl, 'i/settings', $label);
            $editbutton->set_dialog_name(static::get_name());
            $result .= ' ' . $dialogformoutput->render($editbutton);
        }

        return $result;
    }


    /**
     * Returns the user who is responsible for assignment.
     *
     * Override if plugin knows anybody better than admin.
     *
     * @param stdClass $certification
     * @param stdClass $source
     * @param stdClass $assignment
     * @return stdClass user record
     */
    public static function get_assigner(stdClass $certification, stdClass $source, stdClass $assignment): stdClass {
        // NOTE: tweak this if there is a need for tenant specific sender.
        return get_admin();
    }
}

