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

namespace tool_coursemigration\local\storage\type;

use advanced_testcase;

/**
 * The s3_storage test class.
 *
 * @package    tool_coursemigration
 * @author     Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  2025 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\local\storage\type\s3_storage
 */
class s3_storage_test extends advanced_testcase {
    /** @var string Test bucket name */
    const TEST_BUCKET = 'test-bucket';
    /** @var string Test region */
    const TEST_REGION = 'us-east-1';
    /** @var string Test key ID */
    const TEST_KEY_ID = 'test-key-id';
    /** @var string Test secret key */
    const TEST_SECRET_KEY = 'test-secret-key';
    /** @var string File name for test pull */
    const TEST_PULL_FILE = 'testpull.mbz';
    /** @var string File name for test push */
    const TEST_PUSH_FILE = 'testpush.mbz';
    /** @var string File name for test delete */
    const TEST_DELETE_FILE = 'testdelete.mbz';

    /**
     * Setup before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test s3_storage without configuration.
     */
    public function test_without_configuration() {
        $storage = new s3_storage();

        $this->assertFalse($storage->ready_for_pull());
        $this->assertFalse($storage->ready_for_push());
    }

    /**
     * Test s3_storage with missing bucket configuration.
     */
    public function test_missing_bucket_configuration() {
        set_config('storagetype', 'tool_coursemigration\\local\\storage\\type\\s3_storage', 'tool_coursemigration');
        set_config('awss3_s3region', self::TEST_REGION, 'tool_coursemigration');
        set_config('awss3_keyid', self::TEST_KEY_ID, 'tool_coursemigration');
        set_config('awss3_secretkey', self::TEST_SECRET_KEY, 'tool_coursemigration');

        $storage = new s3_storage();

        $this->assertFalse($storage->ready_for_pull());
        $this->assertFalse($storage->ready_for_push());

        // There is no connection error.
        $this->assertEmpty($storage->get_error());
    }

    /**
     * Test s3_storage with missing region configuration.
     */
    public function test_missing_region_configuration() {
        set_config('storagetype', 'tool_coursemigration\\local\\storage\\type\\s3_storage', 'tool_coursemigration');
        set_config('awss3_bucket', self::TEST_BUCKET, 'tool_coursemigration');
        set_config('awss3_keyid', self::TEST_KEY_ID, 'tool_coursemigration');
        set_config('awss3_secretkey', self::TEST_SECRET_KEY, 'tool_coursemigration');

        $storage = new s3_storage();

        $this->assertFalse($storage->ready_for_pull());
        $this->assertFalse($storage->ready_for_push());

        // There is no connection error.
        $this->assertEmpty($storage->get_error());
    }

    /**
     * Test s3_storage with missing credentials when not using SDK creds.
     */
    public function test_missing_credentials_configuration() {
        set_config('storagetype', 'tool_coursemigration\\local\\storage\\type\\s3_storage', 'tool_coursemigration');
        set_config('awss3_bucket', self::TEST_BUCKET, 'tool_coursemigration');
        set_config('awss3_s3region', self::TEST_REGION, 'tool_coursemigration');

        $storage = new s3_storage();

        $this->assertFalse($storage->ready_for_pull());
        $this->assertFalse($storage->ready_for_push());

        // There is no connection error.
        $this->assertEmpty($storage->get_error());
    }

    /**
     * Test s3_storage without missing configuration.
     */
    public function test_with_full_configuration() {
        set_config('storagetype', 'tool_coursemigration\\local\\storage\\type\\s3_storage', 'tool_coursemigration');
        set_config('awss3_bucket', self::TEST_BUCKET, 'tool_coursemigration');
        set_config('awss3_s3region', self::TEST_REGION, 'tool_coursemigration');
        set_config('awss3_keyid', self::TEST_KEY_ID, 'tool_coursemigration');
        set_config('awss3_secretkey', self::TEST_SECRET_KEY, 'tool_coursemigration');

        $storage = new s3_storage();

        // Still not ready, but there is a connection error due to fake configuration values.
        $this->assertFalse($storage->ready_for_pull());
        $this->assertFalse($storage->ready_for_push());

        // There is connection error.
        $this->assertNotEmpty($storage->get_error());
    }

