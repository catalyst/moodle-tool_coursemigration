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

namespace tool_coursemigration\local\settings;

use admin_setting_configselect;

/**
 * Admin setting for a list of AWS regions sourced from the AWS SDK.
 *
 * @package    tool_coursemigration
 * @copyright  2020 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class s3_storage_region_settings_configselect extends admin_setting_configselect {
    /**
     * Build options from the AWS SDK endpoints file and call parent constructor.
     *
     * @param string $name         Config key name.
     * @param string $visiblename  Display name.
     * @param string $description  Description.
     * @param string $defaultsetting Default region value.
     */
    public function __construct(string $name, string $visiblename, string $description, string $defaultsetting = 'ap-southeast-2') {
        parent::__construct($name, $visiblename, $description, $defaultsetting, self::get_region_options());
    }

    /**
     * Build the region choices from the AWS SDK endpoints file.
     *
     * @return array<string,string> value => label pairs
     */
    private static function get_region_options(): array {
        global $CFG;
        // We do require() not require_once() here, as the file returns a value and we may need to get
        // this value more than once.
        $all = require($CFG->dirroot . '/lib/aws-sdk/src/data/endpoints.json.php');
        $ends = $all['partitions'][0]['regions'] ?? [];
        $options = [];
        foreach ($ends as $key => $value) {
            $options[$key] = $key . ' - ' . $value['description'];
        }
        return $options;
    }
}
