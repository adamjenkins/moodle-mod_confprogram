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
 * Defines backup_confprogram_activity_task class.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/confprogram/backup/moodle2/backup_confprogram_stepslib.php');

/**
 * Provides the steps to perform one complete backup of the confprogram instance.
 */
class backup_confprogram_activity_task extends backup_activity_task {
    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines a backup step to store the instance data in the confprogram.xml file.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_confprogram_activity_structure_step('confprogram_structure', 'confprogram.xml'));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts.
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = '/(' . $base . '\/mod\/confprogram\/index.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@CONFPROGRAMINDEX*$2@$', $content);

        $search = '/(' . $base . '\/mod\/confprogram\/view.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@CONFPROGRAMVIEWBYID*$2@$', $content);

        return $content;
    }
}
