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
 * ACE Dashboard page.
 *
 * Main entry point for the Adaptive Challenge Engine student dashboard.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/ace:viewdashboard', $context);

$PAGE->set_url(new moodle_url('/local/aceengine/index.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('dashboard', 'local_aceengine'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Set the secondary navigation active tab.
$PAGE->set_secondary_active_tab('local_aceengine_dashboard');

// Add breadcrumb.
$PAGE->navbar->add(get_string('dashboard', 'local_aceengine'));

// Add CSS class for namespaced styling.
$PAGE->add_body_class('local-ace-dashboard-page');

// Load the AMD module for dashboard interactivity.
$PAGE->requires->js_call_amd('local_aceengine/dashboard', 'init', [$courseid]);

// Create the dashboard renderable with the current user's data.
$dashboard = new \local_aceengine\output\dashboard($USER->id, $courseid);

/** @var \local_aceengine\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_aceengine');

echo $OUTPUT->header();
echo $renderer->render_dashboard($dashboard);
echo $OUTPUT->footer();
