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
 * Quest generator class for creating daily quests.
 *
 * Generates daily quests for users based on course content and user progress.
 * Supports multiple quest types including activity completion, forum posts,
 * quiz challenges, resource views, login streaks, and grade targets.
 *
 * @package    local_ace
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quest_generator {
    /** @var int Default number of daily quests per user. */
    private const DEFAULT_DAILY_QUEST_COUNT = 3;

    /** @var int Default XP reward per quest. */
    private const DEFAULT_XP_PER_QUEST = 50;

    /** @var string Quest type: complete a specific activity. */
    public const TYPE_ACTIVITY = 'activity';

    /** @var string Quest type: post in a forum. */
    public const TYPE_FORUM = 'forum';

    /** @var string Quest type: achieve target score in a quiz. */
    public const TYPE_QUIZ = 'quiz';

    /** @var string Quest type: view a specific resource. */
    public const TYPE_RESOURCE = 'resource';

    /** @var string Quest type: maintain login streak. */
    public const TYPE_LOGIN = 'login';

    /** @var string Quest type: achieve target grade on an assignment. */
    public const TYPE_GRADE = 'grade';

    /** @var string Quest status: active. */
    public const STATUS_ACTIVE = 'active';

    /** @var string Quest status: completed. */
    public const STATUS_COMPLETED = 'completed';

    /** @var string Quest status: expired. */
    public const STATUS_EXPIRED = 'expired';

    /**
     * Generate daily quests for a user in a course.
     *
     * Determines the available quest types based on the user's progress
     * and generates the configured number of quests for today. Avoids
     * duplicating any currently active quests.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return array Array of quest record objects created.
     */
    public function generate_daily_quests(int $userid, int $courseid, array $recommendations = []): array {
        global $DB;

        $questcount = (int) get_config('local_ace', 'dailyquestcount')
            ?: self::DEFAULT_DAILY_QUEST_COUNT;

        // Check how many active quests the user already has for today.
        $todaystart = strtotime('today');
        $todayend = strtotime('today 23:59:59');

        $existingtoday = $DB->count_records_select(
            'local_ace_quests',
            'userid = :userid AND courseid = :courseid AND timecreated >= :todaystart AND timecreated <= :todayend',
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'todaystart' => $todaystart,
                'todayend' => $todayend,
            ]
        );

        $remaining = $questcount - $existingtoday;
        if ($remaining <= 0) {
            // Already generated enough quests for today.
            return [];
        }

        $availabletypes = $this->get_available_quest_types($userid, $courseid);
        if (empty($availabletypes)) {
            return [];
        }

        // If we have AI/rule-based recommendations, prioritize matching quest types.
        if (!empty($recommendations)) {
            $availabletypes = $this->apply_recommendations($availabletypes, $recommendations);
        }

        // Get currently active quest target IDs to avoid duplicates.
        $activequests = $DB->get_records('local_ace_quests', [
            'userid' => $userid,
            'courseid' => $courseid,
            'status' => self::STATUS_ACTIVE,
        ]);
        $activetargets = [];
        foreach ($activequests as $quest) {
            $key = $quest->questtype . '_' . ($quest->targetid ?? 0);
            $activetargets[$key] = true;
        }

        $createdquests = [];
        $attempts = 0;
        $maxattempts = $remaining * 3; // Prevent infinite loops.

        while (count($createdquests) < $remaining && $attempts < $maxattempts) {
            $attempts++;

            // Pick a random available quest type.
            $typedata = $availabletypes[array_rand($availabletypes)];
            $type = $typedata['type'];
            $params = $typedata['params'] ?? [];

            // Check for duplicate with active quests.
            $targetid = $params['targetid'] ?? 0;
            $key = $type . '_' . $targetid;
            if (isset($activetargets[$key])) {
                // Remove this option to avoid repeated attempts.
                $availabletypes = array_filter($availabletypes, function ($item) use ($key) {
                    $itemkey = $item['type'] . '_' . ($item['params']['targetid'] ?? 0);
                    return $itemkey !== $key;
                });
                $availabletypes = array_values($availabletypes);
                if (empty($availabletypes)) {
                    break;
                }
                continue;
            }

            $quest = $this->create_quest($userid, $courseid, $type, $params);
            if ($quest) {
                $createdquests[] = $quest;
                $activetargets[$key] = true;

                // Remove used option from available types.
                $availabletypes = array_filter($availabletypes, function ($item) use ($key) {
                    $itemkey = $item['type'] . '_' . ($item['params']['targetid'] ?? 0);
                    return $itemkey !== $key;
                });
                $availabletypes = array_values($availabletypes);
                if (empty($availabletypes)) {
                    break;
                }
            }
        }

        return $createdquests;
    }

    /**
     * Get available quest types for a user in a course.
     *
     * Analyses the user's progress in the course and returns an array
     * of quest type definitions that are applicable. Each entry includes
     * the quest type string and parameters needed to create the quest.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return array Array of associative arrays with 'type' and 'params' keys.
     */
    public function get_available_quest_types(int $userid, int $courseid): array {
        global $DB;

        $available = [];

        // Use English for quest titles stored in DB (they are displayed via quest type badge).
        $sm = get_string_manager();

        // Always offer login streak quest.
        $available[] = [
            'type' => self::TYPE_LOGIN,
            'params' => [
                'title' => $sm->get_string('questtype_login', 'local_ace', null, 'en'),
                'description' => 'Log in to the course today to maintain your streak.',
            ],
        ];

        // Find incomplete activities for activity quests.
        $sql = "SELECT cm.id, cm.instance, m.name AS modname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
             LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid
                 WHERE cm.course = :courseid
                   AND cm.completion > 0
                   AND cm.deletioninprogress = 0
                   AND (cmc.completionstate IS NULL OR cmc.completionstate = 0)";
        $incompletemodules = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        foreach ($incompletemodules as $mod) {
            $modname = $mod->modname;

            if ($modname === 'forum') {
                // Forum participation quest.
                $forum = $DB->get_record('forum', ['id' => $mod->instance], 'id, name');
                if ($forum) {
                    $available[] = [
                        'type' => self::TYPE_FORUM,
                        'params' => [
                            'targetid' => $mod->id,
                            'title' => $sm->get_string('questtype_forum', 'local_ace', null, 'en') . ': ' . $forum->name,
                            'description' => 'Make a post in the forum: ' . $forum->name,
                        ],
                    ];
                }
            } else if ($modname === 'quiz') {
                // Quiz challenge quest.
                $quiz = $DB->get_record('quiz', ['id' => $mod->instance], 'id, name, grade');
                if ($quiz) {
                    $targetgrade = round((float) $quiz->grade * 0.7, 2); // 70% target.
                    $available[] = [
                        'type' => self::TYPE_QUIZ,
                        'params' => [
                            'targetid' => $mod->id,
                            'targetvalue' => $targetgrade,
                            'title' => $sm->get_string('questtype_quiz', 'local_ace', null, 'en') . ': ' . $quiz->name,
                            'description' => 'Achieve at least ' . $targetgrade . '% on: ' . $quiz->name,
                        ],
                    ];
                }
            } else if ($modname === 'resource' || $modname === 'page' || $modname === 'url') {
                // Resource viewing quest.
                $resource = $DB->get_record($modname, ['id' => $mod->instance], 'id, name');
                if ($resource) {
                    $available[] = [
                        'type' => self::TYPE_RESOURCE,
                        'params' => [
                            'targetid' => $mod->id,
                            'title' => $sm->get_string('questtype_resource', 'local_ace', null, 'en') . ': ' . $resource->name,
                            'description' => 'View the resource: ' . $resource->name,
                        ],
                    ];
                }
            } else if ($modname === 'assign') {
                // Grade target quest for assignments.
                $assign = $DB->get_record('assign', ['id' => $mod->instance], 'id, name, grade');
                if ($assign && (float) $assign->grade > 0) {
                    $targetgrade = round((float) $assign->grade * 0.7, 2);
                    $available[] = [
                        'type' => self::TYPE_GRADE,
                        'params' => [
                            'targetid' => $mod->id,
                            'targetvalue' => $targetgrade,
                            'title' => $sm->get_string('questtype_grade', 'local_ace', null, 'en') . ': ' . $assign->name,
                            'description' => 'Achieve at least ' . $targetgrade . ' on: ' . $assign->name,
                        ],
                    ];
                }

                // Also offer as generic activity completion quest.
                $available[] = [
                    'type' => self::TYPE_ACTIVITY,
                    'params' => [
                        'targetid' => $mod->id,
                        'title' => $sm->get_string('questtype_activity', 'local_ace', null, 'en')
                            . ': ' . ($assign->name ?? 'Activity'),
                        'description' => 'Complete the activity: ' . ($assign->name ?? 'Activity'),
                    ],
                ];
            } else {
                // Generic activity completion quest.
                $instance = $DB->get_record($modname, ['id' => $mod->instance], 'id, name');
                $name = $instance ? $instance->name : 'Activity #' . $mod->id;
                $available[] = [
                    'type' => self::TYPE_ACTIVITY,
                    'params' => [
                        'targetid' => $mod->id,
                        'title' => $sm->get_string('questtype_activity', 'local_ace', null, 'en') . ': ' . $name,
                        'description' => 'Complete the activity: ' . $name,
                    ],
                ];
            }
        }

        return $available;
    }

    /**
     * Create a single quest for a user in a course.
     *
     * Inserts a new quest record into the local_ace_quests table with
     * the specified type and parameters. The quest expires at the end
     * of the current day (23:59:59).
     *
     * @param int    $userid   The ID of the user.
     * @param int    $courseid The ID of the course.
     * @param string $type     The quest type (activity, forum, quiz, resource, login, grade).
     * @param array  $params   Additional quest parameters (targetid, targetvalue, title, description).
     * @return \stdClass|null The created quest record, or null on failure.
     */
    public function create_quest(int $userid, int $courseid, string $type, array $params): ?\stdClass {
        global $DB;

        $validtypes = [
            self::TYPE_ACTIVITY,
            self::TYPE_FORUM,
            self::TYPE_QUIZ,
            self::TYPE_RESOURCE,
            self::TYPE_LOGIN,
            self::TYPE_GRADE,
        ];

        if (!in_array($type, $validtypes, true)) {
            debugging("Invalid quest type: {$type}", DEBUG_DEVELOPER);
            return null;
        }

        // Apply adaptive difficulty and XP adjustment based on user performance.
        $engine = new adaptive_engine();
        $params = $engine->adjust_quest_params($userid, $courseid, $params);

        $basexp = (int) get_config('local_ace', 'xp_per_quest') ?: self::DEFAULT_XP_PER_QUEST;
        $now = time();
        $expirydate = strtotime('today 23:59:59');

        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->questtype = $type;
        $record->title = $params['title'] ?? ucfirst($type) . ' quest';
        $record->description = $params['description'] ?? '';
        $record->targetid = $params['targetid'] ?? null;
        $record->targetvalue = $params['targetvalue'] ?? null;
        $record->xpreward = $params['xpreward'] ?? $basexp;
        $record->difficulty = $params['difficulty'] ?? 1;
        $record->status = self::STATUS_ACTIVE;
        $record->expirydate = $expirydate;
        $record->completeddate = null;
        $record->templateid = $params['templateid'] ?? null;
        $record->recommended = $params['recommended'] ?? 0;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $record->id = $DB->insert_record('local_ace_quests', $record);

        return $record;
    }

    /**
     * Apply AI/rule-based recommendations to reorder and enrich available quest types.
     *
     * Recommendations from the recommendation engine specify preferred quest types
     * and difficulty levels. This method moves matching quest types to the front
     * of the available list and marks them as recommended.
     *
     * @param array $availabletypes The available quest types from get_available_quest_types().
     * @param array $recommendations Array of recommendation arrays with 'questtype', 'difficulty', 'reason'.
     * @return array Reordered and enriched available quest types.
     */
    private function apply_recommendations(array $availabletypes, array $recommendations): array {
        // Map recommendation quest types to their data.
        $recmap = [];
        foreach ($recommendations as $rec) {
            $rectype = $rec['questtype'] ?? '';
            $recmap[$rectype] = $rec;
        }

        // Map recommendation types to actual quest types.
        $typemapping = [
            'completion' => self::TYPE_ACTIVITY,
            'participation' => self::TYPE_FORUM,
            'streak' => self::TYPE_LOGIN,
            'engagement_boost' => self::TYPE_ACTIVITY,
            'grade_improvement' => self::TYPE_GRADE,
            'exploration' => self::TYPE_RESOURCE,
            'challenge' => self::TYPE_QUIZ,
            'general' => self::TYPE_ACTIVITY,
        ];

        $recommended = [];
        $others = [];

        foreach ($availabletypes as $typedata) {
            $type = $typedata['type'];
            $matched = false;

            foreach ($recmap as $rectype => $rec) {
                $mappedtype = $typemapping[$rectype] ?? $rectype;
                if ($type === $mappedtype) {
                    // Apply recommendation's difficulty and mark as recommended.
                    if (isset($rec['difficulty'])) {
                        $typedata['params']['difficulty'] = (int) $rec['difficulty'];
                    }
                    $typedata['params']['recommended'] = 1;
                    $recommended[] = $typedata;
                    $matched = true;
                    // Remove from map so each recommendation matches only once.
                    unset($recmap[$rectype]);
                    break;
                }
            }

            if (!$matched) {
                $others[] = $typedata;
            }
        }

        // Recommended quests first, then others.
        return array_merge($recommended, $others);
    }

    /**
     * Check if a quest has been auto-completed based on its criteria.
     *
     * Evaluates whether the conditions for a quest have been met
     * (e.g., activity completed, forum post made, grade achieved).
     *
     * @param int $questid The ID of the quest to check.
     * @return bool True if the quest criteria are satisfied.
     */
    public function check_quest_completion(int $questid): bool {
        global $DB;

        $quest = $DB->get_record('local_ace_quests', ['id' => $questid]);
        if (!$quest || $quest->status !== self::STATUS_ACTIVE) {
            return false;
        }

        // Check if quest has expired.
        if ($quest->expirydate > 0 && time() > $quest->expirydate) {
            $quest->status = self::STATUS_EXPIRED;
            $quest->timemodified = time();
            $DB->update_record('local_ace_quests', $quest);
            return false;
        }

        switch ($quest->questtype) {
            case self::TYPE_ACTIVITY:
                return $this->check_activity_completion($quest);

            case self::TYPE_FORUM:
                return $this->check_forum_participation($quest);

            case self::TYPE_QUIZ:
                return $this->check_quiz_score($quest);

            case self::TYPE_RESOURCE:
                return $this->check_resource_viewed($quest);

            case self::TYPE_LOGIN:
                return $this->check_login_streak($quest);

            case self::TYPE_GRADE:
                return $this->check_grade_target($quest);

            default:
                return false;
        }
    }

    /**
     * Complete a quest and award XP to the user.
     *
     * Marks the quest as completed and delegates XP awarding to
     * the xp_manager class.
     *
     * @param int $questid The ID of the quest.
     * @param int $userid  The ID of the user completing the quest.
     * @return int The XP earned from completing the quest.
     * @throws \moodle_exception If the quest is not found or already completed.
     */
    public function complete_quest(int $questid, int $userid): int {
        global $DB;

        $quest = $DB->get_record('local_ace_quests', ['id' => $questid]);
        if (!$quest) {
            throw new \moodle_exception('error_questnotfound', 'local_ace');
        }

        if ($quest->status === self::STATUS_COMPLETED) {
            throw new \moodle_exception('error_questalreadycompleted', 'local_ace');
        }

        if ((int) $quest->userid !== (int) $userid) {
            throw new \moodle_exception('error_nopermission', 'local_ace');
        }

        // Check expiry.
        if ($quest->expirydate > 0 && time() > $quest->expirydate) {
            $quest->status = self::STATUS_EXPIRED;
            $quest->timemodified = time();
            $DB->update_record('local_ace_quests', $quest);
            throw new \moodle_exception('questexpired', 'local_ace');
        }

        $now = time();
        $quest->status = self::STATUS_COMPLETED;
        $quest->completeddate = $now;
        $quest->timemodified = $now;
        $DB->update_record('local_ace_quests', $quest);

        // Award XP.
        $xpreward = (int) $quest->xpreward;
        $xpmanager = new xp_manager();
        $xpmanager->award_xp($userid, (int) $quest->courseid, $xpreward, 'quest_completed:' . $questid);

        return $xpreward;
    }

    /**
     * Check if an activity completion quest is satisfied.
     *
     * @param \stdClass $quest The quest record.
     * @return bool True if the target activity is completed.
     */
    private function check_activity_completion(\stdClass $quest): bool {
        global $DB;

        if (empty($quest->targetid)) {
            return false;
        }

        return $DB->record_exists_select(
            'course_modules_completion',
            'coursemoduleid = :cmid AND userid = :userid AND completionstate IN (1, 2)',
            ['cmid' => $quest->targetid, 'userid' => $quest->userid]
        );
    }

    /**
     * Check if a forum participation quest is satisfied.
     *
     * @param \stdClass $quest The quest record.
     * @return bool True if the user has posted in the target forum today.
     */
    private function check_forum_participation(\stdClass $quest): bool {
        global $DB;

        if (empty($quest->targetid)) {
            return false;
        }

        // Get the forum instance from the course module.
        $cm = $DB->get_record('course_modules', ['id' => $quest->targetid], 'instance');
        if (!$cm) {
            return false;
        }

        $todaystart = strtotime('today');

        // Check if user has posted in this forum today.
        $sql = "SELECT COUNT(fp.id)
                  FROM {forum_posts} fp
                  JOIN {forum_discussions} fd ON fd.id = fp.discussion
                 WHERE fd.forum = :forumid
                   AND fp.userid = :userid
                   AND fp.created >= :todaystart";
        $count = (int) $DB->count_records_sql($sql, [
            'forumid' => $cm->instance,
            'userid' => $quest->userid,
            'todaystart' => $todaystart,
        ]);

        return $count > 0;
    }

    /**
     * Check if a quiz challenge quest is satisfied.
     *
     * @param \stdClass $quest The quest record.
     * @return bool True if the user achieved the target score.
     */
    private function check_quiz_score(\stdClass $quest): bool {
        global $DB;

        if (empty($quest->targetid) || $quest->targetvalue === null) {
            return false;
        }

        $cm = $DB->get_record('course_modules', ['id' => $quest->targetid], 'instance');
        if (!$cm) {
            return false;
        }

        // Check for a finished attempt meeting the target grade.
        $sql = "SELECT MAX(qa.sumgrades) AS bestscore
                  FROM {quiz_attempts} qa
                 WHERE qa.quiz = :quizid
                   AND qa.userid = :userid
                   AND qa.state = 'finished'";
        $result = $DB->get_record_sql($sql, [
            'quizid' => $cm->instance,
            'userid' => $quest->userid,
        ]);

        if (!$result || $result->bestscore === null) {
            return false;
        }

        return (float) $result->bestscore >= (float) $quest->targetvalue;
    }

    /**
     * Check if a resource viewing quest is satisfied.
     *
     * @param \stdClass $quest The quest record.
     * @return bool True if the user has viewed the target resource.
     */
    private function check_resource_viewed(\stdClass $quest): bool {
        global $DB;

        if (empty($quest->targetid)) {
            return false;
        }

        // Check completion state for the resource module.
        return $DB->record_exists_select(
            'course_modules_completion',
            'coursemoduleid = :cmid AND userid = :userid AND completionstate IN (1, 2)',
            ['cmid' => $quest->targetid, 'userid' => $quest->userid]
        );
    }

    /**
     * Check if a login streak quest is satisfied.
     *
     * @param \stdClass $quest The quest record.
     * @return bool True if the user has logged into the course today.
     */
    private function check_login_streak(\stdClass $quest): bool {
        global $DB;

        $todaystart = strtotime('today');

        return $DB->record_exists_select(
            'logstore_standard_log',
            "userid = :userid AND courseid = :courseid AND eventname = :eventname AND timecreated >= :todaystart",
            [
                'userid' => $quest->userid,
                'courseid' => $quest->courseid,
                'eventname' => '\\core\\event\\course_viewed',
                'todaystart' => $todaystart,
            ]
        );
    }

    /**
     * Check if a grade target quest is satisfied.
     *
     * @param \stdClass $quest The quest record.
     * @return bool True if the user achieved the target grade.
     */
    private function check_grade_target(\stdClass $quest): bool {
        global $DB;

        if (empty($quest->targetid) || $quest->targetvalue === null) {
            return false;
        }

        $cm = $DB->get_record('course_modules', ['id' => $quest->targetid], 'instance, module');
        if (!$cm) {
            return false;
        }

        $module = $DB->get_record('modules', ['id' => $cm->module], 'name');
        if (!$module) {
            return false;
        }

        // Look up the grade item for this module instance.
        $gradeitem = $DB->get_record('grade_items', [
            'courseid' => $quest->courseid,
            'itemmodule' => $module->name,
            'iteminstance' => $cm->instance,
        ]);

        if (!$gradeitem) {
            return false;
        }

        $gradegrade = $DB->get_record('grade_grades', [
            'itemid' => $gradeitem->id,
            'userid' => $quest->userid,
        ]);

        if (!$gradegrade || $gradegrade->finalgrade === null) {
            return false;
        }

        return (float) $gradegrade->finalgrade >= (float) $quest->targetvalue;
    }
}
