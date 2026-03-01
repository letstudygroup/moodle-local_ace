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
 * Privacy provider for local_ace.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ace\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
/**
 * Privacy provider implementation for local_ace.
 *
 * Describes all database tables storing user data and provides
 * export/deletion functionality for GDPR compliance.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var array Tables that store per-user per-course data. */
    private const USER_TABLES = [
        'local_ace_engagement',
        'local_ace_mastery',
        'local_ace_quests',
        'local_ace_xp',
        'local_ace_analytics',
    ];

    /**
     * Describe all database tables that store personal data.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_ace_engagement',
            [
                'userid' => 'privacy:metadata:userid',
                'courseid' => 'privacy:metadata:courseid',
                'score' => 'privacy:metadata:score',
                'completion_score' => 'privacy:metadata:score',
                'timeliness_score' => 'privacy:metadata:score',
                'participation_score' => 'privacy:metadata:score',
                'consistency_score' => 'privacy:metadata:score',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:local_ace_engagement'
        );

        $collection->add_database_table(
            'local_ace_mastery',
            [
                'userid' => 'privacy:metadata:userid',
                'courseid' => 'privacy:metadata:courseid',
                'score' => 'privacy:metadata:score',
                'grade_score' => 'privacy:metadata:score',
                'improvement_score' => 'privacy:metadata:score',
                'breadth_score' => 'privacy:metadata:score',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:local_ace_mastery'
        );

        $collection->add_database_table(
            'local_ace_quests',
            [
                'userid' => 'privacy:metadata:userid',
                'courseid' => 'privacy:metadata:courseid',
                'questtype' => 'privacy:metadata:local_ace_quests',
                'title' => 'privacy:metadata:local_ace_quests',
                'description' => 'privacy:metadata:local_ace_quests',
                'xpreward' => 'privacy:metadata:xp',
                'status' => 'privacy:metadata:local_ace_quests',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:local_ace_quests'
        );

        $collection->add_database_table(
            'local_ace_xp',
            [
                'userid' => 'privacy:metadata:userid',
                'courseid' => 'privacy:metadata:courseid',
                'xp' => 'privacy:metadata:xp',
                'level' => 'privacy:metadata:xp',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:local_ace_xp'
        );

        // NOTE: local_ace_team_xp is now handled by local_ace_pro privacy provider.

        $collection->add_database_table(
            'local_ace_analytics',
            [
                'userid' => 'privacy:metadata:userid',
                'courseid' => 'privacy:metadata:courseid',
                'engagement_score' => 'privacy:metadata:score',
                'mastery_score' => 'privacy:metadata:score',
                'dropout_risk' => 'privacy:metadata:score',
                'snapshot_date' => 'privacy:metadata:timecreated',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:local_ace_analytics'
        );

        // NOTE: local_ace_mission_templates is now handled by local_ace_pro privacy provider.

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        foreach (self::USER_TABLES as $table) {
            $sql = "SELECT ctx.id
                      FROM {" . $table . "} t
                      JOIN {context} ctx ON ctx.instanceid = t.courseid AND ctx.contextlevel = :contextlevel
                     WHERE t.userid = :userid";

            $contextlist->add_from_sql($sql, [
                'contextlevel' => CONTEXT_COURSE,
                'userid' => $userid,
            ]);
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        foreach (self::USER_TABLES as $table) {
            $sql = "SELECT t.userid
                      FROM {" . $table . "} t
                     WHERE t.courseid = :courseid";
            $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        $courseid = $context->instanceid;

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $params['courseid'] = $courseid;

        foreach (self::USER_TABLES as $table) {
            $DB->delete_records_select($table, "userid $insql AND courseid = :courseid", $params);
        }
    }

    /**
     * Export all user data for the specified approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export data for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;
            $subcontext = [get_string('pluginname', 'local_ace')];

            // Export engagement data.
            $records = $DB->get_records('local_ace_engagement', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
            if (!empty($records)) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('engagementscore', 'local_ace')]),
                    (object) ['engagement' => array_values($records)]
                );
            }

            // Export mastery data.
            $records = $DB->get_records('local_ace_mastery', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
            if (!empty($records)) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('masteryscore', 'local_ace')]),
                    (object) ['mastery' => array_values($records)]
                );
            }

            // Export quest data.
            $records = $DB->get_records('local_ace_quests', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
            if (!empty($records)) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('dailyquests', 'local_ace')]),
                    (object) ['quests' => array_values($records)]
                );
            }

            // Export XP data.
            $records = $DB->get_records('local_ace_xp', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
            if (!empty($records)) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('totalxp', 'local_ace')]),
                    (object) ['xp' => array_values($records)]
                );
            }

            // Export analytics data.
            $records = $DB->get_records('local_ace_analytics', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
            if (!empty($records)) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('analytics', 'local_ace')]),
                    (object) ['analytics' => array_values($records)]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;

        foreach (self::USER_TABLES as $table) {
            $DB->delete_records($table, ['courseid' => $courseid]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete data for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;

            foreach (self::USER_TABLES as $table) {
                $DB->delete_records($table, [
                    'userid' => $userid,
                    'courseid' => $courseid,
                ]);
            }
        }
    }
}
