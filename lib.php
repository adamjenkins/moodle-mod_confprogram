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
 * FEATURE_BACKUP_MOODLE2 is deliberately not claimed yet: no backup/restore
 * steps have been written for this plugin's tables, and this plugin also
 * depends on a course containing a mod_confsubmissions instance (referenced
 * by confsubmissionscmid), which complicates backup/restore further. Add the
 * backup/restore steplibs before flipping this to true.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function confprogram_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO        => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_BACKUP_MOODLE2   => false, // TODO: implement backup/restore steps, then set true.
        FEATURE_GRADE_HAS_GRADE  => false,
        FEATURE_MOD_PURPOSE      => MOD_PURPOSE_OTHER,
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
 * @param int $id The instance id
 * @return bool
 */
function confprogram_delete_instance($id) {
    global $DB;

    if (!$confprogram = $DB->get_record('confprogram', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('confprogram_fieldsetting', ['confprogram' => $id]);
    $DB->delete_records('confprogram_unvetted', ['confprogram' => $id]);
    $DB->delete_records('confprogram_favourite', ['confprogram' => $id]);
    $DB->delete_records('confprogram_decision', ['confprogram' => $id]);
    $DB->delete_records('confprogram_reviewermax', ['confprogram' => $id]);
    $DB->delete_records('confprogram_assignment', ['confprogram' => $id]);

    $DB->delete_records('confprogram', ['id' => $id]);

    return true;
}

/**
 * Adds navigation nodes for the Review phase screens of this activity.
 *
 * Each node is only added for users holding the corresponding capability, so
 * a plain reviewer only ever sees "My review queue", never the
 * organiser-only screens; unvetted.php is deliberately not linked from
 * anywhere else, so it only appears here, only for
 * mod/confprogram:manageunvetted holders.
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
