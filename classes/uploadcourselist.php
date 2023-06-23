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

/**
 * Class that contains the helper functions.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uploadcourselist {

    /**
     * List of valid colums and validation groups for each DB field.
     * One of id or url AND one of category id, category id number, category path should be in the file to pass validation.
     */
    const VALID_COLUMN_GROUPS = ['courseid' => ['courseid', 'url'],
        'destinationcategoryid' => ['categoryid', 'categoryid_number', 'categorypath']];

    /**
     * Function to get upload courses form csv data.
     *
     * @param object $uploadcourseform upload course form
     * @return \csv_import_reader|false
     */
    public static function get_upload_courses_form_csv_data($uploadcourseform) {
        $uploadcourseformdata = $uploadcourseform->get_data();

        if (is_null($uploadcourseformdata)) {
            return false;
        }

        $records = [];
        $iid = \csv_import_reader::get_new_iid('csvfile');
        $cir = new \csv_import_reader($iid, 'csvfile');
        $content = $uploadcourseform->get_file_content('csvfile');

        $cir->load_csv_content($content, $uploadcourseformdata->encoding, $uploadcourseformdata->delimiter_name);

        if (!is_null($cir->get_error())) {
            return false;
        }

        return $cir;
    }


    /**
     * Function to get csv data and import records to DB.
     *
     * @param \csv_import_reader $cir csv import reader
     * @return string
     */
    public static function process_csv_file($cir) {
        $columns = $cir->get_columns();

        list($status, $message, $fields) = self::csv_required_columns($columns);
        if (!$status) {
            return $message;
        }

        $cir->init();
        $rownumber = 1;
        $errors = [];
        $success = 0;
        $failed = 0;

        $coursemigration = new coursemigration;

        while ($row = $cir->next()) {
            [$status, $rowdata, $messages] = self::process_row($row, $fields, $rownumber);
            if ($status) {
                // Add record to DB.
                $coursemigration->from_record($rowdata);
                $coursemigration->save();
                $success++;
            } else {
                $errors = array_merge($errors, $messages);
                $failed++;
            }
            $rownumber++;
        }

        // Prepare return messages.
        $displaymessages = get_string('returnmessages', 'tool_coursemigration',
        ['errorcount' => count($errors),
            'errormessages' => implode("<br\>", $errors),
            'rowcount' => $rownumber - 1,
            'success' => $success,
            'failed' => $failed]);

        return $displaymessages;
    }

    /**
     * Function to process csv row data.
     *
     * @param array $row Record from CSV file.
     * @param array $fields Column indexes to be processed.
     * @param integer $rownumber Current row in CSV file, used for reporting errors.
     * @return array [$status, (object) $data, $message]
     *
     * Structure of $fields:
     * [courseid] Course id field name and index [ columnname, columnindex ]
     * [category] category field name and index [ columnname, columnindex ]
     */
    public static function process_row($row, $fields, $rownumber) {

        // One of id or url AND one of category id, category id number, category path should be in the file to pass validation.
        $validcolumngroups = self::VALID_COLUMN_GROUPS;

        // Initialise return data.
        $data = [];
        $message = [];
        $status = true;

        // Compare column headings in CSV file with valid column groups.
        foreach ($validcolumngroups as $type => $validcolumns) {
            $datafield = $fields[$type]['columnname'];

            // Specific handling for each type of column in CSV fie.
            switch ($datafield) {
                case 'courseid':
                case 'categoryid':
                    $value = $row[$fields[$type]['columnindex']];
                    $params = ['mode' => 'integer', 'csvcolumn' => $datafield, 'rownumber' => $rownumber];
                    $data[$type] = self::validate_csv_field($value, $status,
                        $message, $params);
                    break;
                case 'url':
                    $url = new \moodle_url($row[$fields[$type]['columnindex']]);
                    $data[$type] = $url->get_param('id');
                    break;
            }
        }

        return [$status, (object) $data, $message];
    }

    /**
     * Function to validate number of columns in csv file.
     *
     * @param int $columns column count
     * @return array|null [ status, message[], fields[] ]
     *
     * Deffinition: status   - validation pass or fail (true | false)
     *              messages - string containing validation errors.
     *              fields  - an array of the column numbers in the CSV file.
     */
    public static function csv_required_columns($columns) {

        // Change fields to lowercase.
        $columns = array_map('strtolower', $columns);

        // One of id or url AND one of category id, category id number, category path should be in the file to pass validation.
        $validcolumngroups = self::VALID_COLUMN_GROUPS;

        // Set default vale for passing validation.
        $status = true;
        $errors = [];
        $fields = [];

        // Compare column headings in CSV file with valid column groups.
        foreach ($validcolumngroups as $type => $validcolumns) {
            // The preferred column will be at index 0.
            $check = array_values(array_intersect($columns, $validcolumns));
            if (empty($check)) {
                $status = false;
                $errors[] = get_string('missing_column', 'tool_coursemigration',
                    ['columnlist' => implode(", ", $validcolumns)]);
            } else {
                // Store index of column for processing of rows.
                // This selects one field from each column group in priority order.
                $fields[$type] = ['columnname' => $check[0], 'columnindex' => array_search($check[0], $columns)];
            }
        }

        $message = empty($errors) ? null : implode(" AND ", $errors);
        return [$status, $message, $fields];
    }

    /**
     * Function to display upload courses form.
     *
     * @return string messages.
     */
    public static function display_upload_courses_form() {
        global $CFG;

        if (!get_config('tool_coursemigration', 'destinationwsurl') || !get_config('tool_coursemigration', 'wstoken')) {
            $settingsurl = new \moodle_url('/admin/settings.php', ['section' => 'tool_coursemigration_settings']);
            $link = \html_writer::link($settingsurl, get_string('settings_link_text', 'tool_coursemigration'));
            return get_string('error:pluginnotsetup', 'tool_coursemigration', $link);
        };

        $uploadcoursesurl = new \moodle_url('/admin/tool/coursemigration/uploadcourses.php');
        $uploadcoursesform = new \tool_coursemigration\form\uploadcourselist_form($uploadcoursesurl);

        $uploadcoursesform->get_data();
        $cir = self::get_upload_courses_form_csv_data($uploadcoursesform);

        if ($cir) {
            // Process CSV file.
            $messages = self::process_csv_file($cir);
            // Display results of upload.
            return $messages;
        }

        $uploadcoursesform->display();

        // TODO: Form processing status and error messages.
        return null;
    }

    /**
     * Function to validate CSV field.
     *
     * @param integer|string $datavalue Field value from CSV file.
     * @param boolean $status By ref to update pass or fail of the row.
     * @param array $message By ref to add error messages.
     * @param array $params Information to be included in processing and error messages.
     * @return int|null $value
     *
     * Params: Mode      - only integer used at the moment.
     *         csvcolumn - Name of column in CSV file
     *         rownumber - The current row of the CSV file.
     */
    public static function validate_csv_field($datavalue, &$status, &$message, $params) {
        $value = null;
        switch ($params['mode']) {
            case 'integer':
                if (is_number($datavalue)) {
                    $value = (int)$datavalue;
                } else {
                    $status = false;
                    $message[] = get_string('error:nonintegervalue', 'tool_coursemigration',
                        ['csvcolumn' => $params['csvcolumn'], 'rownumber' => $params['rownumber']]);
                }
                break;
        }
        return $value;
    }

}
