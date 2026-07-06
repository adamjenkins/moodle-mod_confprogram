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
 * Reviewer/reviewer-group assignment screen for mod_confprogram.
 *
 * Lists non-unvetted submissions from the linked mod_confsubmissions
 * instance, optionally filtered by track (or, when arriving from
 * decisions.php's "Start new review round" link, filtered to a single
 * resubmitted submission via the "focus" param). Supports assigning an
 * individual reviewer per submission, and, when groupreviewmode is on,
 * bulk-assigning a reviewer group to a checked set of submissions.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');
require_once($CFG->libdir . '/grouplib.php');

use mod_confprogram\api;
use mod_confprogram\local\reviewer_workload;
use mod_confprogram\local\rounds;
use mod_confsubmissions\api as submissions_api;

$id = required_param('id', PARAM_INT);
$filtertrack = optional_param('trackid', '', PARAM_INT);
$focus = optional_param('focus', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confprogram');
$confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confprogram:managereviewers', $context);

$pageurl = new moodle_url('/mod/confprogram/assign.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confprogram->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);

// Returns the submission only if it belongs to the mod_confsubmissions instance this
// confprogram vets, else null. mod_confsubmissions\api::get_submission() looks up by a
// bare global id with no instance scoping, so every caller here must re-check this
// before trusting or acting on a submissionid taken from a request param.
$getownsubmission = function (int $submissionid) use ($confsubmissionscm): ?\stdClass {
    $submission = submissions_api::get_submission($submissionid);
    if (!$submission || (int) $submission->confsubmissions !== (int) $confsubmissionscm->instance) {
        return null;
    }
    return $submission;
};

// Review-phase-only screen: block state-changing actions here too, not just rendering,
// so assignments cannot still be mutated after the instance switches to Display phase.
if ($confprogram->phase !== 'review') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($confprogram->name), 2);
    echo $OUTPUT->notification(get_string('notinreviewphase', 'mod_confprogram'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Handle POST actions before rendering.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($removeid = optional_param('removeassignment', 0, PARAM_INT)) {
        api::unassign((int) $confprogram->id, $removeid);
        redirect($pageurl->out(false) . ($focus ? '&focus=' . $focus : ''));
    }

    if ($assignsubmissionid = optional_param('assignindividual', 0, PARAM_INT)) {
        if ($getownsubmission($assignsubmissionid)) {
            $reviewerid = optional_param('reviewerselect_' . $assignsubmissionid, 0, PARAM_INT);
            $eligible = get_users_by_capability($context, 'mod/confprogram:review', 'u.id');
            if ($reviewerid && array_key_exists($reviewerid, $eligible)) {
                api::assign_reviewer((int) $confprogram->id, $assignsubmissionid, $reviewerid);

                $round = rounds::get_current_round((int) $confprogram->id, $assignsubmissionid);
                if (!reviewer_workload::has_capacity((int) $confprogram->id, $reviewerid, $round)) {
                    redirect(
                        $pageurl->out(false) . ($focus ? '&focus=' . $focus : ''),
                        get_string('warningreviewercapreached', 'mod_confprogram'),
                        null,
                        \core\output\notification::NOTIFY_WARNING
                    );
                }
            }
        }
        redirect($pageurl->out(false) . ($focus ? '&focus=' . $focus : ''));
    }

    if (optional_param('assigngroup', 0, PARAM_INT)) {
        $groupid = optional_param('groupselect', 0, PARAM_INT);
        $submissionids = optional_param_array('submissionids', [], PARAM_INT);
        $coursegroups = groups_get_all_groups($course->id);

        if ($groupid && array_key_exists($groupid, $coursegroups) && $submissionids) {
            foreach ($submissionids as $sid) {
                if ($getownsubmission((int) $sid)) {
                    api::assign_reviewer_group((int) $confprogram->id, (int) $sid, $groupid);
                }
            }
        }
        redirect($pageurl->out(false) . ($focus ? '&focus=' . $focus : ''));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);
echo $OUTPUT->heading(get_string('assignreviewers', 'mod_confprogram'), 3);

$unvettedids = array_map('intval', array_column(
    $DB->get_records('confprogram_unvetted', ['confprogram' => $confprogram->id], '', 'id, submissionid'),
    'submissionid'
));

$tracknames = [];
foreach (submissions_api::get_tracks($confsubmissionscm->id) as $track) {
    $tracknames[$track->id] = format_string($track->name);
}

if ($focus) {
    echo $OUTPUT->notification(get_string('focusedsubmission', 'mod_confprogram'), 'info');
    echo html_writer::tag('p', html_writer::link($pageurl, get_string('backtoall', 'mod_confprogram')));
    $submissions = [];
    $focussubmission = $getownsubmission($focus);
    if ($focussubmission && !in_array($focus, $unvettedids, true)) {
        $submissions[$focus] = $focussubmission;
    }
} else {
    // Plain GET filter form: no JS required, matches mod_confsubmissions's view.php pattern.
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $pageurl->out_omit_querystring(),
        'class'  => 'form-inline mb-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    $trackfilteroptions = ['' => get_string('alltracks', 'mod_confsubmissions')] + $tracknames;
    echo html_writer::label(get_string('track', 'mod_confsubmissions'), 'menutrackid', false, ['class' => 'mr-1']);
    echo html_writer::select($trackfilteroptions, 'trackid', $filtertrack, null, ['class' => 'mr-3']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter'), 'class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');

    $filters = [];
    if ($filtertrack !== '') {
        $filters['trackid'] = $filtertrack;
    }
    // TODO: consider pagination once submission volumes get large.
    $submissions = submissions_api::get_submissions_for_instance($confsubmissionscm->instance, $filters);
    foreach ($unvettedids as $uid) {
        unset($submissions[$uid]);
    }
}

if (!$submissions) {
    echo $OUTPUT->notification(get_string('nosubmissionsfound', 'mod_confsubmissions'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$eligiblereviewers = get_users_by_capability($context, 'mod/confprogram:review', 'u.*', 'u.lastname ASC, u.firstname ASC');
$coursegroups = groups_get_all_groups($course->id);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out_omit_querystring()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
if ($focus) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'focus', 'value' => $focus]);
}
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$table = new html_table();
$head = [
    $confprogram->groupreviewmode ? get_string('select') : '',
    get_string('title', 'mod_confsubmissions'),
    get_string('track', 'mod_confsubmissions'),
    get_string('currentreviewers', 'mod_confprogram'),
    get_string('assignreviewer', 'mod_confprogram'),
];
$table->head = $head;
$table->attributes['class'] = 'generaltable';

foreach ($submissions as $submission) {
    $assignments = api::get_assignments((int) $confprogram->id, (int) $submission->id);

    $assignmentlines = [];
    foreach ($assignments as $assignment) {
        if (!empty($assignment->reviewerid)) {
            $reviewer = \core_user::get_user($assignment->reviewerid);
            $label = $reviewer ? fullname($reviewer) : '-';
        } else {
            $group = $coursegroups[$assignment->reviewergroupid] ?? null;
            $label = $group ? get_string('reviewergroup', 'mod_confprogram', format_string($group->name)) : '-';
        }
        $removebutton = html_writer::tag('button', get_string('remove'), [
            'type'  => 'submit',
            'name'  => 'removeassignment',
            'value' => $assignment->id,
            'class' => 'btn btn-link btn-sm p-0 ml-2',
        ]);
        $assignmentlines[] = html_writer::tag('span', s($label) . ' ' . $removebutton);
    }
    $currentcell = $assignmentlines
        ? implode(html_writer::empty_tag('br'), $assignmentlines)
        : get_string('noreviewersassigned', 'mod_confprogram');

    $reviewerselect = [0 => get_string('selectreviewer', 'mod_confprogram')];
    foreach ($eligiblereviewers as $reviewer) {
        $reviewerselect[$reviewer->id] = fullname($reviewer);
    }
    $assigncell = html_writer::select($reviewerselect, 'reviewerselect_' . $submission->id, 0, null)
        . ' '
        . html_writer::tag('button', get_string('assignreviewer', 'mod_confprogram'), [
            'type'  => 'submit',
            'name'  => 'assignindividual',
            'value' => $submission->id,
            'class' => 'btn btn-secondary btn-sm',
        ]);

    $checkbox = $confprogram->groupreviewmode
        ? html_writer::checkbox('submissionids[]', $submission->id, false, '', ['class' => 'mr-1'])
        : '';

    $table->data[] = [
        $checkbox,
        format_string($submission->title),
        $submission->trackid ? ($tracknames[$submission->trackid] ?? '-') : get_string('notrack', 'mod_confsubmissions'),
        $currentcell,
        $assigncell,
    ];
}

echo html_writer::table($table);

if ($confprogram->groupreviewmode) {
    echo $OUTPUT->heading(get_string('bulkassigngroup', 'mod_confprogram'), 4);
    $groupoptions = [0 => get_string('selectgroup', 'mod_confprogram')];
    foreach ($coursegroups as $group) {
        $groupoptions[$group->id] = format_string($group->name);
    }
    echo html_writer::select($groupoptions, 'groupselect', 0, null, ['class' => 'mr-2']);
    echo html_writer::tag('button', get_string('assigngroup', 'mod_confprogram'), [
        'type'  => 'submit',
        'name'  => 'assigngroup',
        'value' => 1,
        'class' => 'btn btn-primary',
    ]);
}

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
