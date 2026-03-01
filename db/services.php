<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External services definitions for local_ace.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_ace_get_dashboard_data' => [
        'classname'     => 'local_ace\external\get_dashboard_data',
        'description'   => 'Get ACE dashboard data for a user in a course.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/ace:viewdashboard',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_ace_get_quest_data' => [
        'classname'     => 'local_ace\external\get_quest_data',
        'description'   => 'Get active quests for a user in a course.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/ace:viewdashboard',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_ace_complete_quest' => [
        'classname'     => 'local_ace\external\complete_quest',
        'description'   => 'Mark a quest as completed.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/ace:viewdashboard',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_ace_get_analytics' => [
        'classname'     => 'local_ace\external\get_analytics',
        'description'   => 'Get analytics data for a course.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/ace:viewanalytics',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];

$services = [
    'ACE Services' => [
        'functions' => [
            'local_ace_get_dashboard_data',
            'local_ace_get_quest_data',
            'local_ace_complete_quest',
            'local_ace_get_analytics',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'local_ace',
    ],
];
