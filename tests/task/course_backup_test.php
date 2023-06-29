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

use advanced_testcase;
use core\task\manager;
use Exception;
use invalid_parameter_exception;
use tool_coursemigration\coursemigration;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Course backup tests.
 *
 * @package    tool_coursemigration
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_coursemigration\task\course_backup
 */
class course_backup_test extends advanced_testcase {

    /**
     * Test backup.
     */
    public function test_course_backup() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test restore course']);
        $category = $generator->create_category();

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'courseid' => $course->id,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();

        $task = new course_backup();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('Backup completed.', $output);

        // Confirm the status is now completed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_COMPLETED, $currentcoursemigration->get('status'));
    }

    /**
     * Test backup without param.
     */
    public function test_restore_invalid_param() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $task = new course_backup();
        manager::queue_adhoc_task($task);

        try {
            $task->execute();
        } catch (Exception $e) {
            $exceptionclassname = invalid_parameter_exception::class;
            $this->assertTrue($e instanceof $exceptionclassname);
            $this->assertStringContainsString('Invalid data. Error: missing one of the required parameters.', $e->getMessage());
        }
    }

    /**
     * Test restore with invalid coursemigrationid.
     */
    public function test_backup_invalid_coursemigrationid() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $task = new course_backup();
        $customdata = ['coursemigrationid' => 12345];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);

        try {
            $task->execute();
        } catch (Exception $e) {
            $exceptionclassname = invalid_parameter_exception::class;
            $this->assertTrue($e instanceof $exceptionclassname);
            $this->assertStringContainsString('No match for Course migration id: 12345', $e->getMessage());
        }
    }
}
