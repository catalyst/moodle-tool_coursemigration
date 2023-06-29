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
 * Interface for file storage.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursemigration\local\storage;

/**
 * Interface for file storage.
 * @property string $errormessage
 */
interface storage_interface {
    /**
     * List storage types here.
     */
    const TYPE_SHARED_DISK_STORAGE = 0;

    /**
     * Get settings.
     */
    public function get_settings();

    /**
     * Download (pull) file.
     * @param $filename string Name of file to be restored.
     * @param $contextid integer Context id for file storage.
     * @return \stored_file
     */
    public function pull_file($filename, $contextid);

    /**
     * Upload (push) file.
     * @param $filename string Name of file to be backed up.
     * @param $filecontents \stored_file
     * @return boolean true if successfully cretaed.
     */
    public function push_file($filename, $filecontents);
}
