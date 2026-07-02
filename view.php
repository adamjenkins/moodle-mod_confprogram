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
 * This is a scaffold stub: it renders a placeholder heading showing which
 * phase (Review or Display) the instance is currently in. The full Review
 * and Display phase screens are a follow-up task.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');

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

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);

if (!empty($confprogram->intro)) {
    echo $OUTPUT->box(format_module_intro('confprogram', $confprogram, $cm->id), 'generalbox', 'intro');
}

// TODO: this is a scaffold placeholder. Replace with the real Review phase screen
// (assignment/rubric review UI) or Display phase screen (accepted-submissions
// listing) once those are built.
echo $OUTPUT->notification(
    get_string('currentphase', 'mod_confprogram', get_string('phase_' . $confprogram->phase, 'mod_confprogram')),
    'info'
);

echo $OUTPUT->footer();
