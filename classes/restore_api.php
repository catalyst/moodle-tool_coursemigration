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

use moodle_url;
use moodle_exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use tool_coursemigration\event\http_request_failed;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

/**
 * Class for calling restore API.
 *
 * @package    tool_coursemigration
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_api {
    /**
     * WS name.
     */
    const WS_NAME = 'tool_coursemigration_request_restore';

    /**
     * WS format.
     */
    const WS_FORMAT = 'json';

    /**
     * HTTP client.
     * @var \GuzzleHttp\Client
     */
    private $client = null;

    /**
     * Destination URL.
     * @var string|bool
     */
    private $destinationwsurl = '';

    /**
     * WS token.
     * @var false|bool
     */
    private $wstoken = '';

    /**
     * Constructor.
     *
     * @param \GuzzleHttp\Client|null $client Client for the API.
     */
    public function __construct(Client $client = null) {
        if (empty($client)) {
            $this->client = $this->get_http_client();
        } else {
            $this->client = $client;
        }

        $this->destinationwsurl = get_config('tool_coursemigration', 'destinationwsurl');
        $this->wstoken = get_config('tool_coursemigration', 'wstoken');

        $this->validate_plugin();
    }

    /**
     * Instantiate http client.
     *
     * @return \GuzzleHttp\Client
     */
    private function get_http_client(): Client {
        return new Client(['verify' => false]);
    }


    /**
     * Validate the plugin.
     */
    private function validate_plugin() {
        if (empty($this->destinationwsurl) || empty($this->wstoken)) {
            throw new moodle_exception('error:http:get', 'tool_coursemigration', '', 'Plugin is not configured');
        }
    }

    /**
     * Validate response.
     *
     * @param \GuzzleHttp\Psr7\Response $response Response instance.
     */
    private function validate_response(Response $response): void {
        if ($response->getStatusCode() != 200) {
            throw new moodle_exception(
                'error:http:get', 'tool_coursemigration', '', 'Invalid HTTP code: ' . $response->getStatusCode()
            );
        }

        $content = $response->getBody()->getContents();

        // We expect WS to return "null".
        if ($content !== 'null') {
            $data = json_decode($content, true);
            if (!empty($data['exception'])) {
                $error = !empty($data['message']) ? $data['message'] : '';
                throw new moodle_exception('error:http:get', 'tool_coursemigration', '', $error);
            } else {
                throw new moodle_exception('error:http:get', 'tool_coursemigration', '', 'Unexpected response');
            }
        }
    }

    /**
     * String WS token from the provided string.
     *
     * @param string $string provided string.
     * @return string
     */
    private function strip_wstoken(string $string): string {
        return preg_replace("/(?<=wstoken=).*?(?=&)/", 'XXX', $string);
    }

    /**
     * Request restore.
     *
     * @param string $backupfilename
     * @param int $categoryid
     *
     * @return bool
     */
    public function request_restore(string $backupfilename, int $categoryid): bool {
        $params = [
            'wstoken' => $this->wstoken,
            'wsfunction' => self::WS_NAME,
            'filename' => $backupfilename,
            'categoryid' => $categoryid,
            'moodlewsrestformat' => self::WS_FORMAT,
        ];

        $url = new moodle_url($this->destinationwsurl, $params);
        $uri = $url->out(false);
        $request = new Request('GET', $uri);

        try {
            $response = $this->client->send($request);
            $this->validate_response($response);
            return true;
        } catch (\Exception $exception) {
            http_request_failed::create([
                'other' => [
                    'url' => $this->strip_wstoken($uri),
                    'error' => $this->strip_wstoken($exception->getMessage()),
                ]
            ])->trigger();

            return false;
        }
    }
}
