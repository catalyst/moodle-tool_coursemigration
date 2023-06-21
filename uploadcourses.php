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
 * Settings page to upload courses in csv to restore.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

use tool_coursemigration\uploadcourselist;

admin_externalpage_setup('coursemigrationupload', '', null);

$PAGE->set_heading($SITE->fullname);

$PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'tool_coursemigration'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursemigrationupload', 'tool_coursemigration'));

list($status, $message) = uploadcourselist::display_upload_courses_form();

if (!$status) {
    echo $OUTPUT->notification($message);
}

// Add AJAX feedback
$PAGE->requires->js_call_amd('tool_coursemigration/uploadcourselist', 'init');

echo $OUTPUT->footer();
