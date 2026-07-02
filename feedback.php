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
require_once($CFG->dirroot . '/grade/grading/lib.php');

use mod_confprogram\api;
use mod_confprogram\local\identity;
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

$canviewidentity = identity::can_view_identity($context);

if (!$reviews) {
    echo $OUTPUT->notification(get_string('nofeedbackavailable', 'mod_confprogram'), 'info');
} else {
    $gradingmanager = get_grading_manager($context, 'mod_confprogram', 'review');
    $gradingmethod = $gradingmanager->get_active_method();
    $controller = $gradingmethod ? $gradingmanager->get_controller($gradingmethod) : null;
    $definition = ($controller && $controller->is_form_available()) ? $controller->get_definition() : null;

    $i = 1;
    foreach ($reviews as $review) {
        echo html_writer::start_tag('div', ['class' => 'card mb-3']);
        echo html_writer::start_tag('div', ['class' => 'card-body']);

        if ($canviewidentity) {
            $reviewer = \core_user::get_user($review->reviewerid);
            $reviewerlabel = $reviewer ? fullname($reviewer) : '-';
        } else {
            $reviewerlabel = get_string('anonymousreviewer', 'mod_confprogram', $i);
        }
        echo html_writer::tag('h5', $reviewerlabel);
        echo html_writer::tag('p', get_string('grade', 'mod_confprogram') . ': '
            . ($review->grade !== null ? format_float($review->grade, 2) : '-'));

        // Best-effort per-criterion breakdown for rubric-graded reviews. get_or_create_instance()
        // here always finds the exact existing instance rather than creating a new one: we pass
        // the instance's own id, raterid and itemid straight from the confprogram_review row, and
        // fetch_instance() only returns an instance for an exact (id, raterid, itemid) match (see
        // review.php's block comment on why itemid is the confprogram_review row id, not the
        // submission id). If for any reason the grading area's active method has since changed
        // away from rubric, or the instance record is gone, this quietly falls back to just the
        // numeric grade already printed above.
        $renderedcriteria = false;
        if ($gradingmethod === 'rubric' && $definition && !empty($definition->rubric_criteria) && $review->gradinginstanceid) {
            $instance = $controller->get_or_create_instance(
                (int) $review->gradinginstanceid,
                (int) $review->reviewerid,
                (int) $review->id
            );
            $isexpectedinstance = $instance instanceof \gradingform_rubric_instance
                && (int) $instance->get_id() === (int) $review->gradinginstanceid;
            if ($isexpectedinstance) {
                $filling = $instance->get_rubric_filling();
                if (!empty($filling['criteria'])) {
                    $table = new html_table();
                    $table->head = [
                        get_string('criterion', 'mod_confprogram'),
                        get_string('level', 'mod_confprogram'),
                        get_string('remark', 'mod_confprogram'),
                    ];
                    $table->attributes['class'] = 'generaltable';
                    foreach ($filling['criteria'] as $criterionid => $criteriondata) {
                        $criteriondef = $definition->rubric_criteria[$criterionid] ?? null;
                        if (!$criteriondef) {
                            continue;
                        }
                        $leveldef = $criteriondef['levels'][$criteriondata['levelid']] ?? null;
                        $table->data[] = [
                            format_string($criteriondef['description']),
                            $leveldef ? format_string($leveldef['definition']) : '-',
                            !empty($criteriondata['remark']) ? format_text($criteriondata['remark'], FORMAT_HTML) : '-',
                        ];
                    }
                    echo html_writer::table($table);
                    $renderedcriteria = true;
                }
            }
        }

        if (!$renderedcriteria) {
            echo html_writer::tag('p', get_string('nocriteriondetail', 'mod_confprogram'), ['class' => 'text-muted']);
        }

        echo html_writer::end_tag('div');
        echo html_writer::end_tag('div');
        $i++;
    }
}

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
