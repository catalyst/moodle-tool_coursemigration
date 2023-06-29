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
 * Adhoc task that performs single automated course backup.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursemigration\task;

use backup;
use backup_controller;
use backup_plan_dbops;
use core\task\adhoc_task;
use file_exception;
use invalid_parameter_exception;
use moodle_exception;
use tool_coursemigration\coursemigration;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/helper/backup_cron_helper.class.php');

/**
 * Adhoc task that performs single automated course backup.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_backup extends adhoc_task {

    /**
     * Run the adhoc task and preform the backup.
     */
    public function execute() {
        global $CFG, $USER, $DB;

        $data = (array) $this->get_custom_data();
        if (!$this->is_custom_data_valid($data)) {
            $errormsg = 'Invalid data. Error: missing one of the required parameters.';
            throw new invalid_parameter_exception($errormsg);
        }

        $coursemigrationid = $data['coursemigrationid'];
        $cmrecord = coursemigration::get_record(['id' => $coursemigrationid]);
        if (empty($cmrecord)) {
            throw new invalid_parameter_exception('No match for Course migration id: ' . $coursemigrationid);
        }

        $backupdir = "backup_" . uniqid();
        $destination = $CFG->tempdir . DIRECTORY_SEPARATOR . "backup" . DIRECTORY_SEPARATOR . $backupdir;
        if (!is_dir($destination)) {
            mkdir($destination);
        }

        try {
            $course = get_course($cmrecord->get('courseid'));
            $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id);

            // Override setting to not include users.
            $bc->get_plan()->get_setting('users')->set_value(0);
            $bc->get_plan()->get_setting('anonymize')->set_value(0);

            $format = $bc->get_format();
            $type = $bc->get_type();
            $id = $bc->get_id();
            $users = $bc->get_plan()->get_setting('users')->get_value();
            $anonymised = $bc->get_plan()->get_setting('anonymize')->get_value();
            $filename = backup_plan_dbops::get_default_backup_filename($format, $type, $id, $users, $anonymised);
            $bc->get_plan()->get_setting('filename')->set_value($filename);
            $bc->execute_plan();
            $results = $bc->get_results();
            $file = $results['backup_destination'];

            if ($file) {
                $fullpath = $destination . DIRECTORY_SEPARATOR . $filename;
                mtrace("Writing " . $fullpath);
                if ($file->copy_content_to($fullpath)) {
                    mtrace("Backup completed.");
                    // TODO: Implement trigger WS.
                    $cmrecord->set('status', coursemigration::STATUS_COMPLETED)
                        ->save();
                } else {
                    $message = get_string('error:copydestination', 'tool_coursemigration', $fullpath);
                    throw new file_exception($message);
                }
            }
            $bc->destroy();
        } catch (moodle_exception $e) {
            $message = $e->getMessage();
            $cmrecord->set('status', coursemigration::STATUS_FAILED)
                ->set('error', $message)
                ->save();
            mtrace($message);
        } finally {
            // Delete backup file from course.
            if (isset($file) && $file) {
                $file->delete();
            }
        }
    }

    /**
     * Check custom data is valid, (contains all required params).
     *
     * @param array $data custom data to validate.
     * @return bool true if valid, false otherwise.
     */
    protected function is_custom_data_valid(array $data): bool {
        $keys = array_keys($data);
        $requiredfields = ['coursemigrationid'];

        foreach ($requiredfields as $requiredfield) {
            if (!in_array($requiredfield, $keys)) {
                return false;
            }
        }
        return true;
    }
}
