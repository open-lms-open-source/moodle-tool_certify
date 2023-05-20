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
 * certifications notification manager.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_manager extends \local_openlms\notification\manager {
    /**
     * Returns list of all notifications in plugin.
     *
     * @return array of PHP class names with notificationtype as keys
     */
    public static function get_all_types(): array {
        // Note: order here affects cron task execution.
        return [
            'assignment' => notification\assignment::class,
            'valid' => notification\valid::class,
            'unassignment' => notification\unassignment::class,
        ];
    }

    /**
     * Returns list of candidate types for adding of new notifications.
     *
     * @return array of type names with notificationtype as keys
     */
    public static function get_candidate_types(int $instanceid): array {
        global $DB;

        $types = self::get_all_types();

        $existing = $DB->get_records('local_openlms_notifications',
            ['component' => 'tool_certify', 'instanceid' => $instanceid]);
        foreach ($existing as $notification) {
            unset($types[$notification->notificationtype]);
        }

        /** @var class-string<notification\base> $classname */
        foreach ($types as $type => $classname) {
            $types[$type] = $classname::get_name();
        }

        return $types;
    }

    /**
     * Returns context of instance for notifications.
     *
     * @param int $instanceid
     * @return null|\context
     */
    public static function get_instance_context(int $instanceid): ?\context {
        global $DB;

        $certification = $DB->get_record('tool_certify_certifications', ['id' => $instanceid]);
        if (!$certification) {
            return null;
        }

        return \context::instance_by_id($certification->contextid);
    }

    /**
     * Can the current user view instance notifications?
     *
     * @param int $instanceid
     * @return bool
     */
    public static function can_view(int $instanceid): bool {
        global $DB;
        $certification = $DB->get_record('tool_certify_certifications', ['id' => $instanceid]);
        if (!$certification) {
            return false;
        }

        $context = \context::instance_by_id($certification->contextid);
        return has_capability('tool/certify:view', $context);
    }

    /**
     * Can the current user add/update/delete instance notifications?
     *
     * @param int $instanceid
     * @return bool
     */
    public static function can_manage(int $instanceid): bool {
        global $DB;
        $certification = $DB->get_record('tool_certify_certifications', ['id' => $instanceid]);
        if (!$certification) {
            return false;
        }

        $context = \context::instance_by_id($certification->contextid);
        return has_capability('tool/certify:edit', $context);
    }

    /**
     * Returns name of instance for notifications.
     *
     * @param int $instanceid
     * @return string|null
     */
    public static function get_instance_name(int $instanceid): ?string {
        global $DB;
        $certification = $DB->get_record('tool_certify_certifications', ['id' => $instanceid]);
        if (!$certification) {
            return null;
        }
        return format_string($certification->fullname);
    }

    /**
     * Returns url of UI that shows all plugin notifications for given instance id.
     *
     * @param int $instanceid
     * @return \moodle_url|null
     */
    public static function get_instance_management_url(int $instanceid): ?\moodle_url {
        global $DB;
        $certification = $DB->get_record('tool_certify_certifications', ['id' => $instanceid]);
        if (!$certification) {
            return null;
        }

        $context = \context::instance_by_id($certification->contextid);
        if (!has_capability('tool/certify:view', $context)) {
            return null;
        }

        return new \moodle_url('/admin/tool/certify/management/certification_notifications.php', ['id' => $certification->id]);
    }

    /**
     * Set up notification/view.php page.
     *
     * @param \stdClass $notification
     * @return void
     */
    public static function setup_view_page(\stdClass $notification): void {
        global $PAGE, $DB, $OUTPUT;

        $certification = $DB->get_record('tool_certify_certifications', ['id' => $notification->instanceid]);
        if (!$certification) {
            return;
        }

        $context = \context::instance_by_id($certification->contextid);
        $manageurl = self::get_instance_management_url($notification->instanceid);

        management::setup_certification_page($manageurl, $context, $certification);
        $PAGE->set_url('/local/openlms/notification/view.php', ['id' => $notification->id]);

        echo $OUTPUT->header();

        /** @var \tool_certify\output\management\renderer $managementoutput */
        $managementoutput = $PAGE->get_renderer('tool_certify', 'management');

        echo $managementoutput->render_management_certification_tabs($certification, 'notifications');
    }

    /**
     * Send notifications.
     *
     * @param int|null $certificationid
     * @param int|null $userid
     * @return void
     */
    public static function trigger_notifications(?int $certificationid, ?int $userid): void {
        global $DB;

        if (!enrol_is_enabled('programs')) {
            return;
        }

        $certification = null;
        if ($certificationid) {
            $certification = $DB->get_record('tool_certify_certifications', ['id' => $certificationid], '*', MUST_EXIST);
            if ($certification->archived) {
                return;
            }
        }

        $user = null;
        if ($userid) {
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
            if ($user->deleted || $user->suspended) {
                return;
            }
        }

        $types = self::get_all_types();

        /** @var class-string<notification\base> $classname */
        foreach ($types as $classname) {
            $classname::notify_users($certification, $user);
        }
    }

    /**
     * To be called when deleting certification assignment.
     *
     * @param \stdClass $assignment
     * @return void
     */
    public static function delete_assignment_notifications(\stdClass $assignment) {
        global $DB;

        $notifications = $DB->get_records('local_openlms_notifications',
            ['component' => 'tool_certify', 'instanceid' => $assignment->certificationid]);
        foreach ($notifications as $notification) {
            /** @var class-string<notification\base> $classname */
            $classname = self::get_classname($notification->notificationtype);
            if (!$classname) {
                continue;
            }
            $classname::delete_assignment_notifications($assignment);
        }
    }

    /**
     * To be called when deleting certification period.
     *
     * @param \stdClass $period
     * @return void
     */
    public static function delete_period_notifications(\stdClass $period) {
        global $DB;

        $notifications = $DB->get_records('local_openlms_notifications',
            ['component' => 'tool_certify', 'instanceid' => $period->certificationid]);
        foreach ($notifications as $notification) {
            /** @var class-string<notification\base> $classname */
            $classname = self::get_classname($notification->notificationtype);
            if (!$classname) {
                continue;
            }
            $classname::delete_period_notifications($period);
        }
    }

    /**
     * To be called when deleting certification.
     *
     * @param \stdClass $certification
     * @return void
     */
    public static function delete_certification_notifications(\stdClass $certification) {
        global $DB;

        $notifications = $DB->get_records('local_openlms_notifications',
            ['component' => 'tool_certify', 'instanceid' => $certification->id]);
        foreach ($notifications as $notification) {
            \local_openlms\notification\util::notification_delete($notification->id);
        }
    }

    /**
     * Returns last notification time for given user in certification.
     *
     * @param int $userid
     * @param int $certificationid
     * @param string $notificationtype
     * @return int|null
     */
    public static function get_timenotified(int $userid, int $certificationid, string $notificationtype): ?int {
        global $DB;

        $params = ['certificationid' => $certificationid, 'userid' => $userid, 'type' => $notificationtype];
        $sql = "SELECT MAX(un.timenotified)
                  FROM {tool_certify_assignments} pa
                  JOIN {tool_certify_certifications} p ON p.id = pa.certificationid
                  JOIN {local_openlms_notifications} n
                       ON n.component = 'tool_certify' AND n.notificationtype = :type AND n.instanceid = p.id
                  JOIN {local_openlms_user_notified} un
                       ON un.notificationid = n.id AND un.userid = pa.userid AND un.otherid1 = pa.id
                 WHERE p.id = :certificationid AND pa.userid = :userid";
        return $DB->get_field_sql($sql, $params);
    }
}
