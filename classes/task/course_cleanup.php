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

namespace tool_coursemigration\task;

use core\task\adhoc_task;
use Exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Adhoc task to clean up a course after failing restore.
 *
 * @package    tool_coursemigration
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_cleanup extends adhoc_task {

    /**
     * Run the task.
     */
    public function execute() {
        global $CFG, $DB;

        $data = $this->get_custom_data();
        $courseid = !empty($data->courseid) ? $data->courseid : 0;

        if (empty($courseid)) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid]);

        if (empty($course)) {
            return;
        }

        // Disable recycle bin as we really want to clean up everything.
        $CFG->forced_plugin_settings['tool_recyclebin']['coursebinenable'] = false;
        $CFG->forced_plugin_settings['tool_recyclebin']['categorybinenable'] = false;

        if ($this->get_fail_delay() > 0) {
            // Because a deletion of that course failed before, we are not going to delete whole course again.
            // Instead, we will delete activities one by one first and then attempt to delete a course again.
            $exceptionsthrown = [];
            $coursemodules = $DB->get_records('course_modules', ['course' => $courseid]);

            foreach ($coursemodules as $cm) {
                $attempt = 0;
                $maxattampts = 2;

                // Try deleting few times as it helps in some cases (e.g. when activity not in a section->sequence).
                do {
                    try {
                        $exception = false;
                        course_delete_module(intval($cm->id));
                        break;
                    } catch (Exception $exception) {
                        $attempt++;
                        continue;
                    }
                } while ($attempt < $maxattampts);

                // We tried to delete two times, but failed.
                // Log it, save it. we will throw it once finished with all activities.
                if (!empty($exception)) {
                    mtrace($exception->getMessage());
                    $exceptionsthrown[] = $exception;
                }
            }

            if (!empty($exceptionsthrown)) {
                // Fail the task by throwing the first exception and let it rerun later and try again.
                throw $exceptionsthrown[0];
            } else {
                // Finally all activities deleted we can delete a course.
                delete_course($courseid, false);
            }
        } else {
            // Running first time. Let's attempt to delete whole course.
            delete_course($courseid, false);
        }
    }
}
