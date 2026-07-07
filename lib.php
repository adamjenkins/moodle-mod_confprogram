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
 * Library functions for mod_confprogram.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the features this module supports.
 *
 * FEATURE_ADVANCED_GRADING is claimed (2026-07-06) because this plugin already uses
 * core's Advanced Grading API for rubric reviews (get_grading_manager($context,
 * 'mod_confprogram', 'review'), see review.php/feedback.php) -- declaring it here was
 * simply missing before. This makes core's own backup/restore machinery
 * (backup_activity_grading_structure_step/restore_activity_grading_structure_step)
 * automatically include/restore this activity's grading areas/definitions/instances,
 * and adds a "Grading method setup" settings-navigation link for users holding
 * moodle/grade:managegradingforms -- no other behaviour changes, since this plugin's own
 * mod_form.php does not call the standard grading-elements helper.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function confprogram_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO        => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_BACKUP_MOODLE2   => true,
        FEATURE_GRADE_HAS_GRADE  => false,
        FEATURE_ADVANCED_GRADING => true,
        FEATURE_MOD_PURPOSE      => MOD_PURPOSE_COLLABORATION,
        default                  => null,
    };
}

/**
 * Adds a new instance of the confprogram activity.
 *
 * @param stdClass $data Data from the settings form
 * @param mod_confprogram_mod_form|null $form The form instance
 * @return int The id of the newly inserted record
 */
function confprogram_add_instance(stdClass $data, ?mod_confprogram_mod_form $form = null) {
    global $DB;

    $data->timecreated  = time();
    $data->timemodified = $data->timecreated;

    if (!isset($data->intro)) {
        $data->intro = '';
    }
    if (!isset($data->introformat)) {
        $data->introformat = FORMAT_HTML;
    }
    if (!isset($data->phase)) {
        $data->phase = 'review';
    }

    return $DB->insert_record('confprogram', $data);
}

/**
 * Updates an existing instance of the confprogram activity.
 *
 * @param stdClass $data Data from the settings form
 * @param mod_confprogram_mod_form|null $form The form instance
 * @return bool
 */
function confprogram_update_instance(stdClass $data, ?mod_confprogram_mod_form $form = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    return $DB->update_record('confprogram', $data);
}

/**
 * Deletes an instance of the confprogram activity and all associated data.
 *
 * Grading data (grading_areas/grading_definitions/grading_instances, and any
 * grading-method-specific table such as gradingform_rubric_fillings) is NOT deleted
 * here -- core's own context deletion (\context::delete_content(), called as part of
 * the standard course_delete_module() flow that invokes this function) already calls
 * \grading_manager::delete_all_for_context() for this instance's own module context, so
 * doing it again here would be redundant.
 *
 * @param int $id The instance id
 * @return bool
 */
