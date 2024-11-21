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

use core\task\manager;
use core\task\scheduled_task;
use moodle_exception;
use tool_coursemigration\coursemigration;

/**
 * Class that has functions to create ad-hoc backup tasks;
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class create_backup_tasks extends scheduled_task {

    /**
     * Returns the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:createbackuptasks', 'tool_coursemigration');
    }

    /**
     * Create ad-hoc backup tasks.
     */
    public function execute() {

        mtrace('Starting to create backup adhoc tasks for course migration.');

        $coursemigrations = coursemigration::get_records([
            'action' => coursemigration::ACTION_BACKUP,
            'status' => coursemigration::STATUS_NOT_STARTED
        ]);

        mtrace('  Found ' . count($coursemigrations) . ' courses to backup');
        foreach ($coursemigrations as $coursemigration) {
            try {
                $courseid = $coursemigration->get('courseid');
                // Check if the provided course exists. If not, update status to Failed and save the error.
                $course = get_course($courseid);

                $asynctask = new course_backup();
                $asynctask->set_custom_data([
                    'coursemigrationid' => $coursemigration->get('id')
                ]);
                manager::queue_adhoc_task($asynctask);
                $coursemigration->set('status', coursemigration::STATUS_IN_PROGRESS)
                    ->save();
                mtrace(get_string('successfullycreatebackuptask', 'tool_coursemigration', [
                    'coursemigrationid' => $coursemigration->get('id'),
                ]));
            } catch (moodle_exception $e) {
                $message = get_string('error:createbackuptask', 'tool_coursemigration', [
                    'coursemigrationid' => $coursemigration->get('id'),
                    'errormessage' => $e->getMessage()
                ]);
                $coursemigration->set('status', coursemigration::STATUS_FAILED)
                    ->set('error', $message)
                    ->save();
                mtrace($message);
                continue;
            }
        }
    }

}
