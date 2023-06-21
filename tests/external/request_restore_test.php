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

namespace tool_coursemigration\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use externallib_advanced_testcase;
use external_api;
use tool_coursemigration\coursemigration;

/**
 * The get_bentoboxes test class.
 *
 * @package     tool_coursemigration
 * @copyright   2023 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\external\request_restore
 */
class request_restore_test extends externallib_advanced_testcase {

    /**
     * A helper method to verify web service error.
     *
     * @param array $response Response array.
     */
    protected function verify_error(array $response): void {
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('exception', $response);
        $this->assertTrue($response['error']);
    }

    /**
     * A helper method to verify web service success.
     *
     * @param array $response Response array.
     */
    protected function verify_success(array $response): void {
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertFalse($response['error']);
        $this->assertArrayNotHasKey('exception', $response);
        $this->assertArrayHasKey('data', $response);
    }

    /**
     * Test request_restore to invalid category.
     */
    public function test_request_restore_to_invalid_category() {
        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('defaultcategory', 777, 'tool_coursemigration');

        // Workaround to be able to call external_api::call_external_function.
        $_POST['sesskey'] = sesskey();

        $response = external_api::call_external_function('tool_coursemigration_request_restore', [
            'filename' => 'test_filename.txt',
            'categoryid' => 0,
        ]);

        $this->verify_error($response);
        $this->assertSame('Invalid parameter value detected', $response['exception']->message);
        $this->assertStringContainsString('Invalid category', $response['exception']->debuginfo);
    }

    /**
     * Test request_restore without permissions.
     */
    public function test_request_restore_without_permissions() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Workaround to be able to call external_api::call_external_function.
        $_POST['sesskey'] = sesskey();

        $response = external_api::call_external_function('tool_coursemigration_request_restore', [
            'filename' => 'test_filename.txt',
            'categoryid' => 0,
        ]);

        $this->verify_error($response);
        $this->assertSame(
            'Sorry, but you do not currently have permissions to do that (Restore courses).',
            $response['exception']->message
        );
    }

    /**
     * Test request_restore with permissions.
     */
    public function test_request_restore_with_permissions() {
        $this->resetAfterTest();

        $this->assertSame(0, coursemigration::count_records());
        $user = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();

        $this->setUser($user);
        $this->assignUserCapability('tool/coursemigration:restorecourse', $category->get_context()->id);

        // Workaround to be able to call external_api::call_external_function.
        $_POST['sesskey'] = sesskey();

        $response = external_api::call_external_function('tool_coursemigration_request_restore', [
            'filename' => 'test_filename.txt',
            'categoryid' => $category->id,
        ]);

        $this->verify_success($response);
        $this->assertEquals(1, coursemigration::count_records());
        $record = coursemigration::get_record(['destinationcategoryid' => $category->id]);

        $this->assertEquals(coursemigration::ACTION_RESTORE, $record->get('action'));
        $this->assertEquals(0, $record->get('courseid'));
        $this->assertEquals($category->id, $record->get('destinationcategoryid'));
        $this->assertEquals(coursemigration::STATUS_NOT_STARTED, $record->get('status'));
        $this->assertEquals('test_filename.txt', $record->get('filename'));
        $this->assertEquals(null, $record->get('error'));
        $this->assertEquals($user->id, $record->get('usermodified'));
    }

    /**
     * Test request_restore falls back to default category.
     */
    public function test_request_restore_falls_back_to_default_category() {
        $this->resetAfterTest();

        $this->setAdminUser();

        $this->assertSame(0, coursemigration::count_records());
        $category = $this->getDataGenerator()->create_category();
        set_config('defaultcategory', $category->id, 'tool_coursemigration');

        // Workaround to be able to call external_api::call_external_function.
        $_POST['sesskey'] = sesskey();

        $response = external_api::call_external_function('tool_coursemigration_request_restore', [
            'filename' => 'test_filename.txt',
            'categoryid' => 7777,
        ]);

        $this->verify_success($response);

        $this->assertEquals(1, coursemigration::count_records());
        $record = coursemigration::get_record(['destinationcategoryid' => $category->id]);

        $this->assertEquals(coursemigration::ACTION_RESTORE, $record->get('action'));
        $this->assertEquals(0, $record->get('courseid'));
        $this->assertEquals($category->id, $record->get('destinationcategoryid'));
        $this->assertEquals(coursemigration::STATUS_NOT_STARTED, $record->get('status'));
        $this->assertEquals('test_filename.txt', $record->get('filename'));
        $this->assertEquals(null, $record->get('error'));
    }
}
