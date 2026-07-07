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
 * Finds the submissions a given user speaks on within a single mod_confsubmissions
 * instance -- broader than "submissions they own" (confsubmissions_submission.userid,
 * the submitter): a submission's speaker list (mod_confsubmissions\api::get_speakers())
 * can include co-presenters with their own linked account who are not the submitter.
 *
 * Mirrors the same "walk every submission, check its speaker list for a userid match"
 * pattern mod_confcheckin's own classes/local/eligibility.php already uses for
 * presenter-ticket eligibility -- per this project's "no shared library" architecture
 * (see RELATIONS.md), each plugin that needs this consumes mod_confsubmissions\api
 * directly rather than sharing the logic, so this is a second, independent
 * implementation of the same walk, not a call into mod_confcheckin's code.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class speaker_submissions {
    /**
     * Returns every submission within a mod_confsubmissions instance where the given
     * user appears as a speaker (any role -- primary or co-presenter), matched by
     * confsubmissions_speaker.userid.
     *
     * A guest/name-only speaker entry (no linked userid) never matches, the same way
     * mod_confcheckin\local\eligibility already treats it.
     *
     * @param int $confsubmissionsid The mod_confsubmissions instance id
     * @param int $userid The user to find speaking submissions for
     * @return \stdClass[] Matching confsubmissions_submission records, reindexed from 0
     */
    public static function get_for_user(int $confsubmissionsid, int $userid): array {
        $submissions = submissions_api::get_submissions_for_instance($confsubmissionsid);

        $mine = [];
        foreach ($submissions as $submission) {
            foreach (submissions_api::get_speakers((int) $submission->id) as $speaker) {
                if (!empty($speaker->userid) && (int) $speaker->userid === $userid) {
                    $mine[] = $submission;
                    break;
                }
            }
        }

        return $mine;
    }
}
