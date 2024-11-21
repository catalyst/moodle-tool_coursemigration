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
use tool_coursemigration\coursemigration;

/**
 * Create backup tasks tests.
 *
 * @package    tool_coursemigration
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_backup_tasks_test extends advanced_testcase {

    /**
     * Test create_backup_tasks.
     *
     * @covers ::create_backup_tasks
     */
    public function test_create_backup_tasks() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => $course->id,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();

        // Confirm there is no adhoc tasks.
        $tasks = manager::get_adhoc_tasks(course_backup::class);
        $this->assertCount(0, $tasks);

        // Run the schedule task.
        $task = new create_backup_tasks();
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Confirm there is a adhoc task.
        $this->assertStringContainsString(
            'Successfully created a backup task. Migration id: ' . $coursemigration->get('id'),
            $output
        );
        $tasks = manager::get_adhoc_tasks(course_backup::class);
        $this->assertCount(1, $tasks);

        // Confirm the status is changed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_IN_PROGRESS, $currentcoursemigration->get('status'));
    }

    /**
     * Test ignoring backup tasks.
     *
     * @covers ::create_backup_tasks
     */
    public function test_create_backup_tasks_ignore_restore() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();

        // Confirm there is no adhoc tasks.
        $tasks = manager::get_adhoc_tasks(course_backup::class);
        $this->assertCount(0, $tasks);

        // Run the schedule task.
        $task = new create_backup_tasks();
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Confirm there is still no adhoc tasks.
        $this->assertStringContainsString('Found 0 courses to backup', $output);
        $tasks = manager::get_adhoc_tasks(course_backup::class);
        $this->assertCount(0, $tasks);

        // Confirm the status is not changed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_NOT_STARTED, $currentcoursemigration->get('status'));
    }

    /**
     * Test create_backup_tasks.
     *
     * @covers ::create_backup_tasks
     */
    public function test_fail_to_create_backup_tasks() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => 99999,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();

        // Confirm there is no adhoc tasks.
        $tasks = manager::get_adhoc_tasks(course_backup::class);
        $this->assertCount(0, $tasks);

        // Run the schedule task.
        $task = new create_backup_tasks();
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Confirm there is a adhoc task.
        $this->assertStringContainsString(
            'Error in creating backup task. Migration id: ' . $coursemigration->get('id'),
            $output
        );
        $tasks = manager::get_adhoc_tasks(course_backup::class);
        $this->assertCount(0, $tasks);

        // Confirm the status is changed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_FAILED, $currentcoursemigration->get('status'));
    }
}
