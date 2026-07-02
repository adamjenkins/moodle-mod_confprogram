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
 * Per-reviewer max-reviews cap logic, shared by the assignment screen
 * (classes/local/reviewer_workload.php used there for soft warnings) and the
 * review-taking screen (review.php, used there for hard enforcement).
 *
 * The cap is scoped per review round: confprogram.defaultmaxreviews (or a
 * confprogram_reviewermax override for a specific user) limits how many
 * confprogram_review rows a reviewer may complete for a single round of a
 * single confprogram instance. 0 means unlimited.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reviewer_workload {
    /**
     * The effective max-reviews cap for a reviewer in a confprogram instance:
     * their confprogram_reviewermax override if one exists, otherwise the
     * instance's defaultmaxreviews. 0 means unlimited.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $reviewerid The reviewer's user id
     * @return int The cap, 0 meaning unlimited
     */
    public static function get_max(int $confprogramid, int $reviewerid): int {
        global $DB;

        $override = $DB->get_field(
            'confprogram_reviewermax',
            'maxreviews',
            ['confprogram' => $confprogramid, 'userid' => $reviewerid]
        );
        if ($override !== false) {
            return (int) $override;
        }

        return (int) $DB->get_field('confprogram', 'defaultmaxreviews', ['id' => $confprogramid], MUST_EXIST);
    }

    /**
     * The number of reviews a reviewer has already completed (i.e. actually
     * submitted, not merely started) in a given round of a confprogram
     * instance.
     *
     * Excludes placeholder confprogram_review rows (gradinginstanceid = 0)
     * created the moment a reviewer opens the review form but has not yet
     * submitted it — see \mod_confprogram\api::get_reviews_for_round() for
     * why that placeholder exists. Only counting genuinely-submitted reviews
     * here matters for the cap: a reviewer who has merely opened, then
     * abandoned, a review must not have that count against their cap.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $reviewerid The reviewer's user id
     * @param int $round The review round
     * @return int
     */
    public static function completed_count(int $confprogramid, int $reviewerid, int $round): int {
        global $DB;

        return (int) $DB->count_records_select(
            'confprogram_review',
            'confprogram = :confprogram AND reviewerid = :reviewerid AND round = :round AND gradinginstanceid <> 0',
            ['confprogram' => $confprogramid, 'reviewerid' => $reviewerid, 'round' => $round]
        );
    }

    /**
     * How many more reviews a reviewer may complete in a round before hitting
     * their cap, or null if they are unlimited.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $reviewerid The reviewer's user id
     * @param int $round The review round
     * @return int|null Remaining review count, or null if unlimited
     */
    public static function remaining(int $confprogramid, int $reviewerid, int $round): ?int {
        $max = self::get_max($confprogramid, $reviewerid);
        if ($max === 0) {
            return null;
        }

        $remaining = $max - self::completed_count($confprogramid, $reviewerid, $round);

        return max(0, $remaining);
    }

    /**
     * Whether a reviewer has capacity to start (not re-edit) another review in
     * a round: either they are unlimited, or their completed count for that
     * round is below their cap.
     *
     * Callers enforcing this as a hard block (review.php) must separately
     * allow a reviewer to re-edit a review they have already completed for
     * this exact submission+round, since re-editing must never be blocked by
     * the cap; this method only answers "may a *new* review be started".
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $reviewerid The reviewer's user id
     * @param int $round The review round
     * @return bool
     */
    public static function has_capacity(int $confprogramid, int $reviewerid, int $round): bool {
        $remaining = self::remaining($confprogramid, $reviewerid, $round);

        return $remaining === null || $remaining > 0;
    }
}
