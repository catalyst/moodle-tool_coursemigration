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

namespace tool_coursemigration;

use advanced_testcase;
use tool_coursemigration\local\storage\type\shared_disk_storage;

/**
 * The backup_directory test class.
 *
 * @package     tool_coursemigration
 * @copyright   2023 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\local\storage\type\shared_disk_storage
 */
class shared_disk_storage_test extends advanced_testcase {
    /** @var string Test directory name for where files are stored */
    const TEST_DIRECTORY = '/tmp/directory/';
    /** @var string File name of test pull */
    const TEST_PULL_FILE = 'testpull.txt';
    /** @var string File name of test push */
    const TEST_PUSH_FILE = 'testpush.txt';
    /** @var string File name of test push */
    const TEST_DELETE_FILE = 'testdelete.txt';

    /**
     * Sets up the test file for pull, push and delete tests.
     */
    private function setup_test_file() {

        if (!is_dir(self::TEST_DIRECTORY)) {
            mkdir(self::TEST_DIRECTORY);
        }

        set_config('directory', self::TEST_DIRECTORY, 'tool_coursemigration');

        // Add a test file to the temp dir.
        $file = fopen(self::TEST_DIRECTORY . self::TEST_PULL_FILE, 'w');
        fwrite($file, 'sometestdata');
        fclose($file);

        // Add a file to test the delete of a ready only file.
        copy(self::TEST_DIRECTORY . self::TEST_PULL_FILE, self::TEST_DIRECTORY . self::TEST_DELETE_FILE);
    }

    /**
     * Removes test files and directories..
     */
    private function cleanup() {
        fulldelete(self::TEST_DIRECTORY);
    }

    /**
     * Tests the shared_disk_storage upload, download and delete methods.
     */
    public function test_shared_disk_storage_methods() {
        $this->resetAfterTest();
        $this->setup_test_file();

        $storage = new shared_disk_storage;

        // Check that backup and restore directories are configured.
        $this->assertTrue($storage->ready_for_pull());
        $this->assertTrue($storage->ready_for_push());

        $expected = 'tool_coursemigration\\local\\storage\\storage_interface';
        $classimplements = class_implements($storage);
        // Test that the class implements the storage interface.
        $this->assertCount(1, $classimplements);
        $this->assertEquals($expected, array_key_first($classimplements));

        // Test pull a file that does not exist.
        $filerecord = $storage->pull_file('notexist.txt');
        $this->assertNull($filerecord);
        $expected = 'Cannot read file. Either the file does not exist or there is a permission problem.'
            . ' (/tmp/directory/notexist.txt)';
        $this->assertEquals($expected, $storage->get_error());
        $storage->clear_error();

        // Test pull a file that exists.
        $this->assertTrue($storage->file_exists(self::TEST_PULL_FILE));
        $filerecord = $storage->pull_file(self::TEST_PULL_FILE);
        $this->assertNotNull($filerecord);

        // Test push a file.
        $storage->push_file(self::TEST_PUSH_FILE, $filerecord);
        $this->assertEmpty($storage->get_error());
        $this->assertFileExists(self::TEST_DIRECTORY . self::TEST_PUSH_FILE);
        $this->assertTrue($storage->file_exists(self::TEST_PUSH_FILE));

        // Test delete a file.
        $storage->delete_file(self::TEST_PULL_FILE);
        $this->assertFalse(file_exists(self::TEST_PULL_FILE));
        $storage->clear_error();

        // Test delete a file that does not exist.
        $result = $storage->delete_file(self::TEST_PULL_FILE);
        $this->assertFalse($storage->file_exists(self::TEST_PULL_FILE));
        $expected = 'unlink(/tmp/directory/testpull.txt): No such file or directory';
        $this->assertFalse($result);
        $this->assertEquals($expected, $storage->get_error());

        $this->cleanup();
    }

    /**
     * Test without directories configured.
     */
    public function test_without_directories_configured() {
        $storage = new shared_disk_storage;
        $this->assertFalse($storage->ready_for_pull());
        $this->assertFalse($storage->ready_for_push());
    }

}
