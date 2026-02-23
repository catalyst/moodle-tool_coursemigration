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
 * Admin setting for S3 ACL options sourced from the AWS SDK.
 *
 * @package    tool_coursemigration
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class s3_storage_acl_settings_configselect extends admin_setting_configselect {
    /**
     * Build options from the AWS SDK S3 API shape and call parent constructor.
     *
     * @param string $name         Config key name.
     * @param string $visiblename  Display name.
     * @param string $description  Description.
     * @param string $defaultsetting Default ACL value.
     */
    public function __construct(string $name, string $visiblename, string $description, string $defaultsetting = 'private') {
        parent::__construct($name, $visiblename, $description, $defaultsetting, self::get_acl_options());
    }

    /**
     * Build the ACL choices from the AWS SDK S3 API shape.
     *
     * @return array<string,string> value => label pairs
     */
    private static function get_acl_options(): array {
        global $CFG;
        // We do require() not require_once() here, as the file returns a value and we may need to get
        // this value more than once.
        $api = require($CFG->dirroot . '/lib/aws-sdk/src/data/s3/2006-03-01/api-2.json.php');
        $acls = $api['shapes']['ObjectCannedACL']['enum'] ?? [];
        $options = [];
        foreach ($acls as $value) {
            $options[$value] = $value;
        }
        return $options;
    }
}
