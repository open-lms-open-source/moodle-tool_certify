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
 * Delete revoked user period.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class period_delete extends \local_openlms\dialog_form {
    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $period = $this->_customdata['period'];
        $user = $this->_customdata['user'];
        $context = $this->_customdata['context'];

        $mform->addElement('static', 'userfullname', get_string('user'), fullname($user));

        $mform->addElement('date_time_selector', 'timewindowstart', get_string('windowstartdate', 'tool_certify'), ['optional' => false]);
        $mform->setDefault('timewindowstart', $period->timewindowstart);
        $mform->hardFreeze('timewindowstart');

        $mform->addElement('date_time_selector', 'timerevoked', get_string('revokeddate', 'tool_certify'), ['optional' => true]);
        $mform->setDefault('timerevoked', $period->timerevoked);
        $mform->hardFreeze('timerevoked');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $period->id);

        $this->add_action_buttons(true, get_string('deleteperiod', 'tool_certify'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        return $errors;
    }
}
