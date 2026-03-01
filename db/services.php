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
 * External services definitions for local_aceengine.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_aceengine_get_dashboard_data' => [
        'classname'     => 'local_aceengine\external\get_dashboard_data',
        'description'   => 'Get ACE dashboard data for a user in a course.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/ace:viewdashboard',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_aceengine_get_quest_data' => [
        'classname'     => 'local_aceengine\external\get_quest_data',
        'description'   => 'Get active quests for a user in a course.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/ace:viewdashboard',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_aceengine_complete_quest' => [
        'classname'     => 'local_aceengine\external\complete_quest',
        'description'   => 'Mark a quest as completed.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/ace:viewdashboard',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_aceengine_get_analytics' => [
        'classname'     => 'local_aceengine\external\get_analytics',
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
            'local_aceengine_get_dashboard_data',
            'local_aceengine_get_quest_data',
            'local_aceengine_complete_quest',
            'local_aceengine_get_analytics',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'local_aceengine',
    ],
];
