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
 * Per-course ACE configuration page.
 *
 * Allows teachers to enable or disable ACE for their course.
 *
 * @package    local_ace
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/ace:managequests', $context);

$PAGE->set_url(new moodle_url('/local/ace/course_config.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('courseconfig', 'local_ace'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->set_secondary_active_tab('local_ace_course_config');
$PAGE->navbar->add(get_string('courseconfig', 'local_ace'));

// Build the form.
$mform = new MoodleQuickForm(
    'local_ace_course_config',
    'post',
    new moodle_url('/local/ace/course_config.php', ['courseid' => $courseid])
);

$mform->addElement('header', 'acesettings', get_string('courseconfig', 'local_ace'));

$currentenabled = (bool) get_config('local_ace', 'ace_course_enabled_' . $courseid);

$mform->addElement(
    'advcheckbox',
    'enabled',
    get_string('courseconfig_enable', 'local_ace'),
    get_string('courseconfig_enable_desc', 'local_ace')
);
$mform->setDefault('enabled', $currentenabled ? 1 : 0);

$mform->addElement('submit', 'submitbutton', get_string('savechanges'));

// Handle form submission.
if ($data = $mform->exportValues()) {
    if (optional_param('submitbutton', '', PARAM_TEXT) !== '') {
        require_sesskey();
        $enabled = !empty($data['enabled']) ? 1 : 0;
        set_config('ace_course_enabled_' . $courseid, $enabled, 'local_ace');
        redirect(
            new moodle_url('/local/ace/course_config.php', ['courseid' => $courseid]),
            get_string('courseconfig_saved', 'local_ace'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('courseconfig', 'local_ace'));

$mode = get_config('local_ace', 'enablemode');
if ($mode !== 'percourse') {
    echo $OUTPUT->notification(get_string('courseconfig_globalmode', 'local_ace'), \core\output\notification::NOTIFY_INFO);
}

$mform->display();

echo $OUTPUT->footer();
