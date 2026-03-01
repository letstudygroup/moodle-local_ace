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
 * Dashboard renderable for local_aceengine.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aceengine\output;

use renderable;
use templatable;
use renderer_base;

/**
 * Dashboard renderable class.
 *
 * Loads all ACE data for a user in a course and prepares it for template rendering.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements renderable, templatable {
    /** @var int The user ID. */
    public int $userid;

    /** @var int The course ID. */
    public int $courseid;

    /** @var float The engagement score (0.0 to 100.0). */
    public float $engagementscore;

    /** @var float The mastery score (0.0 to 100.0). */
    public float $masteryscore;

    /** @var int Total XP earned. */
    public int $xp;

    /** @var int Current level. */
    public int $level;

    /** @var float Level progress percentage (0.0 to 100.0). */
    public float $levelprogress;

    /** @var array Active quest records. */
    public array $activequests;

    /** @var int Count of completed quests. */
    public int $completedcount;

    /** @var array Activity recommendations for the student. */
    public array $recommendations;

    /** @var array AI content suggestions for the student (Pro feature). */
    public array $contentsuggestions;

    /** @var int Timestamp when content suggestions were generated. */
    public int $contentsuggestiondate;

    /** @var array AI learning path steps for the student (Pro feature). */
    public array $learningpathsteps;

    /** @var string Learning path summary. */
    public string $learningpathsummary;

    /** @var float Total estimated hours for the learning path. */
    public float $learningpathhours;

    /** @var int Timestamp when learning path was generated. */
    public int $learningpathdate;

    /**
     * Constructor. Loads all dashboard data for the given user and course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     */
    public function __construct(int $userid, int $courseid) {
        global $DB;

        $this->userid = $userid;
        $this->courseid = $courseid;

        // Load engagement score.
        $engagement = $DB->get_record('local_aceengine_engagement', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $this->engagementscore = !empty($engagement) ? (float) $engagement->score : 0.0;

        // Load mastery score.
        $mastery = $DB->get_record('local_aceengine_mastery', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $this->masteryscore = !empty($mastery) ? (float) $mastery->score : 0.0;

        // Load XP and level.
        $xprecord = $DB->get_record('local_aceengine_xp', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $this->xp = !empty($xprecord) ? (int) $xprecord->xp : 0;
        $this->level = !empty($xprecord) ? (int) $xprecord->level : 1;

        // Calculate level progress.
        $this->levelprogress = $this->calculate_level_progress($this->xp, $this->level);

        // Load active quests (exclude expired ones even if cleanup task hasn't run yet).
        $now = time();
        $this->activequests = $DB->get_records_sql(
            'SELECT * FROM {local_aceengine_quests}
             WHERE userid = ? AND courseid = ? AND status = ?
               AND (expirydate = 0 OR expirydate > ?)
             ORDER BY timecreated DESC',
            [$userid, $courseid, 'active', $now]
        );

        // Count completed quests.
        $this->completedcount = $DB->count_records('local_aceengine_quests', [
            'userid' => $userid,
            'courseid' => $courseid,
            'status' => 'completed',
        ]);

        // Build activity recommendations (Pro feature only).
        $this->recommendations = [];
        $this->contentsuggestions = [];
        $this->contentsuggestiondate = 0;
        $this->learningpathsteps = [];
        $this->learningpathsummary = '';
        $this->learningpathhours = 0.0;
        $this->learningpathdate = 0;
        if (class_exists('\local_ace_pro\pro_manager') && \local_ace_pro\pro_manager::is_active()) {
            $this->recommendations = $this->build_recommendations();
            $this->load_content_suggestions();
            $this->load_learning_path();
        }
    }

    /**
     * Export data for the mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array The template context data.
     */
    public function export_for_template(renderer_base $output): array {
        $activequestcards = [];
        foreach ($this->activequests as $quest) {
            $card = new quest_card($quest);
            $activequestcards[] = $card->export_for_template($output);
        }

        // Format content suggestions date.
        $suggestionsdate = '';
        if ($this->contentsuggestiondate > 0) {
            $suggestionsdate = userdate($this->contentsuggestiondate, get_string('strftimedatetimeshort', 'core_langconfig'));
        }

        return [
            'userid' => $this->userid,
            'courseid' => $this->courseid,
            'engagementscore' => round($this->engagementscore),
            'masteryscore' => round($this->masteryscore),
            'xp' => $this->xp,
            'level' => $this->level,
            'levelprogress' => round($this->levelprogress, 1),
            'activequests' => $activequestcards,
            'hasactivequests' => !empty($activequestcards),
            'completedcount' => $this->completedcount,
            'recommendations' => $this->recommendations,
            'hasrecommendations' => !empty($this->recommendations),
            'contentsuggestions' => $this->contentsuggestions,
            'hascontentsuggestions' => !empty($this->contentsuggestions),
            'contentsuggestiondate' => $suggestionsdate,
            'learningpathsteps' => $this->learningpathsteps,
            'haslearningpath' => !empty($this->learningpathsteps),
            'learningpathsummary' => $this->learningpathsummary,
            'learningpathhours' => $this->learningpathhours,
            'learningpathdate' => $this->learningpathdate > 0
                ? userdate($this->learningpathdate, get_string('strftimedatetimeshort', 'core_langconfig'))
                : '',
        ];
    }

    /**
     * Load AI content suggestions from the Pro plugin (if available).
     */
    private function load_content_suggestions(): void {
        if (
            !class_exists('\local_ace_pro\ai\content_suggester')
            || empty(get_config('local_ace_pro', 'ai_content_suggestions'))
        ) {
            return;
        }

        $record = \local_ace_pro\ai\content_suggester::get_latest($this->userid, $this->courseid);
        if (!$record) {
            return;
        }

        $data = \local_ace_pro\ai\content_suggester::decode_suggestions($record);
        $suggestions = $data['suggestions'] ?? $data;
        if (!is_array($suggestions) || empty($suggestions)) {
            return;
        }

        // Map type icons for the template.
        $typemap = [
            'video' => ['icon' => 'fa-play-circle', 'cls' => 'danger', 'label' => 'Video'],
            'article' => ['icon' => 'fa-newspaper-o', 'cls' => 'primary', 'label' => 'Article'],
            'tutorial' => ['icon' => 'fa-laptop', 'cls' => 'success', 'label' => 'Tutorial'],
            'practice' => ['icon' => 'fa-tasks', 'cls' => 'warning', 'label' => 'Practice'],
            'course' => ['icon' => 'fa-graduation-cap', 'cls' => 'info', 'label' => 'Course'],
            'tool' => ['icon' => 'fa-wrench', 'cls' => 'secondary', 'label' => 'Tool'],
        ];

        foreach ($suggestions as $s) {
            $type = $s['type'] ?? 'resource';
            $info = $typemap[$type] ?? ['icon' => 'fa-link', 'cls' => 'secondary', 'label' => ucfirst($type)];

            // Use real URL from AI if available, otherwise fall back to search query.
            $directurl = $s['url'] ?? '';
            $searchquery = $s['search_query'] ?? $s['url_hint'] ?? '';
            $platform = $s['platform'] ?? $s['platform_hint'] ?? '';

            $this->contentsuggestions[] = [
                'title' => $s['title'] ?? '',
                'description' => $s['description'] ?? '',
                'type_icon' => $info['icon'],
                'type_class' => $info['cls'],
                'type_label' => $info['label'],
                'relevance' => $s['relevance'] ?? '',
                'estimated_minutes' => $s['estimated_minutes'] ?? 0,
                'url' => $directurl,
                'has_url' => !empty($directurl),
                'platform' => $platform,
                'search_query' => $searchquery,
                'search_url' => !empty($searchquery) ? 'https://www.google.com/search?q=' . urlencode($searchquery) : '',
            ];
        }

        $this->contentsuggestiondate = (int) $record->timecreated;
    }

    /**
     * Load AI learning path from the Pro plugin (if available).
     */
    private function load_learning_path(): void {
        global $CFG;

        if (
            !class_exists('\local_ace_pro\ai\learning_path_generator')
            || empty(get_config('local_ace_pro', 'ai_learning_paths'))
        ) {
            return;
        }

        $record = \local_ace_pro\ai\learning_path_generator::get_active_path($this->userid, $this->courseid);
        if (!$record) {
            return;
        }

        $data = \local_ace_pro\ai\learning_path_generator::decode_path($record);
        $steps = $data['steps'] ?? [];
        if (!is_array($steps) || empty($steps)) {
            return;
        }

        // Build a cmid → URL lookup from course modules.
        $modinfo = get_fast_modinfo($this->courseid);
        $cmurls = [];
        foreach ($modinfo->get_cms() as $cminfo) {
            if ($cminfo->url) {
                $cmurls[(int) $cminfo->id] = $cminfo->url->out(false);
            }
        }

        // Map activity types to icons.
        $typemap = [
            'quiz' => 'fa-question-circle',
            'assign' => 'fa-pencil-square-o',
            'lesson' => 'fa-book',
            'forum' => 'fa-comments',
            'resource' => 'fa-file-text-o',
            'video' => 'fa-play-circle',
            'review' => 'fa-refresh',
            'practice' => 'fa-tasks',
            'page' => 'fa-file-text-o',
            'url' => 'fa-external-link',
            'book' => 'fa-book',
            'choice' => 'fa-check-square-o',
            'feedback' => 'fa-commenting-o',
            'workshop' => 'fa-users',
            'glossary' => 'fa-list-alt',
            'wiki' => 'fa-wikipedia-w',
            'data' => 'fa-database',
            'h5pactivity' => 'fa-puzzle-piece',
        ];

        // Map priorities to Bootstrap classes.
        $prioritymap = [
            'urgent' => ['cls' => 'danger', 'label' => get_string('learning_path_priority_high', 'local_aceengine')],
            'high' => ['cls' => 'danger', 'label' => get_string('learning_path_priority_high', 'local_aceengine')],
            'medium' => ['cls' => 'warning', 'label' => get_string('learning_path_priority_medium', 'local_aceengine')],
            'low' => ['cls' => 'info', 'label' => get_string('learning_path_priority_low', 'local_aceengine')],
        ];

        foreach ($steps as $step) {
            $type = $step['activity_type'] ?? 'resource';
            $priority = $step['priority'] ?? 'medium';
            $pinfo = $prioritymap[$priority] ?? $prioritymap['medium'];

            // Build URL from cmid if the AI provided one.
            $cmid = !empty($step['cmid']) ? (int) $step['cmid'] : 0;
            $activityurl = '';
            if ($cmid > 0 && isset($cmurls[$cmid])) {
                $activityurl = $cmurls[$cmid];
            }

            $this->learningpathsteps[] = [
                'order' => $step['order'] ?? 0,
                'title' => $step['title'] ?? '',
                'description' => $step['description'] ?? '',
                'activity_type' => $type,
                'type_icon' => $typemap[$type] ?? 'fa-circle-o',
                'estimated_minutes' => $step['estimated_minutes'] ?? 0,
                'reason' => $step['reason'] ?? '',
                'priority' => $priority,
                'priority_class' => $pinfo['cls'],
                'priority_label' => $pinfo['label'],
                'section_hint' => $step['section_hint'] ?? '',
                'activity_url' => $activityurl,
                'has_url' => !empty($activityurl),
            ];
        }

        $this->learningpathsummary = $data['path_summary'] ?? '';
        $this->learningpathhours = (float) ($data['total_estimated_hours'] ?? 0);
        $this->learningpathdate = (int) $record->timecreated;
    }

    /**
     * Build smart activity recommendations for the student (Pro feature).
     *
     * Uses rule-based logic to suggest incomplete activities ordered by
     * relevance: low-grade items first, then section order.
     * No AI credits are consumed — this is local analysis.
     *
     * @return array Array of recommendation data for the template.
     */
    private function build_recommendations(): array {
        global $DB, $CFG;

        $recommendations = [];

        // Get incomplete activities with completion tracking, ordered by section.
        $sql = "SELECT cm.id AS cmid, cm.instance, cm.section, m.name AS modname,
                       cs.name AS sectionname, cs.section AS sectionnumber
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {course_sections} cs ON cs.id = cm.section
             LEFT JOIN {course_modules_completion} cmc
                    ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid
                 WHERE cm.course = :courseid
                   AND cm.completion > 0
                   AND cm.visible = 1
                   AND cm.deletioninprogress = 0
                   AND (cmc.id IS NULL OR cmc.completionstate = 0)
              ORDER BY cs.section ASC, cm.id ASC";
        $activities = $DB->get_records_sql($sql, [
            'userid' => $this->userid,
            'courseid' => $this->courseid,
        ], 0, 20);

        if (empty($activities)) {
            return [];
        }

        // Get low-grade activities to prioritize.
        $lowgrades = $DB->get_records_sql(
            '
            SELECT gi.iteminstance, gi.itemmodule, gg.finalgrade, gi.grademax
              FROM {grade_grades} gg
              JOIN {grade_items} gi ON gi.id = gg.itemid
             WHERE gg.userid = :userid AND gi.courseid = :courseid
               AND gg.finalgrade IS NOT NULL AND gi.grademax > 0
               AND (gg.finalgrade / gi.grademax) < 0.6
          ORDER BY (gg.finalgrade / gi.grademax) ASC',
            ['userid' => $this->userid, 'courseid' => $this->courseid]
        );
        $lowgrademodules = [];
        foreach ($lowgrades as $g) {
            $lowgrademodules[$g->itemmodule . '_' . $g->iteminstance] = true;
        }

        $modinfo = get_fast_modinfo($this->courseid);
        $count = 0;

        // Prioritize: low-grade activities first, then by section order.
        $sorted = [];
        $rest = [];
        foreach ($activities as $act) {
            $key = $act->modname . '_' . $act->instance;
            if (isset($lowgrademodules[$key])) {
                $sorted[] = $act;
            } else {
                $rest[] = $act;
            }
        }
        $sorted = array_merge($sorted, $rest);

        foreach ($sorted as $act) {
            if ($count >= 5) {
                break;
            }

            try {
                $cminfo = $modinfo->get_cm($act->cmid);
            } catch (\Exception $e) {
                continue;
            }

            if (!$cminfo->uservisible) {
                continue;
            }

            $actname = $cminfo->get_formatted_name();
            $acturl = $cminfo->url ? $cminfo->url->out(false) : '';
            $icon = $cminfo->get_icon_url()->out(false);

            // Determine reason for recommendation.
            $key = $act->modname . '_' . $act->instance;
            $islowgrade = isset($lowgrademodules[$key]);

            $reason = get_string('rec_reason_next', 'local_aceengine');
            if ($islowgrade) {
                $reason = get_string('rec_reason_improve', 'local_aceengine');
            }

            $sectionlabel = !empty($act->sectionname)
                ? $act->sectionname
                : get_string('section') . ' ' . $act->sectionnumber;

            $recommendations[] = [
                'name' => $actname,
                'url' => $acturl,
                'iconurl' => $icon,
                'modname' => $act->modname,
                'section' => $sectionlabel,
                'reason' => $reason,
                'islowgrade' => $islowgrade,
            ];
            $count++;
        }

        return $recommendations;
    }

    /**
     * Calculate the progress percentage within the current level.
     *
     * Uses a simple formula: each level requires level * 100 XP.
     * Progress is the percentage of XP earned within the current level bracket.
     *
     * @param int $xp The total XP.
     * @param int $level The current level.
     * @return float The progress percentage (0.0 to 100.0).
     */
    private function calculate_level_progress(int $xp, int $level): float {
        // XP required for the start of the current level.
        $xpforlevel = 0;
        for ($i = 1; $i < $level; $i++) {
            $xpforlevel += $i * 100;
        }

        // XP required for the next level.
        $xpfornextlevel = $level * 100;

        if ($xpfornextlevel == 0) {
            return 0.0;
        }

        $xpinlevel = $xp - $xpforlevel;
        $progress = ($xpinlevel / $xpfornextlevel) * 100.0;

        return max(0.0, min(100.0, $progress));
    }
}