    /**
     * Test pull_file with non-functional client.
     */
    public function test_pull_file_without_configuration() {
        $storage = new s3_storage();

        $result = $storage->pull_file(self::TEST_PULL_FILE);

        $this->assertNull($result);
        $this->assertEquals('S3 client is not configured properly.', $storage->get_error());
    }

    /**
     * Test push_file with non-functional client.
     */
    public function test_push_file_without_configuration() {
        $storage = new s3_storage();

        // Create a test file to push.
        $context = \context_system::instance();
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'tool_coursemigration',
            'filearea' => 'backup',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => self::TEST_PUSH_FILE,
        ];
        $content = 'test content';
        $file = $fs->create_file_from_string($filerecord, $content);

        $result = $storage->push_file(self::TEST_PUSH_FILE, $file);

        $this->assertFalse($result);
        $this->assertEquals('S3 client is not configured properly.', $storage->get_error());

        // Clean up.
        $file->delete();
    }

    /**
     * Test delete_file with non-functional client.
     */
    public function test_delete_file_without_configuration() {
        $storage = new s3_storage();

        $result = $storage->delete_file(self::TEST_DELETE_FILE);

        $this->assertFalse($result);
        $this->assertEquals('S3 client is not configured properly.', $storage->get_error());
    }

    /**
     * Test define_storage_section creates proper admin settings.
     */
    public function test_define_storage_section() {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');

        // Create a new settings page and define the storage section.
        $storage = new s3_storage();
        $settingpage = new \admin_settingpage('test_s3_settings', 'Test S3 Settings');
        $result = $storage->define_storage_section($settingpage);

        // Get settings added to the page.
        $settings = $result->settings;
        $this->assertNotEmpty($settings);

        // Check that key settings are present.
        $settingnames = [];
        foreach ($settings as $setting) {
            if (method_exists($setting, 'get_full_name')) {
                $settingnames[] = $setting->get_full_name();
            }
        }

        // Check that all expected settings are present.
        $this->assertContains('s_tool_coursemigration_awss3_usesdkcreds', $settingnames);
        $this->assertContains('s_tool_coursemigration_awss3_bucket', $settingnames);
        $this->assertContains('s_tool_coursemigration_awss3_bucket_acl', $settingnames);
        $this->assertContains('s_tool_coursemigration_awss3_s3region', $settingnames);
        $this->assertContains('s_tool_coursemigration_awss3_key_prefix', $settingnames);
        $this->assertContains('s_tool_coursemigration_awss3_keyid', $settingnames);
        $this->assertContains('s_tool_coursemigration_awss3_secretkey', $settingnames);
    }

    /**
     * Test delete_existing_file_record static method.
     */
    public function test_delete_existing_file_record() {
        $context = \context_system::instance();
        $fs = get_file_storage();

        // Create a test file.
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'tool_coursemigration',
            'filearea' => 'backup',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'test_delete_existing.mbz',
        ];
        $fs->create_file_from_string($filerecord, 'test content');

        // Verify file exists.
        $this->assertNotFalse($fs->get_file(
            $filerecord['contextid'],
            $filerecord['component'],
            $filerecord['filearea'],
            $filerecord['itemid'],
            $filerecord['filepath'],
            $filerecord['filename']
        ));

        // Delete it using the static method.
        s3_storage::delete_existing_file_record($fs, $filerecord);

        // Verify file is deleted.
        $this->assertFalse($fs->get_file(
            $filerecord['contextid'],
            $filerecord['component'],
            $filerecord['filearea'],
            $filerecord['itemid'],
            $filerecord['filepath'],
            $filerecord['filename']
        ));
    }

    /**
     * Test delete_existing_file_record when file doesn't exist.
     */
    public function test_delete_existing_file_record_no_file() {
        $context = \context_system::instance();
        $fs = get_file_storage();

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'tool_coursemigration',
            'filearea' => 'backup',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'nonexistent.mbz',
        ];

        // Do not throw exception when file doesn't exist.
        s3_storage::delete_existing_file_record($fs, $filerecord);

        // Verify file still doesn't exist.
        $this->assertFalse($fs->get_file(
            $filerecord['contextid'],
            $filerecord['component'],
            $filerecord['filearea'],
            $filerecord['itemid'],
            $filerecord['filepath'],
            $filerecord['filename']
        ));
    }
}
