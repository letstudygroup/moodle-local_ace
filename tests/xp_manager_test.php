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
 * Tests for the xp_manager class.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aceengine\xp_manager
 */
final class xp_manager_test extends \advanced_testcase {
    /**
     * Test that awarding XP correctly adds to the user's total.
     */
    public function test_award_xp(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $manager = new xp_manager();

        // Award initial XP.
        $manager->award_xp($user->id, $course->id, 50, 'first_award');
        $this->assertEquals(50, $manager->get_xp($user->id, $course->id));

        // Award additional XP, should accumulate.
        $manager->award_xp($user->id, $course->id, 75, 'second_award');
        $this->assertEquals(125, $manager->get_xp($user->id, $course->id));

        // Zero XP should not change total.
        $manager->award_xp($user->id, $course->id, 0, 'zero_award');
        $this->assertEquals(125, $manager->get_xp($user->id, $course->id));

        // Negative XP should not change total.
        $manager->award_xp($user->id, $course->id, -10, 'negative_award');
        $this->assertEquals(125, $manager->get_xp($user->id, $course->id));
    }

    /**
     * Test that get_xp returns 0 for a new user with no XP record.
     */
    public function test_get_xp_default(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $manager = new xp_manager();
        $xp = $manager->get_xp($user->id, $course->id);

        $this->assertEquals(0, $xp);
    }

    /**
     * Test that level calculation follows the correct triangular thresholds.
     *
     * Level thresholds:
     * - Level 1: 0 XP
     * - Level 2: 100 XP  (1*100)
     * - Level 3: 300 XP  (1*100 + 2*100)
     * - Level 4: 600 XP  (1*100 + 2*100 + 3*100)
     */
    public function test_level_calculation(): void {
        $manager = new xp_manager();

        // Level 1 thresholds.
        $this->assertEquals(1, $manager->calculate_level(0));
        $this->assertEquals(1, $manager->calculate_level(50));
        $this->assertEquals(1, $manager->calculate_level(99));

        // Level 2 thresholds.
        $this->assertEquals(2, $manager->calculate_level(100));
        $this->assertEquals(2, $manager->calculate_level(200));
        $this->assertEquals(2, $manager->calculate_level(299));

        // Level 3 thresholds.
        $this->assertEquals(3, $manager->calculate_level(300));
        $this->assertEquals(3, $manager->calculate_level(450));
        $this->assertEquals(3, $manager->calculate_level(599));

        // Level 4 thresholds.
        $this->assertEquals(4, $manager->calculate_level(600));
        $this->assertEquals(4, $manager->calculate_level(999));

        // Level 5 threshold: 5*4/2*100 = 1000.
        $this->assertEquals(5, $manager->calculate_level(1000));

        // Negative XP should still return level 1.
        $this->assertEquals(1, $manager->calculate_level(-100));
    }

    /**
     * Test that level progress percentage is calculated correctly.
     */
    public function test_level_progress(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $manager = new xp_manager();

        // Award 200 XP. Level 2 starts at 100, level 3 starts at 300.
        // Progress within level 2: (200 - 100) / (300 - 100) = 100/200 = 50%.
        $manager->award_xp($user->id, $course->id, 200, 'test');

        $progress = $manager->get_level_progress($user->id, $course->id);

        $this->assertEquals(2, $progress['level']);
        $this->assertEquals(200, $progress['xp']);
        $this->assertEquals(300, $progress['xp_for_next']);
        $this->assertEquals(50.0, $progress['progress']);
    }

    /**
     * Test that level progress is 0 for a brand new user.
     */
    public function test_level_progress_new_user(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $manager = new xp_manager();
        $progress = $manager->get_level_progress($user->id, $course->id);

        $this->assertEquals(1, $progress['level']);
        $this->assertEquals(0, $progress['xp']);
        $this->assertEquals(100, $progress['xp_for_next']);
        $this->assertEquals(0.0, $progress['progress']);
    }

    /**
     * Test that the leaderboard returns users in correct descending XP order.
     */
    public function test_leaderboard_ordering(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $manager = new xp_manager();

        // Award XP in non-sorted order.
        $manager->award_xp($user1->id, $course->id, 100, 'test');
        $manager->award_xp($user2->id, $course->id, 300, 'test');
        $manager->award_xp($user3->id, $course->id, 200, 'test');

        $leaderboard = $manager->get_leaderboard($course->id, 10);

        // Should be ordered by XP descending: user2 (300), user3 (200), user1 (100).
        $this->assertCount(3, $leaderboard);

        $this->assertEquals($user2->id, $leaderboard[0]['userid']);
        $this->assertEquals(300, $leaderboard[0]['xp']);
        $this->assertEquals(1, $leaderboard[0]['rank']);

        $this->assertEquals($user3->id, $leaderboard[1]['userid']);
        $this->assertEquals(200, $leaderboard[1]['xp']);
        $this->assertEquals(2, $leaderboard[1]['rank']);

        $this->assertEquals($user1->id, $leaderboard[2]['userid']);
        $this->assertEquals(100, $leaderboard[2]['xp']);
        $this->assertEquals(3, $leaderboard[2]['rank']);
    }

    /**
     * Test that the leaderboard respects the limit parameter.
     */
    public function test_leaderboard_limit(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $manager = new xp_manager();

        // Create 5 users with XP.
        for ($i = 1; $i <= 5; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $manager->award_xp($user->id, $course->id, $i * 100, 'test');
        }

        $leaderboard = $manager->get_leaderboard($course->id, 3);

        $this->assertCount(3, $leaderboard);
        // Highest XP first.
        $this->assertEquals(500, $leaderboard[0]['xp']);
    }

    /**
     * Test that awarding XP updates the level in the database.
     */
    public function test_level_updates_on_xp_award(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $manager = new xp_manager();

        // 50 XP -> Level 1.
        $manager->award_xp($user->id, $course->id, 50, 'test');
        $this->assertEquals(1, $manager->get_level($user->id, $course->id));

        // 100 total XP -> Level 2.
        $manager->award_xp($user->id, $course->id, 50, 'test');
        $this->assertEquals(2, $manager->get_level($user->id, $course->id));

        // 300 total XP -> Level 3.
        $manager->award_xp($user->id, $course->id, 200, 'test');
        $this->assertEquals(3, $manager->get_level($user->id, $course->id));
    }
}
