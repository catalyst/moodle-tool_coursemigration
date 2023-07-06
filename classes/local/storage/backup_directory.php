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

use admin_setting_configdirectory;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/adminlib.php');

/**
 * Setting for backup directory.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_directory extends admin_setting_configdirectory {
    /**
     * Calls parent::__construct with specific arguments.
     */
    public function __construct($identifier) {
        parent::__construct(
            'tool_coursemigration/' . $identifier,
            new \lang_string($identifier, 'tool_coursemigration'),
            new \lang_string($identifier . '_help', 'tool_coursemigration'), ''
        );
    }

    /**
     * Check if the directory must be set.
     * @param mixed $data Gets converted to str for comparison against yes value
     * @return string empty string or error
     */
    public function write_setting($data): string {
        $configselectedstorage = get_config('tool_coursemigration', 'storagetype');
        if ($configselectedstorage == __NAMESPACE__ . '\type\shared_disk_storage') {
            // Allow empty, otherwise must exist and be writable.
            if (!empty($data) && (!file_exists($data) || !is_dir($data) || !is_writable($data))) {
                // The directory must exist and be writable.
                return get_string('backuperrorinvaliddestination');
            }
        }
        return parent::write_setting($data);
    }
}
