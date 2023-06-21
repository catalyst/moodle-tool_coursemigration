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

namespace tool_coursemigration\output;

use moodle_url;
use plugin_renderer_base;

/**
 * Implements the report renderer
 *
 * @author      Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright   2023 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render coursemigration table.
     *
     * @param moodle_url $url Url where this table is displayed.
     * @param int $pagesize Number of records to display per page. 0 means display all.
     * @return string
     */
    public function render_coursemigration_report(moodle_url $url, int $pagesize = 0): string {
        $renderable = new coursemigration_table($url, $pagesize);
        ob_start();
        $renderable->out($pagesize, true);
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

}
