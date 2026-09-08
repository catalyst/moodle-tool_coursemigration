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

require_once($CFG->libdir . '/adminlib.php');

/**
 * Tests for s3_storage_region_settings_configselect.
 *
 * @package    tool_coursemigration
 * @copyright  2026 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\local\settings\s3_storage_region_settings_configselect
 */
final class s3_storage_region_settings_configselect_test extends advanced_testcase {
    /**
     * Test that the region setting can be constructed.
     */
    public function test_constructor(): void {
        $this->resetAfterTest();

        $setting = new s3_storage_region_settings_configselect(
            'tool_coursemigration/awss3_s3region',
            'AWS Region',
            'The AWS region.',
            'ap-southeast-2'
        );
        $this->assertNotNull($setting);
    }

    /**
     * Test that region choices are populated from the AWS SDK.
     */
    public function test_choices_populated_from_sdk(): void {
        global $CFG;

        $this->resetAfterTest();

        $setting = new s3_storage_region_settings_configselect(
            'tool_coursemigration/awss3_s3region',
            'AWS Region',
            'The AWS region.',
            'ap-southeast-2'
        );

        $choices = $setting->choices;
        $this->assertNotEmpty($choices);

        // Verify choices match the SDK's endpoints file exactly.
        $all = require($CFG->dirroot . '/lib/aws-sdk/src/data/endpoints.json.php');
        $ends = $all['partitions'][0]['regions'] ?? [];

        foreach ($ends as $key => $value) {
            $this->assertArrayHasKey($key, $choices);
            $this->assertSame($key . ' - ' . $value['description'], $choices[$key]);
        }
    }
}
