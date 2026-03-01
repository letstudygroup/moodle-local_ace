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

namespace local_ace\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/ace/lib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ace\notification_manager;
use context_course;
use invalid_parameter_exception;
use moodle_exception;
/**
 * External function to mark a quest as completed and award XP.
 *
 * @package    local_ace
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class complete_quest extends external_api {
    /**
     * Describes the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questid' => new external_value(PARAM_INT, 'The quest ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Complete a quest and award XP to the current user.
     *
     * Validates that the quest belongs to the current user, is still active,
     * then marks it as completed and awards the associated XP reward.
     *
     * @param int $questid The quest ID.
     * @return array Completion result with success status, XP earned, new total XP, and new level.
     */
    public static function execute(int $questid): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'questid' => $questid,
        ]);
        $questid = $params['questid'];

        $userid = (int) $USER->id;

        // Get the quest record.
        $quest = $DB->get_record('local_ace_quests', ['id' => $questid]);
        if (!$quest) {
            throw new invalid_parameter_exception(
                get_string('error_questnotfound', 'local_ace')
            );
        }

        // Verify the quest belongs to the current user.
        if ((int) $quest->userid !== $userid) {
            throw new invalid_parameter_exception(
                get_string('error_questnotfound', 'local_ace')
            );
        }

        // Validate context and capability.
        $context = context_course::instance($quest->courseid);
        self::validate_context($context);
        require_capability('local/ace:viewdashboard', $context);

        // Check per-course enablement.
        if (!local_ace_is_enabled_for_course((int) $quest->courseid)) {
            throw new \moodle_exception('error_ace_disabled_for_course', 'local_ace');
        }

        // Check quest is still active.
        if ($quest->status !== 'active') {
            throw new moodle_exception('error_questalreadycompleted', 'local_ace');
        }

        // Check if quest has expired.
        if ($quest->expirydate > 0 && $quest->expirydate < time()) {
            // Mark quest as expired.
            $DB->set_field('local_ace_quests', 'status', 'expired', ['id' => $questid]);
            $DB->set_field('local_ace_quests', 'timemodified', time(), ['id' => $questid]);
            throw new moodle_exception('questexpired', 'local_ace');
        }

        $now = time();
        $xpearned = (int) $quest->xpreward;

        // Mark quest as completed.
        $DB->update_record('local_ace_quests', (object) [
            'id' => $questid,
            'status' => 'completed',
            'completeddate' => $now,
            'timemodified' => $now,
        ]);

        // Award XP to the user.
        $xprecord = $DB->get_record('local_ace_xp', [
            'userid' => $userid,
            'courseid' => $quest->courseid,
        ]);

        $oldlevel = $xprecord ? (int) $xprecord->level : 1;

        if ($xprecord) {
            $newxp = (int) $xprecord->xp + $xpearned;
            $newlevel = (new \local_ace\xp_manager())->calculate_level($newxp);
            $DB->update_record('local_ace_xp', (object) [
                'id' => $xprecord->id,
                'xp' => $newxp,
                'level' => $newlevel,
                'timemodified' => $now,
            ]);
        } else {
            $newxp = $xpearned;
            $newlevel = (new \local_ace\xp_manager())->calculate_level($newxp);
            $DB->insert_record('local_ace_xp', (object) [
                'userid' => $userid,
                'courseid' => $quest->courseid,
                'xp' => $newxp,
                'level' => $newlevel,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        // Send quest completed notification.
        notification_manager::notify_quest_completed($userid, (int) $quest->courseid, $xpearned, $newxp, $newlevel);

        // Send level up notification if applicable.
        if ($newlevel > $oldlevel) {
            notification_manager::notify_level_up($userid, (int) $quest->courseid, $newlevel);
        }

        return [
            'success' => true,
            'xp_earned' => $xpearned,
            'new_total_xp' => $newxp,
            'new_level' => $newlevel,
        ];
    }

    /**
     * Describes the return value for execute.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the quest was completed successfully'),
            'xp_earned' => new external_value(PARAM_INT, 'XP earned from this quest'),
            'new_total_xp' => new external_value(PARAM_INT, 'New total XP for the user in this course'),
            'new_level' => new external_value(PARAM_INT, 'New level after XP award'),
        ]);
    }
}
