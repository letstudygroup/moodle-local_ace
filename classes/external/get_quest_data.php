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

namespace local_ace\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/ace/lib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use context_course;
/**
 * External function to get active quests for the current user in a course.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_quest_data extends external_api {
    /**
     * Describes the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Return active quests for the current user in the given course.
     *
     * Retrieves all quests with an 'active' status for the authenticated user,
     * ordered by expiry date ascending so the most urgent quests appear first.
     *
     * @param int $courseid The course ID.
     * @return array Array of quest objects.
     */
    public static function execute(int $courseid): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);
        $courseid = $params['courseid'];

        // Validate context and check capability.
        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/ace:viewdashboard', $context);

        // Check per-course enablement.
        if (!local_ace_is_enabled_for_course($courseid)) {
            throw new \moodle_exception('error_ace_disabled_for_course', 'local_ace');
        }

        $userid = $USER->id;

        // Get active quests for this user in this course, ordered by expiry date.
        // Exclude expired quests even if the cleanup task hasn't run yet.
        $now = time();
        $quests = $DB->get_records_sql(
            'SELECT * FROM {local_ace_quests}
             WHERE userid = ? AND courseid = ? AND status = ?
               AND (expirydate = 0 OR expirydate > ?)
             ORDER BY expirydate ASC',
            [$userid, $courseid, 'active', $now]
        );

        $result = [];
        foreach ($quests as $quest) {
            $result[] = [
                'id' => (int) $quest->id,
                'questtype' => $quest->questtype,
                'title' => $quest->title,
                'description' => $quest->description ?? '',
                'xpreward' => (int) $quest->xpreward,
                'difficulty' => (int) $quest->difficulty,
                'status' => $quest->status,
                'expirydate' => (int) $quest->expirydate,
            ];
        }

        return $result;
    }

    /**
     * Describes the return value for execute.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Quest ID'),
                'questtype' => new external_value(PARAM_ALPHANUMEXT, 'Type of quest'),
                'title' => new external_value(PARAM_TEXT, 'Quest title'),
                'description' => new external_value(PARAM_RAW, 'Quest description'),
                'xpreward' => new external_value(PARAM_INT, 'XP reward for completing the quest'),
                'difficulty' => new external_value(PARAM_INT, 'Difficulty level (1=easy, 2=medium, 3=hard)'),
                'status' => new external_value(PARAM_ALPHA, 'Quest status'),
                'expirydate' => new external_value(PARAM_INT, 'Quest expiry date as Unix timestamp'),
            ])
        );
    }
}
