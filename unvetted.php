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
 * Unvetted-flag management screen for mod_confprogram.
 *
 * Lets an editing role exempt a submission from the review workflow entirely
 * (e.g. an invited keynote or panel added directly to the programme without
 * going through peer review). Gated on mod/confprogram:manageunvetted, which
 * is not granted to reviewer/student archetypes, and this screen is not
 * linked from anywhere a reviewer or student can reach (see
 * confprogram_extend_navigation() in lib.php).
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
require_capability('mod/confprogram:manageunvetted', $context);

$pageurl = new moodle_url('/mod/confprogram/unvetted.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confprogram->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);

// Returns the submission only if it belongs to the mod_confsubmissions instance this
// confprogram vets, else null.
$getownsubmission = function (int $submissionid) use ($confsubmissionscm): ?\stdClass {
    $submission = submissions_api::get_submission($submissionid);
    if (!$submission || (int) $submission->confsubmissions !== (int) $confsubmissionscm->instance) {
        return null;
    }
    return $submission;
};

// Review-phase-only screen: block state-changing actions here too, not just rendering.
if ($confprogram->phase !== 'review') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($confprogram->name), 2);
    echo $OUTPUT->notification(get_string('notinreviewphase', 'mod_confprogram'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($markid = optional_param('markunvetted', 0, PARAM_INT)) {
        if ($getownsubmission($markid)) {
            api::set_unvetted((int) $confprogram->id, $markid, (int) $USER->id);
        }
        redirect($pageurl);
    }
    if ($unmarkid = optional_param('unmarkunvetted', 0, PARAM_INT)) {
        if ($getownsubmission($unmarkid)) {
            api::unset_unvetted((int) $confprogram->id, $unmarkid);
        }
        redirect($pageurl);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);
echo $OUTPUT->heading(get_string('unvettedsubmissions', 'mod_confprogram'), 3);
echo html_writer::tag('p', get_string('unvettedsubmissions_help', 'mod_confprogram'));

$submissions = submissions_api::get_submissions_for_instance($confsubmissionscm->instance);

$unvettedids = array_map('intval', array_column(
    $DB->get_records('confprogram_unvetted', ['confprogram' => $confprogram->id], '', 'id, submissionid'),
    'submissionid'
));

if (!$submissions) {
    echo $OUTPUT->notification(get_string('nosubmissionsfound', 'mod_confsubmissions'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [get_string('title', 'mod_confsubmissions'), get_string('status', 'mod_confsubmissions'), ''];
$table->attributes['class'] = 'generaltable';

foreach ($submissions as $submission) {
    $isunvetted = in_array((int) $submission->id, $unvettedids, true);

    $form = html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $pageurl->out_omit_querystring(),
        'class'  => 'form-inline',
    ]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    if ($isunvetted) {
        $form .= html_writer::tag('button', get_string('unmarkunvetted', 'mod_confprogram'), [
            'type'  => 'submit',
            'name'  => 'unmarkunvetted',
            'value' => $submission->id,
            'class' => 'btn btn-secondary btn-sm',
        ]);
    } else {
        $form .= html_writer::tag('button', get_string('markunvetted', 'mod_confprogram'), [
            'type'  => 'submit',
            'name'  => 'markunvetted',
            'value' => $submission->id,
            'class' => 'btn btn-secondary btn-sm',
        ]);
    }
    $form .= html_writer::end_tag('form');

    $statuslabel = $isunvetted
        ? get_string('unvetted', 'mod_confprogram')
        : get_string('status_' . $submission->status, 'mod_confsubmissions');

    $table->data[] = [
        format_string($submission->title),
        $statuslabel,
        $form,
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
