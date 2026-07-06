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
 * Defines restore_confprogram_activity_task class.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/confprogram/backup/moodle2/restore_confprogram_stepslib.php');

/**
 * confprogram restore task that provides all the settings and steps to perform one
 * complete restore of the activity.
 */
class restore_confprogram_activity_task extends restore_activity_task {
    /**
     * No particular settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * confprogram only has one structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_confprogram_activity_structure_step('confprogram_structure', 'confprogram.xml'));
    }

    /**
     * Defines the contents in the activity that must be processed by the link decoder.
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('confprogram', ['intro'], 'confprogram');

        return $contents;
    }

    /**
     * Defines the decoding rules for links belonging to the activity.
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('CONFPROGRAMVIEWBYID', '/mod/confprogram/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('CONFPROGRAMINDEX', '/mod/confprogram/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Defines the restore log rules for this activity.
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('confprogram', 'add', 'view.php?id={course_module}', '{confprogram}');
        $rules[] = new restore_log_rule('confprogram', 'update', 'view.php?id={course_module}', '{confprogram}');
        $rules[] = new restore_log_rule('confprogram', 'view', 'view.php?id={course_module}', '{confprogram}');

        return $rules;
    }

    /**
     * Defines the restore log rules for course-level logs, applied by the restore final
     * task. All rules here are not linked to any module instance (cmid = 0).
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('confprogram', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
