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

use core\task\manager as taskmanager;
use moodle_exception;
use \tool_coursemigration\coursemigration;

defined('MOODLE_INTERNAL') || die();

/**
 * Class that has functions to create ad-hoc backup tasks;
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class backup extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('create_ad-hoc_backup_tasks', 'tool_coursemigration');
    }

    /**
     *
     * Create ad-hoc backup tasks.
     */
    public function execute() {
        global $DB;

        // TODO: get limit setting.
        $limit = 20;

        mtrace("Getting admin info");
        $admin = get_admin();
        if (!$admin) {
            mtrace("Error: No admin account was found");
            return;
        }

        mtrace('  Get courses set for backup...');
        $coursemigration = new coursemigration();

        $backuprecords = $coursemigration::get_records([
            'action' => $coursemigration::ACTION_BACKUP,
            'status' => $coursemigration::STATUS_NOT_STARTED
        ], null, null, null, $limit );


        foreach ($backuprecords as $cmrecord) {
            try {
                $courseid = $cmrecord->get('courseid');
                // Check if the provided course exists. If not, update status to Failed and save the error.
                $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);

                $asynctask = new course_backup_task();
                $asynctask->set_blocking(false);
                $asynctask->set_custom_data([
                    'courseid' => $courseid,
                    'adminid' => $admin->id,
                    'coursemigrationid' => $cmrecord->get('id')
                ]);
                taskmanager::queue_adhoc_task($asynctask);
                $cmrecord->set('status', coursemigration::STATUS_IN_PROGRESS);
                $cmrecord->save();
                mtrace(get_string('successfullycreatebackuptask', 'tool_coursemigration', [
                    'coursemigrationid' => $cmrecord->get('id'),
                ]));

            } catch (moodle_exception $e) {
                $message = get_string('error:createbackuptask', 'tool_coursemigration', [
                    'coursemigrationid' => $cmrecord->get('id'),
                    'errormessage' => $e->getMessage()
                ]);
                $cmrecord->set('status', coursemigration::STATUS_FAILED);
                $cmrecord->set('error', $message);
                $cmrecord->save();
                mtrace($message);
                continue;
            }
        }
    }

}
