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
 * A form for filtering Callista Access Start and End Dates report.
 *
 * @package     tool_coursemigration
 * @author      Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright   2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursemigration\form;

use moodleform;
use tool_coursemigration\coursemigration;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');

class report_filter_form extends moodleform {

    /**
     * Definition of the Mform for filters displayed in the report.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'filters', get_string('filters', 'tool_coursemigration'));

        $actionlist = [-1 => ''] + coursemigration::get_action_list();
        $mform->addElement('select', 'action', get_string('action'), $actionlist);
        $mform->setType('action', PARAM_INT);
        $mform->setDefault('action', -1);

        $mform->addElement('date_time_selector', 'datefrom', get_string('from'), array('optional' => true));
        $mform->addElement('date_time_selector', 'datetill', get_string('to'), array('optional' => true));

        $statuslist = [-1 => ''] + coursemigration::get_status_list();
        $mform->addElement('select', 'status', get_string('status'), $statuslist);
        $mform->setType('status', PARAM_INT);
        $mform->setDefault('status', -1);

        $mform->addElement('submit', 'submitbutton', get_string('filter'));
    }

}
