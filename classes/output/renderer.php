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
 * Renderer for local_ace output.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ace\output;

/**
 * Plugin renderer for local_ace.
 *
 * Provides rendering methods for the ACE dashboard and quest card components.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the ACE dashboard page.
     *
     * @param dashboard $page The dashboard renderable.
     * @return string The rendered HTML.
     */
    public function render_dashboard(dashboard $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_ace/dashboard', $data);
    }

    /**
     * Render a single quest card.
     *
     * @param quest_card $quest The quest card renderable.
     * @return string The rendered HTML.
     */
    public function render_quest_card(quest_card $quest): string {
        $data = $quest->export_for_template($this);
        return $this->render_from_template('local_ace/quest_card', $data);
    }

    /**
     * Render the My Quests page.
     *
     * @param my_quests $page The my_quests renderable.
     * @return string The rendered HTML.
     */
    public function render_my_quests(my_quests $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_ace/my_quests', $data);
    }
}
