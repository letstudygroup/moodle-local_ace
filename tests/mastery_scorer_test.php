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
 * Tests for the mastery_scorer class.
 *
 * @package    local_ace
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ace\mastery_scorer
 */
final class mastery_scorer_test extends \advanced_testcase {
    /**
     * Test that calculate returns a score between 0 and 100.
     */
    public function test_calculate_returns_valid_score(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $scorer = new mastery_scorer();
        $score = $scorer->calculate($user->id, $course->id);

        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /**
     * Test that grade score returns 0 when no grades exist.
     */
    public function test_grade_score_no_grades(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $scorer = new mastery_scorer();
        $score = $scorer->get_grade_score($user->id, $course->id);

        $this->assertEquals(0.0, $score);
    }

    /**
     * Test that grade score reflects actual grades.
     */
    public function test_grade_score_with_grades(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create an assignment with a grade.
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);

        // Look up the grade item created for the assignment.
        $gradeitem = $DB->get_record('grade_items', [
            'courseid' => $course->id,
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
        ]);
        $this->assertNotFalse($gradeitem);

        // Insert a grade record (80 out of 100).
        $DB->insert_record('grade_grades', (object) [
            'itemid' => $gradeitem->id,
            'userid' => $user->id,
            'finalgrade' => 80.0,
            'rawgrademax' => 100.0,
            'rawgrademin' => 0.0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $scorer = new mastery_scorer();
        $score = $scorer->get_grade_score($user->id, $course->id);

        $this->assertEquals(80.0, $score);
    }

    /**
     * Test that breadth score returns 0 when no activities exist.
     */
    public function test_breadth_score_no_activities(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $scorer = new mastery_scorer();
        $score = $scorer->get_breadth_score($user->id, $course->id);

        $this->assertEquals(0.0, $score);
    }

    /**
     * Test that breadth score reflects completed activity types.
     */
    public function test_breadth_score_with_completions(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create two different activity types with completion tracking.
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);
        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        // Complete only the assignment.
        $cmassign = get_coursemodule_from_instance('assign', $assign->id, $course->id);
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $cmassign->id,
            'userid' => $user->id,
            'completionstate' => 1,
            'timemodified' => time(),
        ]);

        $scorer = new mastery_scorer();
        $score = $scorer->get_breadth_score($user->id, $course->id);

        // 1 type completed out of 2 types available = 50%.
        $this->assertEquals(50.0, $score);
    }

    /**
     * Test that improvement score returns neutral with insufficient data.
     */
    public function test_improvement_score_insufficient_data(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $scorer = new mastery_scorer();
        $score = $scorer->get_improvement_score($user->id, $course->id);

        // With no grades at all, should return 50.0 (neutral).
        $this->assertEquals(50.0, $score);
    }

    /**
     * Test that save_score creates a record in the database.
     */
    public function test_save_score_creates_record(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $scorer = new mastery_scorer();
        $scorer->save_score($user->id, $course->id, 65.0, [
            'grade_score' => 70.0,
            'improvement_score' => 50.0,
            'breadth_score' => 60.0,
        ]);

        $record = $DB->get_record('local_ace_mastery', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertNotEmpty($record);
        $this->assertEquals(65.0, (float) $record->score);
        $this->assertEquals(70.0, (float) $record->grade_score);
        $this->assertEquals(50.0, (float) $record->improvement_score);
        $this->assertEquals(60.0, (float) $record->breadth_score);
    }

    /**
     * Test that save_score updates an existing record.
     */
    public function test_save_score_updates_record(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $scorer = new mastery_scorer();

        // First save.
        $scorer->save_score($user->id, $course->id, 50.0, [
            'grade_score' => 50.0,
            'improvement_score' => 50.0,
            'breadth_score' => 50.0,
        ]);

        // Second save with updated scores.
        $scorer->save_score($user->id, $course->id, 85.0, [
            'grade_score' => 90.0,
            'improvement_score' => 80.0,
            'breadth_score' => 75.0,
        ]);

        // There should still be only one record.
        $records = $DB->get_records('local_ace_mastery', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);
        $this->assertCount(1, $records);

        $record = reset($records);
        $this->assertEquals(85.0, (float) $record->score);
        $this->assertEquals(90.0, (float) $record->grade_score);
    }
}
