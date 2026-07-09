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
 * Display-phase per-field visibility settings for mod_confprogram: a matrix of
 * available fields (the fixed set plus enabled optional fields from the linked
 * mod_confsubmissions instance) x "show in list" / "show in modal" checkboxes.
 *
 * Reachable regardless of the current phase (review or display): organisers
 * configure this before switching to the Display phase, so it is linked from
 * view.php's edit-mode controls at all times, not just while phase is 'display'.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');

use mod_confprogram\local\field_formatter;
use mod_confprogram\local\field_settings;

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confprogram');
$confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confprogram:managereviewers', $context);

$pageurl = new moodle_url('/mod/confprogram/displaysettings.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confprogram->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);
$availablefields = field_settings::get_available_fields((int) $confsubmissionscm->instance);

if (data_submitted()) {
    require_sesskey();

    $tosave = [];
    foreach ($availablefields as $fieldname) {
        $tosave[$fieldname] = [
            'showinlist'  => optional_param('showinlist_' . $fieldname, 0, PARAM_BOOL),
            'showinmodal' => optional_param('showinmodal_' . $fieldname, 0, PARAM_BOOL),
        ];
    }
    field_settings::upsert((int) $confprogram->id, $tosave);

    redirect($pageurl, get_string('displaysettingssaved', 'mod_confprogram'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$settings = field_settings::get_settings_with_defaults((int) $confprogram->id, $availablefields);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);
echo $OUTPUT->heading(get_string('displaysettings', 'mod_confprogram'), 3);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out_omit_querystring()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
// The form action strips the query string, so the cmid must ride along as a
// hidden field -- without it every save died on required_param('id') (FABLE.md
// review, 2026-07-09: this screen had never been able to save at all).
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);

$table = new html_table();
$table->head = [
    get_string('field', 'mod_confprogram'),
    get_string('showinlist', 'mod_confprogram'),
    get_string('showinmodal', 'mod_confprogram'),
];
$table->attributes['class'] = 'generaltable';

foreach ($availablefields as $fieldname) {
    $fieldsettings = $settings[$fieldname];
    $fieldlabel = field_formatter::get_label($fieldname);
    // Each checkbox carries an aria-label naming both the column and the field:
    // its visible <label> is empty, so without this a screen reader announces
    // two indistinguishable unnamed checkboxes per row (WCAG 4.1.2).
    $table->data[] = [
        $fieldlabel,
        html_writer::checkbox('showinlist_' . $fieldname, 1, (bool) $fieldsettings->showinlist, '', [
            'aria-label' => get_string('showinlist', 'mod_confprogram') . ': ' . $fieldlabel,
        ]),
        html_writer::checkbox('showinmodal_' . $fieldname, 1, (bool) $fieldsettings->showinmodal, '', [
            'aria-label' => get_string('showinmodal', 'mod_confprogram') . ': ' . $fieldlabel,
        ]),
    ];
}

echo html_writer::table($table);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
