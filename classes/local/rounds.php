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
 * Review-round derivation: the trickiest state machine in the plugin.
 *
 * There is deliberately no "current round" column anywhere in the schema.
 * Round is *derived* from confprogram_decision, and confprogram_review rows
 * are naturally partitioned by round so that reviews from an earlier round
 * are never overwritten or confused with reviews from a later one.
 *
 * The rule, exactly:
 * - If a submission has no decision yet, it is in round 1.
 * - If a submission's most recent decision (highest round, ties broken by
 *   most recent timecreated) is NOT 'resubmit' (i.e. accept/reject/waitlist),
 *   the submission's round stays at that decision's round: the review cycle
 *   for that round is considered finished/final.
 *   - If a submission's most recent decision IS 'resubmit', the submission's
 *   round is that decision's round + 1: a resubmit decision always and
 *   immediately opens the next round for review purposes.
 *
 * Design note on "starting a new review round": confprogram_assignment has no
 * round column, and reviewer assignments are NOT round-scoped by design —
 * reviewers assigned to a submission stay assigned across rounds, and simply
 * review it again once a new round opens (their previous round's
 * confprogram_review row is untouched; a fresh one is created for the new
 * round). This means the round advances to N+1 as soon as the 'resubmit'
 * decision itself is recorded, WITHOUT waiting for the submitter to actually
 * edit+resubmit via mod_confsubmissions's edit.php (this plugin has no way to
 * observe that happening in another plugin's table). In practice this is
 * harmless: a reviewer who opens their queue before the submitter has
 * actually revised the submission just reviews the (as yet unrevised)
 * content again; nothing is lost or corrupted, and once the submitter does
 * revise it, the same round's review reflects the revision.
 *
 * decisions.php's "Start new review round" action (see get_current_round()
 * callers there) does not need to mutate any state for this reason: the
 * round has already logically advanced the moment the resubmit decision was
 * saved. That action is a navigational convenience only — it takes an
 * editing-role user straight to assign.php, filtered to the resubmitted
 * submission, which is genuinely "the assignment screen shows resubmitted
 * submissions again for re-assignment" from the spec, and requires no schema
 * beyond what already exists.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rounds {
    /**
     * Returns the most recent confprogram_decision row for a submission
     * within a single confprogram instance (highest round, ties broken by
     * most recent timecreated), or null if no decision has been made yet.
     *
     * Deliberately scoped by confprogramid as well as submissionid: a
     * submissionid is a cross-plugin reference into mod_confsubmissions and
     * is only guaranteed unique there, not within confprogram_decision (in
     * the unlikely case more than one confprogram instance vets overlapping
     * submissions). \mod_confprogram\api::get_decision() is the unscoped
     * convenience version used by simpler callers; this scoped version is
     * what round derivation needs to be correct.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @return \stdClass|null
     */
    public static function get_latest_decision(int $confprogramid, int $submissionid): ?\stdClass {
        global $DB;

        $decisions = $DB->get_records(
            'confprogram_decision',
            ['confprogram' => $confprogramid, 'submissionid' => $submissionid],
            'round DESC, timecreated DESC',
            '*',
            0,
            1
        );

        return $decisions ? reset($decisions) : null;
    }

    /**
     * Returns the review round a submission is currently in, per the rule
     * documented on this class.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @return int The current round, starting from 1
     */
    public static function get_current_round(int $confprogramid, int $submissionid): int {
        $latest = self::get_latest_decision($confprogramid, $submissionid);

        if ($latest === null) {
            return 1;
        }

        if ($latest->decision === 'resubmit') {
            return (int) $latest->round + 1;
        }

        return (int) $latest->round;
    }

    /**
     * Whether a submission's most recent decision was 'resubmit', i.e. it is
     * currently in the resubmission flow: the submitter may edit their
     * submission and see the feedback that led to the resubmit call.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @return bool
     */
    public static function is_awaiting_resubmission(int $confprogramid, int $submissionid): bool {
        $latest = self::get_latest_decision($confprogramid, $submissionid);

        return $latest !== null && $latest->decision === 'resubmit';
    }
}
