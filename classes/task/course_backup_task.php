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
 * Adhoc task that performs single automated course backup.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursemigration\task;
use tool_coursemigration\coursemigration;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/helper/backup_cron_helper.class.php');

/**
 * Adhoc task that performs single automated course backup.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_backup_task extends \core\task\adhoc_task {

    /**
     * Run the adhoc task and preform the backup.
     */
    public function execute() {
        global $DB;

        $lockfactory = \core\lock\lock_config::get_lock_factory('course_backup_adhoc');
        $courseid = $this->get_custom_data()->courseid;
        $coursemigrationid = $this->get_custom_data()->coursemigrationid;
        $cmrecord = coursemigration::get_record(['id' => $coursemigrationid]);

        if (!$lock = $lockfactory->get_lock('course_backup_adhoc_task_' . $courseid, 10)) {
            $message = get_string('error:backupalreadyrunning', 'tool_coursemigration', [
                'coursemigrationid' => $cmrecord->get('id'),
                'courseid' => $courseid
            ]);
            $cmrecord->set('status', coursemigration::STATUS_FAILED);
            $cmrecord->set('error', $message);
            $cmrecord->save();
            mtrace($message);
            return;
        } else {
            mtrace('Processing automated backup for course: ' . $course->fullname);
        }

        try {
            $adminid = $this->get_custom_data()->adminid;
            \tool_coursemigration\task\course_backup_task::backupcourse;
        } catch (\moodle_exception $e) {

        } finally {
            // Everything is finished release lock.
            $lock->release();
            mtrace('Automated backup for course: ' . $course->fullname . ' completed.');
        }
    }

    /**
     * This script allows to do backup.
     * @param integer $courseid
     * @param string $destination
     */
    private static function backupcourse($courseid, $destination) {


        $admin = get_admin();
        if (!$admin) {
            mtrace("Error: No admin account was found");
            die;
        }

// Do we need to store backup somewhere else?
        $dir = rtrim($options['destination'], '/');
        if (!empty($dir)) {
            if (!file_exists($dir) || !is_dir($dir) || !is_writable($dir)) {
                mtrace("Destination directory does not exists or not writable.");
                die;
            }
        }

// Check that the course exists.
        if ($options['courseid']) {
            $course = $DB->get_record('course', array('id' => $options['courseid']), '*', MUST_EXIST);
        } else if ($options['courseshortname']) {
            $course = $DB->get_record('course', array('shortname' => $options['courseshortname']), '*', MUST_EXIST);
        }

        cli_heading('Performing backup...');
        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_YES, backup::MODE_GENERAL, $admin->id);
// Set the default filename.
        $format = $bc->get_format();
        $type = $bc->get_type();
        $id = $bc->get_id();
        $users = $bc->get_plan()->get_setting('users')->get_value();
        $anonymised = $bc->get_plan()->get_setting('anonymize')->get_value();
        $filename = backup_plan_dbops::get_default_backup_filename($format, $type, $id, $users, $anonymised);
        $bc->get_plan()->get_setting('filename')->set_value($filename);

// Execution.
        $bc->finish_ui();
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination']; // May be empty if file already moved to target location.

// Do we need to store backup somewhere else?
        if (!empty($dir)) {
            if ($file) {
                mtrace("Writing " . $dir . '/' . $filename);
                if ($file->copy_content_to($dir . '/' . $filename)) {
                    $file->delete();
                    mtrace("Backup completed.");
                } else {
                    mtrace("Destination directory does not exist or is not writable. Leaving the backup in the course backup file area.");
                }
            }
        } else {
            mtrace("Backup completed, the new file is listed in the backup area of the given course");
        }
        $bc->destroy();
        exit(0);
    }
}
