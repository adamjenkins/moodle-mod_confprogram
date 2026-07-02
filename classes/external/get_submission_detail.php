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
use mod_confprogram\local\field_formatter;
use mod_confprogram\local\field_settings;
use mod_confprogram\local\rounds;
use mod_confprogram\local\schedule_info;
use mod_confprogram\output\submission_modal;
use mod_confsubmissions\api as submissions_api;

/**
 * AJAX-only external function backing the Display-phase submission detail modal
 * (amd/src/programlist.js).
 *
 * This is a public-facing endpoint: it requires nothing beyond
 * mod/confprogram:viewprogram (the broad, guest-inclusive capability), so it is
 * exposed to exactly the same audience as the accepted-submissions list itself.
 * Because of that, it is deliberately hardened against IDOR/enumeration: a
 * submissionid is only ever served if it BOTH belongs to the mod_confsubmissions
 * instance this confprogram vets AND is currently accept-decided in this confprogram
 * instance. Both failure cases (wrong instance, not accepted) throw the exact same
 * exception with the exact same message, so a caller cannot distinguish "doesn't
 * exist"/"belongs elsewhere" from "exists but isn't accepted (or isn't accepted
 * yet)" — that distinction must not be an oracle for probing the review pipeline.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_submission_detail extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The confprogram course-module id'),
            'submissionid' => new external_value(PARAM_INT, 'The confsubmissions_submission id'),
        ]);
    }

    /**
     * Returns the modal title and pre-rendered body HTML for an accepted submission.
     *
     * @param int $cmid The confprogram course-module id
     * @param int $submissionid The confsubmissions_submission id
     * @return array{title: string, html: string}
     */
    public static function execute(int $cmid, int $submissionid): array {
        global $DB, $OUTPUT;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'         => $cmid,
            'submissionid' => $submissionid,
        ]);

        $cm = get_coursemodule_from_id('confprogram', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/confprogram:viewprogram', $context);

        $confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

        // Enforce the phase embargo here too, not just in view.php's rendering branch: this
        // is a directly-callable AJAX endpoint, so without this check a submissionid could be
        // enumerated/probed to learn accept-decision state while still in Review phase, before
        // the organiser has switched to Display phase to make results public.
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
            // Same exception, same message, regardless of which check failed -- see the
            // class docblock for why this must not be distinguishable to the caller.
            throw new \invalid_parameter_exception(get_string('error:submissionnotavailable', 'mod_confprogram'));
        }

        $availablefields = field_settings::get_available_fields((int) $confsubmissionscm->instance);
        // Title is excluded here even if configured showinmodal: it is always used as the
        // modal's own heading (below), so listing it again as a field would be a duplicate --
        // matching the same exclusion view.php applies to the list's title column.
        $modalfields = array_values(array_diff(
            field_settings::get_visible_fieldnames((int) $confprogram->id, $availablefields, 'modal'),
            ['title']
        ));

        $fields = [];
        foreach ($modalfields as $fieldname) {
            $value = field_formatter::format_value($fieldname, $submission);
            if ($value === '') {
                continue;
            }
            $fields[] = ['label' => field_formatter::get_label($fieldname), 'value' => $value];
        }

        $scheduletext = schedule_info::format_for_display(schedule_info::get_for_submission((int) $submission->id));

        $modal = new submission_modal($fields, $scheduletext);

        return [
            'title' => format_string($submission->title),
            'html'  => $OUTPUT->render($modal),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'title' => new external_value(PARAM_TEXT, 'Submission title, for the modal heading'),
            'html' => new external_value(PARAM_RAW, 'Pre-rendered modal body HTML'),
        ]);
    }
}
