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
 * Library functions for local_ace.
 *
 * @package    local_ace
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Check if the ACE Pro plugin is installed.
 *
 * @return bool True if local_ace_pro is present.
 */
function local_ace_is_pro_installed(): bool {
    return array_key_exists('ace_pro', \core_component::get_plugin_list('local'));
}

/**
 * Check if ACE Pro is installed and has a valid license.
 *
 * @return bool True if Pro is installed and license is valid.
 */
function local_ace_is_pro_active(): bool {
    return local_ace_is_pro_installed()
        && \local_ace\licensing\license_manager::is_valid();
}

/**
 * Check if ACE is enabled for a specific course.
 *
 * Respects the global enable setting and the per-course mode.
 * In "global" mode, ACE is active for all courses.
 * In "percourse" mode, ACE is only active in courses where a teacher
 * has explicitly enabled it from the course settings.
 *
 * @param int $courseid The course ID.
 * @return bool True if ACE is enabled for this course.
 */
function local_ace_is_enabled_for_course(int $courseid): bool {
    if (!get_config('local_ace', 'enableplugin')) {
        return false;
    }

    $mode = get_config('local_ace', 'enablemode');
    if ($mode !== 'percourse') {
        return true;
    }

    // In per-course mode, check the course-level config.
    return (bool) get_config('local_ace', 'ace_course_enabled_' . $courseid);
}

/**
 * Extend the course navigation to add the ACE Dashboard link.
 *
 * This callback is invoked by Moodle's navigation system to allow plugins
 * to add items to the course navigation tree.
 *
 * @param navigation_node $parentnode The parent navigation node (course node).
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 */
function local_ace_extend_navigation_course(navigation_node $parentnode, stdClass $course, context_course $context) {
    if (!local_ace_is_enabled_for_course($course->id)) {
        return;
    }

    if (has_capability('local/ace:viewdashboard', $context)) {
        $url = new moodle_url('/local/ace/index.php', ['courseid' => $course->id]);
        $parentnode->add(
            get_string('dashboard', 'local_ace'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_ace_dashboard',
            new pix_icon('icon', get_string('pluginname', 'local_ace'), 'local_ace')
        );
    }

    if (has_capability('local/ace:viewanalytics', $context)) {
        $url = new moodle_url('/local/ace/analytics.php', ['courseid' => $course->id]);
        $parentnode->add(
            get_string('analytics', 'local_ace'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_ace_analytics',
            new pix_icon('icon', get_string('analytics', 'local_ace'), 'local_ace')
        );
    }
}

/**
 * Extend the settings navigation to add ACE admin links.
 *
 * This callback is invoked by Moodle's settings navigation system.
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param context $context The current context.
 */
function local_ace_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    // ACE course config is available via the course edit form (hook_listener).
    // No additional settings navigation entries needed.
}

/**
 * Add "My Quests" link to the user profile page.
 *
 * @param \core_user\output\myprofile\tree $tree The profile tree.
 * @param stdClass $user The user whose profile is being viewed.
 * @param bool $iscurrentuser Whether the profile belongs to the current user.
 * @param stdClass|null $course The course object (null for site-level profile).
 */
function local_ace_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (!$iscurrentuser) {
        return;
    }

    if (!get_config('local_ace', 'enableplugin')) {
        return;
    }

    $url = new moodle_url('/local/ace/my_quests.php');
    $node = new \core_user\output\myprofile\node(
        'miscellaneous',
        'local_ace_myquests',
        get_string('myquests', 'local_ace'),
        null,
        $url
    );
    $tree->add_node($node);
}

/**
 * Add "My Quests" link to the user preferences navigation.
 *
 * @param navigation_node $parentnode The user settings navigation node.
 * @param stdClass $user The user object.
 * @param context_user $usercontext The user context.
 * @param stdClass $course The course object.
 * @param context_course $coursecontext The course context.
 */
function local_ace_extend_navigation_user_settings(navigation_node $parentnode, $user, $usercontext, $course, $coursecontext) {
    global $USER;

    if ($user->id !== $USER->id) {
        return;
    }

    if (!get_config('local_ace', 'enableplugin')) {
        return;
    }

    $url = new moodle_url('/local/ace/my_quests.php');
    $parentnode->add(
        get_string('myquests', 'local_ace'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_ace_myquests',
        new pix_icon('icon', get_string('myquests', 'local_ace'), 'local_ace')
    );
}
