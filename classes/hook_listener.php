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

namespace local_aceengine;

use core_course\hook\after_form_definition;
use core_course\hook\after_form_definition_after_data;
use core_course\hook\after_form_submission;

/**
 * Hook listener for local_aceengine.
 *
 * Hooks into the course edit form to add ACE enable/disable toggle.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Add ACE settings to the course edit form.
     *
     * @param after_form_definition $hook The hook instance.
     */
    public static function extend_course_form(after_form_definition $hook): void {
        if (!get_config('local_aceengine', 'enableplugin')) {
            return;
        }

        $mform = $hook->mform;

        $mform->addElement('header', 'local_aceengine_header', get_string('pluginname', 'local_aceengine'));

        $mform->addElement(
            'advcheckbox',
            'local_aceengine_enabled',
            get_string('courseconfig_enable', 'local_aceengine'),
            get_string('courseconfig_enable_desc', 'local_aceengine')
        );
        $mform->setDefault('local_aceengine_enabled', 0);

        $mode = get_config('local_aceengine', 'enablemode');
        if ($mode !== 'percourse') {
            $mform->addElement(
                'static',
                'local_aceengine_globalinfo',
                '',
                get_string('courseconfig_globalmode', 'local_aceengine')
            );
        }
    }

    /**
     * Set default values for ACE fields after form data is loaded.
     *
     * @param after_form_definition_after_data $hook The hook instance.
     */
    public static function set_course_form_defaults(after_form_definition_after_data $hook): void {
        if (!get_config('local_aceengine', 'enableplugin')) {
            return;
        }

        $course = $hook->formwrapper->get_course();
        if (empty($course->id)) {
            return;
        }

        $enabled = (bool) get_config('local_aceengine', 'ace_course_enabled_' . $course->id);
        $mform = $hook->mform;
        $el = $mform->getElement('local_aceengine_enabled');
        if ($el) {
            $el->setValue($enabled ? 1 : 0);
        }
    }

    /**
     * Save ACE settings when the course form is submitted.
     *
     * @param after_form_submission $hook The hook instance.
     */
    public static function save_course_form(after_form_submission $hook): void {
        if (!get_config('local_aceengine', 'enableplugin')) {
            return;
        }

        $data = $hook->get_data();
        if (empty($data->id)) {
            return;
        }

        $enabled = !empty($data->local_aceengine_enabled) ? 1 : 0;
        set_config('ace_course_enabled_' . $data->id, $enabled, 'local_aceengine');
    }
}
