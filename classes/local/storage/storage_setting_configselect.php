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
 * Autoloads course migration storage config select.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursemigration\local\storage;

class storage_setting_configselect extends \admin_setting_configselect {
    /**
     * Calls parent::__construct with specific arguments.
     */
    public function __construct() {
        $options = self::get_storage_names();
        $default = array_key_first($options);
        parent::__construct(
            'tool_coursemigration/storagetype',
            new \lang_string('storagetype', 'tool_coursemigration'),
            new \lang_string('storagetype_help', 'tool_coursemigration'),
            $default,
            $options
        );
    }
    public static function get_storage_names() {
        $storagenames = [];
        $files = scandir(__DIR__  . '/type');
        foreach ($files as $file) {
            $base = basename($file, '.php');
            if ($base[0] == '.') {
                // Skip hidden.
                continue;
            }
            $base = __NAMESPACE__ . '\type\\' . $base;
            $storageclass = new $base();
            $storagenames[$base] = $storageclass::STORAGE_TYPE_NAME;
        }
        return $storagenames;
    }

    /**
     * Returns a storage class object as selected in configuration.
     * @return storage_interface|null storage class object
     */
    public static function get_selected() {
        $configselectedstorage = get_config('tool_coursemigration', 'storagetype');
        if ($configselectedstorage) {
            return new $configselectedstorage;
        }
        return null;
    }
}
