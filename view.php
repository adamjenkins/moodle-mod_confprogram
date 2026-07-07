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
 * In the Review phase, renders the intro plus quick links into the Review phase
 * screens this user has capabilities for, and (for submitters) a link to their own
 * feedback screen for any of their submissions currently awaiting resubmission.
 *
 * In the Display phase, renders the accepted-submissions list: title, and any other
 * configured fields (see \mod_confprogram\local\field_settings), a favourite-star
 * toggle, a day selector (only shown once mod_confscheduler schedule info exists for
 * at least one accepted submission -- see \mod_confprogram\local\schedule_info) --
 * including an "All days" option (user feedback, 2026-07-05) that renders every day's
 * group as its own heading + table instead of one day at a time -- and a "favourites
 * only" filter. Clicking a row opens the fuller field set in an AJAX-loaded modal
 * (amd/src/programlist.js).
 *
 * A user holding mod/confprogram:managereviewers sees a phase-toggle control and a
 * link to displaysettings.php while in editing mode ("Turn editing on"), regardless
 * of the current phase.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');

use mod_confprogram\api;
use mod_confprogram\local\display_list;
use mod_confprogram\local\field_formatter;
use mod_confprogram\local\field_settings;
use mod_confprogram\local\review_display;
use mod_confprogram\local\rounds;
use mod_confprogram\local\schedule_info;
use mod_confprogram\local\speaker_submissions;
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

// Phase toggle: only ever reachable in editing mode by a managereviewers holder, and
// must run (and redirect) before any output is sent.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('togglephase', 0, PARAM_BOOL)) {
    require_sesskey();
    require_capability('mod/confprogram:managereviewers', $context);

    $newphase = $confprogram->phase === 'review' ? 'display' : 'review';
    $DB->update_record('confprogram', (object) [
        'id'           => $confprogram->id,
        'phase'        => $newphase,
        'timemodified' => time(),
    ]);

    // Switching Review -> Display lifts the embargo on every Accept/Reject decision
    // made so far: push them into mod_confsubmissions's own status column now, since
    // record_decision() deliberately did NOT do this at the time each was recorded
    // (see that method's docblock for why). Deliberately no reverse action when
    // toggling BACK to Review: once a status has been revealed to a submitter, this
    // project treats that as a one-way reveal, not a fully symmetric embargo -- see
    // sync_submission_statuses_to_confsubmissions()'s own docblock for the reasoning.
    if ($newphase === 'display') {
        api::sync_submission_statuses_to_confsubmissions((int) $confprogram->id);

        // Same one-way-reveal timing as the status sync just above (user
        // confirmed, 2026-07-05): every decision notification deferred while this
        // instance was still in Review phase is sent now, in one batch.
        api::send_pending_decision_notifications((int) $confprogram->id);
    }

    // redirect() disables the clean Location-header redirect and instead renders
    // its "Error output, so disabling automatic redirect." fallback page whenever
    // error_get_last() still holds a warning/notice/deprecation matching $CFG->debug
    // -- and PHP does not reset that global at the start of a new request under
    // PHP-FPM, so it can be leftover from a completely unrelated EARLIER request
    // that happened to be handled by the same worker process (confirmed live: this
    // handler's own two calls above emit no warnings on their own, yet the fallback
    // page reproduced intermittently -- same action, same data, different outcome
    // between runs). Clearing it right before our own redirect prevents that stale,
    // unrelated state from masquerading as an error in this request.
    error_clear_last();
    redirect($pageurl);
}

$confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);

