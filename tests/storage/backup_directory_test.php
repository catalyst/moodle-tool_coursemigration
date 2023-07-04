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
use tool_coursemigration\local\storage\backup_directory;


/**
 * The backup_directory test class.
 *
 * @package     tool_coursemigration
 * @copyright   2023 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\local\storage\backup_directory

 */
class backup_directory_test extends advanced_testcase {

    /**
     * Tests the backup_directory element can be created and the data (path) is validated.
     */
    public function test_backup_directory() {
        $this->resetAfterTest();

        // Tests the constructor.
        $backupdirectory = new backup_directory('saveto');
        self::assertNotNull($backupdirectory);

        // Test writing a change to a folder that exists.
        $this->assertEmpty($backupdirectory->write_setting('/tmp'));
        $this->assertEquals('/tmp', get_config('tool_coursemigration', 'saveto'));

        // Test writing a change to a folder that does not exist.
        // Error message should be returned.
        $expected = 'The backup destination folder does not exist or is not writable.';
        $this->assertEquals($expected, $backupdirectory->write_setting('/something'));
        // Value should remain unchanged.
        $this->assertEquals('/tmp', get_config('tool_coursemigration', 'saveto'));
    }
}
