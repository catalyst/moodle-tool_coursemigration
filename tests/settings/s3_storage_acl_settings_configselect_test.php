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
 * Tests for s3_storage_acl_settings_configselect.
 *
 * @package    tool_coursemigration
 * @copyright  2026 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\local\settings\s3_storage_acl_settings_configselect
 */
final class s3_storage_acl_settings_configselect_test extends advanced_testcase {
    /**
     * Test that the ACL setting can be constructed.
     */
    public function test_constructor(): void {
        $this->resetAfterTest();

        $setting = new s3_storage_acl_settings_configselect(
            'tool_coursemigration/awss3_bucket_acl',
            'Bucket ACL',
            'ACL for uploaded objects.',
            'private'
        );
        $this->assertNotNull($setting);
    }

    /**
     * Test that ACL choices are populated from the AWS SDK.
     */
    public function test_choices_populated_from_sdk(): void {
        global $CFG;

        $this->resetAfterTest();

        $setting = new s3_storage_acl_settings_configselect(
            'tool_coursemigration/awss3_bucket_acl',
            'Bucket ACL',
            'ACL for uploaded objects.',
            'private'
        );

        $choices = $setting->choices;
        $this->assertNotEmpty($choices);

        // Verify choices match the SDK's ObjectCannedACL enum exactly.
        $api = require($CFG->dirroot . '/local/aws/sdk/Aws/data/s3/2006-03-01/api-2.json.php');
        $expected = $api['shapes']['ObjectCannedACL']['enum'] ?? [];

        foreach ($expected as $value) {
            $this->assertArrayHasKey($value, $choices);
            $this->assertSame($value, $choices[$value]);
        }
    }
}
