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

namespace local_ace;
/**
 * XP manager class for managing experience points and levels.
 *
 * Handles awarding XP, calculating levels, tracking level progress,
 * and generating course leaderboards. Level thresholds follow a
 * triangular progression: level N requires the sum of (1..N-1)*100 XP.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xp_manager {
    /** @var int XP multiplier per level for threshold calculation. */
    private const XP_PER_LEVEL_UNIT = 100;

    /**
     * Award XP to a user in a course.
     *
     * Adds the specified amount of XP to the user's total in the given
     * course and recalculates their level.
     *
     * @param int    $userid   The ID of the user.
     * @param int    $courseid The ID of the course.
     * @param int    $xp       The amount of XP to award.
     * @param string $reason   A description of why XP was awarded.
     * @return void
     */
    public function award_xp(int $userid, int $courseid, int $xp, string $reason): void {
        global $DB;

        if ($xp <= 0) {
            return;
        }

        $now = time();

        $existing = $DB->get_record('local_ace_xp', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        if ($existing) {
            $newxp = (int) $existing->xp + $xp;
            $newlevel = $this->calculate_level($newxp);

            $existing->xp = $newxp;
            $existing->level = $newlevel;
            $existing->timemodified = $now;
            $DB->update_record('local_ace_xp', $existing);
        } else {
            $newlevel = $this->calculate_level($xp);

            $record = new \stdClass();
            $record->userid = $userid;
            $record->courseid = $courseid;
            $record->xp = $xp;
            $record->level = $newlevel;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_ace_xp', $record);
        }
    }

    /**
     * Get the total XP for a user in a course.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return int The total XP accumulated.
     */
    public function get_xp(int $userid, int $courseid): int {
        global $DB;

        $record = $DB->get_record('local_ace_xp', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        return $record ? (int) $record->xp : 0;
    }

    /**
     * Get the current level for a user in a course.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return int The current level (minimum 1).
     */
    public function get_level(int $userid, int $courseid): int {
        global $DB;

        $record = $DB->get_record('local_ace_xp', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        return $record ? (int) $record->level : 1;
    }

    /**
     * Calculate the level for a given XP amount.
     *
     * Level thresholds follow a triangular number progression:
     * - Level 1: 0 XP (starting level)
     * - Level 2: 100 XP  (sum = 1*100)
     * - Level 3: 300 XP  (sum = 1*100 + 2*100)
     * - Level 4: 600 XP  (sum = 1*100 + 2*100 + 3*100)
     * - Level N: sum(1..N-1) * 100 = N*(N-1)/2 * 100
     *
     * @param int $xp The total XP amount.
     * @return int The calculated level (minimum 1).
     */
    public function calculate_level(int $xp): int {
        if ($xp <= 0) {
            return 1;
        }

        // Level N requires N*(N-1)/2 * 100 XP.
        // Solve for N: N*(N-1)/2 * 100 <= xp.
        // N^2 - N - (2*xp/100) <= 0.
        // N <= (1 + sqrt(1 + 8*xp/100)) / 2.
        $level = (int) floor((1 + sqrt(1 + 8 * $xp / self::XP_PER_LEVEL_UNIT)) / 2);

        return max(1, $level);
    }

    /**
     * Get the level progress details for a user in a course.
     *
     * Returns an array with the current level, total XP, XP required
     * for the next level, and the progress percentage towards the next level.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return array Associative array with keys: 'level', 'xp', 'xp_for_next', 'progress'.
     */
    public function get_level_progress(int $userid, int $courseid): array {
        $xp = $this->get_xp($userid, $courseid);
        $level = $this->calculate_level($xp);

        // XP threshold for current level: level*(level-1)/2 * 100.
        $currentlevelxp = $this->get_xp_for_level($level);

        // XP threshold for next level: (level+1)*level/2 * 100.
        $nextlevelxp = $this->get_xp_for_level($level + 1);

        $xpinlevel = $xp - $currentlevelxp;
        $xpneeded = $nextlevelxp - $currentlevelxp;

        $progress = ($xpneeded > 0) ? round(($xpinlevel / $xpneeded) * 100.0, 2) : 0.0;
        $progress = min(100.0, max(0.0, $progress));

        return [
            'level' => $level,
            'xp' => $xp,
            'xp_for_next' => $nextlevelxp,
            'progress' => $progress,
        ];
    }

    /**
     * Get the course leaderboard ranked by total XP.
     *
     * Returns an ordered list of users with the most XP in the course.
     * Each entry includes the user's ID, full name, XP, and level.
     *
     * @param int $courseid The ID of the course.
     * @param int $limit    Maximum number of entries to return (default 10).
     * @return array Array of associative arrays with user leaderboard data.
     */
    public function get_leaderboard(int $courseid, int $limit = 10): array {
        global $DB;

        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
        $sql = "SELECT xp.id, xp.userid, xp.xp, xp.level,
                       {$namefields->selects}
                  FROM {local_ace_xp} xp
                  JOIN {user} u ON u.id = xp.userid
                 WHERE xp.courseid = :courseid
                   AND u.deleted = 0
                   AND u.suspended = 0
              ORDER BY xp.xp DESC, xp.timemodified ASC";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid], 0, $limit);

        $leaderboard = [];
        $rank = 0;
        foreach ($records as $record) {
            $rank++;
            $leaderboard[] = [
                'rank' => $rank,
                'userid' => (int) $record->userid,
                'fullname' => fullname($record),
                'xp' => (int) $record->xp,
                'level' => (int) $record->level,
            ];
        }

        return $leaderboard;
    }

    /**
     * Get the total XP required to reach a given level.
     *
     * @param int $level The target level.
     * @return int The cumulative XP needed to reach the level.
     */
    private function get_xp_for_level(int $level): int {
        if ($level <= 1) {
            return 0;
        }

        // Level N requires N*(N-1)/2 * 100 XP.
        return (int) ($level * ($level - 1) / 2 * self::XP_PER_LEVEL_UNIT);
    }
}
