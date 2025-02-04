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

namespace tool_coursemigration\output;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/tablelib.php');

use moodle_url;
use table_sql;
use html_writer;
use stdClass;
use tool_coursemigration\coursemigration;
use tool_coursemigration\helper;
use renderable;
use core_course_category;

/**
 * Renderable table for coursemigration.
 *
 * @package     tool_coursemigration
 * @author      Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright   2023 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursemigration_table extends table_sql implements renderable {

    /**
     * A list of filters to be applied to the sql query.
     * @var stdClass
     */
    protected $filters;

    /**
     * Sets up the table.
     *
     * @param moodle_url $url Url where this table is displayed.
     * @param stdClass $filters
     * @param int $page A current page.
     * @param int $pagesize Number of bento boxes to display per page. 0 means display all.
     */
    public function __construct(moodle_url $url, stdClass $filters, int $page = 0, int $pagesize = 0) {
        parent::__construct('coursemigration_table');

        $this->define_columns([
            'id',
            'action',
            'course',
            'destinationcategory',
            'status',
            'filename',
            'timecreated',
            'timemodified',
            'error',
        ]);

        $this->define_headers([
            get_string('migrationid', 'tool_coursemigration'),
            get_string('action'),
            get_string('course'),
            get_string('destinationcategory', 'tool_coursemigration'),
            get_string('status'),
            get_string('filename', 'tool_coursemigration'),
            get_string('timecreated', 'tool_coursemigration'),
            get_string('timemodified', 'tool_coursemigration'),
            get_string('error'),
        ]);

        $this->collapsible(false);

        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->no_sorting('id');
        $this->no_sorting('action');
        $this->no_sorting('course');
        $this->no_sorting('destinationcategory');
        $this->no_sorting('status');
        $this->no_sorting('filename');
        $this->no_sorting('error');

        $this->pageable(!empty($pagesize));
        $this->pagesize = $pagesize;
        $this->is_downloadable(true);
        $this->show_download_buttons_at([TABLE_P_BOTTOM]);
        $this->define_baseurl($url);
        $this->filters = $filters;
    }

    /**
     * Query the reader. Store results in the object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar
     */
    public function query_db($pagesize, $useinitialsbar = false) {
        global $DB;
        $sql = 'SELECT tc.*,
                       c.fullname coursename, cc.name coursecategoryname
                  FROM {tool_coursemigration} tc
             LEFT JOIN {course} c ON tc.courseid = c.id
             LEFT JOIN {course_categories} cc ON tc.destinationcategoryid = cc.id';
        $where = [];
        $params = [];
        foreach ($this->filters as $field => $value) {
            switch ($field) {
                case 'action':
                case 'status':
                    if ($value >= 0) {
                        $where[] = 'tc.' . $field . ' = :' .$field;
                    }
                    break;
                case 'datefrom':
                    if ($value > 0) {
                        $where[] = 'tc.timecreated >= :' .$field;
                    }
                    break;
                case 'datetill':
                    if ($value > 0) {
                        $where[] = 'tc.timecreated <= :' . $field;
                    }
                    break;
                default:
                    break;
            }
            $params[$field] = $value;
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY ' . $this->get_sql_sort();

        if ($this->is_downloading()) {
            $pagesize = 0;
        }

        if (!empty($pagesize)) {
            $countsql = 'SELECT COUNT(*) FROM {tool_coursemigration} tc';
            if (!empty($where)) {
                $countsql .= ' WHERE ' . implode(' AND ', $where);
            }
            $total = $DB->count_records_sql($countsql, $params);
            $this->pagesize($pagesize, $total);

            $records = $DB->get_records_sql($sql, $params, $this->get_page_start(), $this->get_page_size());

            if ($useinitialsbar) {
                $this->initialbars($total > $pagesize);
            }
        } else {
            $records = $DB->get_records_sql($sql, $params);
        }

        $this->rawdata = $records;
    }

    /**
     * Action column.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_id(stdClass $row): string {
        return $row->id;
    }

    /**
     * Action column.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_action(stdClass $row): string {
        return helper::get_action_string($row->action);
    }

    /**
     * Course column.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_course(stdClass $row): string {
        if (!empty($row->coursename)) {
            $coursename = $row->coursename;
            if (!$this->is_downloading()) {
                $url = new moodle_url('/course/view.php', ['id' => $row->courseid]);
                $coursename = html_writer::link($url, $coursename);
            }
        } else if (!empty($row->courseid)) {
            $coursename = get_string('coursedeleted', 'tool_coursemigration', $row->courseid);
        } else {
            $coursename = '';
        }

        return $coursename;
    }

    /**
     * Destination category column.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_destinationcategory(stdClass $row): string {
        $includelinks = !$this->is_downloading();

        switch ($row->action) {
            case coursemigration::ACTION_BACKUP:
                $categoryname = $row->destinationcategoryid ?? '';
                break;
            case coursemigration::ACTION_RESTORE:
                $category = core_course_category::get($row->destinationcategoryid, IGNORE_MISSING);
                if ($category) {
                    $categoryname = $category->get_nested_name($includelinks);
                } else {
                    $categoryname = get_string('categorydeleted', 'tool_coursemigration', $row->destinationcategoryid);
                }
                break;
            default:
                $categoryname = '';
                break;
        }

        return $categoryname;
    }

    /**
     * Status column.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_status(stdClass $row): string {
        return helper::get_status_string($row->status);
    }

    /**
     * Filename column.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_filename(stdClass $row): string {
        return $row->filename ?? '';
    }

    /**
     * Error column.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_error(stdClass $row): string {
        $error = $row->error ?? '';

        if (!empty($error)) {
            $errors = explode(coursemigration::ERROR_DELIMITER, $error);
            $error = implode(html_writer::tag('br', ''), $errors);
        }

        return $error;
    }

    /**
     * Time created column.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_timecreated(stdClass $row): string {
        return userdate($row->timecreated);
    }

    /**
     * Time modified column.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_timemodified(stdClass $row): string {
        return userdate($row->timemodified);
    }
}

