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

/**
 * Dropout risk predictor for local_ace.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ace\analytics;

/**
 * Calculates dropout risk scores for users in a course.
 *
 * Uses a weighted formula combining inactivity, engagement trends,
 * completion rates, and grade trends to produce a risk score between 0.0 and 1.0.
 *
 * Formula:
 *   risk = (0.4 * inactivity_factor) + (0.3 * engagement_decline_factor)
 *        + (0.2 * completion_gap_factor) + (0.1 * grade_decline_factor)
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dropout_predictor {
    /** @var float Weight for inactivity factor. */
    private const WEIGHT_INACTIVITY = 0.4;

    /** @var float Weight for engagement decline factor. */
    private const WEIGHT_ENGAGEMENT = 0.3;

    /** @var float Weight for completion gap factor. */
    private const WEIGHT_COMPLETION = 0.2;

    /** @var float Weight for grade decline factor. */
    private const WEIGHT_GRADE = 0.1;

    /** @var int Maximum days of inactivity before reaching maximum risk factor. */
    private const MAX_INACTIVITY_DAYS = 30;

    /**
     * Predict the dropout risk score for a user in a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float Risk score between 0.0 (no risk) and 1.0 (maximum risk).
     */
    public function predict(int $userid, int $courseid): float {
        $inactivityfactor = $this->calculate_inactivity_factor($userid, $courseid);
        $engagementfactor = $this->calculate_engagement_decline_factor($userid, $courseid);
        $completionfactor = $this->calculate_completion_gap_factor($userid, $courseid);
        $gradefactor = $this->calculate_grade_decline_factor($userid, $courseid);

        $risk = (self::WEIGHT_INACTIVITY * $inactivityfactor)
              + (self::WEIGHT_ENGAGEMENT * $engagementfactor)
              + (self::WEIGHT_COMPLETION * $completionfactor)
              + (self::WEIGHT_GRADE * $gradefactor);

        // Clamp the risk score to [0.0, 1.0].
        return max(0.0, min(1.0, $risk));
    }

    /**
     * Get a human-readable risk label for the given score.
     *
     * @param float $score The risk score (0.0 to 1.0).
     * @return string The risk label string identifier.
     */
    public function get_risk_label(float $score): string {
        if ($score < 0.25) {
            return get_string('dropoutrisk_low', 'local_ace');
        } else if ($score < 0.5) {
            return get_string('dropoutrisk_medium', 'local_ace');
        } else if ($score < 0.75) {
            return get_string('dropoutrisk_high', 'local_ace');
        } else {
            return get_string('dropoutrisk_critical', 'local_ace');
        }
    }

    /**
     * Calculate the inactivity factor based on days since last course access.
     *
     * A user who has not accessed the course recently will have a higher factor.
     * The factor scales linearly from 0.0 (accessed today) to 1.0 (MAX_INACTIVITY_DAYS or more).
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float Inactivity factor between 0.0 and 1.0.
     */
    private function calculate_inactivity_factor(int $userid, int $courseid): float {
        global $DB;

        $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        if (empty($lastaccess)) {
            // No access record means the user may have never accessed the course.
            return 1.0;
        }

        $dayssince = (time() - $lastaccess) / DAYSECS;

        return min(1.0, $dayssince / self::MAX_INACTIVITY_DAYS);
    }

    /**
     * Calculate the engagement decline factor from ACE engagement data.
     *
     * Compares the current engagement score trend. A declining trend yields a higher factor.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float Engagement decline factor between 0.0 and 1.0.
     */
    private function calculate_engagement_decline_factor(int $userid, int $courseid): float {
        global $DB;

        $engagement = $DB->get_record('local_ace_engagement', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        if (empty($engagement)) {
            // No engagement data, assume moderate risk.
            return 0.5;
        }

        // Use the trend field and the overall score.
        $trendfactor = 0.0;
        switch ($engagement->trend) {
            case 'declining':
                $trendfactor = 0.8;
                break;
            case 'stable':
                $trendfactor = 0.3;
                break;
            case 'improving':
                $trendfactor = 0.0;
                break;
            default:
                $trendfactor = 0.5;
                break;
        }

        // Invert the engagement score: low engagement = higher risk.
        // Scores are stored as percentages (0-100), normalise to 0-1.
        $scorefactor = 1.0 - ((float) $engagement->score / 100.0);

        // Blend trend and score equally.
        return ($trendfactor + $scorefactor) / 2.0;
    }

    /**
     * Calculate the completion gap factor based on course completion progress.
     *
     * Compares the user's completion rate to the expected rate based on course timeline.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float Completion gap factor between 0.0 and 1.0.
     */
    private function calculate_completion_gap_factor(int $userid, int $courseid): float {
        global $DB;

        // Get the total number of activities with completion enabled.
        $totalactivities = $DB->count_records_sql(
            "SELECT COUNT(cm.id)
               FROM {course_modules} cm
              WHERE cm.course = :courseid
                AND cm.completion > 0
                AND cm.deletioninprogress = 0",
            ['courseid' => $courseid]
        );

        if ($totalactivities == 0) {
            // No completion tracking configured, so no gap can be calculated.
            return 0.0;
        }

        // Get the number of activities the user has completed.
        $completedactivities = $DB->count_records_sql(
            "SELECT COUNT(cmc.id)
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cm.course = :courseid
                AND cmc.userid = :userid
                AND cmc.completionstate > 0",
            [
                'courseid' => $courseid,
                'userid' => $userid,
            ]
        );

        $completionrate = (float) $completedactivities / (float) $totalactivities;

        // Calculate expected completion rate based on course duration elapsed.
        $course = $DB->get_record('course', ['id' => $courseid], 'startdate, enddate');
        $expectedrate = 1.0;

        if (!empty($course->startdate) && !empty($course->enddate) && $course->enddate > $course->startdate) {
            $elapsed = time() - $course->startdate;
            $duration = $course->enddate - $course->startdate;
            $expectedrate = min(1.0, max(0.0, (float) $elapsed / (float) $duration));
        }

        // The gap is how far behind the user is compared to the expected rate.
        $gap = max(0.0, $expectedrate - $completionrate);

        return min(1.0, $gap);
    }

    /**
     * Calculate the grade decline factor from analytics snapshots.
     *
     * Compares recent mastery scores to earlier ones to detect a declining trend.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float Grade decline factor between 0.0 and 1.0.
     */
    private function calculate_grade_decline_factor(int $userid, int $courseid): float {
        global $DB;

        // Get the two most recent analytics snapshots for this user and course.
        $snapshots = $DB->get_records_sql(
            "SELECT id, mastery_score, snapshot_date
               FROM {local_ace_analytics}
              WHERE userid = :userid
                AND courseid = :courseid
              ORDER BY snapshot_date DESC",
            [
                'userid' => $userid,
                'courseid' => $courseid,
            ],
            0,
            2
        );

        if (count($snapshots) < 2) {
            // Not enough data to determine a trend.
            // If we have one snapshot, use its mastery score inversely.
            if (count($snapshots) == 1) {
                $snapshot = reset($snapshots);
                // Mastery scores are stored as percentages (0-100), normalise to 0-1.
                return max(0.0, 1.0 - ((float) $snapshot->mastery_score / 100.0));
            }
            return 0.5;
        }

        $snapshots = array_values($snapshots);
        $recent = (float) $snapshots[0]->mastery_score;
        $previous = (float) $snapshots[1]->mastery_score;

        if ($previous == 0.0) {
            // Avoid division by zero; if previous was zero, any positive score is improvement.
            return ($recent > 0.0) ? 0.0 : 0.5;
        }

        // Calculate the proportional decline.
        $change = ($recent - $previous) / $previous;

        if ($change >= 0.0) {
            // No decline, grades are stable or improving.
            return 0.0;
        }

        // Map the decline to a factor (absolute change capped at 1.0).
        return min(1.0, abs($change));
    }
}
