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

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * The upload course list test class.
 *
 * @package    tool_coursemigration
 * @author     Glenn Poder <glennpoder@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_coursemigration\uploadcourselist
 */
class upload_course_list_test extends advanced_testcase {
    /**
     * Test test_csv_content
     *
     * @param array $input The mock CSV content
     * @param string $expected The expected resultant messages
     * @param array $dbrecords The db records that should be created
     * @dataProvider csv_content_provider
     */
    public function test_csv_content($input, $expected, $dbrecords) {
        global $DB;
        $this->resetAfterTest(true);

        $content = $input;
        $content = implode("\n", $content);
        $importid = csv_import_reader::get_new_iid('uploadcourse');
        $csvimportreader = new csv_import_reader($importid, 'uploadcourse');
        $csvimportreader->load_csv_content($content, 'utf-8', 'comma');
        $csvimportreader->init();

        foreach ($dbrecords as $record) {
            $this->assertFalse($DB->record_exists('tool_coursemigration', [
                'courseid' => $record[0],
                'destinationcategoryid' => $record[1],
            ]));
        }

        $messages = tool_coursemigration\uploadcourselist::process_submitted_form($csvimportreader);

        foreach ($dbrecords as $record) {
            $this->assertTrue($DB->record_exists('tool_coursemigration', [
                'courseid' => $record[0],
                'destinationcategoryid' => $record[1],
            ]));
        }

        $this->assertEquals($expected, $messages);

    }

    /**
     * Dataprovider for csv_content
     * @return array Data for csv_content
     */
    public function csv_content_provider() {
        return [
            "One row, valid courseid and category" => [
                'input' => ["courseid,categoryid",
                    "2,1"],
                'expected' => "Errors in CSV file: 0<br\>\n<br\><br\>\nTotal rows: 1<br\>\nSuccess: 1<br\>\nFailed: 0<br\>",
                'dbrecords' => [[2,1],],
            ],
            "One row, valid url and category" => [
                'input' => ["url,categoryid",
                    "https://test39.localhost/course/view.php?id=2,1"],
                'expected' => "Errors in CSV file: 0<br\>\n<br\><br\>\nTotal rows: 1<br\>\nSuccess: 1<br\>\nFailed: 0<br\>",
                'dbrecords' => [[2,1],],
            ],
            "Four rows, one valid and three errors" => [
                'input' => ["courseid,categoryid",
                    "2,1",
                    "a,2",
                    "3,abc",
                    "4.5,6"],
                'expected' => "Errors in CSV file: 3<br\>\nNon integer value for courseid found on row 2<br\>Non integer value" .
                    " for categoryid found on row 3<br\>Non integer value for courseid found on row 4<br\><br\>\nTotal rows:" .
                    " 4<br\>\nSuccess: 1<br\>\nFailed: 3<br\>",
                'dbrecords' => [[2,1],],
            ],
            "Invalid columns" => [
                'input' => ["invalid,invalid",
                    "2,1"],
                'expected' => "CSV file must include one of courseid, url as column headings AND CSV file must include one of" .
                    " categoryid, categoryid_number, categorypath as column headings",
                'dbrecords' => [],
            ],
        ];
    }
}
