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
 * Delete certification.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class certification_delete extends \local_openlms\dialog_form {
    protected function definition() {
        $mform = $this->_form;
        $certification = $this->_customdata['certification'];

        $mform->addElement('static', 'fullname', get_string('certificationname', 'tool_certify'), format_string($certification->fullname));

        $mform->addElement('static', 'idnumber', get_string('idnumber'), format_string($certification->idnumber));

        $mform->addElement('select', 'archived', get_string('archived', 'tool_certify'), [0 => get_string('no'), 1 => get_string('yes')]);
        $mform->freeze('archived');
        $mform->setDefault('archived', $certification->archived);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $certification->id);

        $this->add_action_buttons(true, get_string('deletecertification', 'tool_certify'));
    }
}
