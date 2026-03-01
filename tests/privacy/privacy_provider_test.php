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

namespace local_aceengine\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the privacy provider.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aceengine\privacy\provider
 */
final class privacy_provider_test extends provider_testcase {
    /**
     * Test that metadata collection is not empty.
     */
    public function test_get_metadata(): void {
        $collection = new collection('local_aceengine');
        $result = provider::get_metadata($collection);

        $this->assertNotEmpty($result);
        $items = $result->get_collection();

        // The provider describes at least 7 tables (engagement, mastery, quests, xp,
        // team_xp, analytics, mission_templates).
        $this->assertGreaterThanOrEqual(7, count($items));
    }

    /**
     * Test that get_contexts_for_userid returns the correct contexts.
     */
    public function test_get_contexts_for_userid(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $coursecontext = \context_course::instance($course->id);

        // Insert engagement data for the user.
        $DB->insert_record('local_aceengine_engagement', (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'score' => 75.0,
            'completion_score' => 80.0,
            'timeliness_score' => 70.0,
            'participation_score' => 75.0,
            'consistency_score' => 60.0,
            'trend' => 'stable',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Insert XP data for the user.
        $DB->insert_record('local_aceengine_xp', (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'xp' => 200,
            'level' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $contextids = $contextlist->get_contextids();

        $this->assertNotEmpty($contextids);
        $this->assertContains($coursecontext->id, $contextids);
    }

    /**
     * Test that no contexts are returned for a user with no data.
     */
    public function test_get_contexts_for_userid_no_data(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertEmpty($contextlist->get_contextids());
    }

    /**
     * Test that user data is exported correctly.
     */
    public function test_export_user_data(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $coursecontext = \context_course::instance($course->id);

        // Insert data in multiple tables.
        $DB->insert_record('local_aceengine_engagement', (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'score' => 75.0,
            'completion_score' => 80.0,
            'timeliness_score' => 70.0,
            'participation_score' => 75.0,
            'consistency_score' => 60.0,
            'trend' => 'improving',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $DB->insert_record('local_aceengine_xp', (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'xp' => 350,
            'level' => 3,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $DB->insert_record('local_aceengine_quests', (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'questtype' => 'login',
            'title' => 'Daily Login',
            'description' => 'Log in today.',
            'xpreward' => 50,
            'difficulty' => 1,
            'status' => 'completed',
            'expirydate' => time() + DAYSECS,
            'completeddate' => time(),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Export data.
        $contextlist = new approved_contextlist($user, 'local_aceengine', [$coursecontext->id]);
        provider::export_user_data($contextlist);

        // Verify data was written by the privacy writer.
        $writer = writer::with_context($coursecontext);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test that data is deleted for a specific user.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        $coursecontext = \context_course::instance($course->id);

        // Insert data for both users.
        foreach ([$user1, $user2] as $user) {
            $DB->insert_record('local_aceengine_engagement', (object) [
                'userid' => $user->id,
                'courseid' => $course->id,
                'score' => 75.0,
                'completion_score' => 80.0,
                'timeliness_score' => 70.0,
                'participation_score' => 75.0,
                'consistency_score' => 60.0,
                'trend' => 'stable',
                'timecreated' => time(),
                'timemodified' => time(),
            ]);

            $DB->insert_record('local_aceengine_xp', (object) [
                'userid' => $user->id,
                'courseid' => $course->id,
                'xp' => 200,
                'level' => 2,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);

            $DB->insert_record('local_aceengine_quests', (object) [
                'userid' => $user->id,
                'courseid' => $course->id,
                'questtype' => 'login',
                'title' => 'Daily Login',
                'description' => 'Log in today.',
                'xpreward' => 50,
                'difficulty' => 1,
                'status' => 'active',
                'expirydate' => time() + DAYSECS,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        // Delete data only for user1.
        $contextlist = new approved_contextlist($user1, 'local_aceengine', [$coursecontext->id]);
        provider::delete_data_for_user($contextlist);

        // User1's data should be gone.
        $this->assertFalse($DB->record_exists('local_aceengine_engagement', [
            'userid' => $user1->id,
            'courseid' => $course->id,
        ]));
        $this->assertFalse($DB->record_exists('local_aceengine_xp', [
            'userid' => $user1->id,
            'courseid' => $course->id,
        ]));
        $this->assertFalse($DB->record_exists('local_aceengine_quests', [
            'userid' => $user1->id,
            'courseid' => $course->id,
        ]));

        // User2's data should still exist.
        $this->assertTrue($DB->record_exists('local_aceengine_engagement', [
            'userid' => $user2->id,
            'courseid' => $course->id,
        ]));
        $this->assertTrue($DB->record_exists('local_aceengine_xp', [
            'userid' => $user2->id,
            'courseid' => $course->id,
        ]));
        $this->assertTrue($DB->record_exists('local_aceengine_quests', [
            'userid' => $user2->id,
            'courseid' => $course->id,
        ]));
    }

    /**
     * Test that all data is deleted for all users in a context.
     */
    public function test_delete_data_for_all_users(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $coursecontext = \context_course::instance($course->id);

        // Insert data for two users.
        foreach ([$user1, $user2] as $user) {
            $DB->insert_record('local_aceengine_engagement', (object) [
                'userid' => $user->id,
                'courseid' => $course->id,
                'score' => 60.0,
                'completion_score' => 70.0,
                'timeliness_score' => 50.0,
                'participation_score' => 65.0,
                'consistency_score' => 55.0,
                'trend' => 'stable',
                'timecreated' => time(),
                'timemodified' => time(),
            ]);

            $DB->insert_record('local_aceengine_xp', (object) [
                'userid' => $user->id,
                'courseid' => $course->id,
                'xp' => 100,
                'level' => 2,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);

            $DB->insert_record('local_aceengine_mastery', (object) [
                'userid' => $user->id,
                'courseid' => $course->id,
                'score' => 55.0,
                'grade_score' => 60.0,
                'improvement_score' => 50.0,
                'breadth_score' => 45.0,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        // Verify data exists.
        $this->assertEquals(2, $DB->count_records('local_aceengine_engagement', ['courseid' => $course->id]));
        $this->assertEquals(2, $DB->count_records('local_aceengine_xp', ['courseid' => $course->id]));
        $this->assertEquals(2, $DB->count_records('local_aceengine_mastery', ['courseid' => $course->id]));

        // Delete all data for the context.
        provider::delete_data_for_all_users_in_context($coursecontext);

        // All records for this course should be gone.
        $this->assertEquals(0, $DB->count_records('local_aceengine_engagement', ['courseid' => $course->id]));
        $this->assertEquals(0, $DB->count_records('local_aceengine_xp', ['courseid' => $course->id]));
        $this->assertEquals(0, $DB->count_records('local_aceengine_mastery', ['courseid' => $course->id]));
    }
}
