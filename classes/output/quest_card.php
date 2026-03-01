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
 * Quest card renderable for local_ace.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ace\output;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

/**
 * Quest card renderable class.
 *
 * Represents a single quest for display in the dashboard.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quest_card implements renderable, templatable {
    /** @var int Quest ID. */
    public int $id;

    /** @var int User ID. */
    public int $userid;

    /** @var int Course ID. */
    public int $courseid;

    /** @var string Quest type identifier. */
    public string $questtype;

    /** @var string Quest title. */
    public string $title;

    /** @var string Quest description. */
    public string $description;

    /** @var int|null Target course module ID. */
    public ?int $targetid;

    /** @var int XP reward for completion. */
    public int $xpreward;

    /** @var int Difficulty level (1=easy, 2=medium, 3=hard). */
    public int $difficulty;

    /** @var string Quest status (active, completed, expired). */
    public string $status;

    /** @var int Expiry timestamp. */
    public int $expirydate;

    /** @var int|null Completion timestamp. */
    public ?int $completeddate;

    /** @var bool Whether this quest was AI-recommended. */
    public bool $recommended;

    /** @var int Time created. */
    public int $timecreated;

    /**
     * Constructor. Populates properties from a quest database record.
     *
     * @param \stdClass $quest The quest record from the database.
     */
    public function __construct(\stdClass $quest) {
        $this->id = (int) $quest->id;
        $this->userid = (int) $quest->userid;
        $this->courseid = (int) $quest->courseid;
        $this->questtype = $quest->questtype;
        $this->title = $quest->title;
        $this->description = $quest->description ?? '';
        $this->targetid = !empty($quest->targetid) ? (int) $quest->targetid : null;
        $this->xpreward = (int) $quest->xpreward;
        $this->difficulty = (int) $quest->difficulty;
        $this->status = $quest->status;
        $this->expirydate = (int) $quest->expirydate;
        $this->completeddate = !empty($quest->completeddate) ? (int) $quest->completeddate : null;
        $this->recommended = !empty($quest->recommended);
        $this->timecreated = (int) $quest->timecreated;
    }

    /**
     * Export data for the mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array The template context data.
     */
    public function export_for_template(renderer_base $output): array {
        $now = time();

        // Calculate time remaining until expiry.
        $timeremaining = $this->expirydate - $now;
        $expirydisplay = '';
        $isexpired = false;

        if ($this->expirydate > 0) {
            if ($timeremaining <= 0) {
                $expirydisplay = get_string('questexpired', 'local_ace');
                $isexpired = true;
            } else if ($timeremaining < HOURSECS) {
                $minutes = (int) ceil($timeremaining / MINSECS);
                $expirydisplay = get_string('numminutes', 'moodle', $minutes);
            } else if ($timeremaining < DAYSECS) {
                $hours = (int) ceil($timeremaining / HOURSECS);
                $expirydisplay = get_string('numhours', 'moodle', $hours);
            } else {
                $days = (int) ceil($timeremaining / DAYSECS);
                $expirydisplay = get_string('numdays', 'moodle', $days);
            }
        }

        // Build difficulty stars array for template iteration.
        $difficultystars = [];
        for ($i = 1; $i <= 3; $i++) {
            $difficultystars[] = [
                'filled' => ($i <= $this->difficulty),
            ];
        }

        // Get translated quest type label.
        $questtypelabel = $this->get_questtype_label();

        // Resolve activity URL from targetid (cm.id).
        $activityurl = '';
        $hasactivityurl = false;
        if ($this->targetid) {
            $activityurl = $this->resolve_activity_url($this->targetid);
            $hasactivityurl = !empty($activityurl);
        }

        // For login quests, link to the course page.
        if (!$hasactivityurl && $this->questtype === 'login') {
            $activityurl = (new moodle_url('/course/view.php', ['id' => $this->courseid]))->out(false);
            $hasactivityurl = true;
        }

        return [
            'id' => $this->id,
            'userid' => $this->userid,
            'courseid' => $this->courseid,
            'questtype' => $this->questtype,
            'questtypelabel' => $questtypelabel,
            'title' => format_string($this->title),
            'description' => format_text($this->description, FORMAT_PLAIN),
            'xpreward' => $this->xpreward,
            'difficulty' => $this->difficulty,
            'difficultystars' => $difficultystars,
            'status' => $this->status,
            'isactive' => ($this->status === 'active' && !$isexpired),
            'iscompleted' => ($this->status === 'completed'),
            'isexpired' => $isexpired,
            'expirydisplay' => $expirydisplay,
            'expirydate' => $this->expirydate,
            'activityurl' => $activityurl,
            'hasactivityurl' => $hasactivityurl,
            'recommended' => $this->recommended,
        ];
    }

    /**
     * Get the translated label for the quest type.
     *
     * @return string The quest type label.
     */
    private function get_questtype_label(): string {
        $key = 'questtype_' . $this->questtype;
        if (get_string_manager()->string_exists($key, 'local_ace')) {
            return get_string($key, 'local_ace');
        }
        return ucfirst($this->questtype);
    }

    /**
     * Resolve a course module ID to an activity view URL.
     *
     * @param int $cmid The course module ID.
     * @return string The activity URL, or empty string if not found.
     */
    private function resolve_activity_url(int $cmid): string {
        global $DB;

        $sql = "SELECT cm.id, m.name AS modname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid";
        $cm = $DB->get_record_sql($sql, ['cmid' => $cmid]);

        if (!$cm) {
            return '';
        }

        return (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cmid]))->out(false);
    }
}
