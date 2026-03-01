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
 * Mastery scorer class for calculating mastery scores.
 *
 * Calculates a weighted average of grade performance, improvement trend,
 * and breadth of activity completion for a user in a given course.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mastery_scorer {
    /** @var int Default weight for grade performance component. */
    private const DEFAULT_WEIGHT_GRADES = 40;

    /** @var int Default weight for improvement trend component. */
    private const DEFAULT_WEIGHT_IMPROVEMENT = 30;

    /** @var int Default weight for breadth of completion component. */
    private const DEFAULT_WEIGHT_BREADTH = 30;

    /**
     * Calculate the composite mastery score for a user in a course.
     *
     * The score is a weighted average of grade performance, improvement
     * trend, and breadth of activity type completion. Weights are read
     * from admin settings and normalised.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return float The composite mastery score (0-100).
     */
    public function calculate(int $userid, int $courseid): float {
        $gradescore = $this->get_grade_score($userid, $courseid);
        $improvementscore = $this->get_improvement_score($userid, $courseid);
        $breadthscore = $this->get_breadth_score($userid, $courseid);

        $wgrades = (float) get_config('local_aceengine', 'masteryweight_grades')
            ?: self::DEFAULT_WEIGHT_GRADES;
        $wimprovement = (float) get_config('local_aceengine', 'masteryweight_improvement')
            ?: self::DEFAULT_WEIGHT_IMPROVEMENT;
        $wbreadth = (float) get_config('local_aceengine', 'masteryweight_breadth')
            ?: self::DEFAULT_WEIGHT_BREADTH;

        $totalweight = $wgrades + $wimprovement + $wbreadth;

        // Guard against division by zero when all weights are zero.
        if ($totalweight <= 0) {
            $totalweight = 100.0;
            $wgrades = self::DEFAULT_WEIGHT_GRADES;
            $wimprovement = self::DEFAULT_WEIGHT_IMPROVEMENT;
            $wbreadth = self::DEFAULT_WEIGHT_BREADTH;
        }

        $score = (
            ($gradescore * $wgrades) +
            ($improvementscore * $wimprovement) +
            ($breadthscore * $wbreadth)
        ) / $totalweight;

        $score = round(min(100.0, max(0.0, $score)), 4);

        $components = [
            'grade_score' => $gradescore,
            'improvement_score' => $improvementscore,
            'breadth_score' => $breadthscore,
        ];

        $this->save_score($userid, $courseid, $score, $components);

        return $score;
    }

    /**
     * Calculate the average grade performance for a user in a course.
     *
     * Queries the grade_grades table for all graded items in the course
     * and computes the average percentage score.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return float The grade score (0-100).
     */
    public function get_grade_score(int $userid, int $courseid): float {
        global $DB;

        // Get all graded items for the course (excluding the course total).
        $sql = "SELECT gg.id, gg.finalgrade, gi.grademax, gi.grademin
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gg.userid = :userid
                   AND gi.courseid = :courseid
                   AND gi.itemtype != 'course'
                   AND gg.finalgrade IS NOT NULL
                   AND gi.grademax > gi.grademin";
        $grades = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        if (empty($grades)) {
            return 0.0;
        }

        $totalpercentage = 0.0;
        $count = 0;

        foreach ($grades as $grade) {
            $range = (float) $grade->grademax - (float) $grade->grademin;
            if ($range > 0) {
                $percentage = (((float) $grade->finalgrade - (float) $grade->grademin) / $range) * 100.0;
                $totalpercentage += max(0.0, min(100.0, $percentage));
                $count++;
            }
        }

        if ($count === 0) {
            return 0.0;
        }

        return round($totalpercentage / $count, 4);
    }

    /**
     * Calculate the improvement score based on grade trends.
     *
     * Compares the average grades from the first half of graded items
     * (by timemodified) versus the second half. A significant improvement
     * yields a higher score.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return float The improvement score (0-100).
     */
    public function get_improvement_score(int $userid, int $courseid): float {
        global $DB;

        // Get all graded items ordered by time to split into halves.
        $sql = "SELECT gg.id, gg.finalgrade, gi.grademax, gi.grademin, gg.timemodified
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gg.userid = :userid
                   AND gi.courseid = :courseid
                   AND gi.itemtype != 'course'
                   AND gg.finalgrade IS NOT NULL
                   AND gi.grademax > gi.grademin
              ORDER BY gg.timemodified ASC";
        $grades = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        $gradearray = array_values($grades);
        $count = count($gradearray);

        // Need at least 2 graded items to compare halves.
        if ($count < 2) {
            return 50.0; // Neutral score — not enough data.
        }

        $midpoint = (int) floor($count / 2);

        $firsthalfavg = $this->calculate_half_average(array_slice($gradearray, 0, $midpoint));
        $secondhalfavg = $this->calculate_half_average(array_slice($gradearray, $midpoint));

        // Both halves at zero means no meaningful data.
        if ($firsthalfavg === 0.0 && $secondhalfavg === 0.0) {
            return 50.0;
        }

        // Calculate improvement as a relative change.
        // Positive difference means improvement; negative means decline.
        $diff = $secondhalfavg - $firsthalfavg;

        // Map the difference (-100..+100) to a score (0..100).
        // A +50 point improvement maps to 100, -50 maps to 0, no change maps to 50.
        $score = 50.0 + $diff;
        $score = min(100.0, max(0.0, $score));

        return round($score, 4);
    }

    /**
     * Calculate the average percentage for a set of grade records.
     *
     * @param array $grades Array of grade record objects.
     * @return float The average percentage score.
     */
    private function calculate_half_average(array $grades): float {
        if (empty($grades)) {
            return 0.0;
        }

        $total = 0.0;
        $count = 0;

        foreach ($grades as $grade) {
            $range = (float) $grade->grademax - (float) $grade->grademin;
            if ($range > 0) {
                $percentage = (((float) $grade->finalgrade - (float) $grade->grademin) / $range) * 100.0;
                $total += max(0.0, min(100.0, $percentage));
                $count++;
            }
        }

        return ($count > 0) ? ($total / $count) : 0.0;
    }

    /**
     * Calculate the breadth of completion across activity types.
     *
     * Counts the distinct module types (assign, quiz, forum, etc.) for
     * which the user has completed at least one activity, divided by the
     * total number of distinct module types available in the course.
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return float The breadth score (0-100).
     */
    public function get_breadth_score(int $userid, int $courseid): float {
        global $DB;

        // Count distinct module types available in the course with completion tracking.
        $sql = "SELECT COUNT(DISTINCT m.name) AS typecount
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                   AND cm.completion > 0
                   AND cm.deletioninprogress = 0";
        $totalrecord = $DB->get_record_sql($sql, ['courseid' => $courseid]);
        $totaltypes = $totalrecord ? (int) $totalrecord->typecount : 0;

        if ($totaltypes === 0) {
            return 0.0;
        }

        // Count distinct module types the user has completed.
        $sql = "SELECT COUNT(DISTINCT m.name) AS typecount
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cmc.userid = :userid
                   AND cm.course = :courseid
                   AND cm.completion > 0
                   AND cm.deletioninprogress = 0
                   AND cmc.completionstate IN (1, 2)";
        $completedrecord = $DB->get_record_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $completedtypes = $completedrecord ? (int) $completedrecord->typecount : 0;

        return round(($completedtypes / $totaltypes) * 100.0, 4);
    }

    /**
     * Save the mastery score and components to the database.
     *
     * Inserts a new record or updates the existing record for the
     * user/course pair in the local_aceengine_mastery table.
     *
     * @param int   $userid     The ID of the user.
     * @param int   $courseid   The ID of the course.
     * @param float $score      The composite mastery score.
     * @param array $components Associative array of component scores.
     * @return void
     */
    public function save_score(int $userid, int $courseid, float $score, array $components): void {
        global $DB;

        $now = time();

        $existing = $DB->get_record('local_aceengine_mastery', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->score = $score;
        $record->grade_score = $components['grade_score'] ?? 0.0;
        $record->improvement_score = $components['improvement_score'] ?? 0.0;
        $record->breadth_score = $components['breadth_score'] ?? 0.0;
        $record->timemodified = $now;

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_aceengine_mastery', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_aceengine_mastery', $record);
        }
    }
}
