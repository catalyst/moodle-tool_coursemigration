<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     tool_coursemigration
 * @author      Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright   2023 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

use tool_coursemigration\form\report_filter_form;
use tool_coursemigration\output\coursemigration_table;

$download = optional_param('download', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$pagesize = optional_param('pagesize', 50, PARAM_INT);

$context = context_system::instance();

admin_externalpage_setup('tool_coursemigration_reports');

$url = new moodle_url('/admin/tool/coursemigration/reports.php', ['pagesize' => $pagesize]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

$output = $PAGE->get_renderer('tool_coursemigration');
$mform = new report_filter_form();
$filters = $mform->get_data() ?? new stdClass();

$table = new coursemigration_table($url, $filters, $page, $pagesize);
if ($table->is_downloading($download)) {
    \core\session\manager::write_close();
    echo $output->render($table);
    die();
}

$title = get_string('reports');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $output->header();
echo $output->heading($title);
echo $mform->render();
echo $output->render_coursemigration_table($table);
echo $output->footer();
