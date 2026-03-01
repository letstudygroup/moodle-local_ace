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
 * Adaptive engine class for adjusting quest difficulty.
 *
 * Analyses user engagement and mastery scores to determine an appropriate
 * difficulty level and adjusts quest parameters accordingly. Uses a
 * rule-based approach available to all installations.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adaptive_engine {
    /** @var int Difficulty level: easy. */
    public const DIFFICULTY_EASY = 1;

    /** @var int Difficulty level: medium. */
    public const DIFFICULTY_MEDIUM = 2;

    /** @var int Difficulty level: hard. */
    public const DIFFICULTY_HARD = 3;

    /** @var float XP multiplier for hard difficulty. */
    private const XP_MULTIPLIER_HARD = 1.5;

    /** @var float XP multiplier for medium difficulty. */
    private const XP_MULTIPLIER_MEDIUM = 1.0;

    /** @var float XP multiplier for easy difficulty. */
    private const XP_MULTIPLIER_EASY = 0.8;

    /** @var float Score threshold for high performance (hard difficulty). */
    private const HIGH_THRESHOLD = 70.0;

    /** @var float Score threshold for medium performance (medium difficulty). */
    private const MEDIUM_THRESHOLD = 40.0;

    /**
     * Check if the adaptive engine is available.
     *
     * The rule-based adaptive engine is always available when the
     * plugin is enabled. Advanced AI-based adaptation is provided
     * by local_ace_pro.
     *
     * @return bool True if the adaptive engine is available.
     */
    public function is_available(): bool {
        return (bool) get_config('local_ace', 'enableplugin');
    }

    /**
     * Get the recommended difficulty level for a user in a course.
     *
     * Determines difficulty based on the user's current engagement and
     * mastery scores:
     * - Both scores > 70: difficulty 3 (hard)
     * - Both scores > 40: difficulty 2 (medium)
     * - Otherwise: difficulty 1 (easy)
     *
     * @param int $userid  The ID of the user.
     * @param int $courseid The ID of the course.
     * @return int The recommended difficulty level (1-3).
     */
    public function get_recommended_difficulty(int $userid, int $courseid): int {
        global $DB;

        // Retrieve cached engagement score.
        $engagement = $DB->get_record('local_ace_engagement', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $engagementscore = $engagement ? (float) $engagement->score : 0.0;

        // Retrieve cached mastery score.
        $mastery = $DB->get_record('local_ace_mastery', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $masteryscore = $mastery ? (float) $mastery->score : 0.0;

        if ($engagementscore > self::HIGH_THRESHOLD && $masteryscore > self::HIGH_THRESHOLD) {
            return self::DIFFICULTY_HARD;
        }

        if ($engagementscore > self::MEDIUM_THRESHOLD && $masteryscore > self::MEDIUM_THRESHOLD) {
            return self::DIFFICULTY_MEDIUM;
        }

        return self::DIFFICULTY_EASY;
    }

    /**
     * Adjust quest parameters based on user performance.
     *
     * Modifies the quest parameters array by setting the appropriate
     * difficulty level and adjusting the XP reward based on difficulty
     * multipliers. If the engine is not available, parameters are
     * returned unchanged.
     *
     * Difficulty XP multipliers:
     * - Hard (3): 1.5x base XP
     * - Medium (2): 1.0x base XP
     * - Easy (1): 0.8x base XP
     *
     * @param int   $userid      The ID of the user.
     * @param int   $courseid    The ID of the course.
     * @param array $questparams The quest parameters to adjust.
     * @return array The adjusted quest parameters.
     */
    public function adjust_quest_params(int $userid, int $courseid, array $questparams): array {
        if (!$this->is_available()) {
            return $questparams;
        }

        $difficulty = $this->get_recommended_difficulty($userid, $courseid);
        $questparams['difficulty'] = $difficulty;

        // Adjust XP reward based on difficulty.
        $basexp = $questparams['xpreward'] ?? ((int) get_config('local_ace', 'xp_per_quest') ?: 50);

        $multiplier = match ($difficulty) {
            self::DIFFICULTY_HARD => self::XP_MULTIPLIER_HARD,
            self::DIFFICULTY_MEDIUM => self::XP_MULTIPLIER_MEDIUM,
            default => self::XP_MULTIPLIER_EASY,
        };

        $questparams['xpreward'] = (int) round($basexp * $multiplier);

        // Optionally adjust target values for grade/quiz quests.
        if (isset($questparams['targetvalue']) && $questparams['targetvalue'] > 0) {
            $questparams['targetvalue'] = $this->adjust_target_value(
                (float) $questparams['targetvalue'],
                $difficulty
            );
        }

        return $questparams;
    }

    /**
     * Adjust a target value based on difficulty level.
     *
     * For harder difficulties, the target is raised; for easier
     * difficulties, it is lowered. The value is always clamped to
     * a reasonable range.
     *
     * @param float $value     The original target value.
     * @param int   $difficulty The difficulty level (1-3).
     * @return float The adjusted target value.
     */
    private function adjust_target_value(float $value, int $difficulty): float {
        $adjusted = match ($difficulty) {
            self::DIFFICULTY_HARD => $value * 1.2, // 20% harder target.
            self::DIFFICULTY_MEDIUM => $value, // Unchanged.
            default => $value * 0.8, // 20% easier target.
        };

        // Ensure the target stays within reasonable bounds.
        return round(max(1.0, $adjusted), 2);
    }
}
