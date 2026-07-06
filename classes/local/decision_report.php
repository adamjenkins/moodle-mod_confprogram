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

use mod_confprogram\api;
use mod_confsubmissions\api as submissions_api;

/**
 * Data layer behind decisions.php's table and assign.php's "resubmitted"
 * filter mode. Kept out of both page scripts so it's independently
 * unit-testable, matching this plugin's existing display_list.php/
 * grid_data.php convention.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decision_report {
    /**
     * Decorates each submission with its current round, latest decision, and
     * this round's completed reviews -- everything decisions.php's table needs
     * per row, computed once so callers don't repeat the round/decision/review
     * lookups themselves.
     *
     * @param int $confprogramid The confprogram instance id
     * @param array $submissions Id-keyed raw submission objects
     * @return array Id-keyed \stdClass rows: ->submission, ->round, ->latestdecision, ->reviews
     */
    public static function decorate_submissions(int $confprogramid, array $submissions): array {
        $result = [];
        foreach ($submissions as $id => $submission) {
            $round = rounds::get_current_round($confprogramid, (int) $id);
            $result[$id] = (object) [
                'submission'     => $submission,
                'round'          => $round,
                'latestdecision' => rounds::get_latest_decision($confprogramid, (int) $id),
                'reviews'        => api::get_reviews_for_round($confprogramid, (int) $id, $round),
            ];
        }
        return $result;
    }

    /**
     * Filters an already-decorated set down to a single decision-status bucket.
     *
     * @param array $decorated The id-keyed output of decorate_submissions()
     * @param string $status '' (no filter), 'none' (no decision yet), or a decision value
     * @return array The same id-keyed shape, filtered
     */
    public static function filter_by_decision_status(array $decorated, string $status): array {
        if ($status === '') {
            return $decorated;
        }

        return array_filter($decorated, function (\stdClass $row) use ($status): bool {
            if ($status === 'none') {
                return $row->latestdecision === null;
            }
            return $row->latestdecision !== null && $row->latestdecision->decision === $status;
        });
    }

    /**
     * Filters a raw (non-decorated) id-keyed submission set down to only
     * those whose latest decision is 'resubmit'. Shared by decisions.php's
     * "start a new round" bulk-link count and assign.php's ?resubmitted=1
     * filter mode -- both need the identical set.
     *
     * @param int $confprogramid The confprogram instance id
     * @param array $submissions Id-keyed raw submission objects
     * @return array The same id-keyed shape, filtered to resubmit-decided ones
     */
    public static function filter_resubmitted(int $confprogramid, array $submissions): array {
        $result = [];
        foreach ($submissions as $id => $submission) {
            $latest = rounds::get_latest_decision($confprogramid, (int) $id);
            if ($latest !== null && $latest->decision === 'resubmit') {
                $result[$id] = $submission;
            }
        }
        return $result;
    }

    /**
     * Records the same decision for every valid submission in a batch,
     * re-verifying instance membership and unvetted-exclusion per id exactly
     * like the single-row handler in decisions.php already does. An invalid
     * id in the batch is silently skipped, not an error -- a stale or crafted
     * id must never abort the whole batch or leak which ids were valid,
     * mirroring assign.php's existing assigngroup bulk handler.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $confsubmissionsinstanceid The confsubmissions instance this confprogram vets
     * @param array $submissionids The submitted batch of ids (untrusted)
     * @param string $decision One of accept/reject/resubmit/waitlist
     * @param array $unvettedids submissionids still awaiting vetting, to exclude
     * @param int $userid The user recording the decision
     * @return int How many submissions actually got a decision recorded
     */
    public static function apply_bulk_decision(
        int $confprogramid,
        int $confsubmissionsinstanceid,
        array $submissionids,
        string $decision,
        array $unvettedids,
        int $userid
    ): int {
        if (!in_array($decision, ['accept', 'reject', 'resubmit', 'waitlist'], true)) {
            return 0;
        }

        // Normalize unvettedids to ints for consistent comparison.
        $unvettedids = array_map('intval', $unvettedids);

        $count = 0;
        foreach ($submissionids as $submissionid) {
            $submissionid = (int) $submissionid;

            if (in_array($submissionid, $unvettedids, true)) {
                continue;
            }

            $submission = submissions_api::get_submission($submissionid);
            if (!$submission || (int) $submission->confsubmissions !== $confsubmissionsinstanceid) {
                continue;
            }

            $round = rounds::get_current_round($confprogramid, $submissionid);
            api::record_decision($confprogramid, $submissionid, $decision, $round, $userid);
            $count++;
        }

        return $count;
    }
}
