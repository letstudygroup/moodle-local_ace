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
 * Uninstall handler for local_aceengine.
 *
 * Drops all plugin tables and cleans up configuration data.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Uninstall the local_aceengine plugin.
 *
 * @return bool True on success.
 */
function xmldb_local_aceengine_uninstall() {
    global $DB;

    $dbman = $DB->get_manager();

    // Drop all plugin tables.
    $tables = [
        'local_aceengine_engagement',
        'local_aceengine_mastery',
        'local_aceengine_quests',
        'local_aceengine_xp',
        'local_aceengine_analytics',
        'local_aceengine_license',
    ];

    foreach ($tables as $tablename) {
        $table = new xmldb_table($tablename);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
    }

    // Clean up all plugin config.
    unset_all_config_for_plugin('local_aceengine');

    return true;
}
