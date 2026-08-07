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
     * Opens a Moodle backup file (.mbz) and returns the list of activity module
     * names recorded in moodle_backup.xml.
     *
     * @param string $filename Backup filename (as stored in the coursemigration record).
     * @return string[] Module type names, e.g. ['forum', 'quiz'].
     */
    private function get_backup_modulenames(string $filename): array {
        $directory = get_config('tool_coursemigration', 'directory');
        $filepath = rtrim($directory, '/') . '/' . $filename;

        $this->assertFileExists($filepath, "Backup file not found at: $filepath");

        $fp = get_file_packer('application/vnd.moodle.backup');
        $tmpdir = make_backup_temp_directory('test_excluded_mods_' . uniqid());
        $extracted = $fp->extract_to_pathname($filepath, $tmpdir, ['moodle_backup.xml']);
        $xmlfile = $tmpdir . '/moodle_backup.xml';

        $this->assertTrue(
            !empty($extracted) && is_readable($xmlfile),
            "Could not extract moodle_backup.xml from: $filepath"
        );

        $xml = simplexml_load_file($xmlfile);
        remove_dir($tmpdir);

        $modulenames = [];
        foreach (($xml->information->contents->activities->activity ?? []) as $activity) {
            $modulenames[] = strtolower((string) $activity->modulename);
        }
        return $modulenames;
    }

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

        $this->assertStringContainsString('The selected backup storage has not been configured to push backups', $output);

        $eventclass = backup_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($coursemigration->get('id'), $event->objectid);
        $this->assertStringContainsString(
            'The selected backup storage has not been configured to push backups',
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

        $this->assertStringContainsString('backup storage has not been configured', $output);

        $eventclass = backup_failed::class;
        $events = array_filter($eventsink->get_events(), function ($event) use ($eventclass) {
            return $event instanceof $eventclass;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($coursemigration->get('id'), $event->objectid);
        $this->assertStringContainsString('backup storage has not been configured ', $event->get_description());
        $this->assertEquals(get_string('event:backup_failed', 'tool_coursemigration'), $event->get_name());
    }

    /**
     * Test backup with excluded_mods set — backup completes and excluded module is skipped.
     */
    public function test_course_backup_with_excluded_mods(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test module exclusion']);

        // Create a forum and a quiz in the course.
        $generator->create_module('forum', ['course' => $course->id]);
        $generator->create_module('quiz', ['course' => $course->id]);

        // Mock restore api.
        $mockedrestoreapi = $this->createMock(restore_api::class);
        $mockedrestoreapi->method('request_restore')->willReturn(true);
        restore_api_factory::set_restore_api($mockedrestoreapi);

        // Create coursemigration record with excluded_mods = 'quiz'.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => $course->id,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'excluded_mods' => 'quiz',
        ]);
        $coursemigration->save();

        // Confirm excluded_mods value.
        $this->assertEquals('quiz', $coursemigration->get('excluded_mods'));

        // Configure backup directory.
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

        // Confirm quiz is absent from the backup and forum is present.
        $modulenames = $this->get_backup_modulenames($currentcoursemigration->get('filename'));
        $this->assertNotContains('quiz', $modulenames, 'quiz should have been excluded from the backup.');
        $this->assertContains('forum', $modulenames, 'forum should be present in the backup.');
    }

    /**
     * Test backup with excluded_mods containing multiple modules (comma-separated).
     */
    public function test_course_backup_with_multiple_excluded_modss(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test multi-exclusion course']);

        // Create modules in the course.
        $generator->create_module('forum', ['course' => $course->id]);
        $generator->create_module('quiz', ['course' => $course->id]);

        // Mock restore api.
        $mockedrestoreapi = $this->createMock(restore_api::class);
        $mockedrestoreapi->method('request_restore')->willReturn(true);
        restore_api_factory::set_restore_api($mockedrestoreapi);

        // Exclude both forum and quiz (space after comma tests trim() handling in task).
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => $course->id,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
            'excluded_mods' => 'forum, quiz',
        ]);
        $coursemigration->save();

        // Confirm excluded_mods value is persisted correctly.
        $this->assertEquals('forum, quiz', $coursemigration->get('excluded_mods'));

        set_config('directory', $CFG->tempdir, 'tool_coursemigration');

        $task = new course_backup();
        $task->set_custom_data(['coursemigrationid' => $coursemigration->get('id')]);
        manager::queue_adhoc_task($task);
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('Backup completed.', $output);

        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_COMPLETED, $currentcoursemigration->get('status'));
        $this->assertNotEmpty($currentcoursemigration->get('filename'));
        restore_api_factory::reset_restore_api();

        // Confirm both forum and quiz are absent from the backup.
        $modulenames = $this->get_backup_modulenames($currentcoursemigration->get('filename'));
        $this->assertNotContains('forum', $modulenames, 'forum should have been excluded from the backup.');
        $this->assertNotContains('quiz', $modulenames, 'quiz should have been excluded from the backup.');
    }

    /**
     * Test backup with no excluded_mods — standard backup still works.
     */
    public function test_course_backup_without_excluded_mods(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test no exclusion course']);
        $generator->create_module('forum', ['course' => $course->id]);

        // Mock restore api.
        $mockedrestoreapi = $this->createMock(restore_api::class);
        $mockedrestoreapi->method('request_restore')->willReturn(true);
        restore_api_factory::set_restore_api($mockedrestoreapi);

        // Create coursemigration record with no excluded_mods.
        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => $course->id,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);
        $coursemigration->save();

        // Confirm excluded_mods.
        $this->assertNull($coursemigration->get('excluded_mods'));

        set_config('directory', $CFG->tempdir, 'tool_coursemigration');

        $task = new course_backup();
        $task->set_custom_data(['coursemigrationid' => $coursemigration->get('id')]);
        manager::queue_adhoc_task($task);
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('Backup completed.', $output);

        $currentcoursemigration = coursemigration::get_record(['id' => $coursemigration->get('id')]);
        $this->assertEquals(coursemigration::STATUS_COMPLETED, $currentcoursemigration->get('status'));
        $this->assertNotEmpty($currentcoursemigration->get('filename'));
        restore_api_factory::reset_restore_api();

        // Confirm forum is present in the backup (no exclusions applied).
        $modulenames = $this->get_backup_modulenames($currentcoursemigration->get('filename'));
        $this->assertContains('forum', $modulenames, 'forum should be present in the backup when no exclusion is set.');
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
