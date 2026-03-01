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

namespace local_aceengine\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/aceengine/lib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use context_course;
/**
 * External function to get analytics data for a course.
 *
 * Returns engagement trends, mastery trends, and dropout risk data
 * from the analytics snapshots table for the specified time period.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_analytics extends external_api {
    /**
     * Describes the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID', VALUE_REQUIRED),
            'period' => new external_value(PARAM_INT, 'Period in days to retrieve data for', VALUE_DEFAULT, 30),
        ]);
    }

    /**
     * Return analytics data for the given course and time period.
     *
     * Queries the local_aceengine_analytics table for snapshots within the specified
     * period and aggregates engagement trends, mastery trends, and dropout risk
     * data for all enrolled users.
     *
     * @param int $courseid The course ID.
     * @param int $period The period in days (default 30).
     * @return array Analytics data with engagement_trends, mastery_trends, and dropout_risks.
     */
    public static function execute(int $courseid, int $period = 30): array {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'period' => $period,
        ]);
        $courseid = $params['courseid'];
        $period = $params['period'];

        // Validate context and check capability.
        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/ace:viewanalytics', $context);

        // Check per-course enablement.
        if (!local_aceengine_is_enabled_for_course($courseid)) {
            throw new \moodle_exception('error_ace_disabled_for_course', 'local_aceengine');
        }

        // Calculate the start timestamp for the requested period.
        $starttime = time() - ($period * DAYSECS);

        // Fetch engagement trends: average engagement score per snapshot date.
        $engagementtrends = self::get_engagement_trends($courseid, $starttime);

        // Fetch mastery trends: average mastery score per snapshot date.
        $masterytrends = self::get_mastery_trends($courseid, $starttime);

        // Fetch dropout risk data: latest snapshot per user with risk classification.
        $dropoutrisks = self::get_dropout_risks($courseid, $starttime);

        return [
            'engagement_trends' => $engagementtrends,
            'mastery_trends' => $masterytrends,
            'dropout_risks' => $dropoutrisks,
        ];
    }

    /**
     * Describes the return value for execute.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'engagement_trends' => new external_multiple_structure(
                new external_single_structure([
                    'snapshot_date' => new external_value(PARAM_INT, 'Snapshot date as Unix timestamp'),
                    'average_score' => new external_value(PARAM_FLOAT, 'Average engagement score'),
                    'user_count' => new external_value(PARAM_INT, 'Number of users in the snapshot'),
                ])
            ),
            'mastery_trends' => new external_multiple_structure(
                new external_single_structure([
                    'snapshot_date' => new external_value(PARAM_INT, 'Snapshot date as Unix timestamp'),
                    'average_score' => new external_value(PARAM_FLOAT, 'Average mastery score'),
                    'user_count' => new external_value(PARAM_INT, 'Number of users in the snapshot'),
                ])
            ),
            'dropout_risks' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'dropout_risk' => new external_value(PARAM_FLOAT, 'Dropout risk score (0-1)'),
                    'risk_level' => new external_value(PARAM_ALPHA, 'Risk classification: low, medium, high, critical'),
                    'engagement_score' => new external_value(PARAM_FLOAT, 'Latest engagement score'),
                    'mastery_score' => new external_value(PARAM_FLOAT, 'Latest mastery score'),
                ])
            ),
        ]);
    }

    /**
     * Get engagement trend data aggregated by snapshot date.
     *
     * @param int $courseid The course ID.
     * @param int $starttime The earliest timestamp to include.
     * @return array Array of engagement trend data points.
     */
    private static function get_engagement_trends(int $courseid, int $starttime): array {
        global $DB;

        $sql = "SELECT snapshot_date,
                       AVG(engagement_score) AS average_score,
                       COUNT(DISTINCT userid) AS user_count
                  FROM {local_aceengine_analytics}
                 WHERE courseid = :courseid
                   AND snapshot_date >= :starttime
              GROUP BY snapshot_date
              ORDER BY snapshot_date ASC";

        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'starttime' => $starttime,
        ]);

        $trends = [];
        foreach ($records as $record) {
            $trends[] = [
                'snapshot_date' => (int) $record->snapshot_date,
                'average_score' => round((float) $record->average_score, 4),
                'user_count' => (int) $record->user_count,
            ];
        }

        return $trends;
    }

    /**
     * Get mastery trend data aggregated by snapshot date.
     *
     * @param int $courseid The course ID.
     * @param int $starttime The earliest timestamp to include.
     * @return array Array of mastery trend data points.
     */
    private static function get_mastery_trends(int $courseid, int $starttime): array {
        global $DB;

        $sql = "SELECT snapshot_date,
                       AVG(mastery_score) AS average_score,
                       COUNT(DISTINCT userid) AS user_count
                  FROM {local_aceengine_analytics}
                 WHERE courseid = :courseid
                   AND snapshot_date >= :starttime
              GROUP BY snapshot_date
              ORDER BY snapshot_date ASC";

        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'starttime' => $starttime,
        ]);

        $trends = [];
        foreach ($records as $record) {
            $trends[] = [
                'snapshot_date' => (int) $record->snapshot_date,
                'average_score' => round((float) $record->average_score, 4),
                'user_count' => (int) $record->user_count,
            ];
        }

        return $trends;
    }

    /**
     * Get dropout risk data for each user based on the most recent snapshot.
     *
     * @param int $courseid The course ID.
     * @param int $starttime The earliest timestamp to include.
     * @return array Array of dropout risk data per user.
     */
    private static function get_dropout_risks(int $courseid, int $starttime): array {
        global $DB;

        // Get the latest snapshot for each user within the period.
        $sql = "SELECT a.userid,
                       a.dropout_risk,
                       a.engagement_score,
                       a.mastery_score
                  FROM {local_aceengine_analytics} a
            INNER JOIN (
                        SELECT userid, MAX(snapshot_date) AS max_date
                          FROM {local_aceengine_analytics}
                         WHERE courseid = :courseid2
                           AND snapshot_date >= :starttime2
                      GROUP BY userid
                       ) latest ON a.userid = latest.userid
                                AND a.snapshot_date = latest.max_date
                 WHERE a.courseid = :courseid
                   AND a.snapshot_date >= :starttime
              ORDER BY a.dropout_risk DESC";

        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'starttime' => $starttime,
            'courseid2' => $courseid,
            'starttime2' => $starttime,
        ]);

        $risks = [];
        foreach ($records as $record) {
            $dropoutrisk = (float) $record->dropout_risk;
            $risklevel = self::classify_risk($dropoutrisk);

            $risks[] = [
                'userid' => (int) $record->userid,
                'dropout_risk' => round($dropoutrisk, 4),
                'risk_level' => $risklevel,
                'engagement_score' => round((float) $record->engagement_score, 4),
                'mastery_score' => round((float) $record->mastery_score, 4),
            ];
        }

        return $risks;
    }

    /**
     * Classify a dropout risk score into a risk level category.
     *
     * @param float $risk The dropout risk score (0-1).
     * @return string The risk classification: low, medium, high, or critical.
     */
    private static function classify_risk(float $risk): string {
        if ($risk >= 0.75) {
            return 'critical';
        } else if ($risk >= 0.50) {
            return 'high';
        } else if ($risk >= 0.25) {
            return 'medium';
        }
        return 'low';
    }
}
