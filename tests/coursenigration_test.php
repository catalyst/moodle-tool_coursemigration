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
 * Tests for course migration class.
 *
 * @package    tool_coursemigration
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\coursemigration
 */
class coursenigration_test extends advanced_testcase {


    /**
     * Test setting errors.
     */
    public function test_set_errors() {
        $this->resetAfterTest();

        $coursemigration = new coursemigration(0, (object)[
            'action' => coursemigration::ACTION_BACKUP,
            'courseid' => 7777,
            'destinationcategoryid' => 1,
            'status' => coursemigration::STATUS_NOT_STARTED,
        ]);

        $this->assertEmpty($coursemigration->get('error'));

        $coursemigration->set('error', 'Error 1');
        $this->assertSame('Error 1', $coursemigration->get('error'));

        $coursemigration->set('error', 'Error 2');
        $this->assertSame('Error 1' . coursemigration::ERROR_DELIMITER . 'Error 2', $coursemigration->get('error'));

        $coursemigration->set('error', 'Error 3');
        $this->assertSame(
            ['Error 1', 'Error 2', 'Error 3'],
            explode(coursemigration::ERROR_DELIMITER, $coursemigration->get('error'))
        );
    }
}
