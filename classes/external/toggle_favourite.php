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
use mod_confprogram\local\rounds;
use mod_confsubmissions\api as submissions_api;

/**
 * AJAX-only external function backing the favourite-star toggle on the Display-phase
 * accepted-submissions list (amd/src/programlist.js).
 *
 * Requires mod/confprogram:favourite (and, via require_login() inside
 * validate_context(), an actual logged-in session -- not merely guest access, since
 * favouriting is inherently per-user state). Like get_submission_detail, scopes the
 * given submissionid to both this confprogram's mod_confsubmissions instance and to
 * currently-accepted submissions only, so a caller cannot favourite (and thereby
 * probe the existence/decision state of) an arbitrary submissionid.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_favourite extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The confprogram course-module id'),
            'submissionid' => new external_value(PARAM_INT, 'The confsubmissions_submission id'),
            'favourited' => new external_value(PARAM_BOOL, 'The desired favourited state'),
        ]);
    }

    /**
     * Sets (or unsets) the current user's favourite of a submission.
     *
     * @param int $cmid The confprogram course-module id
     * @param int $submissionid The confsubmissions_submission id
     * @param bool $favourited The desired favourited state
     * @return array{favourited: bool}
     */
    public static function execute(int $cmid, int $submissionid, bool $favourited): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'         => $cmid,
            'submissionid' => $submissionid,
            'favourited'   => $favourited,
        ]);

        $cm = get_coursemodule_from_id('confprogram', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/confprogram:favourite', $context);

        $confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

        // Same phase embargo as get_submission_detail.php: without this, a submission could be
        // favourited (and thereby have its accept-decision state probed) before the organiser
        // has switched to Display phase.
        if ($confprogram->phase !== 'display') {
            throw new \invalid_parameter_exception(get_string('error:submissionnotavailable', 'mod_confprogram'));
        }

        $confsubmissionscm = get_coursemodule_from_id(
            'confsubmissions',
            $confprogram->confsubmissionscmid,
            0,
            false,
            MUST_EXIST
        );

        $submission = submissions_api::get_submission($params['submissionid']);
        $belongstoinstance = $submission && (int) $submission->confsubmissions === (int) $confsubmissionscm->instance;

        $decision = $belongstoinstance
            ? rounds::get_latest_decision((int) $confprogram->id, (int) $submission->id)
            : null;
        $isaccepted = $decision !== null && $decision->decision === 'accept';

        if (!$belongstoinstance || !$isaccepted) {
            throw new \invalid_parameter_exception(get_string('error:submissionnotavailable', 'mod_confprogram'));
        }

        if ($params['favourited']) {
            api::add_favourite((int) $confprogram->id, (int) $submission->id, (int) $USER->id);
        } else {
            api::remove_favourite((int) $confprogram->id, (int) $submission->id, (int) $USER->id);
        }

        return ['favourited' => api::is_favourited((int) $USER->id, (int) $submission->id)];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'favourited' => new external_value(PARAM_BOOL, 'The favourited state for the current user after this call'),
        ]);
    }
}
