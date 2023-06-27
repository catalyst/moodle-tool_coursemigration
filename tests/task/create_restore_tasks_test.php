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

defined('MOODLE_INTERNAL') || die();

/**
 * Create restore tasks tests.
 *
 * @package    tool_coursemigration
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_restore_tasks_test extends advanced_testcase {

    /**
     * Test create_restore_tasks.
     *
     * @covers ::create_restore_tasks
     */
    public function test_create_restore_tasks() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $category = $generator->create_category();

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => $category->id,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();

        // Confirm there is no adhoc tasks.
        $tasks = manager::get_adhoc_tasks(restore::class);
        $this->assertCount(0, $tasks);

        // Run the schedule task.
        $task = new create_restore_tasks();
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Confirm there is a adhoc task.
        $this->assertStringContainsString('A restore task has been successfully added. id=' . $coursemigration->get('id'), $output);
        $tasks = manager::get_adhoc_tasks(restore::class);
        $this->assertCount(1, $tasks);

        // Confirm the status is changed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_IN_PROGRESS, $currentcoursemigration->get('status'));
    }

    /**
     * Test create_restore_tasks.
     *
     * @covers ::create_restore_tasks
     */
    public function test_fail_to_create_restore_tasks() {
        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('defaultcategory', 12345, 'tool_coursemigration');

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => 99999,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();

        // Confirm there is no adhoc tasks.
        $tasks = manager::get_adhoc_tasks(restore::class);
        $this->assertCount(0, $tasks);

        // Run the schedule task.
        $task = new create_restore_tasks();
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Confirm there is a adhoc task.
        $this->assertStringContainsString('Could not create a restore task. id=' . $coursemigration->get('id'), $output);
        $tasks = manager::get_adhoc_tasks(restore::class);
        $this->assertCount(0, $tasks);

        // Confirm the status is changed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_FAILED, $currentcoursemigration->get('status'));
    }
}
