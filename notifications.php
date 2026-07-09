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
 * Notification template management screen for mod_confprogram.
 *
 * One template per decision type per instance (confprogram_notiftemplate, unique
 * on confprogram+notiftype -- see db/install.xml). 2026-07-09: previously a
 * single shared template for all decisions; now tab-routed one-per-decision-type,
 * mirroring mod_confsubmissions's equivalent page. Visiting this page for a
 * decision type that has no row yet pre-fills the editor with the built-in
 * fallback content, so an organiser edits from a real starting point rather than
 * a blank box.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');

use mod_confprogram\api;
use mod_confprogram\form\notiftemplate_form;
use mod_confprogram\local\notifier;

$id = required_param('id', PARAM_INT);
$notiftype = optional_param('type', 'accept', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confprogram');
$confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confprogram:managenotifications', $context);

if (!in_array($notiftype, notifier::NOTIFIABLE_DECISIONS, true)) {
    throw new \moodle_exception('error:invalidnotiftype', 'mod_confprogram');
}

$pageurl = new moodle_url('/mod/confprogram/notifications.php', ['id' => $cm->id, 'type' => $notiftype]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confprogram->name) . ': ' . get_string('managenotifications', 'mod_confprogram'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$existing = $DB->get_record('confprogram_notiftemplate', [
    'confprogram' => $confprogram->id,
    'notiftype'   => $notiftype,
]);

$form = new notiftemplate_form($pageurl, ['notiftype' => $notiftype, 'context' => $context]);

$default = notifier::default_template($notiftype);
$form->set_data((object) [
    'notiftype'            => $notiftype,
    'notificationsenabled' => (bool) $confprogram->notificationsenabled,
    'subject'              => $existing->subject ?? $default['subject'],
    'body'                 => [
        'text'   => $existing->body ?? $default['body'],
        'format' => $existing->bodyformat ?? FORMAT_HTML,
    ],
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/confprogram/view.php', ['id' => $cm->id]));
} else if ($data = $form->get_data()) {
    $now = time();

    $DB->update_record('confprogram', (object) [
        'id'                   => $confprogram->id,
        'notificationsenabled' => !empty($data->notificationsenabled) ? 1 : 0,
        'timemodified'         => $now,
    ]);

    $record = (object) [
        'confprogram'  => $confprogram->id,
        'notiftype'    => $notiftype,
        'subject'      => $data->subject,
        'body'         => $data->body['text'],
        'bodyformat'   => $data->body['format'],
        'timemodified' => $now,
    ];

    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('confprogram_notiftemplate', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('confprogram_notiftemplate', $record);
    }

    // Re-enabling the master switch must actually deliver any decision that was
    // recorded (and correctly skipped) while it was off -- flipping the checkbox
    // alone does not call send_pending_decision_notifications(); that is otherwise
    // only manually triggered from the "Send pending notifications" button on
    // view.php. Only meaningful once decisions are actually revealed (Display
    // phase); in Review phase nothing accept/reject/waitlist-shaped is ever
    // pending regardless of the switch, since notify_decision() is never called
    // for those until phase is Display (resubmit sends immediately regardless of
    // phase, so it never accumulates here either way).
    if (!empty($data->notificationsenabled) && $confprogram->phase === 'display') {
        api::send_pending_decision_notifications((int) $confprogram->id);
    }

    redirect($pageurl, get_string('notiftemplatesaved', 'mod_confprogram'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);
echo $OUTPUT->heading(get_string('managenotifications', 'mod_confprogram'), 3);

$tablinks = [];
foreach (notifier::NOTIFIABLE_DECISIONS as $type) {
    $label = get_string('decision_' . $type, 'mod_confprogram');
    if ($type === $notiftype) {
        $tablinks[] = html_writer::tag('strong', $label);
    } else {
        $tablinks[] = html_writer::link(
            new moodle_url('/mod/confprogram/notifications.php', ['id' => $cm->id, 'type' => $type]),
            $label
        );
    }
}
echo html_writer::tag('p', implode(' | ', $tablinks));

$pendingcount = api::count_pending_notifications((int) $confprogram->id);
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/mod/confprogram/pending_notifications.php', ['id' => $cm->id]),
    get_string('pendingnotifications', 'mod_confprogram', $pendingcount)
));

$placeholdernames = ['fullname', 'submissiontitle', 'coursename', 'decision'];
if ($notiftype === 'resubmit') {
    $placeholdernames[] = 'feedbackurl';
}
$placeholderlist = implode(', ', array_map(static fn (string $name): string => "[[{$name}]]", $placeholdernames));
echo $OUTPUT->notification(
    get_string('notifplaceholders', 'mod_confprogram', $placeholderlist),
    'info'
);

$form->display();

echo $OUTPUT->footer();
