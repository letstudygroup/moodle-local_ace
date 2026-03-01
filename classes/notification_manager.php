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

use core\message\message;
use core_user;
use moodle_url;
/**
 * Notification manager for local_ace.
 *
 * Handles sending platform and email notifications for quest events.
 *
 * @package    local_ace
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_manager {
    /**
     * Send a notification when new quests are generated for a user.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param array $quests Array of quest objects that were generated.
     * @return void
     */
    public static function notify_new_quests(int $userid, int $courseid, array $quests): void {
        if (empty($quests)) {
            return;
        }

        $user = core_user::get_user($userid);
        if (!$user || $user->deleted) {
            return;
        }

        $coursename = self::get_course_name($courseid);
        $quest = reset($quests);
        $questcount = count($quests);
        $title = $questcount > 1
            ? get_string('notification_newquest_subject', 'local_ace', $questcount . ' ' . get_string('dailyquests', 'local_ace'))
            : get_string('notification_newquest_subject', 'local_ace', $quest->title);

        $a = new \stdClass();
        $a->title = $quest->title;
        $a->xpreward = $quest->xpreward ?? 0;
        $a->coursename = $coursename;
        $body = get_string('notification_newquest_body', 'local_ace', $a);

        $dashboardurl = new moodle_url('/local/ace/index.php', ['courseid' => $courseid]);

        $message = new message();
        $message->component = 'local_ace';
        $message->name = 'questgenerated';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $title;
        $message->fullmessage = strip_tags($body);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $body;
        $message->smallmessage = $title;
        $message->contexturl = $dashboardurl->out(false);
        $message->contexturlname = get_string('dashboard', 'local_ace');
        $message->courseid = $courseid;

        self::safe_message_send($message);
    }

    /**
     * Send a notification when a quest is completed.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param int $xpearned XP earned from the quest.
     * @param int $totalxp New total XP.
     * @param int $level Current level.
     * @return void
     */
    public static function notify_quest_completed(
        int $userid,
        int $courseid,
        int $xpearned,
        int $totalxp,
        int $level
    ): void {
        $user = core_user::get_user($userid);
        if (!$user || $user->deleted) {
            return;
        }

        $coursename = self::get_course_name($courseid);
        $subject = get_string('notification_questcompleted_subject', 'local_ace', $xpearned);

        $a = new \stdClass();
        $a->xp = $xpearned;
        $a->totalxp = $totalxp;
        $a->level = $level;
        $a->coursename = $coursename;
        $body = get_string('notification_questcompleted_body', 'local_ace', $a);

        $dashboardurl = new moodle_url('/local/ace/index.php', ['courseid' => $courseid]);

        $message = new message();
        $message->component = 'local_ace';
        $message->name = 'questcompleted';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = strip_tags($body);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $body;
        $message->smallmessage = $subject;
        $message->contexturl = $dashboardurl->out(false);
        $message->contexturlname = get_string('dashboard', 'local_ace');
        $message->courseid = $courseid;

        self::safe_message_send($message);
    }

    /**
     * Send a notification when a user levels up.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param int $newlevel The new level achieved.
     * @return void
     */
    public static function notify_level_up(int $userid, int $courseid, int $newlevel): void {
        $user = core_user::get_user($userid);
        if (!$user || $user->deleted) {
            return;
        }

        $coursename = self::get_course_name($courseid);

        $a = new \stdClass();
        $a->level = $newlevel;
        $a->coursename = $coursename;
        $subject = get_string('notification_levelup_subject', 'local_ace', $newlevel);
        $body = get_string('notification_levelup_body', 'local_ace', $a);

        $dashboardurl = new moodle_url('/local/ace/index.php', ['courseid' => $courseid]);

        $message = new message();
        $message->component = 'local_ace';
        $message->name = 'levelup';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = strip_tags($body);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $body;
        $message->smallmessage = $subject;
        $message->contexturl = $dashboardurl->out(false);
        $message->contexturlname = get_string('dashboard', 'local_ace');
        $message->courseid = $courseid;

        self::safe_message_send($message);
    }

    /**
     * Send a notification when new activity recommendations are available.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param int $count Number of recommendations available.
     * @return void
     */
    public static function notify_recommendations(int $userid, int $courseid, int $count): void {
        if ($count <= 0) {
            return;
        }

        $user = core_user::get_user($userid);
        if (!$user || $user->deleted) {
            return;
        }

        $coursename = self::get_course_name($courseid);
        $subject = get_string('notification_recommendation_subject', 'local_ace', $count);

        $a = new \stdClass();
        $a->count = $count;
        $a->coursename = $coursename;
        $body = get_string('notification_recommendation_body', 'local_ace', $a);

        $dashboardurl = new moodle_url('/local/ace/index.php', ['courseid' => $courseid]);

        $message = new message();
        $message->component = 'local_ace';
        $message->name = 'recommendation';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = strip_tags($body);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $body;
        $message->smallmessage = $subject;
        $message->contexturl = $dashboardurl->out(false);
        $message->contexturlname = get_string('dashboard', 'local_ace');
        $message->courseid = $courseid;

        self::safe_message_send($message);
    }

    /**
     * Get the short name of a course.
     *
     * @param int $courseid The course ID.
     * @return string The course full name.
     */
    private static function get_course_name(int $courseid): string {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'fullname', IGNORE_MISSING);
        return $course ? format_string($course->fullname) : '';
    }

    /**
     * Send a message, catching any delivery errors silently.
     *
     * A notification delivery failure (e.g. missing sendmail) should never
     * prevent the user from viewing a page or completing an action.
     *
     * @param message $message The message to send.
     */
    private static function safe_message_send(message $message): void {
        try {
            @message_send($message);
        } catch (\Throwable $e) {
            debugging('ACE notification delivery failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
