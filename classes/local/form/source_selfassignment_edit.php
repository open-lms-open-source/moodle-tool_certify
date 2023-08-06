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
 * Edit certification self assignment settings.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source_selfassignment_edit extends \local_openlms\dialog_form {
    protected function definition() {
        $mform = $this->_form;
        $context = $this->_customdata['context'];
        $source = $this->_customdata['source'];
        $certification = $this->_customdata['certification'];

        $mform->addElement('select', 'enable', get_string('active'), ['1' => get_string('yes'), '0' => get_string('no')]);
        $mform->setDefault('enable', $source->enable);
        if ($source->hasassignments) {
            $mform->hardFreeze('enable');
        }

        $mform->addElement('select', 'selfassignment_allowsignup', get_string('source_selfassignment_allowsignup', 'tool_certify'),
            ['1' => get_string('yes'), '0' => get_string('no')]);
        $mform->setDefault('selfassignment_allowsignup', 1);
        $mform->hideIf('selfassignment_allowsignup', 'enable', 'eq', '0');

        $mform->addElement('passwordunmask', 'selfassignment_key', get_string('source_selfassignment_key', 'tool_certify'));
        $mform->setDefault('selfassignment_key', $source->selfassignment_key);
        $mform->hideIf('selfassignment_key', 'enable', 'eq', '0');

        $mform->addElement('text', 'selfassignment_maxusers', get_string('source_selfassignment_maxusers', 'tool_certify'), 'size="8"');
        $mform->setType('selfassignment_maxusers', PARAM_RAW);
        $mform->setDefault('selfassignment_maxusers', $source->selfassignment_maxusers);
        $mform->hideIf('selfassignment_maxusers', 'enable', 'eq', '0');

        $mform->addElement('hidden', 'certificationid');
        $mform->setType('certificationid', PARAM_INT);
        $mform->setDefault('certificationid', $certification->id);

        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_ALPHANUMEXT);
        $mform->setDefault('type', $source->type);

        $this->add_action_buttons(true, get_string('update'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['selfassignment_maxusers'] !== '') {
            if (!is_number($data['selfassignment_maxusers'])) {
                $errors['selfassignment_maxusers'] = get_string('error');
            } else if ($data['selfassignment_maxusers'] < 0) {
                $errors['selfassignment_maxusers'] = get_string('error');
            }
        }

        return $errors;
    }
}
