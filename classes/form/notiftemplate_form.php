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
 * Notification template editor form used on notifications.php -- there is only one
 * notification type in this plugin (the decision notification), unlike
 * mod_confsubmissions's two, so unlike that plugin's equivalent form there is no
 * hidden 'notiftype' field to route between tabs.
 *
 * Required custom data:
 * - context: \context_module, this instance's own context (required by the
 *   'editor' element even though maxfiles is 0 here)
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notiftemplate_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $context = $this->_customdata['context'];

        $mform->addElement('text', 'subject', get_string('notifsubject', 'mod_confprogram'), ['size' => 64]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addHelpButton('subject', 'notifsubject', 'mod_confprogram');

        $editoroptions = [
            'maxfiles'  => 0,
            'noclean'   => true,
            'context'   => $context,
            'subdirs'   => 0,
        ];
        $mform->addElement(
            'editor',
            'body',
            get_string('notifbody', 'mod_confprogram'),
            null,
            $editoroptions
        );
        $mform->setType('body', PARAM_RAW);
        $mform->addHelpButton('body', 'notifbody', 'mod_confprogram');

        $this->add_action_buttons();
    }
}
