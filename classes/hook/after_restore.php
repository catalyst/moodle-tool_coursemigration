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

namespace tool_coursemigration\hook;

use restore_controller;
use tool_coursemigration\coursemigration;

/**
 * Allows plugins to perform actions after a course migration restore completes.
 *
 * @package     tool_coursemigration
 * @copyright   2026 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class after_restore {
    /**
     * @param restore_controller $controller The restore controller.
     * @param coursemigration $coursemigration The course migration record.
     */
    public function __construct(
        public readonly restore_controller $controller,
        public readonly coursemigration $coursemigration,
    ) {
    }
}