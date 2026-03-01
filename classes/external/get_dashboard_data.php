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

namespace local_aceengine\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/aceengine/lib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use context_course;
/**
 * External function to get ACE dashboard data for the current user.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_dashboard_data extends external_api {
    /**
     * Describes the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Return dashboard data for the current user in the given course.
     *
     * Retrieves engagement score, mastery score, XP, level, level progress,
     * and quest counts for the authenticated user.
     *
     * @param int $courseid The course ID.
     * @return array Dashboard data.
     */
    public static function execute(int $courseid): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);
        $courseid = $params['courseid'];

        // Validate context and check capability.
        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/ace:viewdashboard', $context);

        // Check per-course enablement.
        if (!local_aceengine_is_enabled_for_course($courseid)) {
            throw new \moodle_exception('error_ace_disabled_for_course', 'local_aceengine');
        }

        $userid = $USER->id;

        // Get engagement score.
        $engagement = $DB->get_record('local_aceengine_engagement', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $engagementscore = $engagement ? (float) $engagement->score : 0.0;

        // Get mastery score.
        $mastery = $DB->get_record('local_aceengine_mastery', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $masteryscore = $mastery ? (float) $mastery->score : 0.0;

        // Get XP and level data.
        $xprecord = $DB->get_record('local_aceengine_xp', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $xp = $xprecord ? (int) $xprecord->xp : 0;
        $level = $xprecord ? (int) $xprecord->level : 1;

        // Calculate level progress as a percentage towards the next level.
        // XP required per level increases: level * 100 XP to reach the next level.
        $xpforlevel = self::get_xp_for_level($level);
        $xpfornextlevel = self::get_xp_for_level($level + 1);
        $xpinlevel = $xp - $xpforlevel;
        $xprequired = $xpfornextlevel - $xpforlevel;
        $levelprogress = $xprequired > 0 ? round(($xpinlevel / $xprequired) * 100, 2) : 0.0;

        // Count active quests.
        $activequestscount = $DB->count_records('local_aceengine_quests', [
            'userid' => $userid,
            'courseid' => $courseid,
            'status' => 'active',
        ]);

        // Count completed quests.
        $completedquestscount = $DB->count_records('local_aceengine_quests', [
            'userid' => $userid,
            'courseid' => $courseid,
            'status' => 'completed',
        ]);

        return [
            'engagement_score' => $engagementscore,
            'mastery_score' => $masteryscore,
            'xp' => $xp,
            'level' => $level,
            'level_progress' => $levelprogress,
            'active_quests_count' => $activequestscount,
            'completed_quests_count' => $completedquestscount,
        ];
    }

    /**
     * Describes the return value for execute.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'engagement_score' => new external_value(PARAM_FLOAT, 'Engagement score (0-100)'),
            'mastery_score' => new external_value(PARAM_FLOAT, 'Mastery score (0-100)'),
            'xp' => new external_value(PARAM_INT, 'Total XP earned'),
            'level' => new external_value(PARAM_INT, 'Current level'),
            'level_progress' => new external_value(PARAM_FLOAT, 'Progress towards next level (0-100)'),
            'active_quests_count' => new external_value(PARAM_INT, 'Number of active quests'),
            'completed_quests_count' => new external_value(PARAM_INT, 'Number of completed quests'),
        ]);
    }

    /**
     * Calculate the total XP required to reach a given level.
     *
     * The formula is: sum of (i * 100) for i = 1 to (level - 1).
     * Level 1 requires 0 XP, level 2 requires 100 XP, level 3 requires 300 XP, etc.
     *
     * @param int $level The target level.
     * @return int The total XP required to reach the level.
     */
    private static function get_xp_for_level(int $level): int {
        if ($level <= 1) {
            return 0;
        }
        // Sum of arithmetic series: n*(n+1)/2 * 100, where n = level - 1.
        $n = $level - 1;
        return (int) ($n * ($n + 1) / 2 * 100);
    }
}
