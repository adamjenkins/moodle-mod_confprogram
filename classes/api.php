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

namespace mod_confprogram;

/**
 * Public integration surface for the Conference Program (vetting) workflow.
 *
 * Covers read accessors over this plugin's own tables (phase, unvetted flags,
 * decisions, favourites). Write operations (assigning reviewers, submitting
 * rubric reviews, recording decisions, toggling unvetted/favourite state) are
 * a follow-up task; this scaffold only provides the read paths needed by
 * view.php and by other conference-tools plugins that need to know a
 * submission's vetting outcome.
 *
 * Capability contract: these methods do NOT check capabilities or context
 * themselves — they are a raw data-access layer only. Decision and reviewer
 * data may be sensitive (e.g. who reviewed what, under blind review), so any
 * caller MUST verify the current user's capability (e.g. mod/confprogram:decide,
 * mod/confprogram:viewidentity) against the relevant \context_module before
 * calling, or before exposing the returned data to a user/response.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /**
     * Returns the current phase ('review' or 'display') of the confprogram
     * instance identified by a course-module id.
     *
     * @param int $cmid The course-module id of the confprogram instance
     * @return string The phase, 'review' or 'display'
     */
    public static function get_phase(int $cmid): string {
        global $DB;

        $cm = get_coursemodule_from_id('confprogram', $cmid, 0, false, MUST_EXIST);

        return $DB->get_field('confprogram', 'phase', ['id' => $cm->instance], MUST_EXIST);
    }

    /**
     * Whether a submission has been flagged as unvetted (exempt from review) in
     * any confprogram instance.
     *
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @return bool
     */
    public static function is_unvetted(int $submissionid): bool {
        global $DB;

        return $DB->record_exists('confprogram_unvetted', ['submissionid' => $submissionid]);
    }

    /**
     * Returns the most recent decision recorded for a submission, or null if none
     * has been made yet. When a submission has been through more than one
     * resubmit round, the decision with the highest round (ties broken by most
     * recent timecreated) is returned.
     *
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @return \stdClass|null The confprogram_decision record, or null if not found
     */
    public static function get_decision(int $submissionid): ?\stdClass {
        global $DB;

        $decisions = $DB->get_records(
            'confprogram_decision',
            ['submissionid' => $submissionid],
            'round DESC, timecreated DESC',
            '*',
            0,
            1
        );

        return $decisions ? reset($decisions) : null;
    }

    /**
     * Whether a user has favourited a submission.
     *
     * @param int $userid The user id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @return bool
     */
    public static function is_favourited(int $userid, int $submissionid): bool {
        global $DB;

        return $DB->record_exists('confprogram_favourite', [
            'userid'       => $userid,
            'submissionid' => $submissionid,
        ]);
    }

    /**
     * Returns a user's favourited submissions within a single confprogram instance.
     *
     * @param int $userid The user id
     * @param int $confprogramid The confprogram instance id
     * @return \stdClass[] Array of confprogram_favourite records, keyed by id
     */
    public static function get_favourites(int $userid, int $confprogramid): array {
        global $DB;

        return $DB->get_records(
            'confprogram_favourite',
            ['userid' => $userid, 'confprogram' => $confprogramid],
            'timecreated ASC'
        );
    }
}
