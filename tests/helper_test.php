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
use coding_exception;
use context_user;
use invalid_parameter_exception;
use storage\type\mock_storage_class;

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
        $this->assertCount(5, $list);
        $this->assertSame(get_string('status:notstarted', 'tool_coursemigration'), $list[coursemigration::STATUS_NOT_STARTED]);
        $this->assertSame(get_string('status:inprogress', 'tool_coursemigration'), $list[coursemigration::STATUS_IN_PROGRESS]);
        $this->assertSame(get_string('status:completed', 'tool_coursemigration'), $list[coursemigration::STATUS_COMPLETED]);
        $this->assertSame(get_string('status:failed', 'tool_coursemigration'), $list[coursemigration::STATUS_FAILED]);
        $this->assertSame(get_string('status:retrying', 'tool_coursemigration'), $list[coursemigration::STATUS_RETRYING]);
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
            get_string('status:retrying', 'tool_coursemigration'),
            helper::get_status_string(coursemigration::STATUS_RETRYING)
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
        $usercontext = context_user::instance($user->id);
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

    /**
     * Test restore category.
     */
    public function test_get_restore_category() {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $restorecategory = helper::get_restore_category($category->id);
        $this->assertEquals($category->id, $restorecategory->id);
    }

    /**
     * Test restore default category.
     */
    public function test_get_restore_category_default() {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        set_config('defaultcategory', $category->id, 'tool_coursemigration');
        $restorecategory = helper::get_restore_category(99999);
        $this->assertEquals($category->id, $restorecategory->id);
    }

    /**
     * Test invalid category.
     */
    public function test_get_restore_category_invalid() {
        $this->resetAfterTest();
        set_config('defaultcategory', 12345, 'tool_coursemigration');
        $this->expectException(invalid_parameter_exception::class);
        $this->expectExceptionMessage('Invalid category');
        $restorecategory = helper::get_restore_category(99999);
    }

    /**
     * Test the selected storage class.
     */
    public function test_get_selected() {
        global $CFG;
        $this->resetAfterTest();

        // Configure backup and restore directories.
        set_config('directory', $CFG->tempdir, 'tool_coursemigration');

        // Tests default storage class.
        $selectedclass = helper::get_selected_storage();
        $expected = 'tool_coursemigration\\local\\storage\\storage_interface';
        $classimplements = class_implements($selectedclass);
        $this->assertCount(1, $classimplements);
        $this->assertEquals($expected, array_key_first($classimplements));

        // Tests for plugin not setup - no storage class has been chosen.
        unset_config('storagetype', 'tool_coursemigration');
        $selectedclass = helper::get_selected_storage();
        $this->assertNull($selectedclass);

        // Test selected Storage class does not implement the storage_interface.
        set_config('storagetype', 'tool_coursemigration\helper', 'tool_coursemigration');
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('The selected Storage class does not implement the storage_interface.');
        $selectedclass = helper::get_selected_storage();
    }

    /**
     * Data provider for test_get_retry_number_from_fail_delay.
     *
     * @return \int[][]
     */
    public function get_retry_number_from_fail_delay_data_provider(): array {
        return [
            [-1, 0],
            [0, 0],
            [60, 1],
            [120, 2],
            [240, 3],
            [480, 4],
            [960, 5],
            [86400, 11],
        ];
    }

    /**
     * Test getting retry number from fail delay.
     *
     * @dataProvider get_retry_number_from_fail_delay_data_provider
     * @param int $faildelay Given fail delay,
     * @param int $expected Expected retry number.
     *
     * @return void
     */
    public function test_get_retry_number_from_fail_delay(int $faildelay, int $expected) {
        $this->assertSame($expected, helper::get_retry_number_from_fail_delay($faildelay));
    }

}
