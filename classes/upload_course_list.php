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

namespace tool_coursemigration;

use csv_import_reader;
use moodle_url;

/**
 * Class that contains the helper functions.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_course_list {

    /**
     * List of valid colums and validation groups for each DB field.
     * One of id or url AND one of category id should be in the file to pass validation.
     */
    const VALID_COLUMN_GROUPS = [
        'courseid' => ['courseid', 'url'],
        'destinationcategoryid' => ['categoryid']
    ];

    /**
     * Function to process upload courses form.
     *
     * @param csv_import_reader $csvimportreader CSV import reader object.
     * @return upload_results.
     */
    public static function process_submitted_form(csv_import_reader $csvimportreader): upload_results {
        $results = new upload_results();

        $columns = $csvimportreader->get_columns();
        list($status, $message, $fields) = self::csv_required_columns($columns);
        if (!$status) {
            $results->set_result_message($message);
            return $results;
        }

        $csvimportreader->init();
        $rownumber = 1;
        $errors = [];
        $success = 0;
        $failed = 0;

        while ($row = $csvimportreader->next()) {
            $coursemigration = new coursemigration;
            [$status, $rowdata, $messages] = self::process_row($row, $fields, $rownumber);
            if ($status) {
                // Add record to DB.
                $coursemigration->from_record($rowdata);
                if ($coursemigration->is_valid()) {
                    $coursemigration->save();
                    $success++;
                } else {
                    $errors = array_merge($errors, $coursemigration->get_errors());
                    $failed++;
                }
            } else {
                $errors = array_merge($errors, $messages);
                $failed++;
            }
            $rownumber++;
        }

        // Prepare return messages.
        $rowcount = $rownumber - 1;
        $displaymessage = get_string('returnmessages', 'tool_coursemigration', [
            'errorcount' => count($errors),
            'errormessages' => implode("<br\>", $errors),
            'rowcount' => $rowcount,
            'success' => $success,
            'failed' => $failed
        ]);

        $results->set_errorcount(count($errors));
        $results->set_result_message($displaymessage);
        $results->set_rowcount($rowcount);
        $results->set_failed($failed);
        $results->set_success($success);

        return $results;
    }

    /**
     * Function to process csv row data.
     *
     * @param array $row Record from CSV file.
     * @param array $fields Column indexes to be processed.
     * @param int $rownumber Current row in CSV file, used for reporting errors.
     * @return array [$status, (object) $data, $message]
     *
     * Structure of $fields:
     * [courseid] Course id field name and index [ columnname, columnindex ]
     * [category] category field name and index [ columnname, columnindex ]
     */
    private static function process_row(array $row, array $fields, int $rownumber): array {

        // Initialise return data.
        $data = [];
        $message = [];
        $status = true;

        // Compare column headings in CSV file with valid column groups.
        foreach (self::VALID_COLUMN_GROUPS as $type => $validcolumns) {
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
                    $url = new moodle_url($row[$fields[$type]['columnindex']]);
                    $data[$type] = $url->get_param('id');
                    break;
            }
        }

        return [$status, (object) $data, $message];
    }

    /**
     * Function to validate number of columns in csv file.
     *
     * @param array $columns List of column names.
     * @return array|null [ status, message[], fields[] ]
     *
     * Deffinition: status   - validation pass or fail (true | false)
     *              messages - string containing validation errors.
     *              fields  - an array of the column numbers in the CSV file.
     */
    private static function csv_required_columns(array $columns): ?array {
        // Change fields to lowercase.
        $columns = array_map('strtolower', $columns);

        // Set default vale for passing validation.
        $status = true;
        $errors = [];
        $fields = [];

        // Compare column headings in CSV file with valid column groups.
        foreach (self::VALID_COLUMN_GROUPS as $type => $validcolumns) {
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
     * Function to validate CSV field.
     *
     * @param int|string $datavalue Field value from CSV file.
     * @param bool $status By ref to update pass or fail of the row.
     * @param array $message By ref to add error messages.
     * @param array $params Information to be included in processing and error messages.
     * @return int|null $value
     *
     * Params: Mode      - only integer used at the moment.
     *         csvcolumn - Name of column in CSV file
     *         rownumber - The current row of the CSV file.
     */
    private static function validate_csv_field($datavalue, bool &$status, array &$message, array $params): ?int {
        $value = null;
        if ($params['mode'] == 'integer') {
            if (is_number($datavalue)) {
                $value = (int)$datavalue;
            } else {
                $status = false;
                $message[] = get_string('error:nonintegervalue', 'tool_coursemigration',
                    ['csvcolumn' => $params['csvcolumn'], 'rownumber' => $params['rownumber']]);
            }
        }
        return $value;
    }
}
