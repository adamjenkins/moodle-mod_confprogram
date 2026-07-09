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
 * Pending-notifications queue viewer for mod_confprogram (2026-07-09, user
 * request). Lists every decision notification currently pending (not yet sent,
 * not superseded/dismissed -- see api::get_pending_decisions(), which already
 * holds at most one row per submission by construction, see the dedup fix in
 * api::record_decision()) and lets an organiser dismiss one directly: marking it
 * superseded so it will never send, without touching the underlying decision
 * row's own decision/round/decidedby/timecreated fields.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');

use mod_confprogram\api;
use mod_confsubmissions\api as submissions_api;

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confprogram');
$confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confprogram:managenotifications', $context);

$pageurl = new moodle_url('/mod/confprogram/pending_notifications.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confprogram->name) . ': ' . get_string('pendingnotificationsheading', 'mod_confprogram'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if (data_submitted()) {
    require_sesskey();

    $dismissid = optional_param('dismiss', 0, PARAM_INT);
    if ($dismissid) {
        api::dismiss_pending_decision((int) $confprogram->id, $dismissid);
        redirect($pageurl, get_string('dismissed', 'mod_confprogram'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    redirect($pageurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);
echo $OUTPUT->heading(get_string('pendingnotificationsheading', 'mod_confprogram'), 3);

$pending = api::get_pending_decisions((int) $confprogram->id);

if (!$pending) {
    echo $OUTPUT->notification(get_string('nopendingnotifications', 'mod_confprogram'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('title', 'mod_confsubmissions'),
    get_string('lastdecisioncolumn', 'mod_confprogram'),
    get_string('recipients', 'mod_confprogram'),
    get_string('day', 'mod_confprogram'),
    '',
];
$table->attributes['class'] = 'generaltable';

foreach ($pending as $decision) {
    $submission = submissions_api::get_submission((int) $decision->submissionid);
    $title = $submission ? format_string($submission->title) : get_string('error:submissionnotavailable', 'mod_confprogram');

    $recipients = [];
    foreach (submissions_api::get_speakers((int) $decision->submissionid) as $speaker) {
        if (empty($speaker->userid)) {
            continue;
        }
        $user = \core_user::get_user((int) $speaker->userid);
        if ($user && !$user->deleted) {
            $recipients[] = fullname($user);
        }
    }

    $dismissform = html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out_omit_querystring()]);
    $dismissform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    $dismissform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $dismissform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'dismiss', 'value' => $decision->id]);
    $dismissform .= html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('dismiss', 'mod_confprogram'),
        'class' => 'btn btn-outline-secondary btn-sm',
    ]);
    $dismissform .= html_writer::end_tag('form');

    $table->data[] = [
        $title,
        get_string('decision_' . $decision->decision, 'mod_confprogram'),
        $recipients ? implode(', ', array_map('s', $recipients)) : '-',
        userdate($decision->timecreated),
        $dismissform,
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
