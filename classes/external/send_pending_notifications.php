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

namespace mod_confprogram\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_confprogram\api;

/**
 * AJAX-only external function backing the "Send pending notifications" button on
 * view.php's organiser controls (amd/src/notifications_button.js). 2026-07-09:
 * previously decision notifications were auto-sent on the Review -> Display phase
 * toggle; that automatic call was removed in favour of this explicit action,
 * mirroring mod_confscheduler's own send_pending_notifications external function.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_pending_notifications extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The confprogram course-module id'),
        ]);
    }

    /**
     * Sends every pending decision notification for this confprogram instance.
     *
     * @param int $cmid The confprogram course-module id
     * @return array{sent: int}
     */
    public static function execute(int $cmid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id('confprogram', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/confprogram:managenotifications', $context);

        $confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

        return ['sent' => api::send_pending_decision_notifications((int) $confprogram->id)];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sent' => new external_value(PARAM_INT, 'How many decisions were notified'),
        ]);
    }
}
