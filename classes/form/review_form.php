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

namespace mod_confprogram\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The reviewer-facing rubric review form, embedding a core advanced grading
 * "grading" form element built from a \gradingform_instance the caller
 * (review.php) has already created via the core grading API.
 *
 * Required custom data:
 * - gradinginstance: gradingform_instance|null, the instance to render. Null
 *   when no grading method has been configured yet for this instance's
 *   mod_confprogram/review grading area (organiser has not set one up).
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $gradinginstance = $this->_customdata['gradinginstance'] ?? null;

        if ($gradinginstance) {
            // Core's lib/form/grading.php declares "class MoodleQuickForm_grading extends
            // HTML_QuickForm_input" but only requires HTML/QuickForm/element.php (its
            // grandparent), not HTML/QuickForm/input.php (its direct parent) -- core gets away
            // with this because every real-world form using the "grading" element also adds
            // some other basic element type first (e.g. a text/checkbox field), whose own
            // lib/form/*.php transitively requires HTML/QuickForm/input.php as a side effect.
            // This form adds "grading" as its very first element, so that side effect never
            // happens; without this explicit require, addElement('grading', ...) below fatals
            // with "Class HTML_QuickForm_input not found" the first time this form is built.
            require_once('HTML/QuickForm/input.php');
            $mform->addElement(
                'grading',
                'advancedgrading',
                get_string('review', 'mod_confprogram'),
                ['gradinginstance' => $gradinginstance]
            );
            $mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
            $mform->setType('advancedgradinginstanceid', PARAM_INT);
        } else {
            $mform->addElement('static', 'nogradingmethod', '', get_string('error:noreviewform', 'mod_confprogram'));
        }

        $this->add_action_buttons(true, get_string('submitreview', 'mod_confprogram'));
    }
}
