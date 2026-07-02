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
 * Decision report for mod_confprogram: lists non-unvetted submissions with
 * their current round's completed reviews, and lets an editing role record
 * an Accept/Reject/Resubmit/Waitlist call.
 *
 * See \mod_confprogram\local\rounds for the round-derivation rules this page
 * relies on. In short: a submission whose most recent decision is 'resubmit'
 * is already logically in the next round (round+1) the moment that decision
 * is saved; this page's "Start new review round" link for such a submission
 * is purely navigational (to assign.php, focused on that submission) and
 * does not itself change any state — see the docblock on
 * \mod_confprogram\local\rounds for the full reasoning.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');

use mod_confprogram\api;
use mod_confprogram\local\identity;
use mod_confprogram\local\rounds;
use mod_confsubmissions\api as submissions_api;

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confprogram');
$confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confprogram:decide', $context);

$pageurl = new moodle_url('/mod/confprogram/decisions.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confprogram->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);

// Review-phase-only screen: block state-changing actions here too, not just rendering.
if ($confprogram->phase !== 'review') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($confprogram->name), 2);
    echo $OUTPUT->notification(get_string('notinreviewphase', 'mod_confprogram'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$unvettedids = array_map('intval', array_column(
    $DB->get_records('confprogram_unvetted', ['confprogram' => $confprogram->id], '', 'id, submissionid'),
    'submissionid'
));

$validdecisions = ['accept', 'reject', 'resubmit', 'waitlist'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $decidesubmissionid = optional_param('decidesubmissionid', 0, PARAM_INT);
    $decision = optional_param('decision', '', PARAM_ALPHA);

    if (
        $decidesubmissionid && in_array($decision, $validdecisions, true)
            && !in_array($decidesubmissionid, $unvettedids, true)
    ) {
        $decidesubmission = submissions_api::get_submission($decidesubmissionid);
        if ($decidesubmission && (int) $decidesubmission->confsubmissions === (int) $confsubmissionscm->instance) {
            $round = rounds::get_current_round((int) $confprogram->id, $decidesubmissionid);
            api::record_decision((int) $confprogram->id, $decidesubmissionid, $decision, $round, (int) $USER->id);
            redirect($pageurl, get_string('decisionsaved', 'mod_confprogram'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
    redirect($pageurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);
echo $OUTPUT->heading(get_string('decisionreport', 'mod_confprogram'), 3);

$submissions = submissions_api::get_submissions_for_instance($confsubmissionscm->instance);

// Independently check both capabilities: a :decide holder is not guaranteed to also hold
// :viewidentity (e.g. a coordinator role that decides but should still see reviews blind).
$canviewidentity = identity::can_view_identity($context);

$decisionoptions = [];
foreach ($validdecisions as $decision) {
    $decisionoptions[$decision] = get_string('decision_' . $decision, 'mod_confprogram');
}

$found = false;
foreach ($submissions as $submission) {
    if (in_array((int) $submission->id, $unvettedids, true)) {
        continue;
    }
    $found = true;

    $round = rounds::get_current_round((int) $confprogram->id, $submission->id);
    $latestdecision = rounds::get_latest_decision((int) $confprogram->id, $submission->id);
    $reviews = api::get_reviews_for_round((int) $confprogram->id, $submission->id, $round);

    echo html_writer::start_tag('div', ['class' => 'card mb-3']);
    echo html_writer::start_tag('div', ['class' => 'card-body']);

    echo html_writer::tag('h4', format_string($submission->title), ['class' => 'card-title']);
    echo html_writer::tag('p', get_string('round', 'mod_confprogram') . ': ' . $round, ['class' => 'text-muted']);

    if ($latestdecision) {
        echo html_writer::tag('p', get_string('lastdecision', 'mod_confprogram', [
            'decision' => get_string('decision_' . $latestdecision->decision, 'mod_confprogram'),
            'round'    => $latestdecision->round,
        ]));

        if ($latestdecision->decision === 'resubmit') {
            $assignurl = new moodle_url('/mod/confprogram/assign.php', ['id' => $cm->id, 'focus' => $submission->id]);
            echo html_writer::tag('p', html_writer::link(
                $assignurl,
                get_string('startnewreviewround', 'mod_confprogram'),
                ['class' => 'btn btn-outline-secondary btn-sm']
            ));
        }
    }

    if ($reviews) {
        $table = new html_table();
        $table->head = [get_string('reviewer', 'mod_confprogram'), get_string('grade', 'mod_confprogram')];
        $table->attributes['class'] = 'generaltable';
        $i = 1;
        foreach ($reviews as $review) {
            if ($canviewidentity) {
                $reviewer = \core_user::get_user($review->reviewerid);
                $reviewerlabel = $reviewer ? fullname($reviewer) : '-';
            } else {
                $reviewerlabel = get_string('anonymousreviewer', 'mod_confprogram', $i);
            }
            $table->data[] = [$reviewerlabel, $review->grade !== null ? format_float($review->grade, 2) : '-'];
            $i++;
        }
        echo html_writer::table($table);
    } else {
        echo $OUTPUT->notification(get_string('noreviewsyet', 'mod_confprogram'), 'info');
    }

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $pageurl->out_omit_querystring(),
        'class'  => 'form-inline',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', [
        'type'  => 'hidden',
        'name'  => 'decidesubmissionid',
        'value' => $submission->id,
    ]);
    echo html_writer::select(
        $decisionoptions,
        'decision',
        '',
        ['' => get_string('makedecision', 'mod_confprogram')],
        ['class' => 'mr-2']
    );
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('savedecision', 'mod_confprogram'),
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');

    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');
}

if (!$found) {
    echo $OUTPUT->notification(get_string('nosubmissionsfound', 'mod_confsubmissions'), 'info');
}

echo $OUTPUT->footer();
