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

namespace mod_confprogram\local;

use mod_confsubmissions\api as submissions_api;

/**
 * Formats a single mod_confsubmissions field's label/value for Display-phase output
 * (the accepted-submissions list and the submission detail modal), shared so the two
 * do not duplicate the track/speaker lookup logic.
 *
 * Field labels are deliberately sourced from mod_confsubmissions's own language file
 * (title, abstract, track, speakers, field_<name>) rather than duplicated here, since
 * mod_confsubmissions already owns those strings and mod_confprogram already depends
 * on it.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_formatter {
    /**
     * Returns the display label for a field.
     *
     * @param string $fieldname One of field_settings::FIXED_FIELDS, or an optional fieldname
     * @return string
     */
    public static function get_label(string $fieldname): string {
        $stringkey = in_array($fieldname, field_settings::FIXED_FIELDS, true) ? $fieldname : 'field_' . $fieldname;

        return get_string($stringkey, 'mod_confsubmissions');
    }

    /**
     * Returns the formatted value of a field for a submission. Never returns HTML: the
     * caller is responsible for escaping (e.g. via a Mustache {{ }} tag), since this
     * value may end up in either a server-rendered page or an AJAX-returned modal.
     *
     * @param string $fieldname One of field_settings::FIXED_FIELDS, or an optional fieldname
     * @param \stdClass $submission The confsubmissions_submission record
     * @return string The formatted value, or '' if the field has no value for this submission
     */
    public static function format_value(string $fieldname, \stdClass $submission): string {
        global $DB;

        // Note: format_string() (below) HTML-entity-escapes by default, which would violate this method's
        // "never returns HTML" contract (every caller already escapes on output, e.g. s() or
        // an auto-escaping Mustache {{ }} tag) and produce visible double-escaping. Passing
        // escape => false keeps multilang filtering while returning plain text.
        $formatopts = ['escape' => false];

        switch ($fieldname) {
            case 'title':
                return format_string($submission->title, true, $formatopts);

            case 'abstract':
                return (string) $submission->abstract;

            case 'track':
                if (empty($submission->trackid)) {
                    return get_string('notrack', 'mod_confsubmissions');
                }
                $track = $DB->get_record('confsubmissions_track', ['id' => $submission->trackid]);
                return $track ? format_string($track->name, true, $formatopts) : get_string('notrack', 'mod_confsubmissions');

            case 'speakers':
                $names = [];
                foreach (submissions_api::get_speakers((int) $submission->id) as $speaker) {
                    if (!empty($speaker->userid)) {
                        $user = \core_user::get_user($speaker->userid);
                        if ($user) {
                            $names[] = fullname($user);
                        }
                    } else if (!empty($speaker->name)) {
                        $names[] = format_string($speaker->name, true, $formatopts);
                    }
                }
                return implode(', ', $names);

            default:
                $values = submissions_api::get_optional_field_values((int) $submission->id);
                return (string) ($values[$fieldname] ?? '');
        }
    }
}
