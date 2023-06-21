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
     * Function to get upload courses form csv data.
     *
     * @param object $uploadcourseform upload course form
     * @return array|false
     */
    public static function get_upload_courses_form_csv_data($uploadcourseform) {
        $uploadcourseformdata = $uploadcourseform->get_data();

        $records = [];
        $iid = \csv_import_reader::get_new_iid('csvfile');
        $cir = new \csv_import_reader($iid, 'csvfile');
        $content = $uploadcourseform->get_file_content('csvfile');

        $cir->load_csv_content($content, $uploadcourseformdata->encoding, $uploadcourseformdata->delimiter_name);

        if (!is_null($cir->get_error())) {
            return false;
        }

        $records = self::get_csv_data($uploadcourseformdata, $cir);

        return count($records) > 0 ? $records : false;
    }


    /**
     * Function to get csv data.
     *
     * @param object $uploadcourseformdata upload course form data
     * @param object $cir csv import reader
     * @return array
     */
    public static function get_csv_data($uploadcourseformdata, $cir) {
        $columns = $cir->get_columns();

        // TODO: Possibly remove these.
        if ($uploadcourseformdata->delimiter_name != 'comma') {
            print_error('csvloaderror', '', '', get_string('invaliddelimiter', 'tool_coursemigration'));
        }

        if ($uploadcourseformdata->encoding != 'UTF-8') {
            print_error('csvloaderror', '', '', get_string('invalidencoding', 'tool_coursemigration'));
        }

        list($status, $message, $fields) = uploadcourse_validate::csv_required_columns($columns);
        if (!$status) {
            print_error('csvloaderror', '', '', $message);
        }

        $data = [];
        $id = 1;
        $cir->init();

        while ($row = $cir->next()) {
            $record = new \Stdclass;
            $record->courseid = $id;
            $data[] = $record;
        }

        return $data;
    }

    /**
     * Function to process csv row data.
     *
     * @param array $row Record from CSV file.
     * @param array $fields Column indexes to be processed.
     * @return array
     *
     * Structure of $fields:
     * [courseid] Course id field name and index [ column_name, column_index ]
     * [category] category field name and index [ column_name, column_index ]
     */
    public static function get_csv_data($row, $fields) {
        $courseidfield = $fields['courseid']['column_name'];
        $categoryfield = $fields['category']['column_name'];

        // Initialise return data.
        $data = [];

        // Process course id field.
        switch ($courseidfield) {
            case 'courseid':
                $data['courseid'] = $row[$fields['courseid']['column_index']];
                break;
            case 'url':
                $url = new \moodle_url($row[$fields['courseid']['column_index']]);
                $data['courseid'] = $url->get_param('id');
                break;
        }

        // Process category field.
        switch ($categoryfield) {
            case 'category_id':
                echo 'category_id';
                break;
            case 'category_id_number':
                echo 'category_id_number';
                break;
            case 'category_path':
                echo 'category_path';
                break;
        }
    }



    /**
     * Function to display upload courses form.
     *
     * @return array [ status, messages ].
     */
    public static function display_upload_courses_form() {
        $uploadcoursesurl = new \moodle_url('/admin/tool/coursemigration/uploadcourses.php');
        $uploadcoursesform = new \tool_coursemigration\form\uploadcourselist_form($uploadcoursesurl);

        $uploadcourselist = self::get_upload_courses_form_csv_data($uploadcoursesform);

        if ($uploadcourselist) {
            // Display results of upload
            return [true, null];
        }

        $uploadcoursesform->display();

        // TODO: Form processing status and error messages.
        return [true, null];
    }
}
