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
use tool_coursemigration\event\backup_completed;
use tool_coursemigration\event\backup_failed;
use tool_coursemigration\helper;
use tool_coursemigration\restore_api_factory;

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
        global $CFG, $USER;

        $data = (array) $this->get_custom_data();
        if (!$this->is_custom_data_valid($data)) {
            $errormsg = 'Invalid data. Error: missing one of the required parameters.';
            backup_failed::create([
                'objectid' => 0,
                'other' => [
                    'error' => $errormsg,
                ]
            ])->trigger();
            throw new invalid_parameter_exception($errormsg);
        }

        $coursemigration = coursemigration::get_record(['id' => $data['coursemigrationid']]);
        if (empty($coursemigration)) {
            $errormsg = 'No match for Course migration id: ' . $data['coursemigrationid'];
            backup_failed::create([
                'objectid' => 0,
                'other' => [
                    'error' => $errormsg,
                ]
            ])->trigger();
            throw new invalid_parameter_exception($errormsg);
        }

        $backupdir = "backup_" . uniqid();
        $destination = $CFG->tempdir . DIRECTORY_SEPARATOR . "backup" . DIRECTORY_SEPARATOR . $backupdir;
        if (!is_dir($destination)) {
            mkdir($destination);
        }

        try {
            $course = get_course($coursemigration->get('courseid'));
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
            $filename = $coursemigration->get('id') . '-' .
                backup_plan_dbops::get_default_backup_filename($format, $type, $id, $users, $anonymised);
            $bc->get_plan()->get_setting('filename')->set_value($filename);
            $bc->execute_plan();
            $results = $bc->get_results();
            $file = $results['backup_destination'];

            if ($file) {
                $storage = helper::get_selected();
                // Check that the storage class has been configured.
                if (!$storage) {
                    throw new moodle_exception('error:storagenotconfig', 'tool_coursemigration');
                }
                mtrace("Writing " . $filename);
                if ($storage->push_file($filename, $file)) {
                    $coursemigration->set('filename', $filename);

                    $api = restore_api_factory::get_restore_api();
                    if ($api->request_restore($filename, (int)$coursemigration->get('destinationcategoryid'))) {
                        $coursemigration
                            ->set('status', coursemigration::STATUS_COMPLETED)
                            ->save();
                    } else {
                        throw new moodle_exception('error:restorerequestfailed', 'tool_coursemigration');
                    }

                    mtrace("Backup completed.");

                    backup_completed::create([
                        'objectid' => $coursemigration->get('id'),
                        'other' => [
                            'courseid' => $course->id,
                            'coursename' => $course->fullname,
                            'destinationcategoryid' => $coursemigration->get('destinationcategoryid'),
                            'filename' => $filename,
                        ]
                    ])->trigger();
                } else {
                    throw new file_exception(get_string(
                        'error:copydestination',
                        'tool_coursemigration',
                        $storage->get_error()
                    ));
                }
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $coursemigration->set('status', coursemigration::STATUS_FAILED)
                ->set('error', $message)
                ->save();
            mtrace($message);
            backup_failed::create([
                'objectid' => $coursemigration->get('id'),
                'other' => [
                    'error' => $message,
                ]
            ])->trigger();
        } finally {
            // Delete backup file from course.
            if (isset($file) && $file) {
                $file->delete();
            }
            if (!empty($bc)) {
                $bc->destroy();
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
