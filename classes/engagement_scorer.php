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

namespace local_aceengine;
/**
 * Engagement scorer class for calculating composite engagement scores.
 *
 * Calculates a weighted average of completion, timeliness, participation,
 * and consistency scores for a user in a given course. Weights are
 * configurable via admin settings.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engagement_scorer {
    /** @var int Default weight for completion component. */
    private const DEFAULT_WEIGHT_COMPLETION = 25;

    /** @var int Default weight for timeliness component. */
    private const DEFAULT_WEIGHT_TIMELINESS = 25;

    /** @var int Default weight for participation component. */
    private const DEFAULT_WEIGHT_PARTICIPATION = 25;

    /** @var int Default weight for consistency component. */
    private const DEFAULT_WEIGHT_CONSISTENCY = 25;

    /** @var int Number of days to evaluate for consistency scoring. */
    private const CONSISTENCY_WINDOW_DAYS = 30;

    /**
     * Calculate the composite engagement score for a user in a course.
     *
     * The score is a weighted average of four component scores: completion,
     * timeliness, participation, and consistency. Weights are read from
     * admin settings and normalised so they sum to 100.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return float The composite engagement score (0-100).
     */
    public function calculate(int $userid, int $courseid): float {
        $completionscore = $this->get_completion_score($userid, $courseid);
        $timelinessscore = $this->get_timeliness_score($userid, $courseid);
        $participationscore = $this->get_participation_score($userid, $courseid);
        $consistencyscore = $this->get_consistency_score($userid, $courseid);

        $wcompletion = (float) get_config('local_aceengine', 'engagementweight_completion')
            ?: self::DEFAULT_WEIGHT_COMPLETION;
        $wtimeliness = (float) get_config('local_aceengine', 'engagementweight_timeliness')
            ?: self::DEFAULT_WEIGHT_TIMELINESS;
        $wparticipation = (float) get_config('local_aceengine', 'engagementweight_participation')
            ?: self::DEFAULT_WEIGHT_PARTICIPATION;
        $wconsistency = (float) get_config('local_aceengine', 'engagementweight_consistency')
            ?: self::DEFAULT_WEIGHT_CONSISTENCY;

        $totalweight = $wcompletion + $wtimeliness + $wparticipation + $wconsistency;

        // Guard against division by zero when all weights are zero.
        if ($totalweight <= 0) {
            $totalweight = 100.0;
            $wcompletion = self::DEFAULT_WEIGHT_COMPLETION;
            $wtimeliness = self::DEFAULT_WEIGHT_TIMELINESS;
            $wparticipation = self::DEFAULT_WEIGHT_PARTICIPATION;
            $wconsistency = self::DEFAULT_WEIGHT_CONSISTENCY;
        }

        $score = (
            ($completionscore * $wcompletion) +
            ($timelinessscore * $wtimeliness) +
            ($participationscore * $wparticipation) +
            ($consistencyscore * $wconsistency)
        ) / $totalweight;

        $score = round(min(100.0, max(0.0, $score)), 4);

        $components = [
            'completion_score' => $completionscore,
            'timeliness_score' => $timelinessscore,
            'participation_score' => $participationscore,
            'consistency_score' => $consistencyscore,
        ];

        $this->save_score($userid, $courseid, $score, $components);

        return $score;
    }

    /**
     * Calculate the activity completion ratio for a user in a course.
     *
     * Queries the course_modules_completion table to determine what fraction
     * of the course modules have been completed by the user.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return float The completion score (0-100).
     */
    public function get_completion_score(int $userid, int $courseid): float {
        global $DB;

        // Get all course modules with completion tracking enabled.
        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                 WHERE cm.course = :courseid
                   AND cm.completion > 0
                   AND cm.deletioninprogress = 0";
        $modules = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        $totalmodules = count($modules);
        if ($totalmodules === 0) {
            return 0.0;
        }

        // Count completed modules for this user.
        // completionstate: 1 = complete, 2 = complete with pass, 3 = complete with fail.
        $sql = "SELECT COUNT(cmc.id)
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cmc.userid = :userid
                   AND cm.course = :courseid
                   AND cm.completion > 0
                   AND cm.deletioninprogress = 0
                   AND cmc.completionstate > 0";
        $completed = (int) $DB->count_records_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        return round(($completed / $totalmodules) * 100.0, 4);
    }

    /**
     * Calculate the on-time submission ratio for a user in a course.
     *
     * Checks assignments and quizzes that have due dates and determines
     * how many were submitted before the deadline.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return float The timeliness score (0-100).
     */
    public function get_timeliness_score(int $userid, int $courseid): float {
        global $DB;

        $ontimecount = 0;
        $totalwithdue = 0;

        // Check assignments with due dates.
        $sql = "SELECT a.id, a.duedate, sub.timemodified AS submittime
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = :courseid1
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
             LEFT JOIN {assign_submission} sub ON sub.assignment = a.id
                       AND sub.userid = :userid1
                       AND sub.latest = 1
                       AND sub.status = 'submitted'
                 WHERE a.course = :courseid2
                   AND a.duedate > 0
                   AND cm.deletioninprogress = 0";
        $assignments = $DB->get_records_sql($sql, [
            'courseid1' => $courseid,
            'userid1' => $userid,
            'courseid2' => $courseid,
        ]);

        foreach ($assignments as $assignment) {
            $totalwithdue++;
            if (!empty($assignment->submittime) && $assignment->submittime <= $assignment->duedate) {
                $ontimecount++;
            }
        }

        // Check quizzes with due dates (close date acts as deadline).
        $sql = "SELECT q.id, q.timeclose, qa.timefinish
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = :courseid1
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
             LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id
                       AND qa.userid = :userid1
                       AND qa.state = 'finished'
                 WHERE q.course = :courseid2
                   AND q.timeclose > 0
                   AND cm.deletioninprogress = 0";
        $quizzes = $DB->get_records_sql($sql, [
            'courseid1' => $courseid,
            'userid1' => $userid,
            'courseid2' => $courseid,
        ]);

        foreach ($quizzes as $quiz) {
            $totalwithdue++;
            if (!empty($quiz->timefinish) && $quiz->timefinish <= $quiz->timeclose) {
                $ontimecount++;
            }
        }

        if ($totalwithdue === 0) {
            return 100.0; // No timed items, treat as fully on-time.
        }

        return round(($ontimecount / $totalwithdue) * 100.0, 4);
    }

    /**
     * Calculate the participation score for a user in a course.
     *
     * Measures forum posts and discussions started relative to the course
     * average. A score of 100 means the user is at or above the average;
     * scores below 100 are proportional.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return float The participation score (0-100).
     */
    public function get_participation_score(int $userid, int $courseid): float {
        global $DB;

        // Get forum IDs in this course.
        $sql = "SELECT f.id
                  FROM {forum} f
                  JOIN {course_modules} cm ON cm.instance = f.id AND cm.course = :courseid
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'forum'
                 WHERE f.course = :courseid2
                   AND cm.deletioninprogress = 0";
        $forums = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'courseid2' => $courseid,
        ]);

        if (empty($forums)) {
            return 100.0; // No forums in course; participation is not applicable.
        }

        $forumids = array_keys($forums);
        [$insql, $params] = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED, 'forum');

        // Count the user's posts across all course forums.
        $sql = "SELECT COUNT(fp.id)
                  FROM {forum_posts} fp
                  JOIN {forum_discussions} fd ON fd.id = fp.discussion
                 WHERE fd.forum $insql
                   AND fp.userid = :userid";
        $params['userid'] = $userid;
        $userposts = (int) $DB->count_records_sql($sql, $params);

        // Count the user's discussions started.
        $sql = "SELECT COUNT(fd.id)
                  FROM {forum_discussions} fd
                 WHERE fd.forum $insql
                   AND fd.userid = :userid";
        $userdiscussions = (int) $DB->count_records_sql($sql, $params);

        // Calculate course average participation.
        // Get all enrolled users count.
        $context = \context_course::instance($courseid);
        $enrolledusers = count_enrolled_users($context);

        if ($enrolledusers <= 0) {
            return 0.0;
        }

        // Total posts across all forums.
        [$insql2, $params2] = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED, 'forum');
        $sql = "SELECT COUNT(fp.id)
                  FROM {forum_posts} fp
                  JOIN {forum_discussions} fd ON fd.id = fp.discussion
                 WHERE fd.forum $insql2";
        $totalposts = (int) $DB->count_records_sql($sql, $params2);

        // Total discussions.
        $sql = "SELECT COUNT(fd.id)
                  FROM {forum_discussions} fd
                 WHERE fd.forum $insql2";
        $totaldiscussions = (int) $DB->count_records_sql($sql, $params2);

        $avgposts = $totalposts / $enrolledusers;
        $avgdiscussions = $totaldiscussions / $enrolledusers;

        // Combine posts and discussions with equal weight.
        $useractivity = $userposts + ($userdiscussions * 2); // Discussions weighted more.
        $avgactivity = $avgposts + ($avgdiscussions * 2);

        if ($avgactivity <= 0) {
            // No activity at all in course; give full score if user has any activity.
            return ($useractivity > 0) ? 100.0 : 0.0;
        }

        $ratio = $useractivity / $avgactivity;
        // Cap at 100 — being above average still yields 100.
        $score = min(100.0, $ratio * 100.0);

        return round($score, 4);
    }

    /**
     * Calculate the login consistency score for a user in a course.
     *
     * Examines the logstore_standard_log for course_viewed events over the
     * last 30 days and calculates the ratio of days with activity.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return float The consistency score (0-100).
     */
    public function get_consistency_score(int $userid, int $courseid): float {
        global $DB;

        $now = time();
        $windowstart = $now - (self::CONSISTENCY_WINDOW_DAYS * DAYSECS);

        // Count distinct days with a course_viewed event.
        // Use FLOOR(timecreated / DAYSECS) for cross-DB compatibility (no FROM_UNIXTIME).
        $sql = "SELECT COUNT(DISTINCT FLOOR(timecreated / :daysecs)) AS daycount
                  FROM {logstore_standard_log}
                 WHERE userid = :userid
                   AND courseid = :courseid
                   AND eventname = :eventname
                   AND timecreated >= :windowstart
                   AND timecreated <= :now";
        $record = $DB->get_record_sql($sql, [
            'daysecs' => DAYSECS,
            'userid' => $userid,
            'courseid' => $courseid,
            'eventname' => '\\core\\event\\course_viewed',
            'windowstart' => $windowstart,
            'now' => $now,
        ]);

        $activedays = $record ? (int) $record->daycount : 0;

        $score = ($activedays / self::CONSISTENCY_WINDOW_DAYS) * 100.0;

        return round(min(100.0, $score), 4);
    }

    /**
     * Save the engagement score and components to the database.
     *
     * Inserts a new record or updates the existing record for the
     * user/course pair. Also determines the trend (improving, stable,
     * declining) by comparing with the previous score.
     *
     * @param int   $userid     The ID of the user.
     * @param int   $courseid   The ID of the course.
     * @param float $score      The composite engagement score.
     * @param array $components Associative array of component scores.
     * @return void
     */
    public function save_score(int $userid, int $courseid, float $score, array $components): void {
        global $DB;

        $now = time();

        $existing = $DB->get_record('local_aceengine_engagement', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        // Determine trend by comparing with previous score.
        $trend = 'stable';
        if ($existing) {
            $previousscore = (float) $existing->score;
            $diff = $score - $previousscore;
            if ($diff > 5.0) {
                $trend = 'improving';
            } else if ($diff < -5.0) {
                $trend = 'declining';
            }
        }

        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->score = $score;
        $record->completion_score = $components['completion_score'] ?? 0.0;
        $record->timeliness_score = $components['timeliness_score'] ?? 0.0;
        $record->participation_score = $components['participation_score'] ?? 0.0;
        $record->consistency_score = $components['consistency_score'] ?? 0.0;
        $record->trend = $trend;
        $record->timemodified = $now;

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_aceengine_engagement', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_aceengine_engagement', $record);
        }
    }
}
