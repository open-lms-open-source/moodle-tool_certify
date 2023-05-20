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

/**
 * Certification plugin capabilities.
 *
 * @package    tool_certify
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    /* Access certification catalogue - catalogue uses certification.public, visible cohorts and own assignments. */
    'tool/certify:viewcatalogue' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],

    /* Access the certification management UI - needed for certification management capabilities
       this allows sidestepping of regular certification visibility rules */
    'tool/certify:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'tenantmanager' => CAP_ALLOW,
        ],
    ],

    /* Add and update certifications. */
    'tool/certify:edit' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'tenantmanager' => CAP_ALLOW,
        ],
    ],

    /* Delete certifications. */
    'tool/certify:delete' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'tenantmanager' => CAP_ALLOW,
        ],
    ],

    /* Assign (and unassign) certifications to users manually, used only when manual source enabled in certification,
       applies to special cases such as unassigning suspended cohort auto-allocations after not a membership removal. */
    'tool/certify:assign' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'tenantmanager' => CAP_ALLOW,
        ],
    ],

    /* All other advanced functionality not intended for regular managers */
    'tool/certify:admin' => [
        'riskbitmask' => RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
        ],
    ],
];

// Compatibility hacks for vanilla Moodle.
if (!file_exists(__DIR__ . '/../../../admin/tool/olms_tenant/version.php')) {
    foreach ($capabilities as $k => $unused) {
        unset($capabilities[$k]['archetypes']['tenantmanager']);
    }
}
