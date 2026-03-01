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

namespace local_aceengine\task;

use core\task\scheduled_task;
use context_course;
use local_aceengine\notification_manager;
/**
 * Scheduled task to generate daily quests for all enrolled users.
 *
 * Iterates over all visible courses, finds users with the viewdashboard
 * capability, and generates daily quests for each user-course pair.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_daily_quests extends scheduled_task {
    /**
     * Get the name of the task.
     *
     * @return string The localised task name.
     */
    public function get_name(): string {
        return get_string('task_generate_daily_quests', 'local_aceengine');
    }

    /**
     * Execute the scheduled task.
     *
     * For each visible course, retrieves enrolled users with the
     * 'local/ace:viewdashboard' capability and generates daily quests
     * for each user-course pair using the quest generation logic.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/aceengine/lib.php');

        if (!get_config('local_aceengine', 'enableplugin')) {
            mtrace('ACE plugin is disabled. Skipping daily quest generation.');
            return;
        }

        $dailyquestcount = (int) get_config('local_aceengine', 'dailyquestcount');
        if ($dailyquestcount <= 0) {
            $dailyquestcount = 3;
        }

        // Expire stale quests before generating new ones.
        $expiredcount = $DB->count_records_select(
            'local_aceengine_quests',
            'status = :status AND expirydate < :now',
            ['status' => 'active', 'now' => time()]
        );
        if ($expiredcount > 0) {
            $DB->execute(
                "UPDATE {local_aceengine_quests} SET status = 'expired', timemodified = :now
                  WHERE status = 'active' AND expirydate < :expiry",
                ['now' => time(), 'expiry' => time()]
            );
            mtrace("Expired {$expiredcount} stale quest(s).");
        }

        // Get all visible courses (excluding the site course).
        $courses = $DB->get_records('course', ['visible' => 1], 'id ASC', 'id, shortname');
        $coursecount = 0;
        $usercount = 0;
        $questcount = 0;

        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }

            // Respect per-course enable setting.
            if (!\local_aceengine_is_enabled_for_course($course->id)) {
                continue;
            }

            $context = context_course::instance($course->id, IGNORE_MISSING);
            if (!$context) {
                continue;
            }

            // Get enrolled users who can view the dashboard.
            $users = get_enrolled_users($context, 'local/ace:viewdashboard', 0, 'u.id');
            if (empty($users)) {
                continue;
            }

            $coursecount++;
            mtrace("Processing course {$course->shortname} (ID: {$course->id}) - "
                . count($users) . ' user(s)...');

            // Check if Pro recommendations are available for this run.
            $proactive = class_exists('\local_ace_pro\pro_manager')
                && \local_ace_pro\pro_manager::is_active();

            foreach ($users as $user) {
                $generated = self::generate_quests_for_user($user->id, $course->id, $dailyquestcount);
                $questcount += $generated;
                $usercount++;

                // Send recommendation notification if Pro is active.
                if ($proactive) {
                    self::send_recommendation_notification($user->id, $course->id);
                }
            }
        }

        mtrace("Daily quest generation complete: {$questcount} quest(s) generated "
            . "for {$usercount} user(s) across {$coursecount} course(s).");
    }

    /**
     * Generate daily quests for a single user in a course.
     *
     * Creates quests of varying types and difficulty levels. Avoids creating
     * duplicates by checking for existing active quests of the same type.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param int $count Number of quests to generate.
     * @return int The number of quests actually generated.
     */
    private static function generate_quests_for_user(int $userid, int $courseid, int $count): int {
        global $DB;

        $questtypes = ['activity', 'forum', 'quiz', 'resource', 'login', 'grade'];
        $now = time();
        $expirydate = $now + DAYSECS; // Quests expire in 24 hours.
        $generated = 0;

        // Get existing active quest types to avoid duplicates.
        $existingtypes = $DB->get_fieldset_select(
            'local_aceengine_quests',
            'questtype',
            'userid = :userid AND courseid = :courseid AND status = :status',
            ['userid' => $userid, 'courseid' => $courseid, 'status' => 'active']
        );

        // Filter out quest types that already have an active quest.
        $availabletypes = array_diff($questtypes, $existingtypes);
        if (empty($availabletypes)) {
            return 0;
        }

        // Smart targeting: prioritise types that have actual activities available.
        $prioritisedtypes = self::prioritise_quest_types($userid, $courseid, $availabletypes);

        // Get AI/rule-based recommendations if Pro is active.
        $recommendations = [];
        $recommendedtypes = [];
        if (class_exists('\local_ace_pro\ai\recommendation_engine')) {
            try {
                $recommendations = \local_ace_pro\ai\recommendation_engine::generate_recommendations($userid, $courseid);
                // Map recommendation quest types to actual quest types.
                $typemapping = [
                    'completion' => 'activity', 'participation' => 'forum', 'streak' => 'login',
                    'engagement_boost' => 'activity', 'grade_improvement' => 'grade',
                    'exploration' => 'resource', 'challenge' => 'quiz', 'general' => 'activity',
                ];
                foreach ($recommendations as $rec) {
                    $rectype = $rec['questtype'] ?? '';
                    $mapped = $typemapping[$rectype] ?? $rectype;
                    if (in_array($mapped, $prioritisedtypes)) {
                        $recommendedtypes[$mapped] = $rec;
                    }
                }
            } catch (\Throwable $e) {
                debugging('Recommendation engine error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Reorder: put recommended types first.
        if (!empty($recommendedtypes)) {
            $reordered = [];
            foreach ($recommendedtypes as $rtype => $rec) {
                $reordered[] = $rtype;
            }
            foreach ($prioritisedtypes as $ptype) {
                if (!isset($recommendedtypes[$ptype])) {
                    $reordered[] = $ptype;
                }
            }
            $prioritisedtypes = $reordered;
        }

        // Use adaptive engine for difficulty (instead of random).
        $adaptiveengine = new \local_aceengine\adaptive_engine();

        $xpperquest = (int) get_config('local_aceengine', 'xp_per_quest');
        if ($xpperquest <= 0) {
            $xpperquest = 50;
        }

        // Force English for stored quest titles (displayed via type badge in user's language).
        $sm = get_string_manager();
        $questtitles = [
            'activity' => $sm->get_string('questtype_activity', 'local_aceengine', null, 'en'),
            'forum' => $sm->get_string('questtype_forum', 'local_aceengine', null, 'en'),
            'quiz' => $sm->get_string('questtype_quiz', 'local_aceengine', null, 'en'),
            'resource' => $sm->get_string('questtype_resource', 'local_aceengine', null, 'en'),
            'login' => $sm->get_string('questtype_login', 'local_aceengine', null, 'en'),
            'grade' => $sm->get_string('questtype_grade', 'local_aceengine', null, 'en'),
        ];

        $generatedquests = [];

        for ($i = 0; $i < $count && $i < count($prioritisedtypes); $i++) {
            $type = $prioritisedtypes[$i];

            // Adaptive difficulty based on user performance.
            $questparams = ['xpreward' => $xpperquest];
            $questparams = $adaptiveengine->adjust_quest_params($userid, $courseid, $questparams);
            $difficulty = $questparams['difficulty'] ?? 1;
            $xpreward = $questparams['xpreward'] ?? $xpperquest;

            // Check if this type was recommended.
            $isrecommended = isset($recommendedtypes[$type]) ? 1 : 0;

            // Smart targeting: find a specific activity to target.
            $target = self::find_target_for_type($userid, $courseid, $type);

            // Apply adaptive target value adjustment for grade/quiz quests.
            if (isset($target['targetvalue']) && $target['targetvalue'] > 0) {
                $target['targetvalue'] = $questparams['targetvalue']
                    ?? $target['targetvalue'];
            }

            $quest = new \stdClass();
            $quest->userid = $userid;
            $quest->courseid = $courseid;
            $quest->questtype = $type;
            $quest->title = $target['title'] ?? ($questtitles[$type] ?? $type);
            $quest->description = $target['description'] ?? '';
            $quest->targetid = $target['targetid'] ?? null;
            $quest->targetvalue = $target['targetvalue'] ?? null;
            $quest->xpreward = $xpreward;
            $quest->difficulty = $difficulty;
            $quest->status = 'active';
            $quest->expirydate = $expirydate;
            $quest->completeddate = null;
            $quest->templateid = null;
            $quest->recommended = $isrecommended;
            $quest->timecreated = $now;
            $quest->timemodified = $now;

            $quest->id = $DB->insert_record('local_aceengine_quests', $quest);
            $generatedquests[] = $quest;
            $generated++;
        }

        // Send notification about new quests.
        if (!empty($generatedquests)) {
            notification_manager::notify_new_quests($userid, $courseid, $generatedquests);
        }

        return $generated;
    }

    /**
     * Build and send a recommendation notification for a user in a course.
     *
     * Uses the dashboard's build_recommendations logic (rule-based, no credits)
     * to count available recommendations and notify the user.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return void
     */
    private static function send_recommendation_notification(int $userid, int $courseid): void {
        try {
            $dashboard = new \local_aceengine\output\dashboard($userid, $courseid);
            $reccount = count($dashboard->recommendations);
            if ($reccount > 0) {
                notification_manager::notify_recommendations($userid, $courseid, $reccount);
            }
        } catch (\Throwable $e) {
            debugging('Recommendation notification error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Prioritise quest types based on available activities in the course.
     *
     * Checks which activity types actually exist and have incomplete items
     * for the user, and puts those first. Always includes 'login' as a fallback.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param array $availabletypes Available quest types.
     * @return array Prioritised quest types.
     */
    private static function prioritise_quest_types(int $userid, int $courseid, array $availabletypes): array {
        global $DB;

        $prioritised = [];
        $remaining = [];

        foreach ($availabletypes as $type) {
            $hasactivity = false;
            switch ($type) {
                case 'quiz':
                    $hasactivity = $DB->record_exists('quiz', ['course' => $courseid]);
                    break;
                case 'forum':
                    $hasactivity = $DB->record_exists('forum', ['course' => $courseid]);
                    break;
                case 'activity':
                    // Check for incomplete course modules with completion tracking.
                    $sql = "SELECT COUNT(cm.id)
                              FROM {course_modules} cm
                         LEFT JOIN {course_modules_completion} cmc
                                ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid
                             WHERE cm.course = :courseid
                               AND cm.completion > 0
                               AND cm.visible = 1
                               AND (cmc.id IS NULL OR cmc.completionstate = 0)";
                    $hasactivity = $DB->count_records_sql($sql, [
                        'userid' => $userid,
                        'courseid' => $courseid,
                    ]) > 0;
                    break;
                case 'resource':
                    $hasactivity = $DB->record_exists_select(
                        'course_modules',
                        "course = :courseid AND visible = 1 AND module IN (
                            SELECT id FROM {modules} WHERE name IN ('resource', 'url', 'page', 'book', 'folder')
                        )",
                        ['courseid' => $courseid]
                    );
                    break;
                case 'grade':
                    $hasactivity = $DB->record_exists('grade_items', [
                        'courseid' => $courseid,
                        'itemtype' => 'mod',
                    ]);
                    break;
                case 'login':
                    // Login quests are always available.
                    $hasactivity = true;
                    break;
            }

            if ($hasactivity) {
                $prioritised[] = $type;
            } else {
                $remaining[] = $type;
            }
        }

        // Put types with activities first, then the rest shuffled.
        shuffle($prioritised);
        shuffle($remaining);

        return array_merge($prioritised, $remaining);
    }

    /**
     * Find a specific target activity for a quest type.
     *
     * Attempts to find an incomplete activity that the user hasn't finished yet,
     * making quests more relevant and actionable.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param string $type The quest type.
     * @return array Target data with title, description, targetid, targetvalue keys.
     */
    private static function find_target_for_type(int $userid, int $courseid, string $type): array {
        global $DB;

        // Force English for stored quest titles.
        $sm = get_string_manager();

        switch ($type) {
            case 'quiz':
                // Find a quiz the user hasn't attempted yet, return cm.id.
                $sql = "SELECT cm.id AS cmid, q.name
                          FROM {quiz} q
                          JOIN {course_modules} cm ON cm.instance = q.id
                               AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
                     LEFT JOIN {quiz_attempts} qa
                            ON qa.quiz = q.id AND qa.userid = :userid AND qa.state = 'finished'
                         WHERE q.course = :courseid AND qa.id IS NULL AND cm.visible = 1
                      ORDER BY q.timeopen ASC";
                $record = $DB->get_record_sql($sql, [
                    'userid' => $userid,
                    'courseid' => $courseid,
                ], IGNORE_MULTIPLE);
                if ($record) {
                    return [
                        'title' => $sm->get_string('questtype_quiz', 'local_aceengine', null, 'en') . ': ' . $record->name,
                        'description' => '',
                        'targetid' => (int) $record->cmid,
                        'targetvalue' => null,
                    ];
                }
                break;

            case 'forum':
                // Find a forum in the course, return cm.id.
                $sql = "SELECT cm.id AS cmid, f.name
                          FROM {forum} f
                          JOIN {course_modules} cm ON cm.instance = f.id
                               AND cm.module = (SELECT id FROM {modules} WHERE name = 'forum')
                         WHERE f.course = :courseid AND cm.visible = 1
                      ORDER BY cm.section ASC, cm.id ASC";
                $record = $DB->get_record_sql($sql, [
                    'courseid' => $courseid,
                ], IGNORE_MULTIPLE);
                if ($record) {
                    return [
                        'title' => $sm->get_string('questtype_forum', 'local_aceengine', null, 'en') . ': ' . $record->name,
                        'description' => '',
                        'targetid' => (int) $record->cmid,
                        'targetvalue' => null,
                    ];
                }
                break;

            case 'activity':
                // Find an incomplete activity with completion tracking, return cm.id + name.
                $sql = "SELECT cm.id AS cmid, cm.instance, m.name AS modname
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module
                     LEFT JOIN {course_modules_completion} cmc
                            ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid
                         WHERE cm.course = :courseid
                           AND cm.completion > 0
                           AND cm.visible = 1
                           AND (cmc.id IS NULL OR cmc.completionstate = 0)
                      ORDER BY cm.section ASC, cm.id ASC";
                $cm = $DB->get_record_sql($sql, [
                    'userid' => $userid,
                    'courseid' => $courseid,
                ], IGNORE_MULTIPLE);
                if ($cm) {
                    // Get the activity name from its module table.
                    $actname = $DB->get_field($cm->modname, 'name', ['id' => $cm->instance]);
                    $title = $sm->get_string('questtype_activity', 'local_aceengine', null, 'en');
                    if ($actname) {
                        $title .= ': ' . $actname;
                    }
                    return [
                        'title' => $title,
                        'description' => '',
                        'targetid' => (int) $cm->cmid,
                        'targetvalue' => null,
                    ];
                }
                break;

            case 'resource':
                // Find a resource the user hasn't viewed yet, return cm.id.
                $sql = "SELECT cm.id AS cmid, m.name AS modname, cm.instance
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module
                         WHERE cm.course = :courseid
                           AND cm.visible = 1
                           AND m.name IN ('resource', 'url', 'page', 'book', 'folder')
                      ORDER BY cm.section ASC, cm.id ASC";
                $cm = $DB->get_record_sql($sql, [
                    'courseid' => $courseid,
                ], IGNORE_MULTIPLE);
                if ($cm) {
                    $actname = $DB->get_field($cm->modname, 'name', ['id' => $cm->instance]);
                    $title = $sm->get_string('questtype_resource', 'local_aceengine', null, 'en');
                    if ($actname) {
                        $title .= ': ' . $actname;
                    }
                    return [
                        'title' => $title,
                        'description' => '',
                        'targetid' => (int) $cm->cmid,
                        'targetvalue' => null,
                    ];
                }
                break;

            case 'grade':
                // Find a graded activity, return cm.id.
                $sql = "SELECT cm.id AS cmid, gi.itemname
                          FROM {grade_items} gi
                          JOIN {course_modules} cm ON cm.instance = gi.iteminstance
                               AND cm.module = (SELECT id FROM {modules} WHERE name = gi.itemmodule)
                         WHERE gi.courseid = :courseid
                           AND gi.itemtype = 'mod'
                           AND cm.visible = 1
                      ORDER BY cm.section ASC, cm.id ASC";
                $record = $DB->get_record_sql($sql, [
                    'courseid' => $courseid,
                ], IGNORE_MULTIPLE);
                if ($record) {
                    $title = $sm->get_string('questtype_grade', 'local_aceengine', null, 'en');
                    if ($record->itemname) {
                        $title .= ': ' . $record->itemname;
                    }
                    return [
                        'title' => $title,
                        'description' => '',
                        'targetid' => (int) $record->cmid,
                        'targetvalue' => null,
                    ];
                }
                break;

            case 'login':
                // Login quests don't target a specific activity.
                return [
                    'title' => $sm->get_string('questtype_login', 'local_aceengine', null, 'en'),
                    'description' => '',
                    'targetid' => null,
                    'targetvalue' => null,
                ];
        }

        // Default: no specific target.
        return [
            'title' => null,
            'description' => '',
            'targetid' => null,
            'targetvalue' => null,
        ];
    }
}
