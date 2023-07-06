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
/**
 * Privacy test for the tool_coursemigration.
 *
 * @package    tool_coursemigration
 * @category   test
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_coursemigration\privacy;

use context_system;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\tests\provider_testcase;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_coursemigration\coursemigration;
use tool_coursemigration\privacy\provider;
use stdClass;

/**
 * Privacy test for the tool_coursemigration.
 *
 * @package    tool_coursemigration
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_test extends provider_testcase {

    /**
     * @var object Moodle user object.
     */
    private $user;

    /**
     * @var object Moodle user object (second).
     */
    private $user2;

    /**
     * Setup a test coursemigration data.
     */
    public function setup_test_data(): void {
        $this->resetAfterTest();

        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);

        $data = new stdClass();
        $data->action = coursemigration::ACTION_RESTORE;
        $data->courseid = 123456;
        $data->destinationcategoryid = 654321;
        $coursemigration1 = new coursemigration(0, $data);
        $coursemigration1->save();

        $this->user2 = $this->getDataGenerator()->create_user();
        $this->setUser($this->user2);

        $data = new stdClass();
        $data->action = coursemigration::ACTION_BACKUP;
        $data->courseid = 112233;
        $data->destinationcategoryid = 332211;
        $coursemigration2 = new coursemigration(0, $data);
        $coursemigration2->save();
    }

    /**
     * Test that a collection with data is returned when calling this function.
     * @covers ::get_metadata
     */
    public function test_get_metadata() {
        $items = new collection('tool_coursemigration');
        $result = provider::get_metadata($items);
        $this->assertSame($items, $result);
        $this->assertInstanceOf(collection::class, $result);
    }

    /**
     * Test that the module context for a user who last modified the module is retrieved.
     * @covers ::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid() {
        $this->setup_test_data();
        $contextlist = provider::get_contexts_for_userid($this->user->id);
        $contextids = $contextlist->get_contextids();
        $this->assertCount(1, $contextlist);
        $this->assertEquals(context_system::instance()->id, reset($contextids));
    }

    /**
     * Test that no module context is found for a user who has not modified any section settings.
     * @covers ::get_contexts_for_userid
     */
    public function test_get_no_contexts_for_userid() {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $contexts = provider::get_contexts_for_userid($user->id);
        $contextids = $contexts->get_contextids();
        $this->assertEmpty($contextids);
    }

    /**
     * Test that all users with the system context is fetched.
     * @covers ::get_users_in_context
     */
    public function test_get_users_in_context() {
        $component = 'tool_coursemigration';
        $this->setup_test_data();

        // The list of users for system context should return the users.
        $systemcontext = context_system::instance();
        $userlist = new userlist($systemcontext, $component);
        provider::get_users_in_context($userlist);
        $this->assertCount(2, $userlist);
        $expected = [$this->user->id, $this->user2->id];
        $actual = $userlist->get_userids();
        $this->assertEqualsCanonicalizing($expected, $actual);

        // The list of users for user context should not return any users.
        $usercontext = context_user::instance($this->user->id);
        $userlist = new userlist($usercontext, $component);
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);
    }

    /**
     * Test that user data is exported correctly.
     * @covers ::export_user_data
     */
    public function test_export_user_data() {
        $context = context_system::instance();
        writer::reset();
        $writer = writer::with_context($context);
        $this->assertEmpty($writer->has_any_data());

        $this->setup_test_data();
        $contextlist = provider::get_contexts_for_userid($this->user->id);
        $approvedcontextlist = new approved_contextlist(
            $this->user,
            'tool_coursemigration',
            $contextlist->get_contextids()
        );
        provider::export_user_data($approvedcontextlist);
        $data = $writer->get_data([
            get_string('pluginname', 'tool_coursemigration')
        ]);
        $this->assertNotEmpty($data->coursemigrations);
        $this->assertIsArray($data->coursemigrations);
        $coursemigrations = current($data->coursemigrations);
        $this->assertEquals(coursemigration::ACTION_RESTORE, $coursemigrations['action']);
        $this->assertEquals(123456, $coursemigrations['courseid']);
        $this->assertEquals(654321, $coursemigrations['destinationcategoryid']);

        writer::reset();
        $writer = writer::with_context($context);
        $contextlist = provider::get_contexts_for_userid($this->user2->id);
        $approvedcontextlist = new approved_contextlist(
            $this->user2,
            'tool_coursemigration',
            $contextlist->get_contextids()
        );
        provider::export_user_data($approvedcontextlist);
        $data = $writer->get_data([
            get_string('pluginname', 'tool_coursemigration')
        ]);
        $this->assertNotEmpty($data->coursemigrations);
        $this->assertIsArray($data->coursemigrations);
        $coursemigrations = current($data->coursemigrations);
        $this->assertEquals(coursemigration::ACTION_BACKUP, $coursemigrations['action']);
        $this->assertEquals(112233, $coursemigrations['courseid']);
        $this->assertEquals(332211, $coursemigrations['destinationcategoryid']);
    }

    /**
     * Test deleting all user data for a specific context.
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $this->setup_test_data();

        $this->assertTrue($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user->id]));
        $this->assertTrue($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user2->id]));

        $systemcontext = context_system::instance();
        provider::delete_data_for_all_users_in_context($systemcontext);

        $this->assertFalse($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user->id]));
        $this->assertFalse($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user2->id]));
    }

    /**
     * Test that data for a user in approved userlist is deleted.
     * @covers ::delete_data_for_user
     */
    public function test_delete_data_for_user() {
        global $DB;
        $this->setup_test_data();

        $this->assertTrue($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user->id]));
        $this->assertTrue($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user->id]));

        // Delete everything for the first user.
        $systemcontext = context_system::instance();
        $approvedcontextlist = new approved_contextlist($this->user, 'tool_coursemigration', [$systemcontext->id]);

        provider::delete_data_for_user($approvedcontextlist);

        $this->assertFalse($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user->id]));
        $this->assertTrue($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user2->id]));
    }

    /**
     * Test that data for users in approved userlist is deleted.
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_users() {
        global $DB;
        $this->setup_test_data();

        $this->assertTrue($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user->id]));
        $this->assertTrue($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user->id]));

        // Delete data based on CONTEXT_SYSTEM context.
        $systemcontext = context_system::instance();
        provider::delete_data_for_all_users_in_context($systemcontext);

        $this->assertFalse($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user->id]));
        $this->assertFalse($DB->record_exists('tool_coursemigration', ['usermodified' => $this->user2->id]));
    }
}
