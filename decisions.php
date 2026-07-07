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
 * Decision report for mod_confprogram: a filterable table of non-unvetted
 * submissions with their current round's completed reviews, letting an
 * editing role record an Accept/Reject/Resubmit/Waitlist call individually
 * or in bulk across a checked selection.
 *
 * See \mod_confprogram\local\rounds for the round-derivation rules this page
 * relies on. In short: a submission whose most recent decision is 'resubmit'
 * is already logically in the next round (round+1) the moment that decision
 * is saved. This page's "Start a new round" link is purely navigational (to
 * assign.php, filtered to every resubmit-decided submission via
 * ?resubmitted=1) and does not itself change any state -- see the docblock
 * on \mod_confprogram\local\rounds for the full reasoning.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');

use mod_confprogram\api;
use mod_confprogram\local\decision_report;
use mod_confprogram\local\field_formatter;
use mod_confprogram\local\identity;
use mod_confprogram\local\rounds;
use mod_confsubmissions\api as submissions_api;

$id = required_param('id', PARAM_INT);
$filtertrack = optional_param('trackid', '', PARAM_INT);
$filterstatus = optional_param('decisionstatus', '', PARAM_ALPHA);

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
$PAGE->requires->js_call_amd('mod_confprogram/decisions', 'init');

$confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);
$confsubmissionscontext = context_module::instance($confsubmissionscm->id);
$caneditsubmissions = has_capability('mod/confsubmissions:editany', $confsubmissionscontext);

