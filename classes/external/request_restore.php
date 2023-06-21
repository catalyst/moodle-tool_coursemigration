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

namespace tool_coursemigration\external;

use external_api;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;
use core_course_category;
use context_coursecat;
use tool_coursemigration\coursemigration;

/**
 * Request restore external APIs.
 *
 * @package    tool_coursemigration
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_restore extends external_api {

    /**
     * Describes the parameters for validate_form webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'filename' => new external_value(PARAM_FILE, 'File name to restrore'),
            'categoryid' => new external_value(PARAM_INT, 'Destination category ID', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute API action.
     *
     * @param string $filename Bento ID.
     * @param int $categoryid Destination category ID.
     */
    public static function execute(string $filename, int $categoryid = 0): void {
        $params = self::validate_parameters(self::execute_parameters(), [
            'filename' => $filename,
            'categoryid' => $categoryid,
        ]);

        // Check that category exists.
        if (!empty($categoryid)) {
            $category = core_course_category::get($categoryid, IGNORE_MISSING);
        }

        // If category is not exist fall back to configured default category.
        if (empty($category)) {
            $categoryid = get_config('tool_coursemigration', 'defaultcategory');
            $category = core_course_category::get($categoryid, IGNORE_MISSING);
        }

        // If default category is also not exist, then explode.
        if (empty($category)) {
            throw new invalid_parameter_exception('Invalid category');
        }

        $context = context_coursecat::instance($categoryid);
        self::validate_context($context);
        require_capability('tool/coursemigration:restorecourse', $context);

        $record = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => $categoryid,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'filename' => $filename,
        ]);
        $record->save();
    }

    /**
     * Returns description of method result value.
     */
    public static function execute_returns() {
        return null;
    }
}
