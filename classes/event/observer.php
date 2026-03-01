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

namespace local_ace\event;

use local_ace\notification_manager;
/**
 * Event observer for local_ace.
 *
 * Handles various Moodle events to award XP and check quest completion
 * for the Adaptive Challenge Engine plugin.
 *
 * @package    local_ace
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Handle course module completion events.
     *
     * Awards XP for activity completion and checks if any active quests
     * of type 'activity' or 'resource' are now completed.
     *
     * @param \core\event\course_module_completion_updated $event The event object.
     * @return void
     */
    public static function course_module_completion_updated(\core\event\course_module_completion_updated $event): void {
        $courseid = $event->courseid;
        if (!self::is_enabled_for_course($courseid)) {
            return;
        }

        $userid = $event->relateduserid;

        // Only award XP when activity is marked complete (not incomplete).
        $data = $event->get_record_snapshot('course_modules_completion', $event->objectid);
        if (empty($data) || $data->completionstate == COMPLETION_INCOMPLETE) {
            return;
        }

        $cmid = (int) $event->contextinstanceid;

        self::award_activity_xp($userid, $courseid);
        self::check_quest_completion($userid, $courseid, 'activity', $cmid);
        self::check_quest_completion($userid, $courseid, 'resource', $cmid);
    }

    /**
     * Handle quiz attempt submitted events.
     *
     * Awards XP for quiz submission and checks if any active quiz quests
     * are now completed.
     *
     * @param \mod_quiz\event\attempt_submitted $event The event object.
     * @return void
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        $courseid = $event->courseid;
        if (!self::is_enabled_for_course($courseid)) {
            return;
        }

        $userid = $event->relateduserid;
        $cmid = (int) $event->contextinstanceid;

        self::award_activity_xp($userid, $courseid);
        self::check_quest_completion($userid, $courseid, 'quiz', $cmid);
    }

    /**
     * Handle quiz attempt reviewed events.
     *
     * This fires for both real student attempts and admin preview attempts,
     * ensuring quiz quests complete even when the teacher previews a quiz.
     *
     * @param \mod_quiz\event\attempt_reviewed $event The event object.
     * @return void
     */
    public static function quiz_attempt_reviewed(\mod_quiz\event\attempt_reviewed $event): void {
        $courseid = $event->courseid;
        if (!self::is_enabled_for_course($courseid)) {
            return;
        }

        $userid = $event->relateduserid;
        $cmid = (int) $event->contextinstanceid;

        self::award_activity_xp($userid, $courseid);
        self::check_quest_completion($userid, $courseid, 'quiz', $cmid);
        self::check_quest_completion($userid, $courseid, 'grade', $cmid);
    }

    /**
     * Handle forum post created events.
     *
     * Awards XP for forum participation and checks if any active forum quests
     * are now completed.
     *
     * @param \mod_forum\event\post_created $event The event object.
     * @return void
     */
    public static function forum_post_created(\mod_forum\event\post_created $event): void {
        $courseid = $event->courseid;
        if (!self::is_enabled_for_course($courseid)) {
            return;
        }

        $userid = $event->userid;
        $cmid = (int) $event->contextinstanceid;

        self::award_activity_xp($userid, $courseid);
        self::check_quest_completion($userid, $courseid, 'forum', $cmid);
    }

    /**
     * Handle assignment submission created events.
     *
     * Awards XP for assignment submission and checks if any active assignment quests
     * are now completed.
     *
     * @param \mod_assign\event\submission_created $event The event object.
     * @return void
     */
    public static function assignment_submitted(\mod_assign\event\submission_created $event): void {
        $courseid = $event->courseid;
        if (!self::is_enabled_for_course($courseid)) {
            return;
        }

        $userid = $event->relateduserid;
        $cmid = (int) $event->contextinstanceid;

        self::award_activity_xp($userid, $courseid);
        self::check_quest_completion($userid, $courseid, 'activity', $cmid);
    }

    /**
     * Handle user graded events.
     *
     * Checks if any grade-based quests are now completed.
     * Resolves the grade item to a cm.id for targeted matching.
     *
     * @param \core\event\user_graded $event The event object.
     * @return void
     */
    public static function user_graded(\core\event\user_graded $event): void {
        $courseid = $event->courseid;
        if (!self::is_enabled_for_course($courseid)) {
            return;
        }

        $userid = $event->relateduserid;

        // Resolve grade_grades → grade_items → course_modules to find cm.id.
        $cmid = self::resolve_cmid_from_grade($event->objectid);

        self::check_quest_completion($userid, $courseid, 'grade', $cmid);
    }

    /**
     * Handle course viewed events.
     *
     * Checks if any login streak quests are now completed.
     *
     * @param \core\event\course_viewed $event The event object.
     * @return void
     */
    public static function course_viewed(\core\event\course_viewed $event): void {
        $userid = $event->userid;
        $courseid = $event->courseid;

        if (empty($courseid) || $courseid == SITEID) {
            return;
        }

        if (!self::is_enabled_for_course($courseid)) {
            return;
        }

        self::check_quest_completion($userid, $courseid, 'login', 0);
    }

    /**
     * Handle course module viewed events.
     *
     * Checks if any resource quests are completed when a user views a module.
     *
     * @param \core\event\course_module_viewed $event The event object.
     * @return void
     */
    public static function course_module_viewed(\core\event\course_module_viewed $event): void {
        $courseid = $event->courseid;
        if (!self::is_enabled_for_course($courseid)) {
            return;
        }

        $userid = $event->userid;
        $cmid = (int) $event->contextinstanceid;

        self::check_quest_completion($userid, $courseid, 'resource', $cmid);
        self::check_quest_completion($userid, $courseid, 'activity', $cmid);
    }

    /**
     * Check whether ACE is enabled for a specific course.
     *
     * @param int $courseid The course ID.
     * @return bool True if ACE is enabled for this course.
     */
    private static function is_enabled_for_course(int $courseid): bool {
        global $CFG;
        require_once($CFG->dirroot . '/local/ace/lib.php');
        return \local_ace_is_enabled_for_course($courseid);
    }

    /**
     * Resolve a grade_grades record to the course module ID.
     *
     * @param int $gradeid The grade_grades.id.
     * @return int The cm.id, or 0 if not found.
     */
    private static function resolve_cmid_from_grade(int $gradeid): int {
        global $DB;

        $sql = "SELECT cm.id AS cmid
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                  JOIN {modules} m ON m.name = gi.itemmodule
                  JOIN {course_modules} cm ON cm.instance = gi.iteminstance AND cm.module = m.id
                 WHERE gg.id = :gradeid AND gi.itemtype = 'mod'";
        $record = $DB->get_record_sql($sql, ['gradeid' => $gradeid], IGNORE_MISSING);

        return $record ? (int) $record->cmid : 0;
    }

    /**
     * Award XP for completing an activity.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return void
     */
    private static function award_activity_xp(int $userid, int $courseid): void {
        global $DB;

        $xpperactivity = (int) get_config('local_ace', 'xp_per_activity');
        if ($xpperactivity <= 0) {
            $xpperactivity = 10;
        }

        $now = time();
        $xprecord = $DB->get_record('local_ace_xp', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        if ($xprecord) {
            $newxp = (int) $xprecord->xp + $xpperactivity;
            $newlevel = (new \local_ace\xp_manager())->calculate_level($newxp);
            $DB->update_record('local_ace_xp', (object) [
                'id' => $xprecord->id,
                'xp' => $newxp,
                'level' => $newlevel,
                'timemodified' => $now,
            ]);
        } else {
            $newxp = $xpperactivity;
            $newlevel = (new \local_ace\xp_manager())->calculate_level($newxp);
            $DB->insert_record('local_ace_xp', (object) [
                'userid' => $userid,
                'courseid' => $courseid,
                'xp' => $newxp,
                'level' => $newlevel,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Check if any active quests for the user are now completed.
     *
     * If a quest has a targetid, only complete it when the event's cm.id
     * matches. If the quest has no targetid, any event of that type completes it.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param string $questtype The quest type to check.
     * @param int $cmid The course module ID from the event (0 if not applicable).
     * @return void
     */
    private static function check_quest_completion(int $userid, int $courseid, string $questtype, int $cmid = 0): void {
        global $DB;

        $quests = $DB->get_records('local_ace_quests', [
            'userid' => $userid,
            'courseid' => $courseid,
            'status' => 'active',
            'questtype' => $questtype,
        ]);

        if (empty($quests)) {
            return;
        }

        $now = time();
        $xpperquest = (int) get_config('local_ace', 'xp_per_quest');
        if ($xpperquest <= 0) {
            $xpperquest = 50;
        }

        foreach ($quests as $quest) {
            // Skip expired quests.
            if ($quest->expirydate > 0 && $quest->expirydate < $now) {
                $DB->update_record('local_ace_quests', (object) [
                    'id' => $quest->id,
                    'status' => 'expired',
                    'timemodified' => $now,
                ]);
                continue;
            }

            $completed = false;

            switch ($questtype) {
                case 'activity':
                case 'quiz':
                case 'forum':
                case 'resource':
                    // If quest targets a specific cm, only complete on that cm.
                    if (!empty($quest->targetid) && $cmid > 0) {
                        $completed = ((int) $quest->targetid === $cmid);
                    } else {
                        // No specific target — any event of this type completes it.
                        $completed = true;
                    }
                    break;

                case 'grade':
                    // If quest targets a specific cm, match it.
                    if (!empty($quest->targetid) && $cmid > 0) {
                        $completed = ((int) $quest->targetid === $cmid);
                    } else {
                        // No specific target — any grade event completes it.
                        $completed = true;
                    }
                    break;

                case 'login':
                    $completed = true;
                    break;
            }

            if ($completed) {
                self::complete_quest($quest, $userid, $courseid, $now, $xpperquest);
            }
        }
    }

    /**
     * Mark a quest as completed, award XP, and send notifications.
     *
     * @param \stdClass $quest The quest record.
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param int $now Current timestamp.
     * @param int $defaultxp Default XP if quest has no reward set.
     * @return void
     */
    private static function complete_quest(\stdClass $quest, int $userid, int $courseid, int $now, int $defaultxp): void {
        global $DB;

        $DB->update_record('local_ace_quests', (object) [
            'id' => $quest->id,
            'status' => 'completed',
            'completeddate' => $now,
            'timemodified' => $now,
        ]);

        $xpreward = (int) $quest->xpreward;
        if ($xpreward <= 0) {
            $xpreward = $defaultxp;
        }

        $xprecord = $DB->get_record('local_ace_xp', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        $oldlevel = $xprecord ? (int) $xprecord->level : 1;

        if ($xprecord) {
            $newxp = (int) $xprecord->xp + $xpreward;
            $newlevel = (new \local_ace\xp_manager())->calculate_level($newxp);
            $DB->update_record('local_ace_xp', (object) [
                'id' => $xprecord->id,
                'xp' => $newxp,
                'level' => $newlevel,
                'timemodified' => $now,
            ]);
        } else {
            $newxp = $xpreward;
            $newlevel = (new \local_ace\xp_manager())->calculate_level($newxp);
            $DB->insert_record('local_ace_xp', (object) [
                'userid' => $userid,
                'courseid' => $courseid,
                'xp' => $newxp,
                'level' => $newlevel,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        notification_manager::notify_quest_completed($userid, $courseid, $xpreward, $newxp, $newlevel);

        if ($newlevel > $oldlevel) {
            notification_manager::notify_level_up($userid, $courseid, $newlevel);
        }
    }
}
