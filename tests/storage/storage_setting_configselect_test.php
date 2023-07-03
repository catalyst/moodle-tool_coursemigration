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
defined('MOODLE_INTERNAL') || die();
global $CFG;

use advanced_testcase;
use tool_coursemigration\local\storage\storage_setting_configselect;

require_once($CFG->libdir . '/adminlib.php');

/**
 * The storage_setting_configselect test class.
 *
 * @package     tool_coursemigration
 * @copyright   2023 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\local\storage\storage_setting_configselect
 */
class storage_setting_configselect_test extends advanced_testcase {

    /**
     * Tests the constructor.
     */
    public function test_constructor() {
        $class = new storage_setting_configselect;
        self::assertNotNull($class);
    }
}
