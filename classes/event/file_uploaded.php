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

namespace tool_coursemigration\event;

use core\event\base;
use context_system;
use coding_exception;

/**
 * The CSV file uploaded event class.
 *
 * @package     tool_coursemigration
 * @category    event
 * @copyright   2023 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_uploaded extends base {

    /**
     * Initialise the data.
     */
    protected function init() {
        $this->data['objecttable'] = null;
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['crud'] = 'c';
        $this->context = context_system::instance();
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event:file_uploaded', 'tool_coursemigration');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        return "User with id '{$this->userid}' uploaded file '{$this->other['filename']}'";
    }

    /**
     * Validates the custom data.
     *
     * @throws \coding_exception if missing required data.
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['filename'])) {
            throw new coding_exception('The \'filename\' value must be set in other.');
        }
    }
}
