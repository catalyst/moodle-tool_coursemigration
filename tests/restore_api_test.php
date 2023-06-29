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
use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use tool_coursemigration\event\http_request_failed;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

/**
 * Tests for restore_api_test class.
 *
 * @package     tool_coursemigration
 * @copyright   2023 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\restore_api
 */
class restore_api_test extends advanced_testcase {

    /**
     * Sent requests.
     * @var array
     */
    private $requestssent = [];

    /**
     * Get mocked HTTP handler.
     *
     * @param \GuzzleHttp\Psr7\Response $response Response to set to a handler.
     * @return \GuzzleHttp\HandlerStack
     */
    private function get_mock_http_handler(Response $response): HandlerStack {
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->requestssent));

        return $stack;
    }

    /**
     * Configure the plugin.
     *
     * @return void
     */
    private function configure_plugin() {
        set_config('destinationwsurl', 'https://test.com', 'tool_coursemigration');
        set_config('wstoken', 'sEcReTtOkEn', 'tool_coursemigration');
    }

    /**
     * Test error if the plugin is not configured.
     */
    public function test_plugin_not_configured() {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Error attempting to make HTTP request: Plugin is not configured.');

        $api = new restore_api();
    }

    /**
     * Test guzzle error response.
     */
    public function test_guzzle_error_response() {
        $this->resetAfterTest();
        $this->configure_plugin();

        $eventsink = $this->redirectEvents();

        // 403 should trigger guzzle error.
        $response = new Response(403);
        $client = new Client(['handler' => $this->get_mock_http_handler($response)]);
        $api = new restore_api($client);
        $this->assertFalse($api->request_restore('test', 1));
        $this->assertEquals(1, count($this->requestssent));

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof http_request_failed;
        });

        $this->assertCount(1, $events);
        $event = reset($events);

        $expectederror = 'Client error: `GET https://test.com?wstoken=XXX&wsfunction='
            . 'tool_coursemigration_request_restore&filename=test&categoryid=1&moodlewsrestformat=json`'
            .' resulted in a `403 Forbidden` response';

        $expectedurl = 'https://test.com?wstoken=XXX&wsfunction='
            . 'tool_coursemigration_request_restore&filename=test&categoryid=1&moodlewsrestformat=json';

        $this->assertEquals($expectederror, $event->other['error']);
        $this->assertEquals($expectedurl, $event->other['url']);
    }

    /**
     * Test response is not 200.
     */
    public function test_not_200_response() {
        $this->resetAfterTest();
        $this->configure_plugin();

        $eventsink = $this->redirectEvents();

        $response = new Response(202);
        $client = new Client(['handler' => $this->get_mock_http_handler($response)]);
        $api = new restore_api($client);
        $this->assertFalse($api->request_restore('test', 1));
        $this->assertEquals(1, count($this->requestssent));

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof http_request_failed;
        });

        $this->assertCount(1, $events);
        $event = reset($events);

        $expectederror = 'Error attempting to make HTTP request: Invalid HTTP code: 202.';

        $expectedurl = 'https://test.com?wstoken=XXX&wsfunction='
            . 'tool_coursemigration_request_restore&filename=test&categoryid=1&moodlewsrestformat=json';

        $this->assertEquals($expectederror, $event->other['error']);
        $this->assertEquals($expectedurl, $event->other['url']);
    }

    /**
     * Test exception in response.
     */
    public function test_exception_in_response() {
        $this->resetAfterTest();
        $this->configure_plugin();

        $eventsink = $this->redirectEvents();

        $body = json_encode([
            'exception' => 1,
            'message' => 'Test error message',
        ]);

        $response = new Response(200, [], $body);
        $client = new Client(['handler' => $this->get_mock_http_handler($response)]);
        $api = new restore_api($client);
        $this->assertFalse($api->request_restore('test', 1));
        $this->assertEquals(1, count($this->requestssent));

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof http_request_failed;
        });

        $this->assertCount(1, $events);
        $event = reset($events);

        $expectederror = 'Error attempting to make HTTP request: Test error message.';
        $expectedurl = 'https://test.com?wstoken=XXX&wsfunction='
            . 'tool_coursemigration_request_restore&filename=test&categoryid=1&moodlewsrestformat=json';

        $this->assertEquals($expectederror, $event->other['error']);
        $this->assertEquals($expectedurl, $event->other['url']);
    }

    /**
     * Test exception in response but no message.
     */
    public function test_exception_in_response_but_no_message() {
        $this->resetAfterTest();
        $this->configure_plugin();

        $eventsink = $this->redirectEvents();

        $body = json_encode([
            'exception' => 1,
        ]);

        $response = new Response(200, [], $body);
        $client = new Client(['handler' => $this->get_mock_http_handler($response)]);
        $api = new restore_api($client);
        $this->assertFalse($api->request_restore('test', 1));
        $this->assertEquals(1, count($this->requestssent));

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof http_request_failed;
        });

        $this->assertCount(1, $events);
        $event = reset($events);

        $expectederror = 'Error attempting to make HTTP request: .';
        $expectedurl = 'https://test.com?wstoken=XXX&wsfunction='
            . 'tool_coursemigration_request_restore&filename=test&categoryid=1&moodlewsrestformat=json';

        $this->assertEquals($expectederror, $event->other['error']);
        $this->assertEquals($expectedurl, $event->other['url']);
    }

    /**
     * Test exception in response but no message.
     */
    public function test_unexpected_response() {
        $this->resetAfterTest();
        $this->configure_plugin();

        $eventsink = $this->redirectEvents();

        $body = json_encode([
            'bla' => 1,
        ]);

        $response = new Response(200, [], $body);
        $client = new Client(['handler' => $this->get_mock_http_handler($response)]);
        $api = new restore_api($client);
        $this->assertFalse($api->request_restore('test', 1));
        $this->assertEquals(1, count($this->requestssent));

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof http_request_failed;
        });

        $this->assertCount(1, $events);
        $event = reset($events);

        $expectederror = 'Error attempting to make HTTP request: Unexpected response.';
        $expectedurl = 'https://test.com?wstoken=XXX&wsfunction='
            . 'tool_coursemigration_request_restore&filename=test&categoryid=1&moodlewsrestformat=json';

        $this->assertEquals($expectederror, $event->other['error']);
        $this->assertEquals($expectedurl, $event->other['url']);
    }

    /**
     * Test success.
     */
    public function test_success() {
        $this->resetAfterTest();
        $this->configure_plugin();

        $body = 'null';
        $response = new Response(200, [], $body);
        $client = new Client(['handler' => $this->get_mock_http_handler($response)]);
        $api = new restore_api($client);
        $this->assertTrue($api->request_restore('test', 1));
    }
}
