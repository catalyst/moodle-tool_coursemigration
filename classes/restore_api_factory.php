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
 * Class for getting restore API instance.
 *
 * @package    tool_coursemigration
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_api_factory {

    /**
     * Instance of restore API.
     * @var \tool_coursemigration\restore_api
     */
    protected static $restoreapi = null;

    /**
     * Returns built restore APi instance.
     *
     * @return \tool_coursemigration\restore_api
     */
    public static function get_restore_api(): restore_api {
        if (empty(self::$restoreapi)) {
            self::$restoreapi = new restore_api();
        }

        return self::$restoreapi;
    }

    /**
     * Sets restore API instance.
     *
     * @param \tool_coursemigration\restore_api $restoreapi Instance to set.
     */
    public static function set_restore_api(restore_api $restoreapi) {
        self::$restoreapi = $restoreapi;
    }

    /**
     * Reset restore API instance.
     */
    public static function reset_restore_api() {
        self::$restoreapi = null;
    }
}