if ($PAGE->user_is_editing() && has_capability('mod/confprogram:managereviewers', $context)) {
    $nextphase = $confprogram->phase === 'review' ? 'display' : 'review';

    echo html_writer::start_tag('div', ['class' => 'confprogram-editcontrols mb-3']);

    // The current-phase indicator is organiser-only context shown alongside the phase
    // toggle button (below), not a page-wide banner every viewer sees at the top of the
    // page -- most visitors (reviewers, students) have no use for this internal
    // organiser-facing state and it isn't the first thing they should see on the page.
    echo html_writer::tag(
        'span',
        get_string('currentphase', 'mod_confprogram', get_string('phase_' . $confprogram->phase, 'mod_confprogram')),
        ['class' => 'confprogram-currentphase mr-2 text-muted']
    );

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $pageurl->out_omit_querystring(),
        'class'  => 'form-inline d-inline',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'togglephase', 'value' => 1]);
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('switchtophase', 'mod_confprogram', get_string('phase_' . $nextphase, 'mod_confprogram')),
        'class' => 'btn btn-secondary btn-sm mr-2',
    ]);
    echo html_writer::end_tag('form');

    echo html_writer::link(
        new moodle_url('/mod/confprogram/displaysettings.php', ['id' => $cm->id]),
        get_string('displaysettings', 'mod_confprogram'),
        ['class' => 'btn btn-outline-secondary btn-sm mr-2']
    );

    if (has_capability('mod/confprogram:managenotifications', $context)) {
        echo html_writer::link(
            new moodle_url('/mod/confprogram/notifications.php', ['id' => $cm->id]),
            get_string('managenotifications', 'mod_confprogram'),
            ['class' => 'btn btn-outline-secondary btn-sm mr-2']
        );
    }

    // Persistent entry point to define/edit the review rubric at any time. Previously the
    // only path to grade/grading/manage.php was a warning notification shown inside a
    // specific submission's review.php, and only while no grading method was active yet --
    // once a rubric existed, that link disappeared entirely with no way back to edit it.
    echo html_writer::link(
        new moodle_url('/grade/grading/manage.php', [
            'contextid' => $context->id,
            'component' => 'mod_confprogram',
            'area'      => 'review',
            'returnurl' => $pageurl->out(false),
        ]),
        get_string('managereviewform', 'mod_confprogram'),
        ['class' => 'btn btn-outline-secondary btn-sm']
    );

    echo html_writer::end_tag('div');
}

