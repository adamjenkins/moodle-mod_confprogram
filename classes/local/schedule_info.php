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

/**
 * Soft, optional integration point with mod_confscheduler (room/time scheduling).
 *
 * mod_confscheduler has NOT been built yet at the time this class was written: it does
 * not exist in this environment, and may not exist in any given install of
 * mod_confprogram. This class is a forward-looking integration point only: it degrades
 * gracefully (returns null, never fatal-errors or emits a warning/notice) whenever
 * mod_confscheduler is absent, which is the normal case today.
 *
 * The informal contract the two plugins share, for whoever builds mod_confscheduler next:
 * mod_confscheduler MUST provide a public static method
 *
 *   \mod_confscheduler\api::get_schedule_for_submission(int $submissionid): ?array
 *
 * returning either null (the submission has no scheduled slot yet) or an array with
 * exactly these keys:
 *   - 'starttime' (int) a unix timestamp
 *   - 'endtime'   (int) a unix timestamp
 *   - 'room'      (string) a human-readable room/location label
 *
 * This class does not check capabilities: schedule information is treated as part of
 * the public Display-phase programme content, gated the same way the rest of the
 * accepted-submissions list is (mod/confprogram:viewprogram).
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule_info {
    /**
     * Returns the scheduled time/room for a submission, or null if mod_confscheduler is
     * not installed, does not (yet) implement the expected method, or has no schedule
     * recorded for this submission.
     *
     * Deliberately defensive at every step: none of the checks below (component lookup,
     * class_exists, method_exists) will fatal-error or emit a warning when
     * mod_confscheduler is absent, which is the actual state of this environment.
     *
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @return array{starttime: int, endtime: int, room: string}|null
     */
    public static function get_for_submission(int $submissionid): ?array {
        if (\core_component::get_component_directory('mod_confscheduler') === null) {
            return null;
        }

        if (!class_exists('\mod_confscheduler\api') || !method_exists('\mod_confscheduler\api', 'get_schedule_for_submission')) {
            return null;
        }

        return \mod_confscheduler\api::get_schedule_for_submission($submissionid);
    }

    /**
     * Formats a schedule_info array (or null) for display, including the "not yet
     * scheduled" fallback text.
     *
     * @param array|null $schedule A schedule array (with 'starttime'/'endtime'/'room' keys) or null
     * @return string
     */
    public static function format_for_display(?array $schedule): string {
        if ($schedule === null || empty($schedule['starttime'])) {
            return get_string('notyetscheduled', 'mod_confprogram');
        }

        $text = userdate((int) $schedule['starttime'], get_string('strftimedatetime', 'langconfig'));
        if (!empty($schedule['endtime'])) {
            $text .= ' - ' . userdate((int) $schedule['endtime'], get_string('strftimetime', 'langconfig'));
        }
        if (!empty($schedule['room'])) {
            $text .= ', ' . format_string((string) $schedule['room']);
        }

        return $text;
    }
}
