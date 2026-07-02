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
 * Main view page for mod_confprogram.
 *
 * Renders the intro plus quick links into the Review phase screens this
 * user has capabilities for, and (for submitters) a link to their own
 * feedback screen for any of their submissions currently awaiting
 * resubmission. The Display phase screens (accepted-submissions listing,
 * favourites, etc.) are a follow-up task; while phase is 'display' this page
 * still only shows the placeholder notice below.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');

use mod_confprogram\local\rounds;
use mod_confsubmissions\api as submissions_api;

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confprogram');
$confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confprogram:viewprogram', $context);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$pageurl = new moodle_url('/mod/confprogram/view.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confprogram->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);

if (!empty($confprogram->intro)) {
    echo $OUTPUT->box(format_module_intro('confprogram', $confprogram, $cm->id), 'generalbox', 'intro');
}

echo $OUTPUT->notification(
    get_string('currentphase', 'mod_confprogram', get_string('phase_' . $confprogram->phase, 'mod_confprogram')),
    'info'
);

if ($confprogram->phase === 'review') {
    $links = [];
    if (has_capability('mod/confprogram:review', $context)) {
        $links[] = html_writer::link(
            new moodle_url('/mod/confprogram/review.php', ['id' => $cm->id]),
            get_string('myreviewqueue', 'mod_confprogram')
        );
    }
    if (has_capability('mod/confprogram:managereviewers', $context)) {
        $links[] = html_writer::link(
            new moodle_url('/mod/confprogram/assign.php', ['id' => $cm->id]),
            get_string('assignreviewers', 'mod_confprogram')
        );
    }
    if (has_capability('mod/confprogram:decide', $context)) {
        $links[] = html_writer::link(
            new moodle_url('/mod/confprogram/decisions.php', ['id' => $cm->id]),
            get_string('decisionreport', 'mod_confprogram')
        );
    }
    if (has_capability('mod/confprogram:manageunvetted', $context)) {
        $links[] = html_writer::link(
            new moodle_url('/mod/confprogram/unvetted.php', ['id' => $cm->id]),
            get_string('unvettedsubmissions', 'mod_confprogram')
        );
    }
    if ($links) {
        echo html_writer::tag('p', implode(' | ', $links));
    }

    // Submitters get a direct link to their own feedback screen for any submission of
    // theirs that is currently awaiting resubmission. Fetching own submissions here (rather
    // than requiring mod/confsubmissions:viewall) is safe: get_submissions_for_instance()
    // is filtered to userid = $USER->id, i.e. only the current user's own data.
    $confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);
    $mysubmissions = submissions_api::get_submissions_for_instance($confsubmissionscm->instance, ['userid' => $USER->id]);
    $resubmitlinks = [];
    foreach ($mysubmissions as $mysubmission) {
        if (rounds::is_awaiting_resubmission((int) $confprogram->id, (int) $mysubmission->id)) {
            $resubmitlinks[] = html_writer::link(
                new moodle_url('/mod/confprogram/feedback.php', ['id' => $cm->id, 'submissionid' => $mysubmission->id]),
                get_string('myfeedback', 'mod_confprogram', format_string($mysubmission->title))
            );
        }
    }
    if ($resubmitlinks) {
        echo $OUTPUT->heading(get_string('resubmissionsneeded', 'mod_confprogram'), 4);
        echo html_writer::alist($resubmitlinks);
    }
}

echo $OUTPUT->footer();
