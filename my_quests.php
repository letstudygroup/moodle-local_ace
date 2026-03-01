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
 * My Quests page — shows all quests across all courses.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$PAGE->set_url(new moodle_url('/local/ace/my_quests.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('myquests', 'local_ace'));
$PAGE->set_heading(get_string('myquests', 'local_ace'));
$PAGE->set_pagelayout('standard');

$PAGE->navbar->add(get_string('myquests', 'local_ace'));
$PAGE->add_body_class('local-ace-myquests-page');

$myquests = new \local_ace\output\my_quests($USER->id);

/** @var \local_ace\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_ace');

echo $OUTPUT->header();
echo $renderer->render_my_quests($myquests);
echo $OUTPUT->footer();
