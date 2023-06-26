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

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

$download = optional_param('download', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$pagesize = optional_param('pagesize', 50, PARAM_INT);
$action = optional_param('action', -1, PARAM_INT);
$status = optional_param('status', -1, PARAM_INT);

$mform = new report_filter_form();
$filters = $mform->get_data();
$datefrom = $filters->datefrom ?? 0;
if (empty($datefrom)) {
    $datefrom = optional_param('datefrom', 0, PARAM_INT);
}
$datetill = $filters->datetill ?? 0;
if (empty($datetill)) {
    $datetill = optional_param('datetill', 0, PARAM_INT);
}
if (empty($filters)) {
    $filters = new stdClass();
    $filters->action = $action;
    if ($datefrom) {
        $filters->datefrom = $datefrom;
    }
    if ($datetill) {
        $filters->datetill = $datetill;
    }
    $filters->status = $status;
    $mform->set_data($filters);
}

admin_externalpage_setup('tool_coursemigration_reports');

$url = new moodle_url('/admin/tool/coursemigration/reports.php');
$url->param('page', $page);
$url->param('pagesize', $pagesize);
$url->param('action', $action);
if ($datefrom) {
    $url->param('datefrom', $datefrom);
}
if ($datetill) {
    $url->param('datetill', $datetill);
}
$url->param('status', $status);
$PAGE->set_url($url);

$output = $PAGE->get_renderer('tool_coursemigration');

$table = new coursemigration_table($url, $filters, $page, $pagesize);
if ($table->is_downloading($download, 'coursemigration_report')) {
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
