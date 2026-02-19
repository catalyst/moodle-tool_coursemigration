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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use admin_settingpage;
use Aws\Exception\MultipartUploadException;
use Aws\S3\ObjectUploader;
use Aws\S3\S3Client;
use context_system;
use Exception;
use stored_file;
use tool_coursemigration\helper;
use tool_coursemigration\local\settings\s3_storage_acl_settings_configselect;
use tool_coursemigration\local\settings\s3_storage_region_settings_configselect;
use tool_coursemigration\local\storage\storage_interface;

/**
 * Class to handle Amazon S3 file storage functions.
 *
 * @package    tool_coursemigration
 * @author     Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  2025 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class s3_storage implements storage_interface {
    /**
     * @var S3Client|null AWS S3 client instance.
     */
    protected $client = null;

    /**
     * @var string S3 bucket name.
     */
    protected $bucket;

    /**
     * @var string AWS region.
     */
    protected $region;

    /**
     * @var string Bucket ACL permissions.
     */
    protected $bucketacl;

    /**
     * @var string Key prefix for S3 objects.
     */
    protected $keyprefix;

    /**
     * @var string Any error message from exception.
     */
    protected $errormessage = '';

    /**
     * Construct the S3 storage.
     */
    public function __construct() {
        if (helper::is_selected_storage($this)) {
            // Initialise S3 settings.
            $this->bucket = get_config('tool_coursemigration', 'awss3_bucket');
            $this->region = get_config('tool_coursemigration', 'awss3_s3region');
            $this->bucketacl = get_config('tool_coursemigration', 'awss3_bucket_acl') ?: 'private';
            $this->keyprefix = get_config('tool_coursemigration', 'awss3_key_prefix') ?: '';

            // Initialize S3 client.
            $this->set_client();
        }
    }

    /**
     * Sets AWS S3 client.
     */
    private function set_client(): void {
        if (!$this->is_configured()) {
            $this->client = null;
        } else {
            $settings = [
                'region' => $this->region,
                'version' => 'latest',
            ];

            $usesdkcreds = get_config('tool_coursemigration', 'awss3_usesdkcreds');
            if (!$usesdkcreds) {
                $keyid = get_config('tool_coursemigration', 'awss3_keyid');
                $secretkey = get_config('tool_coursemigration', 'awss3_secretkey');
                $settings['credentials'] = ['key' => $keyid, 'secret' => $secretkey];
            }

            try {
                $this->client = new S3Client($settings);
            } catch (Exception $e) {
                $this->errormessage = $e->getMessage();
                $this->client = null;
            }
        }
    }

    /**
     * Check if the client is configured properly.
     *
     * @return bool
     */
    private function is_configured(): bool {
        if (empty($this->bucket) || empty($this->region)) {
            return false;
        }

        $usesdkcreds = get_config('tool_coursemigration', 'awss3_usesdkcreds');
        if (!$usesdkcreds) {
            $keyid = get_config('tool_coursemigration', 'awss3_keyid');
            $secretkey = get_config('tool_coursemigration', 'awss3_secretkey');
            if (empty($keyid) || empty($secretkey)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the client is functional.
     *
     * @return bool
     */
    private function is_functional(): bool {
        // If the client is set, it's functional.
        if (isset($this->client)) {
            return true;
        }

        // If there's already a specific error recorded (for example from set_client), keep it.
        if (empty($this->errormessage)) {
            // Provide a generic error message so callers can report why the client is not functional.
            $this->errormessage = 'S3 client is not configured properly.';
        }

        return false;
    }

    /**
     * Download (pull) file from S3.
     *
     * @param string $filename Name of file to be restored.
     * @return stored_file|null A file record object of the retrieved file.
     */
    public function pull_file(string $filename): ?stored_file {
        if (!$this->is_functional()) {
            return null;
        }

        try {
            $context = context_system::instance();
            $fs = get_file_storage();
            $filerecord = [
                'contextid' => $context->id,
                'component' => 'tool_coursemigration',
                'filearea' => 'backup',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename,
                'timecreated' => time(),
                'timemodified' => time(),
            ];

            // Delete existing file (if any).
            helper::delete_existing_file_record($fs, $filerecord);

            // Download from S3 to a temporary file.
            $tempfile = make_temp_directory('tool_coursemigration') . '/' . $filename;
            $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->keyprefix . $filename,
                'SaveAs' => $tempfile,
            ]);

            // Create file record from temporary file.
            $storedfile = $fs->create_file_from_pathname($filerecord, $tempfile);

            // Clean up temporary file.
            if (file_exists($tempfile)) {
                unlink($tempfile);
            }

            return $storedfile;
        } catch (Exception $e) {
            $this->errormessage = $e->getMessage();
            return null;
        } finally {
            // Ensure temporary file is cleaned up in case of any exception.
            if (isset($tempfile) && file_exists($tempfile)) {
                unlink($tempfile);
            }
        }
    }

    /**
     * Upload (push) file to S3.
     *
     * @param string $filename Name of file to be backed up.
     * @param stored_file $filerecord A file record object of the file to be backed up.
     * @return boolean true if successfully created.
     */
    public function push_file(string $filename, stored_file $filerecord): bool {
        if (!$this->is_functional()) {
            return false;
        }

        // Open file handle directly from stored_file.
        $filehandle = $filerecord->get_content_file_handle();

        if (!$filehandle) {
            $this->errormessage = 'Can not open the file for reading: ' . $filename;
            return false;
        }

        try {
            // Upload to S3 using ObjectUploader for better handling of large files.
            $uploader = new ObjectUploader(
                $this->client,
                $this->bucket,
                $this->keyprefix . $filename,
                $filehandle,
                $this->bucketacl,
                [
                    'params' => [
                        'ContentType' => 'application/octet-stream',
                    ],
                ]
            );
            $uploader->upload();
            $success = true;
        } catch (MultipartUploadException $e) {
            $params = $e->getState()->getId();
            $this->client->abortMultipartUpload($params);
            $this->errormessage = $e->getMessage();
            $success = false;
        } catch (Exception $e) {
            $this->errormessage = $e->getMessage();
            $success = false;
        } finally {
            // Ensure file handle is closed in case of any exception.
            if (is_resource($filehandle)) {
                fclose($filehandle);
            }
        }

        return $success;
    }

    /**
     * Delete file from S3.
     *
     * @param string $filename Name of file to be deleted.
     * @return boolean true if successfully deleted.
     */
    public function delete_file(string $filename): bool {
        if (!$this->is_functional()) {
            return false;
        }

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $this->keyprefix . $filename,
            ]);
            return true;
        } catch (Exception $e) {
            $this->errormessage = $e->getMessage();
            return false;
        }
    }

    /**
     * Check if the file exists in S3.
     *
     * @param string $filename Name of file.
     * @return boolean true if file exists.
     */
    public function file_exists(string $filename): bool {
        if (!$this->is_functional()) {
            return false;
        }

        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $this->keyprefix . $filename,
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Any error message from exception.
     *
     * @return string error message.
     */
    public function get_error(): string {
        return $this->errormessage;
    }

    /**
     * Clear error message from exception.
     */
    public function clear_error(): void {
        $this->errormessage = '';
    }

    /**
     * Verifies that storage is configured for restore.
     *
     * @return boolean true if configuration is valid.
     */
    public function ready_for_pull(): bool {
        if (!$this->is_configured() || !$this->is_functional()) {
            return false;
        }

        // Test connection by checking if bucket exists.
        try {
            $this->client->headBucket(['Bucket' => $this->bucket]);
            return true;
        } catch (Exception $e) {
            $this->errormessage = $e->getMessage();
            return false;
        }
    }

    /**
     * Verifies that storage is configured for backup.
     *
     * @return boolean true if configuration is valid.
     */
    public function ready_for_push(): bool {
        return $this->ready_for_pull();
    }

    /**
     * Define storage-specific settings section.
     *
     * @param \admin_settingpage $settings The settings page object
     * @return \admin_settingpage Modified settings page
     */
    public function define_settings(admin_settingpage $settings): admin_settingpage {
        // Read S3-specific configuration.
        $usesdkcreds = get_config('tool_coursemigration', 'awss3_usesdkcreds');

        // Add AWS S3 settings header with connection check.
        $settings->add(new \admin_setting_heading(
            'tool_coursemigration/awss3',
            get_string('settings:awss3', 'tool_coursemigration'),
            $this->define_storage_check()
        ));

        $settings->add(new \admin_setting_configcheckbox(
            'tool_coursemigration/awss3_usesdkcreds',
            get_string('settings:awss3_usesdkcreds', 'tool_coursemigration'),
            get_string('settings:awss3_usesdkcredsdesc', 'tool_coursemigration'),
            0
        ));

        // Only show key and secret if not using SDK credentials.
        if (empty($usesdkcreds)) {
            $settings->add(new \admin_setting_configtext(
                'tool_coursemigration/awss3_keyid',
                get_string('settings:awss3_keyid', 'tool_coursemigration'),
                get_string('settings:awss3_keyiddesc', 'tool_coursemigration'),
                '',
                PARAM_TEXT
            ));

            $settings->add(new \admin_setting_configpasswordunmask(
                'tool_coursemigration/awss3_secretkey',
                get_string('settings:awss3_secretkey', 'tool_coursemigration'),
                get_string('settings:awss3_secretkeydesc', 'tool_coursemigration'),
                ''
            ));
        }

        $settings->add(new \admin_setting_configtext(
            'tool_coursemigration/awss3_bucket',
            get_string('settings:awss3_bucket', 'tool_coursemigration'),
            get_string('settings:awss3_bucketdesc', 'tool_coursemigration'),
            '',
            PARAM_TEXT
        ));

        $settings->add(new s3_storage_acl_settings_configselect(
            'tool_coursemigration/awss3_bucket_acl',
            get_string('settings:awss3_bucket_acl', 'tool_coursemigration'),
            get_string('settings:awss3_bucket_acldesc', 'tool_coursemigration'),
            'private'
        ));

        $settings->add(new s3_storage_region_settings_configselect(
            'tool_coursemigration/awss3_s3region',
            get_string('settings:awss3_s3region', 'tool_coursemigration'),
            get_string('settings:awss3_s3regiondesc', 'tool_coursemigration'),
            'ap-southeast-2'
        ));

        $settings->add(new \admin_setting_configtext(
            'tool_coursemigration/awss3_key_prefix',
            get_string('settings:awss3_key_prefix', 'tool_coursemigration'),
            get_string('settings:awss3_key_prefixdesc', 'tool_coursemigration'),
            '',
            PARAM_TEXT
        ));

        return $settings;
    }

    /**
     * Display connection and permission check status.
     *
     * @return string HTML notification output
     */
    private function define_storage_check(): string {
        global $OUTPUT;
        $output = '';

        if (!helper::is_coursemigration_settings_page()) {
            // Only check on course migration settings page.
            return $output;
        }

        if ($this->is_configured()) {
            // Test connection for pull (restore).
            if ($this->ready_for_pull()) {
                $output .= $OUTPUT->notification(
                    get_string('settings:awss3_connectionpullsuccess', 'tool_coursemigration'),
                    'notifysuccess'
                );
            } else {
                $output .= $OUTPUT->notification(
                    get_string('settings:awss3_connectionpullfailure', 'tool_coursemigration') .
                    (!empty($this->errormessage) ? '<br>' . $this->errormessage : ''),
                    'notifyproblem'
                );
            }

            // Clear error before testing push.
            $this->clear_error();

            // Test connection for push (backup).
            if ($this->ready_for_push()) {
                $output .= $OUTPUT->notification(
                    get_string('settings:awss3_connectionpushsuccess', 'tool_coursemigration'),
                    'notifysuccess'
                );
            } else {
                $output .= $OUTPUT->notification(
                    get_string('settings:awss3_connectionpushfailure', 'tool_coursemigration') .
                    (!empty($this->errormessage) ? '<br>' . $this->errormessage : ''),
                    'notifyproblem'
                );
            }
        } else {
            $output .= $OUTPUT->notification(
                get_string('settings:awss3_notconfigured', 'tool_coursemigration'),
                'notifywarning'
            );
        }

        return $output;
    }
}
