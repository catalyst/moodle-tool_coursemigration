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
use tool_coursemigration\coursemigration;
use renderable;

/**
 * Renderable table for coursemigration.
 *
 * @author      Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright   2023 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursemigration_table extends table_sql implements renderable {

    /**
     * Sets up the table.
     *
     * @param moodle_url $url Url where this table is displayed.
     * @param int $pagesize Number of bento boxes to display per page. 0 means display all.
     */
    public function __construct(moodle_url $url, int $pagesize = 0) {
        parent::__construct('coursemigration_table');

        $this->define_columns([
            'id',
            'action',
            'course',
            'destinationcategory',
            'status',
            'filename',
            'error',
            'usermodified',
            'timecreated',
            'timemodified',
        ]);

        $this->define_headers([
            get_string('idnumber'),
            get_string('action'),
            get_string('course'),
            get_string('destinationcategory', 'tool_coursemigration'),
            get_string('status'),
            get_string('filename', 'tool_coursemigration'),
            get_string('error'),
            get_string('user'),
            get_string('timecreated', 'tool_coursemigration'),
            get_string('timemodified', 'tool_coursemigration'),
        ]);

        $this->collapsible(false);
        $this->sortable(false);

        $this->pageable(!empty($pagesize));
        $this->pagesize = $pagesize;
        $this->is_downloadable(true);
        $this->define_baseurl($url);
    }

    /**
     * Query the reader. Store results in the object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar
     */
    public function query_db($pagesize, $useinitialsbar = false) {
        if (!empty($pagesize)) {
            $total = coursemigration::count_records();
            $this->pagesize($pagesize, $total);

            $records = coursemigration::get_records([], 'timecreated', 'DESC',
                $this->get_page_start(), $this->get_page_size());

            if ($useinitialsbar) {
                $this->initialbars($total > $pagesize);
            }
        } else {
            $records = coursemigration::get_records([], 'timecreated', 'DESC');
        }

        $this->rawdata = $records;
    }

    /**
     * ID column.
     *
     * @param coursemigration $row
     * @return string
     */
    public function col_id(coursemigration $row): string {
        return $row->get('id');
    }

    /**
     * Action column.
     *
     * @param coursemigration $row
     * @return string
     */
    public function col_action(coursemigration $row): string {
        return $row->get('action');
    }

    /**
     * Course column.
     *
     * @param coursemigration $row
     * @return string
     */
    public function col_course(coursemigration $row): string {
        return $row->get('courseid');
    }

    /**
     * Destination category column.
     *
     * @param coursemigration $row
     * @return string
     */
    public function col_destinationcategory(coursemigration $row): string {
        return $row->get('destinationcategoryid');
    }

    /**
     * Status column.
     *
     * @param coursemigration $row
     * @return string
     */
    public function col_status(coursemigration $row): string {
        return $row->get('status');
    }

    /**
     * Filename column.
     *
     * @param coursemigration $row
     * @return string
     */
    public function col_filename(coursemigration $row): string {
        return $row->get('filename');
    }

    /**
     * Error column.
     *
     * @param coursemigration $row
     * @return string
     */
    public function col_error(coursemigration $row): string {
        return $row->get('error') ?? '';
    }

    /**
     * User modified column.
     *
     * @param coursemigration $row
     * @return string
     */
    public function col_usermodified(coursemigration $row): string {
        return $row->get('usermodified');
    }

    /**
     * Time created column.
     *
     * @param coursemigration $row
     * @return string
     */
    public function col_timecreated(coursemigration $row): string {
        return userdate($row->get('timecreated'));
    }

    /**
     * Time modified column.
     *
     * @param coursemigration $row
     * @return string
     */
    public function col_timemodified(coursemigration $row): string {
        return userdate($row->get('timemodified'));
    }

}
