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
use backup;
use backup_controller;
use context_course;
use core\task\asynchronous_restore_task;
use core\task\manager;
use tool_coursemigration\coursemigration;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Create restore tests.
 *
 * @package    tool_coursemigration
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_test extends advanced_testcase {

    /**
     * Test restore.
     *
     * @covers ::restore
     */
    public function test_restore() {
        global $CFG, $USER, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test restore course']);
        $category = $generator->create_category();

        // Backup the course.
        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
                backup::INTERACTIVE_YES, backup::MODE_GENERAL, $USER->id);
        $bc->finish_ui();
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Get the backup file.
        $coursecontext = context_course::instance($course->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($coursecontext->id, 'backup', 'course', false, 'id ASC');
        $backupfile = reset($files);
        $backuppath = $CFG->tempdir . DIRECTORY_SEPARATOR . "restoretest";
        $backupfile->copy_content_to($backuppath);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => $category->id,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'filename' => $backuppath,
        ]);
        $coursemigration->save();

        $task = new restore();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Confirm there is a adhoc task.
        $this->assertStringContainsString('The restore task has been successfully completed.', $output);

        // Confirm the status is now completed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_COMPLETED, $currentcoursemigration->get('status'));

        // Confirm the course is restored.
        $newcourse = get_course($currentcoursemigration->get('courseid'));
        $this->assertNotEquals($course->id, $newcourse->id);
        $this->assertEquals($category->id, $newcourse->category);
        $this->assertStringContainsString('Test restore course', $newcourse->fullname);
    }
}
