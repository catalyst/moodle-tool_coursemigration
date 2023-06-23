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

/**
 * Helper class.
 *
 * @package     tool_coursemigration
 * @author      Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright   2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Get all actions with string by associative array.
     * @return array
     */
    public static function get_action_list() {
        $list = [];
        foreach (coursemigration::ACTIONS as $val) {
            $list[$val] = self::get_action_string($val);
        }
        return $list;
    }

    /**
     * Get all statuses with string by associative array.
     * @return array
     */
    public static function get_status_list() {
        $list = [];
        foreach (coursemigration::STATUSES as $val) {
            $list[$val] = self::get_status_string($val);
        }
        return $list;
    }

    /**
     * Returns action as a string.
     *
     * @param int $action Action to display.
     * @return string
     */
    public static function get_action_string(int $action): string {
        switch ($action) {
            case coursemigration::ACTION_BACKUP:
                $string = get_string('settings:backup', 'tool_coursemigration');
                break;
            case coursemigration::ACTION_RESTORE:
                $string = get_string('settings:restore', 'tool_coursemigration');
                break;
            default:
                $string = get_string('status:invalid', 'tool_coursemigration');
                break;
        }
        return $string;
    }

    /**
     * Returns status as a string.
     *
     * @param int $status Status to display.
     * @return string
     */
    public static function get_status_string(int $status): string {
        switch ($status) {
            case coursemigration::STATUS_NOT_STARTED:
                $string = get_string('status:notstarted', 'tool_coursemigration');
                break;
            case coursemigration::STATUS_IN_PROGRESS:
                $string = get_string('status:inprogress', 'tool_coursemigration');
                break;
            case coursemigration::STATUS_COMPLETED:
                $string = get_string('status:completed', 'tool_coursemigration');
                break;
            case coursemigration::STATUS_FAILED:
                $string = get_string('status:failed', 'tool_coursemigration');
                break;
            default:
                $string = get_string('status:invalid', 'tool_coursemigration');
                break;
        }
        return $string;
    }
}