$returnurlparams = ['id' => $cm->id];
if ($filtertrack !== '') {
    $returnurlparams['trackid'] = $filtertrack;
}
if ($filterstatus !== '') {
    $returnurlparams['decisionstatus'] = $filterstatus;
}
$decisionsreturnurl = new moodle_url('/mod/confprogram/decisions.php', $returnurlparams);

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

    if (optional_param('applybulkdecision', 0, PARAM_INT)) {
        $bulkdecision = optional_param('bulkdecision', '', PARAM_ALPHA);
        $submissionids = optional_param_array('submissionids', [], PARAM_INT);

        $count = decision_report::apply_bulk_decision(
            (int) $confprogram->id,
            (int) $confsubmissionscm->instance,
            $submissionids,
            $bulkdecision,
            $unvettedids,
            (int) $USER->id
        );

        redirect(
            $pageurl,
            get_string('bulkdecisionsaved', 'mod_confprogram', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $decidesubmissionid = optional_param('decidesubmissionid', 0, PARAM_INT);

    if ($decidesubmissionid && !in_array($decidesubmissionid, $unvettedids, true)) {
        $decision = optional_param('decision_' . $decidesubmissionid, '', PARAM_ALPHA);

        if (in_array($decision, $validdecisions, true)) {
            $decidesubmission = submissions_api::get_submission($decidesubmissionid);
            if ($decidesubmission && (int) $decidesubmission->confsubmissions === (int) $confsubmissionscm->instance) {
                $round = rounds::get_current_round((int) $confprogram->id, $decidesubmissionid);
                api::record_decision((int) $confprogram->id, $decidesubmissionid, $decision, $round, (int) $USER->id);
                redirect(
                    $pageurl,
                    get_string('decisionsaved', 'mod_confprogram'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
        }
    }
    redirect($pageurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);
echo $OUTPUT->heading(get_string('decisionreport', 'mod_confprogram'), 3);

// Independent of any track/status filter below -- this is a call to action
// to go do a DIFFERENT task on assign.php, so it must reflect the true,
// unfiltered set, not whatever the table below currently happens to show.
$allsubmissions = submissions_api::get_submissions_for_instance($confsubmissionscm->instance);
foreach ($unvettedids as $uid) {
    unset($allsubmissions[$uid]);
}
$resubmitted = decision_report::filter_resubmitted((int) $confprogram->id, $allsubmissions);
if ($resubmitted) {
    $assignurl = new moodle_url('/mod/confprogram/assign.php', ['id' => $cm->id, 'resubmitted' => 1]);
    echo html_writer::tag('p', html_writer::link(
        $assignurl,
        get_string('startnewroundforresubmits', 'mod_confprogram', count($resubmitted)),
        ['class' => 'btn btn-outline-secondary btn-sm']
    ));
}

// Plain GET filter form: track + decision status. No JS required, matches
// assign.php's existing track-filter pattern.
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $pageurl->out_omit_querystring(),
    'class'  => 'form-inline mb-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);

$tracknames = [];
foreach (submissions_api::get_tracks($confsubmissionscm->id) as $track) {
    $tracknames[$track->id] = format_string($track->name);
}
$trackfilteroptions = ['' => get_string('alltracks', 'mod_confsubmissions')] + $tracknames;
echo html_writer::label(get_string('track', 'mod_confsubmissions'), 'menutrackid', false, ['class' => 'mr-1']);
echo html_writer::select($trackfilteroptions, 'trackid', $filtertrack, null, ['class' => 'mr-3']);

$statusfilteroptions = [
    ''         => get_string('alldecisionstatuses', 'mod_confprogram'),
    'none'     => get_string('nodecisionyet', 'mod_confprogram'),
    'accept'   => get_string('decision_accept', 'mod_confprogram'),
    'reject'   => get_string('decision_reject', 'mod_confprogram'),
    'resubmit' => get_string('decision_resubmit', 'mod_confprogram'),
    'waitlist' => get_string('decision_waitlist', 'mod_confprogram'),
];
echo html_writer::label(get_string('decisionstatus', 'mod_confprogram'), 'menudecisionstatus', false, ['class' => 'mr-1']);
echo html_writer::select($statusfilteroptions, 'decisionstatus', $filterstatus, null, ['class' => 'mr-3']);

echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter'), 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

$filters = [];
if ($filtertrack) {
    $filters['trackid'] = $filtertrack;
}
$submissions = submissions_api::get_submissions_for_instance($confsubmissionscm->instance, $filters);
foreach ($unvettedids as $uid) {
    unset($submissions[$uid]);
}

$decorated = decision_report::decorate_submissions((int) $confprogram->id, $submissions);
$decorated = decision_report::filter_by_decision_status($decorated, $filterstatus);

if (!$decorated) {
    echo $OUTPUT->notification(get_string('nosubmissionsfound', 'mod_confsubmissions'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Independently check both capabilities: a :decide holder is not guaranteed to also hold
// :viewidentity (e.g. a coordinator role that decides but should still see reviews blind).
$canviewidentity = identity::can_view_identity($context);

$decisionoptions = [];
foreach ($validdecisions as $decision) {
    $decisionoptions[$decision] = get_string('decision_' . $decision, 'mod_confprogram');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $pageurl->out_omit_querystring(),
    'id'     => 'mod_confprogram-decisions-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_tag('div', ['class' => 'mod_confprogram-bulk-toolbar form-inline mb-3']);
echo html_writer::label(get_string('makedecision', 'mod_confprogram'), 'menubulkdecision', false, ['class' => 'sr-only']);
echo html_writer::select(
    $decisionoptions,
    'bulkdecision',
    '',
    ['' => get_string('makedecision', 'mod_confprogram')],
    ['class' => 'mr-2']
);
echo html_writer::tag('button', get_string('applybulkdecision', 'mod_confprogram'), [
    'type'  => 'submit',
    'name'  => 'applybulkdecision',
    'value' => 1,
    'class' => 'btn btn-primary mod_confprogram-apply-bulk-decision',
]);
echo html_writer::end_tag('div');

$table = new html_table();
$table->head = [
    html_writer::empty_tag('input', [
        'type'       => 'checkbox',
        'class'      => 'mod_confprogram-select-all',
        'aria-label' => get_string('selectall', 'mod_confprogram'),
    ]),
    get_string('title', 'mod_confsubmissions'),
    get_string('track', 'mod_confsubmissions'),
    get_string('round', 'mod_confprogram'),
    get_string('lastdecisioncolumn', 'mod_confprogram'),
    get_string('reviews', 'mod_confprogram'),
    get_string('makedecision', 'mod_confprogram'),
];
$table->attributes['class'] = 'generaltable';

foreach ($decorated as $row) {
    $submission = $row->submission;

    $decisioncell = $row->latestdecision
        ? get_string('lastdecision', 'mod_confprogram', [
            'decision' => get_string('decision_' . $row->latestdecision->decision, 'mod_confprogram'),
            'round'    => $row->latestdecision->round,
        ])
        : get_string('nodecisionyet', 'mod_confprogram');

    if ($row->reviews) {
        $lines = [];
        $anonymousindex = 1;
        foreach ($row->reviews as $review) {
            if ($canviewidentity) {
                $reviewer = \core_user::get_user($review->reviewerid);
                $reviewerlabel = $reviewer ? fullname($reviewer) : '-';
            } else {
                $reviewerlabel = get_string('anonymousreviewer', 'mod_confprogram', $anonymousindex);
                $anonymousindex++;
            }
            $lines[] = s($reviewerlabel) . ': ' . ($review->grade !== null ? format_float($review->grade, 2) : '-');
        }
        $reviewscell = implode(html_writer::empty_tag('br'), $lines);
    } else {
        $reviewscell = get_string('noreviewsyet', 'mod_confprogram');
    }

    $decisioncontrolcell = html_writer::select(
        $decisionoptions,
        'decision_' . $submission->id,
        '',
        ['' => get_string('makedecision', 'mod_confprogram')],
        ['class' => 'mr-2']
    ) . html_writer::tag('button', get_string('savedecision', 'mod_confprogram'), [
        'type'  => 'submit',
        'name'  => 'decidesubmissionid',
        'value' => $submission->id,
        'class' => 'btn btn-primary btn-sm',
    ]);

    $titlecell = format_string($submission->title);
    if ($caneditsubmissions) {
        $editurl = new moodle_url('/mod/confsubmissions/edit.php', [
            'id'           => $confsubmissionscm->id,
            'submissionid' => $submission->id,
            'returnurl'    => $decisionsreturnurl->out(false),
        ]);
        $titlecell .= ' ' . html_writer::link($editurl, get_string('edit'), [
            'class'      => 'ml-2',
            'aria-label' => get_string('editsubmissionlink', 'mod_confprogram', format_string($submission->title)),
        ]);
    }

    $table->data[] = [
        html_writer::checkbox('submissionids[]', $submission->id, false, '', [
            'class'      => 'mod_confprogram-row-checkbox',
            'aria-label' => get_string('selectsubmission', 'mod_confprogram', format_string($submission->title)),
        ]),
        $titlecell,
        field_formatter::get_track_pill_html($submission),
        $row->round,
        $decisioncell,
        $reviewscell,
        $decisioncontrolcell,
    ];
}

echo html_writer::table($table);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
