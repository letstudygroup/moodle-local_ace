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
 * Tests for the engagement_scorer class.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ace\engagement_scorer
 */
final class engagement_scorer_test extends \advanced_testcase {
    /**
     * Test that calculate returns a score between 0 and 100.
     */
    public function test_calculate_returns_valid_score(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $scorer = new engagement_scorer();
        $score = $scorer->calculate($user->id, $course->id);

        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /**
     * Test that completion score is 0 when no activities exist.
     */
    public function test_completion_score_no_activities(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $scorer = new engagement_scorer();
        $score = $scorer->get_completion_score($user->id, $course->id);

        $this->assertEquals(0.0, $score);
    }

    /**
     * Test that completion score reflects actual completions.
     */
    public function test_completion_score_with_completions(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create two activities with completion tracking.
        $assign1 = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);
        $assign2 = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        // Mark one as complete.
        $cmid = get_coursemodule_from_instance('assign', $assign1->id, $course->id)->id;
        $completion = new \completion_info($course);
        $cm = get_coursemodule_from_id('assign', $cmid);

        // Directly insert completion record.
        $DB->insert_record('course_modules_completion', [
            'coursemoduleid' => $cmid,
            'userid' => $user->id,
            'completionstate' => 1,
            'timemodified' => time(),
        ]);

        $scorer = new engagement_scorer();
        $score = $scorer->get_completion_score($user->id, $course->id);

        // 1 out of 2 = 50%.
        $this->assertEquals(50.0, $score);
    }

    /**
     * Test that timeliness score handles no timed items.
     */
    public function test_timeliness_score_no_items(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $scorer = new engagement_scorer();
        $score = $scorer->get_timeliness_score($user->id, $course->id);

        // No timed items should give 100.
        $this->assertEquals(100.0, $score);
    }

    /**
     * Test that participation score handles no forums.
     */
    public function test_participation_score_no_forums(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $scorer = new engagement_scorer();
        $score = $scorer->get_participation_score($user->id, $course->id);

        // No forums should give 100.
        $this->assertEquals(100.0, $score);
    }

    /**
     * Test that consistency score is 0 for brand new user.
     */
    public function test_consistency_score_new_user(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $scorer = new engagement_scorer();
        $score = $scorer->get_consistency_score($user->id, $course->id);

        $this->assertEquals(0.0, $score);
    }

    /**
     * Test that save_score creates a record.
     */
    public function test_save_score_creates_record(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $scorer = new engagement_scorer();
        $scorer->save_score($user->id, $course->id, 75.5, [
            'completion_score' => 80.0,
            'timeliness_score' => 70.0,
            'participation_score' => 75.0,
            'consistency_score' => 60.0,
        ]);

        $record = $DB->get_record('local_ace_engagement', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertNotEmpty($record);
        $this->assertEquals(75.5, (float)$record->score);
        $this->assertEquals('stable', $record->trend);
    }

    /**
     * Test that save_score updates existing record and detects trend.
     */
    public function test_save_score_updates_and_detects_trend(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $components = [
            'completion_score' => 50.0,
            'timeliness_score' => 50.0,
            'participation_score' => 50.0,
            'consistency_score' => 50.0,
        ];

        $scorer = new engagement_scorer();

        // First save.
        $scorer->save_score($user->id, $course->id, 50.0, $components);

        // Second save with higher score (should be "improving").
        $scorer->save_score($user->id, $course->id, 80.0, $components);

        $record = $DB->get_record('local_ace_engagement', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertEquals(80.0, (float)$record->score);
        $this->assertEquals('improving', $record->trend);
    }
}
