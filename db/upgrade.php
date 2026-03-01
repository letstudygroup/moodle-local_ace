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
 * Upgrade steps for local_aceengine.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_aceengine plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_local_aceengine_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026022202) {
        // Migrate per-course enable from block_instances to config_plugins.
        // Any course that had a block_ace instance is considered "enabled".
        if ($dbman->table_exists('block_instances')) {
            $sql = "SELECT DISTINCT bi.parentcontextid
                      FROM {block_instances} bi
                     WHERE bi.blockname = 'ace'";
            $contexts = $DB->get_records_sql($sql);

            foreach ($contexts as $ctx) {
                // Resolve context to course ID.
                $ctxrecord = $DB->get_record('context', [
                    'id' => $ctx->parentcontextid,
                    'contextlevel' => CONTEXT_COURSE,
                ]);
                if ($ctxrecord) {
                    $courseid = $ctxrecord->instanceid;
                    // Only set if not already configured.
                    $existing = get_config('local_aceengine', 'ace_course_enabled_' . $courseid);
                    if ($existing === false) {
                        set_config('ace_course_enabled_' . $courseid, 1, 'local_aceengine');
                    }
                }
            }
        }

        // Delete all active quests — they may have wrong targetid format
        // (instance ID instead of cm.id). New quests will be regenerated
        // by the daily scheduled task with correct targeting.
        $DB->delete_records('local_aceengine_quests', ['status' => 'active']);

        upgrade_plugin_savepoint(true, 2026022202, 'local', 'ace');
    }

    if ($oldversion < 2026022203) {
        // Remove auto-deployed block_ace instances.
        // The block is now optional — teachers add it manually if wanted.
        // ACE enable/disable is now in the course edit form via hooks.
        $DB->delete_records('block_instances', ['blockname' => 'ace']);

        upgrade_plugin_savepoint(true, 2026022203, 'local', 'ace');
    }

    if ($oldversion < 2026022204) {
        // Dual Plugin Architecture upgrade (v1.3.0).
        // Pro features (team_xp, mission_templates, managetemplates,
        // viewteamxp, manageteamxp capabilities) have been moved to
        // local_ace_pro. We do NOT drop existing tables — they are
        // preserved for local_ace_pro to adopt.

        // Clean up Pro-only config values that are no longer used.
        unset_config('adaptivedifficulty', 'local_aceengine');
        unset_config('teamxp', 'local_aceengine');

        upgrade_plugin_savepoint(true, 2026022204, 'local', 'ace');
    }

    if ($oldversion < 2026022205) {
        // Add credit and AI config cache columns to local_aceengine_license.
        $table = new \xmldb_table('local_aceengine_license');

        $field = new \xmldb_field(
            'credits_remaining',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'response_signature'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new \xmldb_field('credits_total', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'credits_remaining');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new \xmldb_field('ai_config', XMLDB_TYPE_TEXT, null, null, null, null, null, 'credits_total');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026022205, 'local', 'ace');
    }

    if ($oldversion < 2026022206) {
        // Add 'recommended' column to local_aceengine_quests for AI-powered quest recommendations.
        $table = new \xmldb_table('local_aceengine_quests');

        $field = new \xmldb_field('recommended', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'completeddate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026022206, 'local', 'ace');
    }

    if ($oldversion < 2026022208) {
        // Add heartbeat columns to local_aceengine_license for periodic license validation.
        $table = new \xmldb_table('local_aceengine_license');

        $field = new \xmldb_field('heartbeat_interval', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '21600', 'ai_config');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new \xmldb_field('last_heartbeat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'heartbeat_interval');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new \xmldb_field('integrity_hash', XMLDB_TYPE_TEXT, null, null, null, null, null, 'last_heartbeat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026022208, 'local', 'ace');
    }

    return true;
}
