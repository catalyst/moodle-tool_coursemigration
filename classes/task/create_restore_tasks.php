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
use core_course_category;
use core\task\asynchronous_restore_task;
use core\task\manager;
use core\task\scheduled_task;
use Exception;
use moodle_exception;
use tool_coursemigration\coursemigration;
use restore_controller;
use restore_controller_dbops;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Scheduled task to create restore adhoc tasks.
 *
 * @package    tool_coursemigration
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_restore_tasks extends scheduled_task {

    /**
     * Returns the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:createrestoretasks', 'tool_coursemigration');
    }

    /**
     * Run the task to create restore adhoc tasks.
     */
    public function execute() {
        global $USER;

        mtrace('Starting to create restore adhoc tasks for course migration.');

        $coursemigrations = coursemigration::get_records(['status' => coursemigration::STATUS_NOT_STARTED]);
        mtrace(count($coursemigrations) . ' courses found.');
        foreach ($coursemigrations as $coursemigration) {
            try {
                $category = core_course_category::get($coursemigration->get('destinationcategoryid'));
            } catch (moodle_exception $e) {
                $errormsg = 'Invalid categoryid:' . $e->getMessage();
                $coursemigration->set('status', coursemigration::STATUS_FAILED);
                $coursemigration->set('error', $errormsg);
                $coursemigration->save();
                mtrace($errormsg);
                continue;
            }

            $backupdir = "restore_" . uniqid();

            try {
                $coursemigration->set('status', coursemigration::STATUS_IN_PROGRESS);
                $coursemigration->save();
                list($fullname, $shortname) = restore_controller_dbops::calculate_course_names(0, get_string('restoringcourse', 'backup'),
                    get_string('restoringcourseshortname', 'backup'));

                $courseid = restore_controller_dbops::create_new_course($fullname, $shortname, $category->id);

                $rc = new restore_controller($backupdir, $courseid, backup::INTERACTIVE_NO,
                    backup::MODE_GENERAL, $USER->id, backup::TARGET_NEW_COURSE);

                $restoreid = $rc->get_restoreid();
                $asynctask = new asynchronous_restore_task();
                $asynctask->set_blocking(false);
                $asynctask->set_userid($USER->id);
                $asynctask->set_custom_data(array('backupid' => $restoreid));
                manager::queue_adhoc_task($asynctask);
                mtrace('A restore task is successfully added.');
            } catch (Exception $e) {
                $errormsg = 'Cannot create adhoc task:' . $e->getMessage();
                $coursemigration->set('status', coursemigration::STATUS_FAILED);
                $coursemigration->set('error', $errormsg);
                $coursemigration->save();
                mtrace($errormsg);
                continue;
            }
        }
    }
}
