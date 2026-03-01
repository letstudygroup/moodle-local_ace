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
 * Scheduled task definitions for local_ace.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_ace\task\generate_daily_quests',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '5',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_ace\task\calculate_scores',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '*/4',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_ace\task\cleanup_expired',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '3',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '0',
    ],
    [
        'classname' => 'local_ace\task\sync_license',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '12',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
