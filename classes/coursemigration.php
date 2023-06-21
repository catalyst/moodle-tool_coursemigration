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

namespace tool_coursemigration;

use core\persistent;

/**
 * coursemigration persistent class.
 *
 * @package    tool_coursemigration
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursemigration extends persistent {

    /**
     * Table name for course migration.
     */
    const TABLE = 'tool_coursemigration';

    /**
     * Status not started.
     */
    const STATUS_NOT_STARTED = 1;

    /**
     * Status in progress.
     */
    const STATUS_IN_PROGRESS = 2;

    /**
     * Status completed.
     */
    const STATUS_COMPLETED = 3;

    /**
     * Status failed.
     */
    const STATUS_FAILED = 0;

    /**
     * Action backup.
     */
    const ACTION_BACKUP = 0;

    /**
     * Action restore.
     */
    const ACTION_RESTORE = 1;

    /**
     * A list of all statuses.
     */
    const STATUSES = [self::STATUS_NOT_STARTED, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_FAILED];

    /**
     * A list of all actions.
     */
    const ACTIONS = [self::ACTION_BACKUP, self::ACTION_RESTORE];

    /**
     * Return the definition of the properties.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'action' => [
                'type' => PARAM_INT,
                'default' => self::ACTION_BACKUP,
                'choices' => self::ACTIONS,
            ],
            'courseid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'destinationcategoryid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'status' => [
                'type' => PARAM_INT,
                'default' => self::STATUS_NOT_STARTED,
                'choices' => self::STATUSES,
            ],
            'filename' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'error' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }
}
