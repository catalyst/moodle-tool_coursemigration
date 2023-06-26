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

namespace tool_coursemigration;

use advanced_testcase;

/**
 * Tests for helper class.
 *
 * @package     tool_coursemigration
 * @author      Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright   2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\helper
 */
class helper_test extends advanced_testcase {

    /**
     * Test can get action list.
     */
    public function test_get_action_list() {
        $list = helper::get_action_list();
        $this->assertCount(2, $list);
        $this->assertSame(get_string('settings:backup', 'tool_coursemigration'), $list[coursemigration::ACTION_BACKUP]);
        $this->assertSame(get_string('settings:restore', 'tool_coursemigration'), $list[coursemigration::ACTION_RESTORE]);
    }

    /**
     * Test can get status list.
     */
    public function test_get_status_list() {
        $list = helper::get_status_list();
        $this->assertCount(4, $list);
        $this->assertSame(get_string('status:notstarted', 'tool_coursemigration'), $list[coursemigration::STATUS_NOT_STARTED]);
        $this->assertSame(get_string('status:inprogress', 'tool_coursemigration'), $list[coursemigration::STATUS_IN_PROGRESS]);
        $this->assertSame(get_string('status:completed', 'tool_coursemigration'), $list[coursemigration::STATUS_COMPLETED]);
        $this->assertSame(get_string('status:failed', 'tool_coursemigration'), $list[coursemigration::STATUS_FAILED]);
    }

    /**
     * Test can get action string.
     */
    public function test_get_action_string() {
        $this->assertSame(
            get_string('settings:backup', 'tool_coursemigration'),
            helper::get_action_string(coursemigration::ACTION_BACKUP)
        );

        $this->assertSame(
            get_string('settings:restore', 'tool_coursemigration'),
            helper::get_action_string(coursemigration::ACTION_RESTORE)
        );

        $this->assertSame(
            get_string('status:invalid', 'tool_coursemigration'),
            helper::get_action_string(-1)
        );
    }

    /**
     * Test can get status string.
     */
    public function test_get_status_string() {
        $this->assertSame(
            get_string('status:notstarted', 'tool_coursemigration'),
            helper::get_status_string(coursemigration::STATUS_NOT_STARTED)
        );

        $this->assertSame(
            get_string('status:inprogress', 'tool_coursemigration'),
            helper::get_status_string(coursemigration::STATUS_IN_PROGRESS)
        );

        $this->assertSame(
            get_string('status:completed', 'tool_coursemigration'),
            helper::get_status_string(coursemigration::STATUS_COMPLETED)
        );

        $this->assertSame(
            get_string('status:failed', 'tool_coursemigration'),
            helper::get_status_string(coursemigration::STATUS_FAILED)
        );

        $this->assertSame(
            get_string('status:invalid', 'tool_coursemigration'),
            helper::get_status_string(-1)
        );
    }

    /**
     * Test can get uploaded file name.
     */
    public function test_get_uploaded_filename() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $usercontext = \context_user::instance($user->id);
        $fs = get_file_storage();

        // Emulate file upload as it's uploaded as component user and draft file area.
        $filerecord = [
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'test.txt',
            'source' => 'PHPUnit test',
        ];
        $uploadedfile = $fs->create_file_from_string($filerecord, 'Test content');

        // User uploaded file should get a file name.
        $this->setUser($user);
        $this->assertSame('', helper::get_uploaded_filename(99999));
        $this->assertSame('test.txt', helper::get_uploaded_filename($uploadedfile->get_itemid()));

        // Another user shouldn't get a filename as it's been uploaded by another user.
        $this->setAdminUser();
        $this->assertSame('', helper::get_uploaded_filename(99999));
        $this->assertSame('', helper::get_uploaded_filename($uploadedfile->get_itemid()));
    }
}
