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

namespace tool_coursemigration\task;

use backup;
use core\task\adhoc_task;
use Exception;
use invalid_parameter_exception;
use tool_coursemigration\coursemigration;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Adhoc task to restore the course.
 *
 * @package    tool_coursemigration
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore extends adhoc_task {

    /**
     * Run the task to restore the course.
     */
    public function execute() {
        global $CFG, $USER;

        $data = (array) $this->get_custom_data();
        if (!$this->is_custom_data_valid($data)) {
            // TODO: #18 Event when restore adhoc task failed.
            throw new invalid_parameter_exception('Invalid data. Error: missing one of the required parameters.');
        }

        $coursemigrationid = $data['coursemigrationid'];
        $coursemigration = coursemigration::get_record(['id' => $coursemigrationid]);
        if (empty($coursemigration)) {
            // TODO: #18 Event when restore adhoc task failed.
            throw new invalid_parameter_exception('Invalid id. Error: could not find record for restore.');
        }

        $backupdir = "restore_" . uniqid();
        $path = $CFG->tempdir . DIRECTORY_SEPARATOR . "backup" . DIRECTORY_SEPARATOR . $backupdir;

        try {
            $fp = get_file_packer('application/vnd.moodle.backup');
            // TODO: Replace the filename to actual location.
            $fp->extract_to_pathname($coursemigration->get('filename'), $path);

            list($fullname, $shortname) = restore_dbops::calculate_course_names(0, get_string('restoringcourse', 'backup'),
                get_string('restoringcourseshortname', 'backup'));

            $courseid = restore_dbops::create_new_course($fullname, $shortname, $coursemigration->get('destinationcategoryid'));
            $coursemigration->set('courseid', $courseid)->save();

            $rc = new restore_controller($backupdir, $courseid, backup::INTERACTIVE_NO,
                backup::MODE_GENERAL, $USER->id, backup::TARGET_NEW_COURSE);
            $rc->execute_precheck();
            $rc->execute_plan();
            $rc->destroy();
            $coursemigration->set('status', coursemigration::STATUS_COMPLETED)
                ->set('courseid', $courseid)
                ->save();
            // TODO: #17 Event when restore adhoc task completed.
        } catch (Exception $e) {
            $errormsg = 'Cannot restore the course. ' . $e->getMessage();
            $coursemigration->set('status', coursemigration::STATUS_FAILED)
                ->set('error', $errormsg)
                ->save();
            // TODO: #18 Event when restore adhoc task failed.
            fulldelete($path);
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
