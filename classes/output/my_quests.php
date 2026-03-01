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
 * My Quests renderable for local_aceengine.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aceengine\output;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

/**
 * My Quests renderable class.
 *
 * Loads all quests across all enrolled courses where ACE is enabled,
 * grouped by course, for a global "My Quests" view.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class my_quests implements renderable, templatable {
    /** @var int The user ID. */
    private int $userid;

    /**
     * Constructor.
     *
     * @param int $userid The user ID.
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Export data for the mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array The template context data.
     */
    public function export_for_template(renderer_base $output): array {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/local/aceengine/lib.php');

        // Get all courses where the user is enrolled.
        $enrolledcourses = enrol_get_users_courses($this->userid, true, 'id, fullname, shortname');

        $courses = [];
        $totalxp = 0;
        $totalactivequests = 0;
        $totalcompletedquests = 0;

        foreach ($enrolledcourses as $course) {
            if ($course->id == SITEID) {
                continue;
            }

            if (!local_aceengine_is_enabled_for_course($course->id)) {
                continue;
            }

            // Load XP and level for this course.
            $xprecord = $DB->get_record('local_aceengine_xp', [
                'userid' => $this->userid,
                'courseid' => $course->id,
            ]);
            $xp = $xprecord ? (int) $xprecord->xp : 0;
            $level = $xprecord ? (int) $xprecord->level : 1;
            $totalxp += $xp;

            // Load active quests (exclude expired).
            $now = time();
            $activequests = $DB->get_records_sql(
                'SELECT * FROM {local_aceengine_quests}
                 WHERE userid = ? AND courseid = ? AND status = ?
                   AND (expirydate = 0 OR expirydate > ?)
                 ORDER BY timecreated DESC',
                [$this->userid, $course->id, 'active', $now]
            );

            // Load completed quests.
            $completedquests = $DB->get_records('local_aceengine_quests', [
                'userid' => $this->userid,
                'courseid' => $course->id,
                'status' => 'completed',
            ], 'completeddate DESC');

            $activecount = count($activequests);
            $completedcount = count($completedquests);

            // Skip courses with no quests at all.
            if ($activecount === 0 && $completedcount === 0) {
                continue;
            }

            $totalactivequests += $activecount;
            $totalcompletedquests += $completedcount;

            // Build quest card data.
            $activecards = [];
            foreach ($activequests as $quest) {
                $card = new quest_card($quest);
                $activecards[] = $card->export_for_template($output);
            }

            $completedcards = [];
            foreach ($completedquests as $quest) {
                $card = new quest_card($quest);
                $completedcards[] = $card->export_for_template($output);
            }

            $courseurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
            $dashboardurl = (new moodle_url('/local/aceengine/index.php', ['courseid' => $course->id]))->out(false);

            $courses[] = [
                'courseid' => $course->id,
                'coursename' => format_string($course->fullname),
                'courseshortname' => format_string($course->shortname),
                'courseurl' => $courseurl,
                'dashboardurl' => $dashboardurl,
                'xp' => $xp,
                'level' => $level,
                'activequestcount' => $activecount,
                'completedquestcount' => $completedcount,
                'activequests' => $activecards,
                'completedquests' => $completedcards,
                'hasactivequests' => $activecount > 0,
                'hascompletedquests' => $completedcount > 0,
            ];
        }

        return [
            'courses' => $courses,
            'hascourses' => !empty($courses),
            'totalactivequests' => $totalactivequests,
            'totalcompletedquests' => $totalcompletedquests,
            'totalxp' => $totalxp,
        ];
    }
}
