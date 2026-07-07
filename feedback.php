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
 * Submitter-facing "my feedback" screen for mod_confprogram.
 *
 * Shown only for a submission whose most recent decision is 'resubmit': the
 * submitter can see the reviewer feedback (scores/comments, with reviewer
 * identity respecting blind review) from the round that led to that
 * decision, then follow a link to mod_confsubmissions's own edit.php to
 * revise their submission. This page does not rebuild the submission form —
 * that plugin owns it.
 *
 * Gated on submission ownership, checked directly: there is no shared
 * capability between mod_confsubmissions and mod_confprogram for "is the
 * owner of this submission", so require_login() plus an explicit userid
 * comparison is the access check here, not require_capability().
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');

use mod_confprogram\api;
use mod_confprogram\local\review_display;
use mod_confprogram\local\rounds;
use mod_confsubmissions\api as submissions_api;

$id = required_param('id', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confprogram');
$confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

$confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);

$submission = submissions_api::get_submission($submissionid);
if (!$submission || (int) $submission->confsubmissions !== (int) $confsubmissionscm->instance) {
    throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submission');
}

if ((int) $submission->userid !== (int) $USER->id) {
    throw new \moodle_exception('error:notowner', 'mod_confprogram');
}

$pageurl = new moodle_url('/mod/confprogram/feedback.php', ['id' => $cm->id, 'submissionid' => $submissionid]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confprogram->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);

if ($confprogram->phase !== 'review') {
    echo $OUTPUT->notification(get_string('notinreviewphase', 'mod_confprogram'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->heading(get_string('myfeedback', 'mod_confprogram', format_string($submission->title)), 3);

// A submission can in principle be flagged unvetted after a resubmit decision was already
// recorded (e.g. converted to an invited panel post-review); re-check here so that edge case
// doesn't leave a resubmit-and-edit flow reachable for a submission staff meant to exempt.
if ($DB->record_exists('confprogram_unvetted', ['confprogram' => $confprogram->id, 'submissionid' => $submissionid])) {
    echo $OUTPUT->notification(get_string('nofeedbackavailable', 'mod_confprogram'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$latestdecision = rounds::get_latest_decision((int) $confprogram->id, $submissionid);

if (!$latestdecision || $latestdecision->decision !== 'resubmit') {
    echo $OUTPUT->notification(get_string('nofeedbackavailable', 'mod_confprogram'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$feedbackround = (int) $latestdecision->round;
$reviews = api::get_reviews_for_round((int) $confprogram->id, $submissionid, $feedbackround);

echo review_display::render($context, $reviews);

// NOTE (known follow-up limitation, out of this plugin's scope): mod_confsubmissions's own
// edit.php additionally gates editing on its "call is open" window (timeopen/timeclose),
// independent of anything here. If the call has since closed, a submitter who is invited to
// resubmit will hit that plugin's "call not open" notice instead of the edit form. Since
// mod_confsubmissions owns edit.php, resolving that (e.g. an "or is in an active resubmit
// cycle" exception there) is follow-up work for that plugin, not this one.
$editurl = new moodle_url('/mod/confsubmissions/edit.php', [
    'id'           => $confsubmissionscm->id,
    'submissionid' => $submissionid,
]);
echo $OUTPUT->single_button($editurl, get_string('editmysubmission', 'mod_confprogram'), 'get');

echo $OUTPUT->footer();
