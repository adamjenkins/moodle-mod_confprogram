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
 * Reviewer-facing screens for mod_confprogram: with no "submissionid" param,
 * shows the current user's review queue (assigned submissions still needing
 * a review for the current round, plus ones already reviewed). With a
 * "submissionid" param, shows the submission content plus the rubric review
 * form for that submission.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

use mod_confprogram\api;
use mod_confprogram\form\review_form;
use mod_confprogram\local\field_formatter;
use mod_confprogram\local\identity;
use mod_confprogram\local\reviewer_workload;
use mod_confprogram\local\rounds;
use mod_confsubmissions\api as submissions_api;

$id = required_param('id', PARAM_INT);
$submissionid = optional_param('submissionid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confprogram');
$confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confprogram:review', $context);

$queueurl = new moodle_url('/mod/confprogram/review.php', ['id' => $cm->id]);
$pageurl = $submissionid
    ? new moodle_url('/mod/confprogram/review.php', ['id' => $cm->id, 'submissionid' => $submissionid])
    : $queueurl;

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confprogram->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);

// Submissions flagged unvetted are excluded from the review workflow entirely.
$unvettedids = array_map('intval', array_column(
    $DB->get_records('confprogram_unvetted', ['confprogram' => $confprogram->id], '', 'id, submissionid'),
    'submissionid'
));

if (!$submissionid) {
    // Review queue mode. No form/redirect involved here, so it's safe to render
    // normally (this branch's own header()/footer() calls are unaffected by the fix
    // below, which only concerns single-submission mode further down).
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($confprogram->name), 2);

    if ($confprogram->phase !== 'review') {
        echo $OUTPUT->notification(get_string('notinreviewphase', 'mod_confprogram'), 'info');
        echo $OUTPUT->footer();
        exit;
    }

    echo $OUTPUT->heading(get_string('myreviewqueue', 'mod_confprogram'), 3);

    $assignedids = api::get_assigned_submission_ids_for_user((int) $confprogram->id, (int) $USER->id);

    $pending = [];
    $completed = [];
    foreach ($assignedids as $sid) {
        if (in_array($sid, $unvettedids, true)) {
            continue;
        }
        $submission = submissions_api::get_submission($sid);
        // Defence in depth: only ever show/act on submissions that genuinely belong to the
        // mod_confsubmissions instance this confprogram vets, even though the assignment
        // write path (assign.php) is itself now instance-scoped.
        if (!$submission || (int) $submission->confsubmissions !== (int) $confsubmissionscm->instance) {
            continue;
        }
        $round = rounds::get_current_round((int) $confprogram->id, $sid);
        $review = api::get_review((int) $confprogram->id, $sid, (int) $USER->id, $round);
        // A gradinginstanceid of 0 means only a placeholder row exists (the reviewer opened
        // the form but never actually submitted it) -- see get_reviews_for_round()'s
        // docblock. That must still show up as pending, not completed.
        if ($review && (int) $review->gradinginstanceid !== 0) {
            $completed[] = (object) ['submission' => $submission, 'round' => $round, 'review' => $review];
        } else {
            $pending[] = (object) ['submission' => $submission, 'round' => $round];
        }
    }

    echo $OUTPUT->heading(get_string('pendingreviews', 'mod_confprogram'), 4);
    if ($pending) {
        $table = new html_table();
        $table->head = [
            get_string('title', 'mod_confsubmissions'),
            get_string('round', 'mod_confprogram'),
            '',
        ];
        $table->attributes['class'] = 'generaltable';
        foreach ($pending as $row) {
            $reviewurl = new moodle_url('/mod/confprogram/review.php', ['id' => $cm->id, 'submissionid' => $row->submission->id]);
            $table->data[] = [
                format_string($row->submission->title),
                $row->round,
                html_writer::link($reviewurl, get_string('startreview', 'mod_confprogram')),
            ];
        }
        echo html_writer::table($table);
    } else {
        echo $OUTPUT->notification(get_string('noreviewspending', 'mod_confprogram'), 'info');
    }

    echo $OUTPUT->heading(get_string('completedreviews', 'mod_confprogram'), 4);
    if ($completed) {
        $table = new html_table();
        $table->head = [
            get_string('title', 'mod_confsubmissions'),
            get_string('round', 'mod_confprogram'),
            get_string('grade', 'mod_confprogram'),
            '',
        ];
        $table->attributes['class'] = 'generaltable';
        foreach ($completed as $row) {
            $reviewurl = new moodle_url('/mod/confprogram/review.php', ['id' => $cm->id, 'submissionid' => $row->submission->id]);
            $table->data[] = [
                format_string($row->submission->title),
                $row->round,
                $row->review->grade !== null ? format_float($row->review->grade, 2) : '-',
                html_writer::link($reviewurl, get_string('editreview', 'mod_confprogram')),
            ];
        }
        echo html_writer::table($table);
    } else {
        echo $OUTPUT->notification(get_string('noreviewscompleted', 'mod_confprogram'), 'info');
    }

    echo $OUTPUT->footer();
    exit;
}

