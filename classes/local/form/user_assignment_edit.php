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

namespace tool_certify\local\form;

/**
 * Edit user assignment.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_assignment_edit extends \local_openlms\dialog_form {
    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $certification = $this->_customdata['certification'];
        $assignment = $this->_customdata['assignment'];
        $user = $this->_customdata['user'];
        $context = $this->_customdata['context'];

        $mform->addElement('static', 'userfullname', get_string('user'), fullname($user));

        $mform->addElement('advcheckbox', 'archived', get_string('archived', 'tool_certify'), ' ');
        $mform->setDefault('archived', $assignment->archived);

        if ($certification->recertify !== null) {
            $stoprecertify = !$DB->record_exists('tool_certify_periods', [
                'certificationid' => $assignment->certificationid,
                'userid' => $assignment->userid,
                'recertifiable' => 1,
            ]);

            $mform->addElement('advcheckbox', 'stoprecertify', get_string('stoprecertify', 'tool_certify'), ' ');
            $mform->setDefault('stoprecertify', $stoprecertify);
        }

        $mform->addElement('date_time_selector', 'timecertifieduntil', get_string('certifieduntiltemporary', 'tool_certify'), ['optional' => true]);
        $mform->setDefault('timecertifieduntil', $assignment->timecertifieduntil);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $assignment->id);

        $this->add_action_buttons(true, get_string('updateassignment', 'tool_certify'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        return $errors;
    }
}
