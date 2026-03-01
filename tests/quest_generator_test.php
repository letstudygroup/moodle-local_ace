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
 * Tests for the quest_generator class.
 *
 * @package    local_ace
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ace\quest_generator
 */
final class quest_generator_test extends \advanced_testcase {
    /**
     * Test that generate_daily_quests creates the configured number of quests.
     */
    public function test_generate_daily_quests_creates_quests(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create several activities with completion tracking to provide quest options.
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'grade' => 100,
        ]);
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'grade' => 100,
        ]);
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        set_config('dailyquestcount', 3, 'local_ace');

        $generator = new quest_generator();
        $quests = $generator->generate_daily_quests($user->id, $course->id);

        $this->assertCount(3, $quests);

        // Verify each quest is associated with the correct user and course.
        foreach ($quests as $quest) {
            $this->assertEquals($user->id, $quest->userid);
            $this->assertEquals($course->id, $quest->courseid);
            $this->assertEquals(quest_generator::STATUS_ACTIVE, $quest->status);
        }
    }

    /**
     * Test that generate_daily_quests does not create duplicate active quests.
     */
    public function test_generate_daily_quests_no_duplicates(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create activities to provide quest variety.
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'grade' => 100,
        ]);
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        set_config('dailyquestcount', 3, 'local_ace');

        $generator = new quest_generator();

        // First generation creates quests.
        $quests1 = $generator->generate_daily_quests($user->id, $course->id);
        $this->assertNotEmpty($quests1);

        // Second generation should return empty as today's quota is filled.
        $quests2 = $generator->generate_daily_quests($user->id, $course->id);
        $this->assertCount(0, $quests2);
    }

    /**
     * Test that complete_quest awards XP.
     */
    public function test_complete_quest_awards_xp(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        set_config('xp_per_quest', 100, 'local_ace');

        $generator = new quest_generator();
        $quest = $generator->create_quest($user->id, $course->id, quest_generator::TYPE_LOGIN, [
            'title' => 'Daily Login',
            'description' => 'Log in to the course today.',
            'xpreward' => 100,
        ]);

        $xpearned = $generator->complete_quest($quest->id, $user->id);

        $this->assertEquals(100, $xpearned);

        // Verify XP was actually awarded in the xp table.
        $xpmanager = new xp_manager();
        $totalxp = $xpmanager->get_xp($user->id, $course->id);
        $this->assertEquals(100, $totalxp);
    }

    /**
     * Test that complete_quest changes quest status to completed.
     */
    public function test_complete_quest_changes_status(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $generator = new quest_generator();
        $quest = $generator->create_quest($user->id, $course->id, quest_generator::TYPE_LOGIN, [
            'title' => 'Daily Login',
            'description' => 'Log in to the course today.',
        ]);

        // Verify the quest starts as active.
        $this->assertEquals(quest_generator::STATUS_ACTIVE, $quest->status);

        $generator->complete_quest($quest->id, $user->id);

        // Verify the status changed to completed in the database.
        $updated = $DB->get_record('local_ace_quests', ['id' => $quest->id]);
        $this->assertEquals(quest_generator::STATUS_COMPLETED, $updated->status);
        $this->assertNotNull($updated->completeddate);
        $this->assertGreaterThan(0, (int) $updated->completeddate);
    }

    /**
     * Test that all quest types returned by get_available_quest_types are valid.
     */
    public function test_quest_types_are_valid(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create activities of various types to trigger different quest type generation.
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'grade' => 100,
        ]);
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        $validtypes = [
            quest_generator::TYPE_ACTIVITY,
            quest_generator::TYPE_FORUM,
            quest_generator::TYPE_QUIZ,
            quest_generator::TYPE_RESOURCE,
            quest_generator::TYPE_LOGIN,
            quest_generator::TYPE_GRADE,
        ];

        $generator = new quest_generator();
        $available = $generator->get_available_quest_types($user->id, $course->id);

        $this->assertNotEmpty($available);

        foreach ($available as $typedata) {
            $this->assertArrayHasKey('type', $typedata);
            $this->assertContains(
                $typedata['type'],
                $validtypes,
                "Quest type '{$typedata['type']}' is not a valid type."
            );
        }
    }

    /**
     * Test that creating a quest with an invalid type returns null.
     */
    public function test_create_quest_invalid_type_returns_null(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $generator = new quest_generator();
        $quest = $generator->create_quest($user->id, $course->id, 'invalid_type', []);

        $this->assertNull($quest);
    }

    /**
     * Test that completing an already completed quest throws exception.
     */
    public function test_complete_already_completed_quest_throws(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $generator = new quest_generator();
        $quest = $generator->create_quest($user->id, $course->id, quest_generator::TYPE_LOGIN, [
            'title' => 'Daily Login',
        ]);

        // Complete it once.
        $generator->complete_quest($quest->id, $user->id);

        // Attempting to complete again should throw.
        $this->expectException(\moodle_exception::class);
        $generator->complete_quest($quest->id, $user->id);
    }
}
