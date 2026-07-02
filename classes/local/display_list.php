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
 * Builds the Display-phase accepted-submissions list: which submissions qualify,
 * decorated with (optional) schedule info, sorted, and grouped by calendar day.
 *
 * Kept out of view.php (which stays a thin page-logic file, matching the Review
 * phase pages) so the accept-decision + instance-scoping filter is independently
 * unit-testable without rendering anything.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class display_list {
    /**
     * Returns the accept-decided submissions belonging to a single mod_confsubmissions
     * instance, for a single confprogram instance's Display phase.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $confsubmissionsid The mod_confsubmissions instance id
     * @return \stdClass[] Accepted submission records
     */
    public static function get_accepted_submissions(int $confprogramid, int $confsubmissionsid): array {
        $submissions = submissions_api::get_submissions_for_instance($confsubmissionsid);

        return self::filter_accepted($submissions, $confprogramid);
    }

    /**
     * Filters a list of submissions down to those whose most recent decision (within a
     * single confprogram instance) is 'accept'. Submissions from any other instance
     * that ended up in the input list are, by definition, never accept-decided within
     * THIS confprogram instance (rounds::get_latest_decision() is scoped by
     * confprogramid), so this doubles as instance scoping as long as the caller
     * already scoped the input list to the right mod_confsubmissions instance (as
     * get_accepted_submissions() does).
     *
     * Factored out from get_accepted_submissions() so it can be unit tested against a
     * hand-built array of submission stubs, without needing a real
     * mod_confsubmissions instance.
     *
     * @param \stdClass[] $submissions Submission records (must have an 'id' property)
     * @param int $confprogramid The confprogram instance id
     * @return \stdClass[] The accepted subset, reindexed from 0
     */
    public static function filter_accepted(array $submissions, int $confprogramid): array {
        $accepted = [];

        foreach ($submissions as $submission) {
            $decision = rounds::get_latest_decision($confprogramid, (int) $submission->id);
            if ($decision !== null && $decision->decision === 'accept') {
                $accepted[] = $submission;
            }
        }

        return array_values($accepted);
    }

    /**
     * Decorates a list of submissions with their (optional) schedule info.
     *
     * @param \stdClass[] $submissions Submission records
     * @return \stdClass[] Objects with 'submission' and 'schedule' properties
     */
    public static function attach_schedule(array $submissions): array {
        $decorated = [];

        foreach ($submissions as $submission) {
            $decorated[] = (object) [
                'submission' => $submission,
                'schedule'   => schedule_info::get_for_submission((int) $submission->id),
            ];
        }

        return $decorated;
    }

    /**
     * Sorts decorated rows chronologically by schedule start time (unscheduled rows
     * last), then alphabetically by title, per the spec's default ordering.
     *
     * @param \stdClass[] $decorated Rows as returned by attach_schedule()
     * @return \stdClass[] The same rows, sorted (array is also sorted in place)
     */
    public static function sort_by_schedule_then_title(array $decorated): array {
        usort($decorated, function ($a, $b) {
            $starta = $a->schedule['starttime'] ?? null;
            $startb = $b->schedule['starttime'] ?? null;

            if ($starta !== null && $startb !== null && $starta !== $startb) {
                return $starta <=> $startb;
            }

            if (($starta === null) !== ($startb === null)) {
                // Exactly one of the two is unscheduled: the unscheduled one sorts last.
                return $starta === null ? 1 : -1;
            }

            return strcasecmp((string) $a->submission->title, (string) $b->submission->title);
        });

        return $decorated;
    }

    /**
     * Groups decorated rows by calendar day, derived from schedule start time.
     *
     * When none of the rows have schedule info (the common case while
     * mod_confscheduler is not installed), everything lands in a single 'unscheduled'
     * bucket and the caller should not show a day selector at all — see the spec on
     * this soft integration. When at least one row has schedule info, rows without one
     * still get their own 'unscheduled' bucket rather than being dropped.
     *
     * @param \stdClass[] $decorated Rows as returned by attach_schedule()
     * @return array<string, \stdClass[]> Rows keyed by day ('Y-m-d') or 'unscheduled'
     */
    public static function group_by_day(array $decorated): array {
        $groups = [];
        $hasscheduled = false;

        foreach ($decorated as $row) {
            if (!empty($row->schedule['starttime'])) {
                $hasscheduled = true;
                $key = userdate((int) $row->schedule['starttime'], '%Y-%m-%d');
                $groups[$key][] = $row;
            } else {
                $groups['unscheduled'][] = $row;
            }
        }

        if (!$hasscheduled) {
            return ['unscheduled' => $decorated];
        }

        return $groups;
    }

    /**
     * Picks the day key (from group_by_day()'s keys) whose calendar date is nearest to
     * now, ignoring the 'unscheduled' bucket. Falls back to 'unscheduled' only if there
     * are no real day keys at all.
     *
     * @param array $groups Rows keyed by day ('Y-m-d') or 'unscheduled', as returned by group_by_day()
     * @return string A day key ('Y-m-d') or 'unscheduled'
     */
    public static function default_day_key(array $groups): string {
        $now = time();
        $best = null;
        $bestdiff = null;

        foreach (array_keys($groups) as $key) {
            if ($key === 'unscheduled') {
                continue;
            }
            $timestamp = strtotime($key . ' 00:00:00');
            if ($timestamp === false) {
                continue;
            }
            $diff = abs($timestamp - $now);
            if ($bestdiff === null || $diff < $bestdiff) {
                $bestdiff = $diff;
                $best = $key;
            }
        }

        return $best ?? 'unscheduled';
    }
}
