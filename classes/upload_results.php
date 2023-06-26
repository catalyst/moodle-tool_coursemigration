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

namespace tool_coursemigration;

/**
 * Class represents upload results.
 *
 * @package    tool_coursemigration
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_results {

    /**
     * Count of errors.
     * @var int
     */
    protected $errorcount = 0;

    /**
     * Results message.
     * @var string
     */
    protected $resultmessage = '';

    /**
     * Count of rows.
     * @var int
     */
    protected $rowcount = 0;

    /**
     * Count of successfully loaded rows.
     * @var int
     */
    protected $success = 0;

    /**
     * Count of failed rows.
     *
     * @var int
     */
    protected $failed = 0;


    /**
     * Set number of errors.
     * @param int $errorcount
     */
    public function set_errorcount(int $errorcount): void {
        $this->errorcount = $errorcount;
    }

    /**
     * Get number of errors.
     *
     * @return int
     */
    public function get_errorcount(): int {
        return $this->errorcount;
    }

    /**
     * Set result message.
     *
     * @param string $message
     */
    public function set_result_message(string $message): void {
        $this->resultmessage = $message;
    }

    /**
     * Get result messages.
     *
     * @return string
     */
    public function get_result_message(): string {
        return $this->resultmessage;
    }

    /**
     * Set rows count.
     *
     * @param int $rowcount
     */
    public function set_rowcount(int $rowcount): void {
        $this->rowcount = $rowcount;
    }

    /**
     * Get rows count.
     *
     * @return int
     */
    public function get_rowcount(): int {
        return $this->rowcount;
    }

    /**
     * Set a count of successfully loaded rows.
     *
     * @param int $success
     */
    public function set_success(int $success): void {
        $this->success = $success;
    }

    /**
     * get a count of successfully loaded rows.
     *
     * @return int
     */
    public function get_success(): int {
        return $this->success;
    }

    /**
     * Set a count of failed rows.
     *
     * @param int $failed
     */
    public function set_failed(int $failed): void {
        $this->failed = $failed;
    }

    /**
     * Get a count of failed rows.
     *
     * @return int
     */
    public function get_failed(): int {
        return $this->failed;
    }
}
