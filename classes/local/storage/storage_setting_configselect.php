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

namespace tool_coursemigration\local\storage;

use \admin_setting_configselect;

/**
 * Autoloads course migration storage config select.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class storage_setting_configselect extends admin_setting_configselect {
    /**
     * Calls parent::__construct with specific arguments.
     */
    public function __construct() {
        $options = $this->get_storage_names();
        $default = array_key_first($options);
        parent::__construct(
            'tool_coursemigration/storagetype',
            new \lang_string('storagetype', 'tool_coursemigration'),
            new \lang_string('storagetype_help', 'tool_coursemigration'),
            $default,
            $options
        );
    }

    /**
     * Gets the display name of the storage classes to be used in config settings.
     * @return array A list of the options for the drop down list.
     */
    private function get_storage_names(): array {
        $storagenames = [];
        $files = scandir(__DIR__  . '/type');
        foreach ($files as $file) {
            $base = basename($file, '.php');
            if ($base[0] == '.') {
                // Skip hidden.
                continue;
            }
            $fullpath = __NAMESPACE__ . '\type\\' . $base;
            $storagenames[$fullpath] = get_string('storage:' . $base, 'tool_coursemigration');
        }
        return $storagenames;
    }
}