// Single-submission review mode. Everything below that is capable of triggering a
// redirect() (the grading-instance setup, building the form, and checking
// is_cancelled()/get_data()) happens BEFORE any echo $OUTPUT->header() call -- see the
// large comment right above the is_cancelled()/get_data() check for why. Early-exit
// branches that throw a moodle_exception (not-assigned, wrong instance) are unaffected
// by this: unlike redirect(), an exception is safe to raise regardless of page output
// state, so those checks are unchanged from their original position.

if ($confprogram->phase !== 'review') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($confprogram->name), 2);
    echo $OUTPUT->notification(get_string('notinreviewphase', 'mod_confprogram'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$submission = submissions_api::get_submission($submissionid);
if (!$submission || (int) $submission->confsubmissions !== (int) $confsubmissionscm->instance) {
    throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submission');
}

// The assignment check MUST run before the unvetted check, not after: mod/confprogram:review
// is a broad, instance-wide capability (not per-submission), so any reviewer can request any
// submissionid in this instance. If the unvetted check ran first, an unassigned reviewer would
// get a distinguishable error for unvetted vs. non-unvetted submissions -- an oracle that lets
// them enumerate which submissions are unvetted, which must be completely invisible to them.
if (!api::is_user_assigned((int) $confprogram->id, $submissionid, (int) $USER->id)) {
    throw new \moodle_exception('error:notassigned', 'mod_confprogram');
}

// Defensive re-check: the queue listing already excludes unvetted submissions, but this
// page must not trust a stale/crafted link either (task 5's race-condition guard). Safe to
// check now that we know the caller is genuinely assigned to this submission.
if (in_array($submissionid, $unvettedids, true)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($confprogram->name), 2);
    echo $OUTPUT->notification(get_string('error:unvetted', 'mod_confprogram'), 'error');
    echo $OUTPUT->footer();
    exit;
}

$round = rounds::get_current_round((int) $confprogram->id, $submissionid);
$existingreview = api::get_review((int) $confprogram->id, $submissionid, (int) $USER->id, $round);

// Max-reviews cap: only enforced for *starting* a new review. Re-editing a review the
// reviewer has already completed for this exact submission+round is never blocked.
if (!$existingreview && !reviewer_workload::has_capacity((int) $confprogram->id, (int) $USER->id, $round)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($confprogram->name), 2);
    echo $OUTPUT->notification(get_string('error:reviewcapreached', 'mod_confprogram'), 'error');
    echo $OUTPUT->footer();
    exit;
}

// IMPORTANT: the grading API's itemid here is deliberately the confprogram_review row id,
// NOT $submissionid, despite the integration recipe's example using the submission id as
// itemid. Reason: gradingform_controller::get_current_instance() (and, through it,
// create_instance(), which get_or_create_instance() falls back to when no instance yet
// exists) filters only by itemid, NOT raterid -- the raterid filter is present in the code
// but wrapped in "if (false)" behind a "TODO MDL-31237 should be: if
// ($manager->allow_multiple_raters())" comment (grade/grading/form/lib.php). In other
// words core's advanced grading API does not yet support more than one rater grading the
// same itemid independently: the second reviewer to open a submission that another
// reviewer has already scored would have their form silently pre-filled with the first
// reviewer's rubric answers (found via that rater-blind lookup), which would both break
// review independence and leak content across a blind review boundary.
//
// Fixing this without patching core: mint an itemid that is unique per
// (confprogram, submissionid, reviewerid, round) by using the confprogram_review row's own
// id, ensuring the row exists (as a blank placeholder, upserted with a null grade) before
// the grading instance is created. No other reviewer can ever collide with this itemid, so
// the core lookup above can never find a different rater's instance. The same id is passed
// to submit_and_get_grade() below, per its docblock ("itemid must be specified here").
if (!$existingreview) {
    api::upsert_review((int) $confprogram->id, $submissionid, (int) $USER->id, $round, 0, null);
    $existingreview = api::get_review((int) $confprogram->id, $submissionid, (int) $USER->id, $round);
}
$reviewitemid = (int) $existingreview->id;

$gradingmanager = get_grading_manager($context, 'mod_confprogram', 'review');
$gradinginstance = null;
// Deferred until after header() (below) -- built here so the decision of WHICH notice (if
// any) to show is made before any output, but the notice is only ever echoed once we know
// this request is neither a cancel nor a successful submit.
$noreviewformnotice = null;

