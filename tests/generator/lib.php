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

use tool_certify\local\certification;

/**
 * Certification generator class.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certify_generator extends component_generator_base {
    /**
     * @var int Framework count.
     */
    private $certificationcount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        parent::reset();
        $this->certificationcount = 0;
    }

    /**
     * Create certification.
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_certification($record = null): stdClass {
        global $DB;

        $this->certificationcount++;

        $record = (object)(array)$record;

        if (!isset($record->fullname)) {
            $record->fullname = 'Certification ' . $this->certificationcount;
        }
        if (!isset($record->idnumber)) {
            $record->idnumber = 'crt' . $this->certificationcount;
        }
        if (!isset($record->description)) {
            $record->description = '';
        }
        if (!isset($record->descriptionformat)) {
            $record->descriptionformat = FORMAT_HTML;
        }
        if (!isset($record->contextid)) {
            if (!empty($record->category)) {
                $category = $DB->get_record('course_categories', ['name' => $record->category], '*', MUST_EXIST);
                $context = context_coursecat::instance($category->id);
                $record->contextid = $context->id;
            } else {
                $syscontext = \context_system::instance();
                $record->contextid = $syscontext->id;
            }
        }
        unset($record->category);

        if (!empty($record->program1)) {
            $program = $DB->get_record('enrol_programs_programs', ['idnumber' => $record->program1], '*', MUST_EXIST);
            $record->programid1 = $program->id;
        }
        unset($record->program1);
        if (!empty($record->program2)) {
            $program = $DB->get_record('enrol_programs_programs', ['idnumber' => $record->program2], '*', MUST_EXIST);
            $record->programid2 = $program->id;
        }
        unset($record->program2);

        $sources = [];
        if (!empty($record->sources)) {
            if (is_array($record->sources)) {
                $sources = $record->sources;
            }
            if (is_string($record->sources)) {
                foreach (explode(',', $record->sources) as $type) {
                    $type = trim($type);
                    if ($type === '') {
                        continue;
                    }
                    $sources[$type] = [];
                }
            }
        }
        unset($record->sources);

        $cohorts = empty($record->cohorts) ? [] : $record->cohorts;
        unset($record->cohorts);

        $periodsdefauls = (array)certification::get_periods_defaults();
        $periods = [];
        foreach ((array)$record as $key => $value) {
            if (!str_starts_with($key, 'periods_')) {
                continue;
            }
            unset($record->$key);
            $k = preg_replace('/^periods_/', '', $key);
            if (!array_key_exists($k, $periodsdefauls)) {
                continue;
            }
            $periods[$k] = $value;
        }
        $certification = certification::add_certification($record);

        if ($cohorts) {
            $cohortids = [];
            if (!is_array($cohorts)) {
                $cohorts = explode(',', $cohorts);
            }
            foreach ($cohorts as $cohort) {
                $cohort = trim($cohort);
                if (is_number($cohort)) {
                    $cohortids[] = $cohort;
                } else {
                    $record = $DB->get_record('cohort', ['name' => $cohort], '*', MUST_EXIST);
                    $cohortids[] = $record->id;
                }

            }
            certification::update_certification_visibility((object)['id' => $certification->id, 'public' => $certification->public, 'cohorts' => $cohortids]);
        }

        if ($periods) {
            $periods['id'] = $certification->id;
            $certification = certification::update_certification_settings((object)$periods);
        }

        foreach ($sources as $source => $data) {
            $data['enable'] = 1;
            $data['certificationid'] = $certification->id;
            $data['type'] = $source;
            $data = (object)$data;
            \tool_certify\local\source\base::update_source($data);
        }

        return $certification;
    }

    /**
     * Add certification notification.
     *
     * @param $record
     * @return \stdClass notification record
     */
    public function create_certifiction_notification($record): stdClass {
        global $DB;

        $record = (object)(array)$record;

        if (!empty($record->certificationid)) {
            $certification = $DB->get_record('tool_certify_certifications', ['id' => $record->certificationid], '*', MUST_EXIST);
        } else {
            $certification = $DB->get_record('tool_certify_certifications', ['fullname' => $record->certification], '*', MUST_EXIST);
        }

        $alltypes = \tool_certify\local\notification_manager::get_all_types();
        if (!$record->notificationtype || !isset($alltypes[$record->notificationtype])) {
            throw new coding_exception('Invalid notification type');
        }

        $data = [
            'component' => 'tool_certify',
            'notificationtype' => $record->notificationtype,
            'instanceid' => $certification->id,
            'enabled' => '1',
        ];
        return \local_openlms\notification\util::notification_create($data);
    }
}
