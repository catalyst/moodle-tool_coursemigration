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
use cleaner_muc\clean;
use tool_coursemigration\local\storage\backup_directory;
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
// Test directory name for where files are saved to.
const SAVE_TO = '/tmp/saveto';
// Test directory name for where files are restored from.
const RESTORE_FROM = '/tmp/restorefrom';
// File name of test pull.
const TEST_PULL_FILE = 'testpull.txt';
// File name of test push.
const TEST_PUSH_FILE = 'testpush.txt';


    /**
     * Sets up the test file for pull, push and delete tests.
     */
    private function setup_test_file() {

        if (!is_dir($this::SAVE_TO)) {
            mkdir($this::SAVE_TO, '0777');
        }

        if (!is_dir($this::RESTORE_FROM)) {
            mkdir($this::RESTORE_FROM, '0777');
        }

        set_config('saveto', $this::SAVE_TO, 'tool_coursemigration');
        set_config('restorefrom', $this::RESTORE_FROM, 'tool_coursemigration');

        // Add a test file to the temp dir.
        $file = fopen($this::RESTORE_FROM . '/test.txt', 'w');
        fwrite($file, 'sometestdata');
        fclose($file);
    }

    /**
     * Removes test files and directories..
     */
    private function cleanup() {
        if (file_exists($this::SAVE_TO . '/testpush.txt')) {
            unlink($this::SAVE_TO . '/testpush.txt');
        }

        if (file_exists($this::RESTORE_FROM . '/test.txt')) {
            unlink($this::RESTORE_FROM . '/test.txt');
        }
        
        if (is_dir($this::SAVE_TO)) {
            rmdir($this::SAVE_TO);
        }

        if (is_dir($this::RESTORE_FROM)) {
            rmdir($this::RESTORE_FROM);
        }
    }

    /**
     * Tests the shared_disk_storage upload, download and delete methods.
     */
    public function test_shared_disk_storage_methods() {
        $this->resetAfterTest();
        $this->setup_test_file();

        $storage = new shared_disk_storage;
        $expected = 'tool_coursemigration\\local\\storage\\storage_interface';
        $classimplements = class_implements($storage);
        // Test that the class implements the storage interface.
        $this->assertCount(1, $classimplements);
        $this->assertEquals($expected, array_key_first($classimplements));

        // Test pull a file that does not exist.
        $filerecord = $storage->pull_file('notexist.txt');
        $this->assertNull($filerecord);
        $expected = 'Cannot read file. Either the file does not exist or there is a permission problem. (/tmp/restorefrom/notexist.txt)';
        $this->assertEquals($expected, $storage->get_error());

        // Test pull a file that exists.
        $filerecord = $storage->pull_file('test.txt');
        $this->assertNotNull($filerecord);

        // Test push a file.
        $storage->push_file('testpush.txt', $filerecord);
        $this->assertFileExists('/tmp/saveto/testpush.txt');

        $this->cleanup();
    }
}
