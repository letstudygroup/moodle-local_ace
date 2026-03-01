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
 * Dashboard interactivity module for local_aceengine.
 *
 * Initializes the ACE dashboard on a course page.
 * Quest completion is handled automatically via Moodle event observers
 * when students complete the linked activities.
 *
 * @module     local_aceengine/dashboard
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var {int} courseid The course ID for this dashboard. */
let courseid = 0;

/**
 * Initialize the dashboard module.
 *
 * @param {int} courseId The course ID.
 */
export const init = (courseId) => {
    courseid = courseId;
};
