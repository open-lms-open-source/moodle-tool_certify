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

use tool_certify\local\period;

/**
 * Edit user period.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class period_add extends \local_openlms\dialog_form {
    protected $arguments;

    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $certification = $this->_customdata['certification'];
        $assignment = $this->_customdata['assignment'];
        $user = $this->_customdata['user'];
        $context = $this->_customdata['context'];
        $now = time();

        $firstperiod = $DB->get_record('tool_certify_periods', ['certificationid' => $certification->id, 'userid' => $user->id, 'first' => 1]);

        $mform->addElement('static', 'userfullname', get_string('user'), fullname($user));

        $this->arguments = ['certificationid' => $certification->id];
        $settings = \tool_certify\local\certification::get_periods_settings($certification);

        $defaultdates = period::get_default_dates($certification, $user->id, []);

        \tool_certify\external\form_certification_periods_programid::add_form_element(
            $mform, $this->arguments, 'programid', get_string('program', 'enrol_programs'));
        if ($firstperiod) {
            $mform->setDefault('programid', $settings->programid2);
        } else {
            $mform->setDefault('programid', $settings->programid1);
        }
        $mform->addRule('programid', get_string('required'), 'required', null, 'client');

        $mform->addElement('date_time_selector', 'timewindowstart', get_string('windowstartdate', 'tool_certify'), ['optional' => false]);
        $mform->setDefault('timewindowstart', $defaultdates['timewindowstart']);

        $mform->addElement('date_time_selector', 'timewindowdue', get_string('windowduedate', 'tool_certify'), ['optional' => true]);
        $mform->setDefault('timewindowdue', $defaultdates['timewindowdue']);

        $mform->addElement('date_time_selector', 'timewindowend', get_string('windowenddate', 'tool_certify'), ['optional' => true]);
        $mform->setDefault('timewindowend', $defaultdates['timewindowend']);

        $mform->addElement('date_time_selector', 'timefrom', get_string('fromdate', 'tool_certify'), ['optional' => true]);
        $mform->setDefault('timefrom', $defaultdates['timefrom']);

        $mform->addElement('date_time_selector', 'timeuntil', get_string('untildate', 'tool_certify'), ['optional' => true]);
        $mform->setDefault('timeuntil', $defaultdates['timeuntil']);

        $mform->addElement('hidden', 'assignmentid');
        $mform->setType('assignmentid', PARAM_INT);
        $mform->setDefault('assignmentid', $assignment->id);

        $this->add_action_buttons(true, get_string('addperiod', 'tool_certify'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['timewindowdue'] && $data['timewindowdue'] <= $data['timewindowstart']) {
            $errors['timewindowdue'] = get_string('error');
        }
        if ($data['timewindowend'] && $data['timewindowend'] <= $data['timewindowstart']) {
            $errors['timewindowend'] = get_string('error');
        }
        if ($data['timewindowdue'] && $data['timewindowend'] && $data['timewindowend'] < $data['timewindowdue']) {
            $errors['timewindowend'] = get_string('error');
        }
        if ($data['timefrom'] && $data['timeuntil'] && $data['timefrom'] >= $data['timeuntil']) {
            $errors['timeuntil'] = get_string('error');
        }

        if (\tool_certify\external\form_certification_periods_programid::validate_form_value($this->arguments, $data['programid']) !== null) {
            $errors['programid'] = get_string('error');
        }

        return $errors;
    }
}
