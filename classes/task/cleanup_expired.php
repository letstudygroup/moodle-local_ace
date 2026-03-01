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

namespace local_ace\task;

use core\task\scheduled_task;
/**
 * Scheduled task to clean up expired quests and old analytics data.
 *
 * Marks active quests that have passed their expiry date as expired,
 * and deletes analytics snapshot records older than 90 days.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_expired extends scheduled_task {
    /**
     * Get the name of the task.
     *
     * @return string The localised task name.
     */
    public function get_name(): string {
        return get_string('task_cleanup_expired', 'local_ace');
    }

    /**
     * Execute the scheduled task.
     *
     * Performs two cleanup operations:
     * 1. Marks all active quests past their expiry date as expired.
     * 2. Deletes analytics snapshots older than 90 days.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        if (!get_config('local_ace', 'enableplugin')) {
            mtrace('ACE plugin is disabled. Skipping cleanup.');
            return;
        }

        $now = time();

        // 1. Mark expired quests.
        $expiredcount = self::expire_quests($now);
        mtrace("Expired {$expiredcount} quest(s) that passed their expiry date.");

        // 2. Clean old analytics data (older than 90 days).
        $cleanedcount = self::clean_old_analytics($now);
        mtrace("Cleaned {$cleanedcount} analytics snapshot(s) older than 90 days.");
    }

    /**
     * Mark active quests that have passed their expiry date as expired.
     *
     * @param int $now The current timestamp.
     * @return int The number of quests marked as expired.
     */
    private static function expire_quests(int $now): int {
        global $DB;

        // Find active quests with a non-zero expiry date that has passed.
        $sql = "SELECT id
                  FROM {local_ace_quests}
                 WHERE status = :status
                   AND expirydate > 0
                   AND expirydate < :now";

        $expiredquests = $DB->get_records_sql($sql, [
            'status' => 'active',
            'now' => $now,
        ]);

        if (empty($expiredquests)) {
            return 0;
        }

        $ids = array_keys($expiredquests);

        // Batch update: mark all expired quests.
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $params['status'] = 'expired';
        $params['timemodified'] = $now;

        $DB->execute(
            "UPDATE {local_ace_quests}
                SET status = :status,
                    timemodified = :timemodified
              WHERE id {$insql}",
            $params
        );

        return count($ids);
    }

    /**
     * Delete analytics snapshots older than 90 days.
     *
     * @param int $now The current timestamp.
     * @return int The number of analytics records deleted.
     */
    private static function clean_old_analytics(int $now): int {
        global $DB;

        $cutoff = $now - (90 * DAYSECS);

        // Count records before deletion for reporting.
        $count = $DB->count_records_select(
            'local_ace_analytics',
            'timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );

        if ($count > 0) {
            $DB->delete_records_select(
                'local_ace_analytics',
                'timecreated < :cutoff',
                ['cutoff' => $cutoff]
            );
        }

        return $count;
    }
}
