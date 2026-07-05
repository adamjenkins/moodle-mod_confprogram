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
 * A single notification type (the accept/reject/waitlist decision notification),
 * so -- unlike mod_confsubmissions's equivalent page -- there are no tabs, just one
 * form. One row per instance (confprogram_notiftemplate, unique on
 * confprogram+notiftype). Pre-fills the editor with the built-in fallback content
 * when no row exists yet, same "always usable, never blank" convention as every
 * other template screen in this project.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');

use mod_confprogram\form\notiftemplate_form;
use mod_confprogram\local\notifier;

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confprogram');
$confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confprogram:managenotifications', $context);

$pageurl = new moodle_url('/mod/confprogram/notifications.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confprogram->name) . ': ' . get_string('managenotifications', 'mod_confprogram'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$existing = $DB->get_record('confprogram_notiftemplate', [
    'confprogram' => $confprogram->id,
    'notiftype'   => 'decision',
]);

$form = new notiftemplate_form($pageurl, ['context' => $context]);

$default = notifier::default_template();
$form->set_data((object) [
    'subject' => $existing->subject ?? $default['subject'],
    'body'    => [
        'text'   => $existing->body ?? $default['body'],
        'format' => $existing->bodyformat ?? FORMAT_HTML,
    ],
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/confprogram/view.php', ['id' => $cm->id]));
} else if ($data = $form->get_data()) {
    $now = time();
    $record = (object) [
        'confprogram'  => $confprogram->id,
        'notiftype'    => 'decision',
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

    redirect($pageurl, get_string('notiftemplatesaved', 'mod_confprogram'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);
echo $OUTPUT->heading(get_string('managenotifications', 'mod_confprogram'), 3);

$placeholderlist = implode(', ', array_map(
    static fn (string $name): string => "[[{$name}]]",
    ['fullname', 'submissiontitle', 'coursename', 'decision']
));
echo $OUTPUT->notification(
    get_string('notifplaceholders', 'mod_confprogram', $placeholderlist),
    'info'
);

$form->display();

echo $OUTPUT->footer();
