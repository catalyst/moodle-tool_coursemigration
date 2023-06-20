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
 * Upload and store CSV file for migrating courses.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursemigration;

defined('MOODLE_INTERNAL') || die();

/**
 * Class that contains the helper functions
 * for the uploading and storing of the CSV file for migrating courses
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uploadcourselist {

    /**
     * Function to display upload courses form.
     *
     * @return array [ status, messages ].
     */
    public static function display_upload_courses_form() {
        $uploadcoursesurl = new \moodle_url('/admin/tool/coursemigration/uploadcourses.php');
        $uploadcoursesform = new \tool_coursemigration\form\uploadcourselist_form($uploadcoursesurl);

        $uploadcoursesform->display();

        // TODO: Form processing status and error messages.
        return [true, null];
    }
}
