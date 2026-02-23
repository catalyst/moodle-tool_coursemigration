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

namespace tool_coursemigration\local\settings;
defined('MOODLE_INTERNAL') || die();
global $CFG;

use advanced_testcase;
use tool_coursemigration\local\storage\type\shared_disk_storage;

require_once($CFG->libdir . '/adminlib.php');

/**
 * The storage_setting_configselect test class.
 *
 * @package     tool_coursemigration
 * @copyright   2023 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\local\settings\storage_setting_configselect
 */
final class storage_setting_configselect_test extends advanced_testcase {
    /**
     * Tests the constructor.
     */
    public function test_constructor(): void {
        $this->resetAfterTest();

        $class = new storage_setting_configselect();
        self::assertNotNull($class);
    }

    /**
     * Tests that the storage options are not empty.
     */
    public function test_options_not_empty(): void {
        $this->resetAfterTest();

        // The storage options should not be empty as they are required for the setting to function correctly.
        $class = new storage_setting_configselect();
        $this->assertNotEmpty($class->choices);

        // Shared disk storage must be one of the options and should be the default.
        $expecteddclass = shared_disk_storage::class;
        $this->assertArrayHasKey($expecteddclass, $class->choices);

        // Default stored in the setting should be the shared disk storage class.
        $this->assertSame($expecteddclass, $class->get_defaultsetting());
    }
}