if (!empty($confprogram->intro)) {
    echo $OUTPUT->box(format_module_intro('confprogram', $confprogram, $cm->id), 'generalbox', 'intro');
}

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

    // Speaker-facing "my reviews" (user request, 2026-07-07): once at least one reviewer
    // has completed their review of a presentation the current user speaks on (any role,
    // not just the submitter -- see speaker_submissions.php), its content is shown right
    // here, one section per presentation, reusing feedback.php's own review_display
    // renderer. Deliberately Review-phase only, unlike the resubmission links above:
    // this whole branch of view.php never runs once $confprogram->phase is 'display', so
    // there is nothing further to gate -- the reviews simply stop being reachable the
    // moment an organiser switches phase, the same one-way "no code path renders this in
    // Display phase" pattern the rest of this plugin's Review-phase content already
    // relies on (see RELATIONS.md's phase-embargo section).
    $speakingsubmissions = speaker_submissions::get_for_user((int) $confsubmissionscm->instance, (int) $USER->id);
    $reviewsections = [];
    foreach ($speakingsubmissions as $speakingsubmission) {
        $round = rounds::get_current_round((int) $confprogram->id, (int) $speakingsubmission->id);
        $reviews = api::get_reviews_for_round((int) $confprogram->id, (int) $speakingsubmission->id, $round);
        if ($reviews) {
            $reviewsections[] = ['submission' => $speakingsubmission, 'reviews' => $reviews];
        }
    }
    if ($reviewsections) {
        echo $OUTPUT->heading(get_string('myreviews', 'mod_confprogram'), 4);
        foreach ($reviewsections as $reviewsection) {
            echo $OUTPUT->heading(format_string($reviewsection['submission']->title), 5);
            echo review_display::render($context, $reviewsection['reviews']);
        }
    }
} else if ($confprogram->phase === 'display') {
    $favouritesonly = optional_param('favouritesonly', 0, PARAM_BOOL);
    $selectedday = optional_param('day', '', PARAM_SAFEDIR);

    // Track filter (Revision round 1, 2026-07-03): a mod_confscheduler track pill links
    // here with ?trackid=X. Following this file's existing "day" param convention, an
    // invalid/foreign trackid degrades gracefully to "no filter" rather than erroring --
    // but unlike "day" (which only narrows an already-instance-scoped list), a trackid
    // is a globally-unique id (like a submission id -- see RELATIONS.md's chain-of-
    // custody discussion), so it MUST be verified to belong to the confsubmissions
    // instance this confprogram vets before its name is ever echoed back, or before it
    // is trusted for anything beyond a harmless no-op filter. submissions_api::get_tracks()
    // is itself already instance-scoped (keyed by cmid), so checking membership in its
    // result is sufficient; only 0 is a well-formed unauthenticated filter.
    $requestedtrackid = optional_param('trackid', 0, PARAM_INT);
    $trackid = 0;
    $trackname = null;
    if ($requestedtrackid > 0) {
        $availabletracks = submissions_api::get_tracks($confsubmissionscm->id);
        if (isset($availabletracks[$requestedtrackid])) {
            $trackid = $requestedtrackid;
            $trackname = format_string($availabletracks[$requestedtrackid]->name);
        }
    }

    $availablefields = field_settings::get_available_fields((int) $confsubmissionscm->instance);
    $allvisiblelistfields = field_settings::get_visible_fieldnames((int) $confprogram->id, $availablefields, 'list');
    $showtrackpill = in_array('track', $allvisiblelistfields, true);
    $listfields = array_values(array_diff(
        $allvisiblelistfields,
        // Title is always rendered as the row's clickable link, never duplicated as a plain
        // field; track gets its own coloured-pill cell below (built from trusted HTML, not
        // run through s() like every other field in this loop) instead of a plain-text cell.
        ['title', 'track']
    ));

    $accepted = display_list::get_accepted_submissions((int) $confprogram->id, (int) $confsubmissionscm->instance);
    $decorated = display_list::sort_by_schedule_then_title(display_list::attach_schedule($accepted));
    $decorated = display_list::filter_by_track($decorated, $trackid);

    $canfavourite = !isguestuser() && has_capability('mod/confprogram:favourite', $context);

    if ($favouritesonly) {
        $decorated = array_values(array_filter(
            $decorated,
            fn($row) => api::is_favourited((int) $USER->id, (int) $row->submission->id)
        ));
    }

    echo $OUTPUT->heading(get_string('acceptedsubmissions', 'mod_confprogram'), 3);

    if ($trackid) {
        $clearfilterurl = new moodle_url(
            $pageurl,
            array_filter(['day' => $selectedday, 'favouritesonly' => $favouritesonly ?: null])
        );
        echo html_writer::tag('p', get_string('filteredbytrack', 'mod_confprogram', $trackname) . ' '
            . html_writer::link($clearfilterurl, get_string('clearfilter', 'mod_confprogram')));
    }

    $filterurl = new moodle_url($pageurl, array_filter(['day' => $selectedday, 'trackid' => $trackid ?: null]));
    if ($favouritesonly) {
        $filterurl->param('favouritesonly', 0);
        echo html_writer::tag('p', html_writer::link($filterurl, get_string('showallsubmissions', 'mod_confprogram'), [
            'class' => 'btn btn-outline-secondary btn-sm',
        ]));
    } else {
        $filterurl->param('favouritesonly', 1);
        echo html_writer::tag('p', html_writer::link($filterurl, get_string('favouritesonly', 'mod_confprogram'), [
            'class' => 'btn btn-outline-secondary btn-sm',
        ]));
    }

    $groups = display_list::group_by_day($decorated);
    $daykeys = array_keys($groups);
    $showdayselector = !($daykeys === ['unscheduled']);

    // Renders one day's (or the whole flat list's) rows as a single table. Shared by
    // the single-day path below and the "All days" path (user feedback, 2026-07-05),
    // which calls this once per day instead of once for a single selected day.
    $rendertable = function (array $rows) use ($listfields, $showtrackpill, $cm, $canfavourite, $USER) {
        if (!$rows) {
            return false;
        }

        $table = new html_table();
        $table->attributes['class'] = 'generaltable confprogram-list-table';
        $head = [get_string('title', 'mod_confsubmissions')];
        if ($showtrackpill) {
            $head[] = get_string('track', 'mod_confsubmissions');
        }
        foreach ($listfields as $fieldname) {
            $head[] = field_formatter::get_label($fieldname);
        }
        $head[] = get_string('timeandroom', 'mod_confprogram');
        $head[] = get_string('favourite', 'mod_confprogram');
        $table->head = $head;

        foreach ($rows as $row) {
            $submission = $row->submission;
            $data = [];

            // A submission this confprogram instance already accepted (possibly already
            // scheduled) can be withdrawn afterwards by its own submitter, entirely inside
            // mod_confsubmissions -- there is no cross-plugin cascade/notification when
            // that happens (see RELATIONS.md). Rather than silently continuing to list it
            // as if nothing changed, flag the row here so a viewer isn't misled into
            // thinking it's still happening (user request, 2026-07-07): greyed out with a
            // strikethrough across the row (.confprogram-row-withdrawn, in styles.css,
            // mirrors mod_confscheduler's identical Display-mode treatment of the same
            // 'withdrawn' status), plus an explicit "Withdrawn" badge next to the title,
            // since colour/strikethrough alone isn't reliably conveyed to everyone (e.g.
            // screen reader users, colour-blind users).
            $iswithdrawn = $submission->status === 'withdrawn';
            if ($iswithdrawn) {
                $rowindex = count($table->data);
                $table->rowclasses[$rowindex] = 'confprogram-row-withdrawn';
            }

            $titlelink = html_writer::link('#', format_string($submission->title), [
                'class'              => 'confprogram-open-detail',
                'data-cmid'          => $cm->id,
                'data-submissionid'  => $submission->id,
            ]);
            $titlecontent = $titlelink;
            if ($iswithdrawn) {
                $titlecontent .= ' ' . html_writer::tag(
                    'span',
                    get_string('status_withdrawn', 'mod_confsubmissions'),
                    ['class' => 'badge badge-secondary confprogram-withdrawn-badge']
                );
            }
            $titlecell = new html_table_cell($titlecontent);
            $titlecell->attributes['data-label'] = get_string('title', 'mod_confsubmissions');
            $data[] = $titlecell;

            if ($showtrackpill) {
                $trackcell = new html_table_cell(field_formatter::get_track_pill_html($submission));
                $trackcell->attributes['data-label'] = get_string('track', 'mod_confsubmissions');
                $data[] = $trackcell;
            }

            foreach ($listfields as $fieldname) {
                $value = field_formatter::format_value($fieldname, $submission);
                $cell = new html_table_cell(s($value));
                $cell->attributes['data-label'] = field_formatter::get_label($fieldname);
                $data[] = $cell;
            }

            $scheduletext = schedule_info::format_for_display(schedule_info::get_for_submission((int) $submission->id));
            $schedulecell = new html_table_cell(s($scheduletext));
            $schedulecell->attributes['data-label'] = get_string('timeandroom', 'mod_confprogram');
            $schedulecell->attributes['class'] .= ' confprogram-schedule';
            $data[] = $schedulecell;

            if ($canfavourite) {
                $isfavourited = api::is_favourited((int) $USER->id, (int) $submission->id);
                $starlabel = $isfavourited
                    ? get_string('unfavourite', 'mod_confprogram')
                    : get_string('favourite', 'mod_confprogram');
                $starbutton = html_writer::tag('button', html_writer::tag('i', '', [
                    'class'       => $isfavourited ? 'icon fa fa-star' : 'icon fa fa-star-o',
                    'aria-hidden' => 'true',
                ]) . html_writer::tag('span', $starlabel, ['class' => 'sr-only']), [
                    'type'              => 'button',
                    'class'             => 'confprogram-favourite-toggle' . ($isfavourited ? ' confprogram-favourited' : ''),
                    'data-cmid'         => $cm->id,
                    'data-submissionid' => $submission->id,
                    'data-favourited'   => $isfavourited ? '1' : '0',
                    'aria-pressed'      => $isfavourited ? 'true' : 'false',
                ]);
                $favcell = new html_table_cell($starbutton);
            } else {
                $favcell = new html_table_cell('');
            }
            $favcell->attributes['data-label'] = get_string('favourite', 'mod_confprogram');
            $data[] = $favcell;

            $table->data[] = $data;
        }

        echo html_writer::table($table);
        return true;
    };

    // All-days view (user feedback, 2026-07-05): every day's group rendered as its own
    // heading + table, one after another, instead of a single day at a time. A plain
    // local variable, not a global define() -- only ever read within this file.
    $alldayskey = 'all';
    $isalldays = false;

    if ($showdayselector) {
        $default = ($selectedday !== '' && ($selectedday === $alldayskey || isset($groups[$selectedday])))
            ? $selectedday
            : display_list::default_day_key($groups);
        $isalldays = ($default === $alldayskey);

        $options = [$alldayskey => get_string('alldays', 'mod_confprogram')];
        foreach ($daykeys as $key) {
            $options[$key] = $key === 'unscheduled'
                ? get_string('unscheduled', 'mod_confprogram')
                : userdate(strtotime($key . ' 00:00:00'), get_string('strftimedate', 'langconfig'));
        }

        echo html_writer::start_tag('form', [
            'method' => 'get',
            'action' => $pageurl->out_omit_querystring(),
            'class'  => 'form-inline mb-3',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
        if ($favouritesonly) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'favouritesonly', 'value' => 1]);
        }
        echo html_writer::tag('label', get_string('day', 'mod_confprogram'), ['for' => 'confprogram-day', 'class' => 'mr-2']);
        // No submit button (user request, 2026-07-07): amd/src/programlist.js submits this
        // form itself on the select's 'change' event instead, via a delegated listener --
        // see that module's SELECTORS.DAY_SELECT. A <noscript> fallback submit button keeps
        // the day filter reachable with JS disabled, matching this file's existing pattern
        // of degrading gracefully rather than hard-depending on JS (see this whole form's
        // plain GET-with-hidden-fields design).
        echo html_writer::select($options, 'day', $default, false, ['id' => 'confprogram-day', 'class' => 'mr-2']);
        echo html_writer::start_tag('noscript');
        echo html_writer::empty_tag('input', [
            'type'  => 'submit',
            'value' => get_string('showday', 'mod_confprogram'),
            'class' => 'btn btn-secondary btn-sm',
        ]);
        echo html_writer::end_tag('noscript');
        echo html_writer::end_tag('form');

        $rows = $isalldays ? [] : ($groups[$default] ?? []);
    } else {
        $rows = $decorated;
    }

    // Called exactly once, unconditionally, regardless of whether this page load has any
    // rows to show: programlist.js also wires up the day-selector's auto-submit-on-change
    // (see the form above), which must work even when the currently selected day is empty.
    // Calling js_call_amd() more than once per page load would attach its delegated
    // document listeners more than once too, double-firing every click/change handler --
    // so every other call site below was removed rather than left in place alongside this
    // one.
    $PAGE->requires->js_call_amd('mod_confprogram/programlist', 'init');

    if ($isalldays) {
        echo html_writer::start_tag('div', ['class' => 'mod_confprogram-list']);
        $anyrendered = false;
        foreach ($daykeys as $key) {
            $heading = $key === 'unscheduled'
                ? get_string('unscheduled', 'mod_confprogram')
                : userdate(strtotime($key . ' 00:00:00'), get_string('strftimedate', 'langconfig'));
            echo $OUTPUT->heading($heading, 4, 'mod_confprogram-day-heading');
            $anyrendered = $rendertable($groups[$key]) || $anyrendered;
        }
        echo html_writer::end_tag('div');
        if (!$anyrendered) {
            echo $OUTPUT->notification(get_string('noacceptedsubmissions', 'mod_confprogram'), 'info');
        }
    } else if ($rows) {
        echo html_writer::start_tag('div', ['class' => 'mod_confprogram-list']);
        $rendertable($rows);
        echo html_writer::end_tag('div');
    } else {
        echo $OUTPUT->notification(get_string('noacceptedsubmissions', 'mod_confprogram'), 'info');
    }
}

echo $OUTPUT->footer();
