<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_coursemigration\task;

use advanced_testcase;
use core\task\manager;
use moodle_exception;

/**
 * Course cleanup tests.
 *
 * @package    tool_coursemigration
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_coursemigration\task\course_cleanup
 */
class course_cleanup_test extends advanced_testcase {

    /**
     * Test clean up when no fail delay.
     */
    public function test_cleanup_no_fail_delay() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Test course']);
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $task = new course_cleanup();
        $customdata = ['courseid' => $course->id];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        $task->execute();

        // Course should be deleted.
        $this->assertFalse($DB->get_record('course', array('id' => $course->id)));
    }

    /**
     * Test clean up when with fail delay.
     */
    public function test_cleanup_with_fail_delay() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Test course']);
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $task = new course_cleanup();
        $task->set_fail_delay(128);
        $customdata = ['courseid' => $course->id];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);
        $task->execute();

        // Course should be deleted.
        $this->assertFalse($DB->get_record('course', array('id' => $course->id)));
    }

    /**
     * Test clean course with broken section and no fail delay.
     */
    public function test_cleanup_fails_with_broken_section_no_fail_delay() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Test course']);
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
        $this->assertEquals($page1->cmid . ',' . $page2->cmid, $section->sequence);

        $section->sequence = '';
        $DB->update_record('course_sections', $section);

        $task = new course_cleanup();
        $customdata = ['courseid' => $course->id];
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessageMatches('/Invalid course module ID: (' . $page1->cmid . '|' . $page2->cmid . ')/');
        $task->execute();
    }

    /**
     * Test clean course with broken section and with fail delay.
     */
    public function test_cleanup_fails_with_broken_section_with_fail_delay() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Test course']);
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
        $this->assertEquals($page1->cmid . ',' . $page2->cmid, $section->sequence);

        $section->sequence = '';
        $DB->update_record('course_sections', $section);

        $task = new course_cleanup();
        $customdata = ['courseid' => $course->id];
        $task->set_custom_data($customdata);
        $task->set_fail_delay(128);

        manager::queue_adhoc_task($task);

        $task->execute();
        // Course should be deleted.
        $this->assertFalse($DB->get_record('course', array('id' => $course->id)));
    }
}
