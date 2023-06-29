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
 * Class to handle shared disk file functions.
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursemigration\local\storage\type;
use moodle_exception;
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
    public function __construct() {
        $configselectedstorage = get_config('tool_coursemigration', 'storagetype');
        $thisclass = get_class($this);

        if ($configselectedstorage == $thisclass) {
            // Savetodirectory => Full path to the directory where you want to save the backup files.
            // Restorefromdirectory => Full path to the directory where the backup files are restored from.
            $this->settings = (object)[
                'savetodirectory' => rtrim(get_config('tool_coursemigration', 'saveto'), '/') . '/',
                'restorefromdirectory' => rtrim(get_config('tool_coursemigration', 'restorefrom'), '/') . '/'
            ];
        }
    }

    /** Name of storage type */
    const STORAGE_TYPE_NAME = 'Shared disk storage';

    /**
     * @var object The settings from this class.
     */
    protected $settings;

    /**
     * @var string Any error message from exception.
     */
    protected $errormessage;

    /**
     * @return object Get the save to and restore from directories.
     */
    public function get_settings($property = null) {
        if ($property) {
            if (property_exists($this->settings, $property)) {
                return $this->settings->$property;
            } else {
                return null;
            }
        } else {
            return $this->settings;
        }
    }

    /**
     * Download (pull) file.
     * @param $filename string Name of file to be restored.
     * @return \stored_file|null A file record object of the retrieved file.
     */
    public function pull_file($filename, $contextid) {
        try {
            $sourcefullpath = self::get_settings('restorefromdirectory') . $filename;
            $fs = get_file_storage();
            $filerecord = array('contextid' => $contextid, 'component' => 'course', 'filearea' => 'backup',
                'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
                'timecreated' => time(), 'timemodified' => time());
            // Delete existing file (if any) and create new one.
            $this::delete_existing_file_record($fs, $filerecord);
            return $fs->create_file_from_pathname($filerecord, $sourcefullpath);
        } catch (moodle_exception $e) {
            $this->errormessage = $e->getMessage();
            return null;
        }
    }

    /**
     * Upload (push) file.
     * @param $filename string Name of file to be backed up.
     * @param $filerecord \stored_file A file record object of the fle to be backed up.
     * @return boolean true if successfully cretaed.
     */
    public function push_file($filename, $filerecord): ?bool
    {
        try {
            $destinationfullpath = self::get_settings('savetodirectory') . $filename;
            return $filerecord->copy_content_to($destinationfullpath);
        } catch (moodle_exception $e) {
            $this->errormessage = $e->getMessage();
            return null;
        }
    }

    /**
     * Any error message from exception.
     * @return string error message.
     */
    public function get_error() {
        return $this->errormessage;
    }

    /**
     * Wrapper function useful for deleting an existing file (if present) just
     * before creating a new one.
     *
     * @param file_storage $fs File storage
     * @param array $filerecord File record in same format used to create file
     */
    public static function delete_existing_file_record(\file_storage $fs, array $filerecord) {
        if ($existing = $fs->get_file($filerecord['contextid'], $filerecord['component'],
            $filerecord['filearea'], $filerecord['itemid'], $filerecord['filepath'],
            $filerecord['filename'])) {
            $existing->delete();
        }
    }
}
