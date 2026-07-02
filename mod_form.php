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
 * Activity settings form for mod_confprogram.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Settings form for the Conference Program activity.
 *
 * This is a minimal scaffold: name/intro and the link to the
 * mod_confsubmissions instance this program vets. The full settings UI
 * (blind review, group-review mode, default max reviews, and any
 * phase-specific settings for Review vs. Display) is a follow-up task.
 */
class mod_confprogram_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;
        $courseid = $this->current->course ?? $this->course->id;

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Which mod_confsubmissions instance this program vets.
        $options = [0 => get_string('choosedots')];
        // TODO: consider get_coursemodules_in_course('confsubmissions', $courseid) once its
        // exact return shape (does it include the activity name, or only course_modules
        // fields?) has been confirmed against the target Moodle version; querying course_modules
        // joined to modules directly here is unambiguous in the meantime.
        $confsubmissionscms = $DB->get_records_sql(
            "SELECT cm.id, cs.name
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'confsubmissions'
               JOIN {confsubmissions} cs ON cs.id = cm.instance
              WHERE cm.course = :courseid
           ORDER BY cs.name ASC",
            ['courseid' => $courseid]
        );
        foreach ($confsubmissionscms as $cm) {
            $options[$cm->id] = format_string($cm->name);
        }

        $mform->addElement(
            'select',
            'confsubmissionscmid',
            get_string('confsubmissionscmid', 'mod_confprogram'),
            $options
        );
        $mform->addRule('confsubmissionscmid', null, 'required', null, 'client');
        $mform->addHelpButton('confsubmissionscmid', 'confsubmissionscmid', 'mod_confprogram');
        if (count($options) <= 1) {
            $mform->addElement(
                'static',
                'noconfsubmissions',
                '',
                get_string('error:noconfsubmissions', 'mod_confprogram')
            );
        }

        // TODO: phase-specific settings (blind review, group-review mode, default max
        // reviews, Display-phase field visibility) are a follow-up task. For now these
        // columns are populated with their schema defaults by confprogram_add_instance().

        // Standard module elements (visibility, groups, etc.).
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Server-side validation.
     *
     * @param array $data Submitted form data
     * @param array $files Uploaded files
     * @return array Errors keyed by field name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['confsubmissionscmid'])) {
            $errors['confsubmissionscmid'] = get_string('required');
        }

        return $errors;
    }
}
