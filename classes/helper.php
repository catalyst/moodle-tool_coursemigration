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

use coding_exception;
use context_user;
use core_course_category;
use invalid_parameter_exception;
use tool_coursemigration\local\storage\storage_interface;

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
    public static function get_action_list(): array {
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
    public static function get_status_list(): array {
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
            case coursemigration::STATUS_RETRYING:
                $string = get_string('status:retrying', 'tool_coursemigration');
                break;
            default:
                $string = get_string('status:invalid', 'tool_coursemigration');
                break;
        }
        return $string;
    }

    /**
     * Gets file name for uploaded file.
     *
     * @param string $fileitemid Uploaded file itemid.
     * @return string
     */
    public static function get_uploaded_filename(string $fileitemid): string {
        global $USER;

        $filename = '';

        $fs = get_file_storage();
        $usercontext = context_user::instance($USER->id);
        if ($files = $fs->get_area_files($usercontext->id, 'user', 'draft', $fileitemid, 'id DESC', false)) {
            $file = reset($files);
        }

        if (!empty($file)) {
            $filename = $file->get_filename();
        }

        return $filename;
    }

    /**
     * Get category by categoryid with defaultcategory fallback.
     *
     * @param int $categoryid
     * @return core_course_category
     */
    public static function get_restore_category(int $categoryid): core_course_category {

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

        return $category;
    }

    /**
     * Returns a storage class object as selected in configuration.
     * @return storage_interface|null storage class object
     */
    public static function get_selected_storage(): ?storage_interface {
        $configselectedstorage = get_config('tool_coursemigration', 'storagetype');
        if ($configselectedstorage) {
            $storage = new $configselectedstorage;
            $classimplements = class_implements($storage);
            if (!isset($classimplements['tool_coursemigration\local\storage\storage_interface'])) {
                throw new coding_exception('The selected Storage class does not implement the storage_interface.');
            }
            return $storage;
        }
        return null;
    }

    /**
     * Gets retry number from task fail delay,
     *
     * @param int $faildelay Fail delay value.
     * @return int
     */
    public static function get_retry_number_from_fail_delay(int $faildelay): int {
        if ($faildelay <= 0) {
            return 0;
        }

        return intval(log($faildelay / 60, 2) + 1);
    }
}
