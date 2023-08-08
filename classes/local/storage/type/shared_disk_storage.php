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

namespace tool_coursemigration\local\storage\type;
use context_system;
use Exception;
use file_storage;
use stored_file;
use tool_coursemigration\local\storage\storage_interface;

/**
 * Class to handle shared disk file functions.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shared_disk_storage implements storage_interface {

    /**
     * @var string Full path to the directory where you want to store the backup files.
     */
    protected $directory;

    /**
     * @var string Any error message from exception.
     */
    protected $errormessage = '';

    /**
     * Construct the shared disk storage.
     */
    public function __construct() {
        $configselectedstorage = get_config('tool_coursemigration', 'storagetype');
        $thisclass = get_class($this);

        if ($configselectedstorage == $thisclass) {
            // Initialise directory paths.
            $directory = get_config('tool_coursemigration', 'directory');
            $this->directory = ($directory) ? rtrim($directory, '/') . '/' : null;
        }
    }

    /**
     * Download (pull) file.
     * @param $filename string Name of file to be restored.
     * @return stored_file|null A file record object of the retrieved file.
     */
    public function pull_file(string $filename): ?stored_file {
        try {
            $context = context_system::instance();
            $sourcefullpath = $this->directory . $filename;
            $fs = get_file_storage();
            $filerecord = array('contextid' => $context->id, 'component' => 'tool_coursemigration', 'filearea' => 'backup',
                'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
                'timecreated' => time(), 'timemodified' => time());
            // Delete existing file (if any) and create new one.
            $this::delete_existing_file_record($fs, $filerecord);
            return $fs->create_file_from_pathname($filerecord, $sourcefullpath);
        } catch (Exception $e) {
            $this->errormessage = $e->getMessage();
            return null;
        }
    }

    /**
     * Upload (push) file.
     * @param $filename string Name of file to be backed up.
     * @param $filerecord stored_file A file record object of the fle to be backed up.
     * @return boolean true if successfully cretaed.
     */
    public function push_file(string $filename, stored_file $filerecord): bool {
        try {
            $destinationfullpath = $this->directory . $filename;
            return $filerecord->copy_content_to($destinationfullpath);
        } catch (Exception $e) {
            $this->errormessage = $e->getMessage();
            return false;
        }
    }

    /**
     * Delete file.
     * @param $filename string Name of file to be backed up.
     * @return boolean true if successfully deleted.
     */
    public function delete_file(string $filename): bool {
        try {
            $sourcefullpath = $this->directory . $filename;
            return unlink($sourcefullpath);
        } catch (Exception $e) {
            $this->errormessage = $e->getMessage();
            return false;
        }
    }

    /**
     * Check if the file exist.
     * @param $filename string Name of file .
     * @return boolean true if file exists.
     */
    public function file_exists(string $filename): bool {
        $fullpath = $this->directory . $filename;

        return file_exists($fullpath);
    }

    /**
     * Any error message from exception.
     * @return string error message.
     */
    public function get_error(): string {
        return $this->errormessage;
    }

    /**
     * Clear error message from exception.
     */
    public function clear_error() {
        $this->errormessage = '';
    }

    /**
     * Wrapper function useful for deleting an existing file (if present) just
     * before creating a new one.
     *
     * @param file_storage $fs File storage
     * @param array $filerecord File record in same format used to create file
     */
    public static function delete_existing_file_record(file_storage $fs, array $filerecord) {
        if ($existing = $fs->get_file($filerecord['contextid'], $filerecord['component'],
            $filerecord['filearea'], $filerecord['itemid'], $filerecord['filepath'],
            $filerecord['filename'])) {
            $existing->delete();
        }
    }

    /**
     * Verifies that storage is configured for restore.
     * @return boolean true if configuration is valid.
     */
    public function ready_for_pull(): bool {
        return !empty($this->directory);
    }

    /**
     * Verifies that storage is configured for backup.
     * @return boolean true if configuration is valid.
     */
    public function ready_for_push(): bool {
        return !empty($this->directory);
    }
}
