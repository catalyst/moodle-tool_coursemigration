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

use core_course_category;
use core\task\manager;
use core\task\scheduled_task;
use moodle_exception;
use tool_coursemigration\coursemigration;
use tool_coursemigration\helper;

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

        $coursemigrations = coursemigration::get_records([
            'action' => coursemigration::ACTION_RESTORE,
            'status' => coursemigration::STATUS_NOT_STARTED
        ]);
        mtrace(count($coursemigrations) . ' courses found.');
        foreach ($coursemigrations as $coursemigration) {
            try {
                $category = helper::get_restore_category($coursemigration->get('destinationcategoryid'));
                $task = new restore();
                $customdata = ['coursemigrationid' => $coursemigration->get('id')];
                $task->set_custom_data($customdata);
                manager::queue_adhoc_task($task);
                mtrace('A restore task has been successfully added. id=' . $coursemigration->get('id'));
                $coursemigration->set('status', coursemigration::STATUS_IN_PROGRESS)
                    ->save();
            } catch (moodle_exception $e) {
                $errormsg = 'Could not create a restore task. id=' . $coursemigration->get('id') . ',error=' . $e->getMessage();
                $coursemigration->set('status', coursemigration::STATUS_FAILED)
                    ->set('error', $errormsg)
                    ->save();
                mtrace($errormsg);
                continue;
            }
        }
    }
}
