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

use stored_file;

/**
 * Interface for file storage.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface storage_interface {
    /**
     * Download (pull) file.
     * @param $filename string Name of file to be restored.
     * @return stored_file|null
     */
    public function pull_file(string $filename): ?stored_file;

    /**
     * Upload (push) file.
     * @param $filename string Name of file to be backed up.
     * @param $filerecord stored_file A file record object of the fle to be backed up.
     * @return boolean true if successfully cretaed.
     */
    public function push_file(string $filename, stored_file $filerecord): bool;

    /**
     * Delete file.
     * @param $filename string Name of file to be backed up.
     * @return boolean true if successfully deleted.
     */
    public function delete_file(string $filename): bool;

    /**
     * Any error message from exception.
     * @return string error message.
     */
    public function get_error(): string;

    /**
     * Verifies that storage is configured for restore.
     * @return boolean true if configuration is valid.
     */
    public function ready_for_pull(): bool;

    /**
     * Verifies that storage is configured for backup.
     * @return boolean true if configuration is valid.
     */
    public function ready_for_push(): bool;
}
