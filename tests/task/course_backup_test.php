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
use tool_coursemigration\event\backup_completed;
use tool_coursemigration\event\backup_failed;
use tool_coursemigration\restore_api;
use tool_coursemigration\restore_api_factory;

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
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test restore course']);

        // Mock restore api.
        $mockedrestoreapi = $this->createMock(restore_api::class);
        $mockedrestoreapi->method('request_restore')->willReturn(true);
        restore_api_factory::set_restore_api($mockedrestoreapi);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => $course->id,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();
        $this->assertEmpty($coursemigration->get('filename'));

        // Configure backup and restore directories.
        set_config('directory', $CFG->tempdir, 'tool_coursemigration');

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
        $this->assertNotEmpty($currentcoursemigration->get('filename'));
        $this->assertStringStartsWith($coursemigration->get('id'), $currentcoursemigration->get('filename'));
        restore_api_factory::reset_restore_api();

        $eventclass = backup_completed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($currentcoursemigration->get('id'), $event->objectid);
        $this->assertEquals($course->id, $event->other['courseid']);
        $this->assertEquals($course->fullname, $event->other['coursename']);
        $this->assertEquals(1, $event->other['destinationcategoryid']);
        $this->assertEquals($currentcoursemigration->get('filename'), $event->other['filename']);

        $expectdescription = "Backup course '{$course->fullname}' (id: {$course->id})" .
            " is successfully completed to file '{$currentcoursemigration->get('filename')}'" .
            " for category id: 1.";
        $this->assertEquals($expectdescription, $event->get_description());
        $this->assertEquals(get_string('event:backup_completed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test backup failed on WS call.
     */
    public function test_course_backup_failed_on_ws_call() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test restore course']);

        // Mock restore api.
        $mockedrestoreapi = $this->createMock(restore_api::class);
        $mockedrestoreapi->method('request_restore')->willReturn(false);
        restore_api_factory::set_restore_api($mockedrestoreapi);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => $course->id,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();
        $this->assertEmpty($coursemigration->get('filename'));

        // Configure backup and restore directories.
        set_config('directory', $CFG->tempdir, 'tool_coursemigration');

        $task = new course_backup();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('Restore request WS call failed.', $output);

        // Confirm the status is now completed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_FAILED, $currentcoursemigration->get('status'));
        restore_api_factory::reset_restore_api();

        $eventclass = backup_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $expectdescription = "Backup course is failed. Error: Restore request WS call failed.";
        $this->assertEquals($currentcoursemigration->get('id'), $event->objectid);
        $this->assertEquals($expectdescription, $event->get_description());
        $this->assertEquals(get_string('event:backup_failed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test backup without param.
     */
    public function test_backup_invalid_param() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        $task = new course_backup();
        manager::queue_adhoc_task($task);

        try {
            $task->execute();
        } catch (Exception $e) {
            $exceptionclassname = invalid_parameter_exception::class;
            $this->assertTrue($e instanceof $exceptionclassname);
            $this->assertStringContainsString('Invalid data. Error: missing one of the required parameters.', $e->getMessage());
        }

        $eventclass = backup_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $expectdescription = "Backup course is failed. Error: Invalid data. Error: missing one of the required parameters.";
        $this->assertEquals(0, $event->objectid);
        $this->assertEquals($expectdescription, $event->get_description());
        $this->assertEquals(get_string('event:backup_failed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test restore with invalid coursemigrationid.
     */
    public function test_backup_invalid_coursemigrationid() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

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

        $eventclass = backup_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $expectdescription = "Backup course is failed. Error: No match for Course migration id: 12345";
        $this->assertEquals(0, $event->objectid);
        $this->assertEquals($expectdescription, $event->get_description());
        $this->assertEquals(get_string('event:backup_failed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test push file error.
     */
    public function test_push_file_error() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test restore course']);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => $course->id,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();
        $this->assertEmpty($coursemigration->get('filename'));

        // Configure INVALID backup and restore directories to force exception.
        set_config('directory', $CFG->tempdir . 'something', 'tool_coursemigration');

        $task = new course_backup();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'Unable to upload a course. The shared directory has not been configured properly.',
            $output
        );

        $eventclass = backup_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($coursemigration->get('id'), $event->objectid);
        $this->assertStringContainsString(
            'Unable to upload a course. The shared directory has not been configured properly.',
            $event->get_description()
        );
        $this->assertEquals(get_string('event:backup_failed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test not_configured_storage.
     */
    public function test_not_configured_storage() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test restore course']);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => $course->id,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();
        $this->assertEmpty($coursemigration->get('filename'));

        // Break config for a storage.
        set_config('storagetype', '', 'tool_coursemigration');

        $task = new course_backup();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('A storage class has not been configured', $output);

        $eventclass = backup_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($coursemigration->get('id'), $event->objectid);
        $this->assertStringContainsString('A storage class has not been configured', $event->get_description());
        $this->assertEquals(get_string('event:backup_failed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test restore without configured backup directory.
     */
    public function test_restore_not_configured_backup_directory() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test restore course']);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => $course->id,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();
        $this->assertEmpty($coursemigration->get('filename'));

        // Break config to directory.
        set_config('directory', '', 'tool_coursemigration');

        $task = new course_backup();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('directory has not been configured', $output);

        $eventclass = backup_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($coursemigration->get('id'), $event->objectid);
        $this->assertStringContainsString('directory has not been configured', $event->get_description());
        $this->assertEquals(get_string('event:backup_failed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test delete after fail.
     */
    public function test_delete_after_fail() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $eventsink = $this->redirectEvents();

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test restore course']);

        // Mock restore api.
        $mockedrestoreapi = $this->createMock(restore_api::class);
        $mockedrestoreapi->method('request_restore')->willReturn(false);
        restore_api_factory::set_restore_api($mockedrestoreapi);

        // Create coursemigration record.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => $course->id,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();
        $this->assertEmpty($coursemigration->get('filename'));

        // Configure backup and restore directories.
        set_config('directory', $CFG->tempdir, 'tool_coursemigration');

        // Set to delete backup after failed restore.
        set_config('failbackupdelete', 1, 'tool_coursemigration');

        $task = new course_backup();
        $customdata = ['coursemigrationid' => $coursemigration->get('id')];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Confirm the status is now failed.
        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_FAILED, $currentcoursemigration->get('status'));

        // Confirm the backup file has been deleted.
        $this->assertFalse(file_exists($CFG->tempdir . DIRECTORY_SEPARATOR . $currentcoursemigration->get('filename')));
    }
}