if ($gradingmethod = $gradingmanager->get_active_method()) {
    $controller = $gradingmanager->get_controller($gradingmethod);
    if ($controller->is_form_available()) {
        // Reuse this reviewer's own existing grading instance for this submission+round
        // when re-editing or re-posting after a validation error, rather than letting the
        // grading API spin up a fresh copy every time; 0 means "create a new one" and is
        // only ever hit the first time this reviewer opens this submission+round.
        $default = (int) $existingreview->gradinginstanceid;
        $instanceid = optional_param('advancedgradinginstanceid', $default, PARAM_INT);
        $controller->set_grade_range(make_grades_menu(100), true);
        $gradinginstance = $controller->get_or_create_instance($instanceid, $USER->id, $reviewitemid);
    } else {
        $noreviewformnotice = $controller->form_unavailable_notification();
    }
} else if (has_capability('mod/confprogram:managereviewers', $context)) {
    $manageurl = new moodle_url('/grade/grading/manage.php', [
        'contextid'  => $context->id,
        'component'  => 'mod_confprogram',
        'area'       => 'review',
        'returnurl'  => $pageurl->out(false),
    ]);
    $noreviewformnotice = get_string('error:noreviewform', 'mod_confprogram') . ' '
        . html_writer::link($manageurl, get_string('setupreviewform', 'mod_confprogram'));
} else {
    $noreviewformnotice = get_string('error:noreviewform', 'mod_confprogram');
}

$mform = new review_form($pageurl, ['gradinginstance' => $gradinginstance]);

// This is the fix: is_cancelled()/get_data() are checked, and any resulting redirect()
// fired, BEFORE echo $OUTPUT->header() below -- unlike the previous version of this file,
// which rendered the submission detail (header, heading, speakers, track, abstract,
// optional fields) before ever reaching this point. redirect() cannot perform a real HTTP
// redirect once the page has started outputting (moodle_page::state past
// STATE_BEFORE_HEADER); calling it after output had already started this cancel/submit
// error live (see this plugin's changelog.md) with a full "You should really redirect
// before you start page output" stack trace. Every sibling page in this plugin
// (assign.php, decisions.php, unvetted.php) already does all POST-handling-and-redirect
// before any header output -- this restores that same, already-correct pattern here.
if ($mform->is_cancelled()) {
    redirect($queueurl);
} else if ($gradinginstance && ($data = $mform->get_data())) {
    // Same itemid as used to create the instance above -- see the block comment there.
    $grade = $gradinginstance->submit_and_get_grade($data->advancedgrading, $reviewitemid);
    // Note: submit_and_get_grade() returns -1 (not null) for an intentionally-empty/cleared
    // rubric; normalise that sentinel to null to match the schema's "null until submitted"
    // contract for confprogram_review.grade.
    $storedgrade = ($grade !== null && (float) $grade >= 0) ? (float) $grade : null;

    api::upsert_review(
        (int) $confprogram->id,
        $submissionid,
        (int) $USER->id,
        $round,
        (int) $gradinginstance->get_id(),
        $storedgrade
    );

    redirect($queueurl, get_string('reviewsaved', 'mod_confprogram'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Reached only on the initial GET, or a redraw after a validation error -- neither
// cancelled nor a successful submit. Safe to start page output from here on.
$canviewidentity = identity::can_view_identity($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);
echo $OUTPUT->heading(format_string($submission->title), 3);

// Speaker identity is only fetched at all when the viewer is allowed to see it (defence in
// depth against a stray debug/log statement leaking it while blinded).
if ($canviewidentity) {
    $speakerlines = [];
    foreach (submissions_api::get_speakers($submissionid) as $speaker) {
        if (!empty($speaker->userid)) {
            $user = \core_user::get_user($speaker->userid);
            $speakerlines[] = $user ? fullname($user) : '-';
        } else if (!empty($speaker->name)) {
            $speakerlines[] = format_string($speaker->name);
        }
    }
    if ($speakerlines) {
        echo html_writer::tag('p', html_writer::tag('strong', get_string('speakers', 'mod_confsubmissions') . ': ')
            . implode(', ', $speakerlines));
    }
} else {
    echo $OUTPUT->notification(get_string('identityhidden', 'mod_confprogram'), 'info');
}

echo html_writer::tag('p', html_writer::tag('strong', get_string('track', 'mod_confsubmissions') . ': ')
    . field_formatter::get_track_pill_html($submission));

echo html_writer::tag('div', format_text($submission->abstract, FORMAT_PLAIN), ['class' => 'mb-3']);

// Every organiser-defined optional field is shown here unconditionally, regardless of
// the Display-phase show-in-list/show-in-modal visibility matrix (classes/local/
// field_settings.php) -- that matrix only governs what the public Display-phase list
// surfaces; a reviewer here always sees everything. Fields are identified by their
// confsubmissions_field id, not name: mod_confsubmissions's fields are organiser-free-text
// (not a fixed lang-string vocabulary), so a field's own name is used directly as its
// label rather than looked up via get_string().
$fieldvalues = submissions_api::get_optional_field_values($submissionid);
foreach (submissions_api::get_fields($confsubmissionscm->instance) as $field) {
    $value = $fieldvalues[$field->id] ?? '';
    if ($value === '') {
        continue;
    }
    echo html_writer::tag('p', html_writer::tag('strong', format_string($field->name) . ': ') . s($value));
}

echo $OUTPUT->heading(get_string('review', 'mod_confprogram'), 3);

if ($noreviewformnotice !== null) {
    echo $OUTPUT->notification($noreviewformnotice, 'warning');
}

$mform->display();

echo $OUTPUT->footer();
