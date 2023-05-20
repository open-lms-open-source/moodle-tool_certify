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
 * Delete user assignment.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_assignment_delete extends \local_openlms\dialog_form {
    protected function definition() {
        $mform = $this->_form;
        $assignment = $this->_customdata['assignment'];
        $user = $this->_customdata['user'];
        $context = $this->_customdata['context'];

        $mform->addElement('static', 'userfullname', get_string('user'), fullname($user));

        $mform->addElement('select', 'archived', get_string('archived', 'tool_certify'), [0 => get_string('no'), 1 => get_string('yes')]);
        $mform->freeze('archived');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $assignment->id);

        $this->add_action_buttons(true, get_string('deleteassignment', 'tool_certify'));

        $this->set_data($assignment);
    }

    public function validation($assignment, $files) {
        $errors = parent::validation($assignment, $files);

        return $errors;
    }
}
