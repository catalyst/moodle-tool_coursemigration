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
 * Upload course validate  - Class.
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
 * for the upload course validate .
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uploadcourse_validate {

    /**
     * Function to validate number of columns in csv file.
     *
     * @param int $columncount column count
     * @return array|null [ status, indexes, message[] ]
     *
     * Deffinition: status   - validation pass or fail (true | false)
     *              messages - string containing validation errors.
     *              fields  - an array of the column numbers in the CSV file.
     */
    public static function csv_required_columns($columns) {

        // Change fields to lowercase
        $columns = array_map('strtolower', $columns);

        // One of id or url AND one of category id, category id number, category path should be in the file to pass validation.
        $validcolumngroups = ['courseid' => ['courseid', 'url'], 'category' => ['category_id', 'category_id_number', 'category_path']];

        // Set default vale for passing validation.
        $pass = true;
        $errors = [];
        $fields = [];

        // Compare column headings in CSV file with valid column groups.
        foreach ($validcolumngroups as $type => $validcolumns) {
            $check = array_intersect($columns, $validcolumns);
            if (empty($check)) {
                $pass = false;
                $errors[] = get_string('missing_column', 'tool_coursemigration',
                    ['columnlist' => implode(", ", $validcolumns)]);
            } else {
                // Store index of column for processing of rows.
                // This selects one field from each column group in priority order.
                $fields[$type] = ['column_name' => $check[0], 'column_index' => array_search($check[0], $columns)];
            }
        }

        $message = empty($errors) ? null : implode(" AND ", $errors);
        return [$pass, $message, $fields];
    }
}