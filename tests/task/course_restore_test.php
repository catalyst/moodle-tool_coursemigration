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
use core\task\manager;
use Exception;
use invalid_parameter_exception;
use tool_coursemigration\coursemigration;
use tool_coursemigration\event\restore_completed;
use tool_coursemigration\event\restore_failed;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Course restore tests.
 *
 * @package    tool_coursemigration
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_coursemigration\task\course_restore
 */
class course_restore_test extends advanced_testcase {

    /**
     * Test restore.
     */
    public function test_restore() {
        global $CFG, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test restore course']);
        $category = $generator->create_category();

        // Backup the course.
        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
                backup::INTERACTIVE_YES, backup::MODE_GENERAL, $USER->id);
        $bc->finish_ui();
        $bc->execute_plan();
        $bc->destroy();

        // Get the backup file.
        $coursecontext = context_course::instance($course->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($coursecontext->id, 'backup', 'course', false, 'id ASC');
        /** @var \stored_file $backupfile */
        $backupfile = reset($files);
        $filename = $backupfile->get_filename();
        $backuppath = $CFG->tempdir . DIRECTORY_SEPARATOR;
        $backupfile->copy_content_to($backuppath . $filename);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => $category->id,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'filename' => $filename,
        ]);

        set_config('directory', $backuppath, 'tool_coursemigration');

        $coursemigration->save();

        $task = new course_restore();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        $task->execute();

        // Confirm the status is now completed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_COMPLETED, $currentcoursemigration->get('status'));

        // Confirm the course is restored.
        $newcourse = get_course($currentcoursemigration->get('courseid'));
        $this->assertNotEquals($course->id, $newcourse->id);
        $this->assertEquals($category->id, $newcourse->category);
        $this->assertStringContainsString('Test restore course', $newcourse->fullname);
        $this->assertEquals(1, $newcourse->visible);

        $eventclass = restore_completed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($currentcoursemigration->get('id'), $event->objectid);
        $this->assertEquals($newcourse->id, $event->other['courseid']);
        $this->assertEquals($newcourse->fullname, $event->other['coursename']);
        $this->assertEquals($category->id, $event->other['destinationcategoryid']);
        $this->assertEquals($category->name, $event->other['destinationcategoryname']);
        $this->assertEquals($backupfile->get_filename(), $event->other['filename']);

        $expectdescription = "Restoring course '{$newcourse->fullname}' (id: {$newcourse->id})" .
            " is successfully completed into category '{$category->name}' (id: {$category->id})" .
            " from file '{$backupfile->get_filename()}'.";
        $this->assertEquals($expectdescription, $event->get_description());
        $this->assertEquals(get_string('event:restore_completed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test restore as hidden course.
     */
    public function test_restore_hidden() {
        global $CFG, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Set to restore as hidden course.
        set_config('hiddencourse', 1, 'tool_coursemigration');

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test restore course']);
        $category = $generator->create_category();

        // Backup the course.
        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_YES, backup::MODE_GENERAL, $USER->id);
        $bc->finish_ui();
        $bc->execute_plan();
        $bc->destroy();

        // Get the backup file.
        $coursecontext = context_course::instance($course->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($coursecontext->id, 'backup', 'course', false, 'id ASC');
        /** @var \stored_file $backupfile */
        $backupfile = reset($files);
        $filename = $backupfile->get_filename();
        $backuppath = $CFG->tempdir . DIRECTORY_SEPARATOR;
        $backupfile->copy_content_to($backuppath . $filename);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => $category->id,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'filename' => $filename,
        ]);

        set_config('directory', $backuppath, 'tool_coursemigration');

        $coursemigration->save();

        $task = new course_restore();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        $task->execute();

        // Confirm the status is now completed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_COMPLETED, $currentcoursemigration->get('status'));

        // Confirm the course is restored.
        $newcourse = get_course($currentcoursemigration->get('courseid'));
        $this->assertNotEquals($course->id, $newcourse->id);
        $this->assertEquals($category->id, $newcourse->category);
        $this->assertStringContainsString('Test restore course', $newcourse->fullname);
        $this->assertEquals(0, $newcourse->visible);
    }

    /**
     * Test restore without param.
     *
     * @covers ::restore
     */
    public function test_restore_invalid_param() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        $task = new course_restore();
        manager::queue_adhoc_task($task);

        try {
            $task->execute();
        } catch (Exception $e) {
            $exceptionclassname = invalid_parameter_exception::class;
            $this->assertTrue($e instanceof $exceptionclassname);
            $this->assertStringContainsString('Invalid data. Error: missing one of the required parameters.', $e->getMessage());
        }

        $eventclass = restore_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals(0, $event->objectid);
        $expectdescription = "Restoring course is failed. Error: Invalid data. Error: missing one of the required parameters.";
        $this->assertEquals($expectdescription, $event->get_description());
        $this->assertEquals(get_string('event:restore_failed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test restore with invalid coursemigrationid.
     *
     * @covers ::restore
     */
    public function test_restore_invalid_coursemigrationid() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        $task = new course_restore();
        $customdata = ['coursemigrationid' => 12345];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);

        try {
            $task->execute();
        } catch (Exception $e) {
            $exceptionclassname = invalid_parameter_exception::class;
            $this->assertTrue($e instanceof $exceptionclassname);
            $this->assertStringContainsString('Invalid id. Error: could not find record for restore.', $e->getMessage());
        }

        $eventclass = restore_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals(0, $event->objectid);
        $expectdescription = "Restoring course is failed. Error: Invalid id. Error: could not find record for restore.";
        $this->assertEquals($expectdescription, $event->get_description());
        $this->assertEquals(get_string('event:restore_failed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test restore with invalid filename.
     *
     * @covers ::restore
     */
    public function test_restore_invalid_filename() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        $generator = $this->getDataGenerator();
        $category = $generator->create_category();

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => $category->id,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'filename' => 'invalid file name',
        ]);
        $coursemigration->save();

        $backuppath = $CFG->tempdir . DIRECTORY_SEPARATOR;
        set_config('directory', $backuppath, 'tool_coursemigration');

        $task = new course_restore();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);

        $this->expectOutputRegex('/Cannot restore the course. File can not be pulled from the storage. Error: Cannot read file/');

        $task->execute();

        // Check exception was thrown.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $expected = 'Cannot restore the course. File can not be pulled from the storage. Error: Cannot read file. ' .
            'Either the file does not exist or there is a permission problem. (' . $backuppath . 'invalid file name)';

        $this->assertEquals($expected, $currentcoursemigration->get('error'));

        $eventclass = restore_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($coursemigration->get('id'), $event->objectid);
        $this->assertEquals($coursemigration->get('filename'), $event->other['filename']);
        $this->assertStringContainsString("file does not exist", $event->get_description());
        $this->assertStringContainsString($event->other['filename'], $event->get_description());
        $this->assertEquals(get_string('event:restore_failed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test restore with not configured storage.
     *
     * @covers ::restore
     */
    public function test_restore_not_configured_storage() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        $category = $this->getDataGenerator()->create_category();

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => $category->id,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'filename' => 'testfilename',
        ]);
        $coursemigration->save();

        // Break config for a storage.
        set_config('storagetype', '', 'tool_coursemigration');

        $task = new course_restore();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);

        $expected = 'Cannot restore the course. A storage class has not been configured';
        $this->expectOutputRegex('/' . $expected  . '/');

        $task->execute();

        // Check exception was thrown.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals($expected, $currentcoursemigration->get('error'));

        $eventclass = restore_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($coursemigration->get('id'), $event->objectid);
        $this->assertEquals($coursemigration->get('filename'), $event->other['filename']);
        $this->assertStringContainsString($event->other['filename'], $event->get_description());
        $this->assertStringContainsString("A storage class has not been configured", $event->get_description());
    }

    /**
     * Test restore without configured restore directory.
     *
     * @covers ::restore
     */
    public function test_restore_not_configured_restore_directory() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        $category = $this->getDataGenerator()->create_category();

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => $category->id,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'filename' => 'testfilename',
        ]);
        $coursemigration->save();

        // Break config for a restore from directory.
        set_config('restorefrom', '', 'tool_coursemigration');

        $task = new course_restore();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);

        $this->expectOutputRegex('/Cannot restore the course. Unable to restore course. The \[restore from\] directory has not been configured/');
        $task->execute();

        // Check exception was thrown.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $expected = 'Cannot restore the course. Unable to restore course. The [restore from] directory has not been configured';
        $this->assertEquals($expected, $currentcoursemigration->get('error'));

        $eventclass = restore_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertStringContainsString("directory has not been configured", $event->get_description());
    }

    /**
     * Test delete after fail.
     */
    public function test_delete_after_fail() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create bad format backup file.
        $backuppath = $CFG->tempdir . DIRECTORY_SEPARATOR;
        $filename = 'badfile.txt';
        $file = fopen($backuppath . $filename, 'w');
        fwrite($file, 'sometestdata');
        fclose($file);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'filename' => $filename,
        ]);

        $coursemigration->save();

        set_config('directory', $backuppath, 'tool_coursemigration');

        // Set to delete backup after failed restore.
        set_config('failrestoredelete', 1, 'tool_coursemigration');

        $task = new course_restore();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);

        $this->expectOutputRegex('/Cannot restore the course. error\/cannot_precheck_wrong_status/');

        $task->execute();

        // Confirm the status is now failed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_FAILED, $currentcoursemigration->get('status'));

        // Confirm the backup file has been deleted.
        $this->assertFalse(file_exists($backuppath . $filename));
        $this->assertDebuggingCalledCount(1);
    }

    /**
     * Test restore when a course is set to course migration item.
     */
    public function test_restore_when_course_is_already_set() {
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
        $bc->execute_plan();
        $bc->destroy();

        // Get the backup file.
        $coursecontext = context_course::instance($course->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($coursecontext->id, 'backup', 'course', false, 'id ASC');
        /** @var \stored_file $backupfile */
        $backupfile = reset($files);
        $filename = $backupfile->get_filename();
        $backuppath = $CFG->tempdir . DIRECTORY_SEPARATOR;
        $backupfile->copy_content_to($backuppath . $filename);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => $category->id,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'filename' => $filename,
            'courseid' => $course->id,
        ]);

        set_config('directory', $backuppath, 'tool_coursemigration');

        $coursemigration->save();

        $task = new course_restore();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        $task->execute();

        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        // Confirm the status is now completed.
        $this->assertEquals(coursemigration::STATUS_COMPLETED, $currentcoursemigration->get('status'));
        // Course should be deleted.
        $this->assertFalse($DB->get_record('course', array('id' => $course->id)));
        // Course id should be updated to a new course.
        $this->assertNotEquals($course->id, $currentcoursemigration->get('courseid'));
        // Error should be set.
        $this->assertEquals(
            get_string('error:taskrestarted', 'tool_coursemigration', $course->id),
            $currentcoursemigration->get('error')
        );
        // Confirm the course is restored.
        $newcourse = get_course($currentcoursemigration->get('courseid'));
        $this->assertNotEquals($course->id, $newcourse->id);
        $this->assertEquals($category->id, $newcourse->category);
        $this->assertStringContainsString('Test restore course', $newcourse->fullname);
    }

    /**
     * Test that we set retry status if failed task, but file is there, so we can retry.
     */
    public function test_set_retry_status_if_failed_but_file_is_there() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create bad format backup file.
        $backuppath = $CFG->tempdir . DIRECTORY_SEPARATOR;
        $filename = 'badfile.txt';
        $file = fopen($backuppath . $filename, 'w');
        fwrite($file, 'sometestdata');
        fclose($file);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_RESTORE,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'filename' => $filename,
        ]);

        $coursemigration->save();

        set_config('directory', $backuppath, 'tool_coursemigration');

        // Set to keep backup after failed restore.
        set_config('failrestoredelete', 0, 'tool_coursemigration');

        $task = new course_restore();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);

        $this->expectOutputRegex('/Cannot restore the course. error\/cannot_precheck_wrong_status/');

        // We need to catch as we will fail the task by throwing an exception.
        try {
            $task->execute();
        } catch (Exception $exception) {
            // Confirm the status is now retrying.
            $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
            $this->assertEquals(coursemigration::STATUS_RETRYING, $currentcoursemigration->get('status'));
            $this->assertStringContainsString(
                'Cannot restore the course. error/cannot_precheck_wrong_status',
                $currentcoursemigration->get('error')
            );
            $this->assertDebuggingCalledCount(1);
        }
    }
}
