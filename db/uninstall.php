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
 * Uninstall handler for local_ace.
 *
 * Drops all plugin tables and cleans up configuration data.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Uninstall the local_ace plugin.
 *
 * @return bool True on success.
 */
function xmldb_local_ace_uninstall() {
    global $DB;

    $dbman = $DB->get_manager();

    // Drop all plugin tables.
    $tables = [
        'local_ace_engagement',
        'local_ace_mastery',
        'local_ace_quests',
        'local_ace_xp',
        'local_ace_analytics',
        'local_ace_license',
    ];

    foreach ($tables as $tablename) {
        $table = new xmldb_table($tablename);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
    }

    // Clean up all plugin config.
    unset_all_config_for_plugin('local_ace');

    return true;
}
