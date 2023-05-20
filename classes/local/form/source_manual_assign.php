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
 * assign users and cohorts manually.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source_manual_assign extends \local_openlms\dialog_form {
    /** @var array $arguments for WS call to get candidate users */
    protected $arguments;
    protected $dueoptional = true;
    protected function definition() {
        $mform = $this->_form;
        $certification = $this->_customdata['certification'];
        $source = $this->_customdata['source'];
        $context = $this->_customdata['context'];

        $settings = \tool_certify\local\certification::get_periods_settings($certification);

        $this->arguments = ['certificationid' => $certification->id];
        \tool_certify\external\form_source_manual_assign_users::add_form_element(
            $mform, $this->arguments, 'users', get_string('users'));

        $options = ['contextid' => $context->id, 'multiple' => false];
        $mform->addElement('cohort', 'cohortid', get_string('cohort', 'cohort'), $options);

        $now = time();
        $mform->addElement('date_time_selector', 'timewindowstart', get_string('windowstartdate', 'tool_certify'),
            ['optional' => false]);
        $mform->setDefault('timewindowstart', $now);

        if ($settings->valid1 === 'windowdue'
            || $settings->windowend1 === 'windowdue'
            || $settings->expiration1 === 'windowdue'
        ) {
            $this->dueoptional = false;
        }
        $mform->addElement('date_time_selector', 'timewindowdue', get_string('windowduedate', 'tool_certify'),
            ['optional' => $this->dueoptional]);
        if (!$this->dueoptional) {
            $mform->addRule('timewindowdue', get_string('required'), 'required', null, 'client');
        }
        if ($settings->due1 !== null) {
            $mform->setDefault('timewindowdue', $now + $settings->due1);
        }

        $mform->addElement('hidden', 'certificationid');
        $mform->setType('certificationid', PARAM_INT);
        $mform->setDefault('certificationid', $source->certificationid);

        $mform->addElement('hidden', 'sourceid');
        $mform->setType('sourceid', PARAM_INT);
        $mform->setDefault('sourceid', $source->id);

        $this->add_action_buttons(true, get_string('source_manual_assignusers', 'tool_certify'));
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $context = $this->_customdata['context'];

        if ($data['cohortid']) {
            $cohort = $DB->get_record('cohort', ['id' => $data['cohortid']], '*', MUST_EXIST);
            $cohortcontext = \context::instance_by_id($cohort->contextid);
            if (!$cohort->visible && !has_capability('moodle/cohort:view', $cohortcontext)) {
                $errors['cohortid'] = get_string('error');
            }
            if (\tool_certify\local\tenant::is_active()) {
                $tenantid = \tool_olms_tenant\tenants::get_context_tenant_id($context);
                if ($tenantid) {
                    $cohorttenantid = \tool_olms_tenant\tenants::get_context_tenant_id($cohortcontext);
                    if ($cohorttenantid && $cohorttenantid != $tenantid) {
                        $errors['cohortid'] = get_string('error');
                    }
                }
            }
        }

        if ($data['users']) {
            foreach ($data['users'] as $userid) {
                $error = \tool_certify\external\form_source_manual_assign_users::validate_form_value(
                    $this->arguments, $userid, $context);
                if ($error !== null) {
                    $errors['users'] = $error;
                    break;
                }
            }
        }

        return $errors;
    }

}