function confprogram_delete_instance($id) {
    global $DB;

    if (!$confprogram = $DB->get_record('confprogram', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('confprogram_fieldsetting', ['confprogram' => $id]);
    $DB->delete_records('confprogram_notiftemplate', ['confprogram' => $id]);
    $DB->delete_records('confprogram_unvetted', ['confprogram' => $id]);
    $DB->delete_records('confprogram_favourite', ['confprogram' => $id]);
    $DB->delete_records('confprogram_decision', ['confprogram' => $id]);
    $DB->delete_records('confprogram_review', ['confprogram' => $id]);
    $DB->delete_records('confprogram_reviewermax', ['confprogram' => $id]);
    $DB->delete_records('confprogram_assignment', ['confprogram' => $id]);

    $DB->delete_records('confprogram', ['id' => $id]);

    return true;
}

/**
 * Adds navigation nodes for the Review and Display phase screens of this activity.
 *
 * Each node is only added for users holding the corresponding capability, so
 * a plain reviewer only ever sees "My review queue", never the
 * organiser-only screens; unvetted.php is deliberately not linked from
 * anywhere else, so it only appears here, only for
 * mod/confprogram:manageunvetted holders. The Display field settings link is
 * added regardless of the current phase: organisers configure it before
 * switching to the Display phase.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param stdClass $module The module instance record
 * @param cm_info $cm The course-module object
 */
function confprogram_extend_navigation(navigation_node $navigation, stdClass $course, stdClass $module, cm_info $cm) {
    $context = context_module::instance($cm->id);

    if (has_capability('mod/confprogram:review', $context)) {
        $navigation->add(
            get_string('myreviewqueue', 'mod_confprogram'),
            new moodle_url('/mod/confprogram/review.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'confprogramreviewqueue'
        );
    }

    if (has_capability('mod/confprogram:managereviewers', $context)) {
        $navigation->add(
            get_string('assignreviewers', 'mod_confprogram'),
            new moodle_url('/mod/confprogram/assign.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'confprogramassign'
        );

        $navigation->add(
            get_string('displaysettings', 'mod_confprogram'),
            new moodle_url('/mod/confprogram/displaysettings.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'confprogramdisplaysettings'
        );
    }

    if (has_capability('mod/confprogram:decide', $context)) {
        $navigation->add(
            get_string('decisionreport', 'mod_confprogram'),
            new moodle_url('/mod/confprogram/decisions.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'confprogramdecisions'
        );
    }

    if (has_capability('mod/confprogram:manageunvetted', $context)) {
        $navigation->add(
            get_string('unvettedsubmissions', 'mod_confprogram'),
            new moodle_url('/mod/confprogram/unvetted.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'confprogramunvetted'
        );
    }
}

/**
 * Adds the confprogram-specific elements to the course reset form.
 *
 * @param MoodleQuickForm $mform The course reset form
 * @return void
 */
function confprogram_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'confprogramheader', get_string('modulenameplural', 'confprogram'));
    $mform->addElement('advcheckbox', 'reset_confprogram_reviews', get_string('removereviews', 'confprogram'));
}

/**
 * Course reset form defaults.
 *
 * @param stdClass $course The course object
 * @return array
 */
function confprogram_reset_course_form_defaults($course) {
    return ['reset_confprogram_reviews' => 1];
}

/**
 * Removes every reviewer assignment, per-reviewer max-review override, rubric review,
 * decision, favourite, and unvetted flag for every confprogram instance in a course, when
 * a teacher resets the course for reuse -- and switches each instance back to Review
 * phase, so a reused course restarts the whole vetting workflow rather than opening
 * already in Display phase with nothing left to display. Instance CONFIGURATION --
 * Display-phase field visibility settings, notification templates -- is deliberately
 * left untouched, matching mod_confsubmissions's own "config survives a reset, user data
 * doesn't" convention.
 *
 * Grading data cleanup is best-effort, not exhaustive: every grading_instances row
 * scoped to each instance's own module context (safe to wholesale-delete -- a module
 * context's grading data is exclusively that instance's own, never shared) is deleted
 * directly, but a grading-method-specific table (e.g. gradingform_rubric_fillings) is
 * NOT also cleaned up via that method's own gradingform_instance::cancel(), unlike a full
 * instance deletion (which core's own context::delete_content() handles correctly via
 * \grading_manager::delete_all_for_context()). This leaves a harmless orphaned row behind
 * in that plugin-specific table -- never surfaced anywhere, since core always joins FROM
 * grading_instances, never the reverse -- traded off against the cost of dispatching to
 * every active grading method's own per-instance cleanup API individually during a reset.
 * Rubric CRITERIA/LEVELS (the grading_definitions themselves) are never touched, matching
 * the "config survives a reset" convention above.
 *
 * @param stdClass $data The data submitted from the reset course form
 * @return array status array
 */
function confprogram_reset_userdata($data) {
    global $DB;

    $componentstr = get_string('modulenameplural', 'confprogram');
    $status = [];

    if (!empty($data->reset_confprogram_reviews)) {
        $confprogramids = $DB->get_fieldset_select('confprogram', 'id', 'course = ?', [$data->courseid]);

        foreach ($confprogramids as $confprogramid) {
            $DB->delete_records('confprogram_assignment', ['confprogram' => $confprogramid]);
            $DB->delete_records('confprogram_reviewermax', ['confprogram' => $confprogramid]);
            $DB->delete_records('confprogram_review', ['confprogram' => $confprogramid]);
            $DB->delete_records('confprogram_decision', ['confprogram' => $confprogramid]);
            $DB->delete_records('confprogram_favourite', ['confprogram' => $confprogramid]);
            $DB->delete_records('confprogram_unvetted', ['confprogram' => $confprogramid]);

            $cm = get_coursemodule_from_instance('confprogram', $confprogramid, $data->courseid);
            if ($cm) {
                $context = context_module::instance($cm->id);
                $DB->delete_records_select(
                    'grading_instances',
                    'definitionid IN (SELECT id FROM {grading_definitions}
                        WHERE areaid IN (SELECT id FROM {grading_areas}
                            WHERE contextid = ? AND component = ?))',
                    [$context->id, 'mod_confprogram']
                );
            }

            $DB->set_field('confprogram', 'phase', 'review', ['id' => $confprogramid]);
        }

        $status[] = [
            'component' => $componentstr,
            'item' => get_string('removereviews', 'confprogram'),
            'error' => false,
        ];
    }

    return $status;
}
