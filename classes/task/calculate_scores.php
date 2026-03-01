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
use context_course;
/**
 * Scheduled task to recalculate engagement and mastery scores.
 *
 * Iterates over all visible courses and enrolled users, recalculates
 * engagement and mastery scores, and saves analytics snapshots.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculate_scores extends scheduled_task {
    /**
     * Get the name of the task.
     *
     * @return string The localised task name.
     */
    public function get_name(): string {
        return get_string('task_calculate_scores', 'local_ace');
    }

    /**
     * Execute the scheduled task.
     *
     * For each visible course and enrolled user, recalculates engagement
     * and mastery scores based on course activity data, updates the score
     * tables, and creates analytics snapshots for trend tracking.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/ace/lib.php');

        if (!get_config('local_ace', 'enableplugin')) {
            mtrace('ACE plugin is disabled. Skipping score calculation.');
            return;
        }

        $now = time();
        $snapshotdate = self::get_snapshot_date($now);

        // Get configured weights for engagement scoring.
        $weights = self::get_engagement_weights();
        $masteryweights = self::get_mastery_weights();

        // Get all visible courses (excluding the site course).
        $courses = $DB->get_records('course', ['visible' => 1], 'id ASC', 'id, shortname');
        $coursecount = 0;
        $usercount = 0;

        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }

            // Respect per-course enable setting.
            if (!\local_ace_is_enabled_for_course($course->id)) {
                continue;
            }

            $context = context_course::instance($course->id, IGNORE_MISSING);
            if (!$context) {
                continue;
            }

            // Get enrolled users with dashboard capability.
            $users = get_enrolled_users($context, 'local/ace:viewdashboard', 0, 'u.id');
            if (empty($users)) {
                continue;
            }

            $coursecount++;
            mtrace("Calculating scores for course {$course->shortname} (ID: {$course->id}) - "
                . count($users) . ' user(s)...');

            foreach ($users as $user) {
                // Calculate engagement score.
                $engagementdata = self::calculate_engagement($user->id, $course->id, $weights);
                self::save_engagement_score($user->id, $course->id, $engagementdata, $now);

                // Calculate mastery score.
                $masterydata = self::calculate_mastery($user->id, $course->id, $masteryweights);
                self::save_mastery_score($user->id, $course->id, $masterydata, $now);

                // Calculate dropout risk based on engagement.
                $dropoutrisk = self::calculate_dropout_risk($engagementdata['score'], $masterydata['score']);

                // Save analytics snapshot.
                $DB->insert_record('local_ace_analytics', (object) [
                    'userid' => $user->id,
                    'courseid' => $course->id,
                    'engagement_score' => $engagementdata['score'],
                    'mastery_score' => $masterydata['score'],
                    'dropout_risk' => $dropoutrisk,
                    'snapshot_date' => $snapshotdate,
                    'timecreated' => $now,
                ]);

                $usercount++;
            }
        }

        mtrace("Score calculation complete: processed {$usercount} user(s) across {$coursecount} course(s).");
    }

    /**
     * Get the configured engagement score weights.
     *
     * @return array Associative array with keys: completion, timeliness, participation, consistency.
     */
    private static function get_engagement_weights(): array {
        return [
            'completion' => (float) (get_config('local_ace', 'engagementweight_completion') ?: 25),
            'timeliness' => (float) (get_config('local_ace', 'engagementweight_timeliness') ?: 25),
            'participation' => (float) (get_config('local_ace', 'engagementweight_participation') ?: 25),
            'consistency' => (float) (get_config('local_ace', 'engagementweight_consistency') ?: 25),
        ];
    }

    /**
     * Get the configured mastery score weights.
     *
     * @return array Associative array with keys: grades, improvement, breadth.
     */
    private static function get_mastery_weights(): array {
        return [
            'grades' => (float) (get_config('local_ace', 'masteryweight_grades') ?: 40),
            'improvement' => (float) (get_config('local_ace', 'masteryweight_improvement') ?: 30),
            'breadth' => (float) (get_config('local_ace', 'masteryweight_breadth') ?: 30),
        ];
    }

    /**
     * Calculate the engagement score for a user in a course.
     *
     * The score is a weighted average of four sub-scores:
     * - Completion: ratio of completed activities to total activities.
     * - Timeliness: proportion of on-time submissions.
     * - Participation: forum and interactive activity participation.
     * - Consistency: login frequency consistency over the last 30 days.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param array $weights The weight configuration.
     * @return array Engagement data with keys: score, completion, timeliness, participation, consistency, trend.
     */
    private static function calculate_engagement(int $userid, int $courseid, array $weights): array {
        global $DB;

        // Calculate completion sub-score.
        $completionscore = self::calculate_completion_score($userid, $courseid);

        // Calculate timeliness sub-score.
        $timelinessscore = self::calculate_timeliness_score($userid, $courseid);

        // Calculate participation sub-score.
        $participationscore = self::calculate_participation_score($userid, $courseid);

        // Calculate consistency sub-score.
        $consistencyscore = self::calculate_consistency_score($userid, $courseid);

        // Compute weighted average.
        $totalweight = $weights['completion'] + $weights['timeliness']
            + $weights['participation'] + $weights['consistency'];

        $score = 0;
        if ($totalweight > 0) {
            $score = (
                ($completionscore * $weights['completion']) +
                ($timelinessscore * $weights['timeliness']) +
                ($participationscore * $weights['participation']) +
                ($consistencyscore * $weights['consistency'])
            ) / $totalweight;
        }

        $score = round(min(100, max(0, $score)), 4);

        // Determine trend based on previous score.
        $previousscore = $DB->get_field('local_ace_engagement', 'score', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        $trend = 'stable';
        if ($previousscore !== false) {
            $diff = $score - (float) $previousscore;
            if ($diff > 5) {
                $trend = 'improving';
            } else if ($diff < -5) {
                $trend = 'declining';
            }
        }

        return [
            'score' => $score,
            'completion' => round($completionscore, 4),
            'timeliness' => round($timelinessscore, 4),
            'participation' => round($participationscore, 4),
            'consistency' => round($consistencyscore, 4),
            'trend' => $trend,
        ];
    }

    /**
     * Calculate the activity completion sub-score.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float Score from 0-100.
     */
    private static function calculate_completion_score(int $userid, int $courseid): float {
        global $DB;

        // Count total trackable activities in the course.
        $totalactivities = $DB->count_records_select(
            'course_modules',
            'course = :courseid AND completion > 0 AND deletioninprogress = 0',
            ['courseid' => $courseid]
        );

        if ($totalactivities == 0) {
            return 0;
        }

        // Count completed activities for this user.
        $sql = "SELECT COUNT(cmc.id)
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cmc.userid = :userid
                   AND cmc.completionstate > 0";

        $completedactivities = $DB->count_records_sql($sql, [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);

        return ($completedactivities / $totalactivities) * 100;
    }

    /**
     * Calculate the timeliness sub-score based on on-time submissions.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float Score from 0-100.
     */
    private static function calculate_timeliness_score(int $userid, int $courseid): float {
        global $DB;

        // Check assignment submissions relative to due dates.
        $sql = "SELECT COUNT(s.id) AS total,
                       SUM(CASE WHEN s.timemodified <= a.duedate OR a.duedate = 0 THEN 1 ELSE 0 END) AS ontime
                  FROM {assign_submission} s
                  JOIN {assign} a ON a.id = s.assignment
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                 WHERE a.course = :courseid
                   AND s.userid = :userid
                   AND s.status = 'submitted'";

        $result = $DB->get_record_sql($sql, [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);

        if (empty($result) || (int) $result->total == 0) {
            return 100; // No submissions to evaluate; default to full score.
        }

        return ((int) $result->ontime / (int) $result->total) * 100;
    }

    /**
     * Calculate the participation sub-score based on forum activity.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float Score from 0-100.
     */
    private static function calculate_participation_score(int $userid, int $courseid): float {
        global $DB;

        // Count forum posts by this user in this course over the last 30 days.
        $since = time() - (30 * DAYSECS);

        $sql = "SELECT COUNT(fp.id)
                  FROM {forum_posts} fp
                  JOIN {forum_discussions} fd ON fd.id = fp.discussion
                  JOIN {forum} f ON f.id = fd.forum
                 WHERE f.course = :courseid
                   AND fp.userid = :userid
                   AND fp.created >= :since";

        $postcount = (int) $DB->count_records_sql($sql, [
            'courseid' => $courseid,
            'userid' => $userid,
            'since' => $since,
        ]);

        // Score: up to 100 for 10+ posts in 30 days.
        return min(100, ($postcount / 10) * 100);
    }

    /**
     * Calculate the consistency sub-score based on login frequency.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float Score from 0-100.
     */
    private static function calculate_consistency_score(int $userid, int $courseid): float {
        global $DB;

        $since = time() - (30 * DAYSECS);

        // Count distinct days with course access in the last 30 days.
        // Use FLOOR(timecreated / DAYSECS) for cross-DB compatibility (no FROM_UNIXTIME).
        $sql = "SELECT COUNT(DISTINCT FLOOR(timecreated / :daysecs)) AS accessdays
                  FROM {logstore_standard_log}
                 WHERE userid = :userid
                   AND courseid = :courseid
                   AND timecreated >= :since
                   AND action = 'viewed'
                   AND target = 'course'";

        $result = $DB->get_record_sql($sql, [
            'daysecs' => DAYSECS,
            'userid' => $userid,
            'courseid' => $courseid,
            'since' => $since,
        ]);

        $accessdays = $result ? (int) $result->accessdays : 0;

        // Score: up to 100 for accessing 20+ of the last 30 days.
        return min(100, ($accessdays / 20) * 100);
    }

    /**
     * Calculate the mastery score for a user in a course.
     *
     * The score is a weighted average of three sub-scores:
     * - Grades: average grade performance as a percentage.
     * - Improvement: grade improvement trend.
     * - Breadth: breadth of activity types completed.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param array $weights The weight configuration.
     * @return array Mastery data with keys: score, grade, improvement, breadth.
     */
    private static function calculate_mastery(int $userid, int $courseid, array $weights): array {
        global $DB;

        // Grade sub-score: average normalised grades.
        $sql = "SELECT AVG(CASE WHEN gi.grademax > 0 THEN (gg.finalgrade / gi.grademax) * 100 ELSE 0 END) AS avgscore
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.courseid = :courseid
                   AND gg.userid = :userid
                   AND gg.finalgrade IS NOT NULL
                   AND gi.itemtype != 'course'";

        $graderesult = $DB->get_record_sql($sql, [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);
        $gradescore = $graderesult && $graderesult->avgscore !== null
            ? (float) $graderesult->avgscore : 0;

        // Improvement sub-score: compare recent grades to earlier grades.
        $improvementscore = self::calculate_improvement_score($userid, $courseid);

        // Breadth sub-score: variety of activity module types completed.
        $breadthscore = self::calculate_breadth_score($userid, $courseid);

        // Compute weighted average.
        $totalweight = $weights['grades'] + $weights['improvement'] + $weights['breadth'];
        $score = 0;
        if ($totalweight > 0) {
            $score = (
                ($gradescore * $weights['grades']) +
                ($improvementscore * $weights['improvement']) +
                ($breadthscore * $weights['breadth'])
            ) / $totalweight;
        }

        $score = round(min(100, max(0, $score)), 4);

        return [
            'score' => $score,
            'grade' => round($gradescore, 4),
            'improvement' => round($improvementscore, 4),
            'breadth' => round($breadthscore, 4),
        ];
    }

    /**
     * Calculate the grade improvement sub-score.
     *
     * Compares the average of the most recent half of grades to the earlier half.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float Score from 0-100.
     */
    private static function calculate_improvement_score(int $userid, int $courseid): float {
        global $DB;

        $sql = "SELECT gg.finalgrade, gi.grademax
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.courseid = :courseid
                   AND gg.userid = :userid
                   AND gg.finalgrade IS NOT NULL
                   AND gi.itemtype != 'course'
                   AND gi.grademax > 0
              ORDER BY gg.timemodified ASC";

        $grades = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);

        if (count($grades) < 2) {
            return 50; // Not enough data; return neutral score.
        }

        $normalised = [];
        foreach ($grades as $grade) {
            $normalised[] = ((float) $grade->finalgrade / (float) $grade->grademax) * 100;
        }

        $midpoint = (int) floor(count($normalised) / 2);
        $earlyhalf = array_slice($normalised, 0, $midpoint);
        $latehalf = array_slice($normalised, $midpoint);

        $earlyavg = array_sum($earlyhalf) / count($earlyhalf);
        $lateavg = array_sum($latehalf) / count($latehalf);

        // Improvement: positive difference means improvement.
        $diff = $lateavg - $earlyavg;

        // Scale: +20 points improvement = 100, -20 = 0, 0 = 50.
        return min(100, max(0, 50 + ($diff * 2.5)));
    }

    /**
     * Calculate the breadth sub-score based on variety of activity types completed.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float Score from 0-100.
     */
    private static function calculate_breadth_score(int $userid, int $courseid): float {
        global $DB;

        // Count distinct module types available in the course.
        $sql = "SELECT COUNT(DISTINCT m.name)
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                   AND cm.deletioninprogress = 0
                   AND cm.completion > 0";

        $totalmodtypes = (int) $DB->count_records_sql($sql, ['courseid' => $courseid]);

        if ($totalmodtypes == 0) {
            return 0;
        }

        // Count distinct module types the user has completed.
        $sql = "SELECT COUNT(DISTINCT m.name)
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                   AND cmc.userid = :userid
                   AND cmc.completionstate > 0";

        $completedmodtypes = (int) $DB->count_records_sql($sql, [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);

        return ($completedmodtypes / $totalmodtypes) * 100;
    }

    /**
     * Save or update the engagement score for a user in a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param array $data The engagement data.
     * @param int $now The current timestamp.
     * @return void
     */
    private static function save_engagement_score(int $userid, int $courseid, array $data, int $now): void {
        global $DB;

        $existing = $DB->get_record('local_ace_engagement', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        if ($existing) {
            $DB->update_record('local_ace_engagement', (object) [
                'id' => $existing->id,
                'score' => $data['score'],
                'completion_score' => $data['completion'],
                'timeliness_score' => $data['timeliness'],
                'participation_score' => $data['participation'],
                'consistency_score' => $data['consistency'],
                'trend' => $data['trend'],
                'timemodified' => $now,
            ]);
        } else {
            $DB->insert_record('local_ace_engagement', (object) [
                'userid' => $userid,
                'courseid' => $courseid,
                'score' => $data['score'],
                'completion_score' => $data['completion'],
                'timeliness_score' => $data['timeliness'],
                'participation_score' => $data['participation'],
                'consistency_score' => $data['consistency'],
                'trend' => $data['trend'],
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Save or update the mastery score for a user in a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param array $data The mastery data.
     * @param int $now The current timestamp.
     * @return void
     */
    private static function save_mastery_score(int $userid, int $courseid, array $data, int $now): void {
        global $DB;

        $existing = $DB->get_record('local_ace_mastery', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        if ($existing) {
            $DB->update_record('local_ace_mastery', (object) [
                'id' => $existing->id,
                'score' => $data['score'],
                'grade_score' => $data['grade'],
                'improvement_score' => $data['improvement'],
                'breadth_score' => $data['breadth'],
                'timemodified' => $now,
            ]);
        } else {
            $DB->insert_record('local_ace_mastery', (object) [
                'userid' => $userid,
                'courseid' => $courseid,
                'score' => $data['score'],
                'grade_score' => $data['grade'],
                'improvement_score' => $data['improvement'],
                'breadth_score' => $data['breadth'],
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Calculate dropout risk based on engagement and mastery scores.
     *
     * A lower combined score indicates higher dropout risk. The risk is
     * normalised to a 0-1 scale where 1 is the highest risk.
     *
     * @param float $engagementscore The engagement score (0-100).
     * @param float $masteryscore The mastery score (0-100).
     * @return float Dropout risk from 0 (no risk) to 1 (maximum risk).
     */
    private static function calculate_dropout_risk(float $engagementscore, float $masteryscore): float {
        // Weight engagement more heavily as it directly indicates student activity.
        $combinedscore = ($engagementscore * 0.7) + ($masteryscore * 0.3);

        // Invert: lower scores mean higher dropout risk.
        $risk = max(0, min(1, (100 - $combinedscore) / 100));

        return round($risk, 4);
    }

    /**
     * Get the snapshot date as a normalised timestamp (midnight of the current day).
     *
     * @param int $timestamp The current timestamp.
     * @return int The normalised snapshot date timestamp.
     */
    private static function get_snapshot_date(int $timestamp): int {
        return strtotime('midnight', $timestamp);
    }
}
