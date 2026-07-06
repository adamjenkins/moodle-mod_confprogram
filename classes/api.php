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

use mod_confprogram\local\rounds;
use mod_confsubmissions\api as submissions_api;

/**
 * Public integration surface for the Conference Program (vetting) workflow.
 *
 * Covers read accessors over this plugin's own tables (phase, unvetted flags,
 * decisions, favourites) plus the write operations needed by the Review
 * phase screens (assigning reviewers/reviewer groups, upserting rubric
 * review scores, recording decisions, toggling the unvetted flag) and the
 * Display phase (favourite/unfavourite a submission).
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
     * The total number of users who have favourited a submission, across every
     * confprogram instance -- used by mod_confscheduler's room-capacity overbooking
     * warning (user request, 2026-07-05). Not instance-scoped, matching
     * is_favourited()'s own signature/known limitation just below (the same
     * submissionid could theoretically be favourited under more than one confprogram
     * instance; a real fix needs per-(confprogram, submission) tracking throughout,
     * out of scope for this feature).
     *
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @return int
     */
    public static function count_favourites(int $submissionid): int {
        global $DB;

        return $DB->count_records('confprogram_favourite', ['submissionid' => $submissionid]);
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
     * Favourites a submission for a user. A no-op if already favourited.
     *
     * This is one of the two write-side methods a future mod_confscheduler is expected
     * to call directly for its own "my timetable" toggle (per this project's
     * direct-API-coupling, no-shared-plugin architecture), so favourites stay in sync
     * between the two plugins' UIs. See also remove_favourite() and the read-side
     * is_favourited()/get_favourites() above.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @param int $userid The user id favouriting the submission
     * @return void
     */
    public static function add_favourite(int $confprogramid, int $submissionid, int $userid): void {
        global $DB;

        $existing = $DB->record_exists('confprogram_favourite', [
            'confprogram'  => $confprogramid,
            'submissionid' => $submissionid,
            'userid'       => $userid,
        ]);
        if ($existing) {
            return;
        }

        $DB->insert_record('confprogram_favourite', (object) [
            'confprogram'  => $confprogramid,
            'submissionid' => $submissionid,
            'userid'       => $userid,
            'timecreated'  => time(),
        ]);
    }

    /**
     * Removes a user's favourite of a submission. A no-op if not currently favourited.
     *
     * See add_favourite()'s docblock for the mod_confscheduler integration contract
     * this method is also part of.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @param int $userid The user id removing their favourite
     * @return void
     */
    public static function remove_favourite(int $confprogramid, int $submissionid, int $userid): void {
        global $DB;

        $DB->delete_records('confprogram_favourite', [
            'confprogram'  => $confprogramid,
            'submissionid' => $submissionid,
            'userid'       => $userid,
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

    /**
     * Returns the reviewer assignments (individual or group) for a submission
     * within a single confprogram instance.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @return \stdClass[] Array of confprogram_assignment records, keyed by id
     */
    public static function get_assignments(int $confprogramid, int $submissionid): array {
        global $DB;

        return $DB->get_records(
            'confprogram_assignment',
            ['confprogram' => $confprogramid, 'submissionid' => $submissionid],
            'timecreated ASC'
        );
    }

    /**
     * Whether a user is assigned to review a submission, either directly
     * (reviewerid) or via membership of an assigned reviewer group
     * (reviewergroupid).
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @param int $userid The user id to check
     * @return bool
     */
    public static function is_user_assigned(int $confprogramid, int $submissionid, int $userid): bool {
        global $DB;

        $directassignment = $DB->record_exists('confprogram_assignment', [
            'confprogram'  => $confprogramid,
            'submissionid' => $submissionid,
            'reviewerid'   => $userid,
        ]);
        if ($directassignment) {
            return true;
        }

        $groupassignments = $DB->get_records('confprogram_assignment', [
            'confprogram'  => $confprogramid,
            'submissionid' => $submissionid,
        ]);

        foreach ($groupassignments as $assignment) {
            if (!empty($assignment->reviewergroupid) && groups_is_member($assignment->reviewergroupid, $userid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the ids of submissions (within a confprogram instance) that a
     * user is assigned to review, either directly or via reviewer group
     * membership.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $userid The reviewer's user id
     * @return int[] Distinct submission ids, not in any particular order
     */
    public static function get_assigned_submission_ids_for_user(int $confprogramid, int $userid): array {
        global $DB;

        $submissionids = $DB->get_fieldset_select(
            'confprogram_assignment',
            'DISTINCT submissionid',
            'confprogram = :confprogram AND reviewerid = :reviewerid',
            ['confprogram' => $confprogramid, 'reviewerid' => $userid]
        );

        $groupassignments = $DB->get_records_select(
            'confprogram_assignment',
            'confprogram = :confprogram AND reviewergroupid IS NOT NULL',
            ['confprogram' => $confprogramid]
        );
        foreach ($groupassignments as $assignment) {
            if (groups_is_member($assignment->reviewergroupid, $userid)) {
                $submissionids[] = (int) $assignment->submissionid;
            }
        }

        return array_values(array_unique(array_map('intval', $submissionids)));
    }

    /**
     * Assigns an individual reviewer to a submission. A no-op (returns the
     * existing row's id) if this exact assignment already exists.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @param int $reviewerid The reviewer's user id
     * @return int The confprogram_assignment id
     */
    public static function assign_reviewer(int $confprogramid, int $submissionid, int $reviewerid): int {
        global $DB;

        $existing = $DB->get_field('confprogram_assignment', 'id', [
            'confprogram'  => $confprogramid,
            'submissionid' => $submissionid,
            'reviewerid'   => $reviewerid,
        ]);
        if ($existing) {
            return (int) $existing;
        }

        return $DB->insert_record('confprogram_assignment', (object) [
            'confprogram'     => $confprogramid,
            'submissionid'    => $submissionid,
            'reviewerid'      => $reviewerid,
            'reviewergroupid' => null,
            'timecreated'     => time(),
        ]);
    }

    /**
     * Assigns a reviewer group (a standard course group) to a submission. A
     * no-op (returns the existing row's id) if this exact assignment already
     * exists.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @param int $groupid The course group id
     * @return int The confprogram_assignment id
     */
    public static function assign_reviewer_group(int $confprogramid, int $submissionid, int $groupid): int {
        global $DB;

        $existing = $DB->get_field('confprogram_assignment', 'id', [
            'confprogram'     => $confprogramid,
            'submissionid'    => $submissionid,
            'reviewergroupid' => $groupid,
        ]);
        if ($existing) {
            return (int) $existing;
        }

        return $DB->insert_record('confprogram_assignment', (object) [
            'confprogram'     => $confprogramid,
            'submissionid'    => $submissionid,
            'reviewerid'      => null,
            'reviewergroupid' => $groupid,
            'timecreated'     => time(),
        ]);
    }

    /**
     * Removes a reviewer or reviewer-group assignment.
     *
     * @param int $confprogramid The confprogram instance id, so the deletion is scoped to it
     * @param int $assignmentid The confprogram_assignment id
     * @return bool
     */
    public static function unassign(int $confprogramid, int $assignmentid): bool {
        global $DB;

        // Scoped by confprogramid so a managereviewers holder in one instance cannot delete
        // an assignment row belonging to a different confprogram instance by guessing its id.
        return $DB->delete_records('confprogram_assignment', [
            'id'         => $assignmentid,
            'confprogram' => $confprogramid,
        ]);
    }

    /**
     * Returns a single reviewer's review of a submission for a given round,
     * or null if they have not reviewed it (in that round) yet.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @param int $reviewerid The reviewer's user id
     * @param int $round The review round
     * @return \stdClass|null
     */
    public static function get_review(int $confprogramid, int $submissionid, int $reviewerid, int $round): ?\stdClass {
        global $DB;

        $record = $DB->get_record('confprogram_review', [
            'confprogram'  => $confprogramid,
            'submissionid' => $submissionid,
            'reviewerid'   => $reviewerid,
            'round'        => $round,
        ]);

        return $record ?: null;
    }

    /**
     * Returns all COMPLETED reviews of a submission for a given round, across
     * all reviewers.
     *
     * "Completed" excludes placeholder rows with gradinginstanceid = 0: a
     * placeholder is created by upsert_review() the moment a reviewer opens
     * the review form (so a stable itemid exists for the core grading API to
     * use — see review.php for why), before they have actually submitted
     * anything. Such a row must not be reported as a real review here (nor
     * counted in \mod_confprogram\local\reviewer_workload::completed_count(),
     * which applies the same filter): a reviewer who merely opened the form
     * and never submitted has not completed a review.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @param int $round The review round
     * @return \stdClass[] Array of confprogram_review records, keyed by id
     */
    public static function get_reviews_for_round(int $confprogramid, int $submissionid, int $round): array {
        global $DB;

        return $DB->get_records_select(
            'confprogram_review',
            'confprogram = :confprogram AND submissionid = :submissionid AND round = :round AND gradinginstanceid <> 0',
            ['confprogram' => $confprogramid, 'submissionid' => $submissionid, 'round' => $round],
            'timecreated ASC'
        );
    }

    /**
     * Inserts or updates a reviewer's review of a submission for a round.
     * Upserts on the (confprogram, submissionid, reviewerid, round) unique
     * key, matching the grading API instance the review mirrors: re-editing
     * an existing review updates the same row rather than creating a new one.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @param int $reviewerid The reviewer's user id
     * @param int $round The review round
     * @param int $gradinginstanceid The core grading API grading_instances.id
     * @param float|null $grade The numeric grade computed by the rubric
     * @return int The confprogram_review id
     */
    public static function upsert_review(
        int $confprogramid,
        int $submissionid,
        int $reviewerid,
        int $round,
        int $gradinginstanceid,
        ?float $grade
    ): int {
        global $DB;

        $existing = self::get_review($confprogramid, $submissionid, $reviewerid, $round);
        $now = time();

        if ($existing) {
            $DB->update_record('confprogram_review', (object) [
                'id'                => $existing->id,
                'gradinginstanceid' => $gradinginstanceid,
                'grade'             => $grade,
                'timemodified'      => $now,
            ]);
            return (int) $existing->id;
        }

        return $DB->insert_record('confprogram_review', (object) [
            'confprogram'       => $confprogramid,
            'submissionid'      => $submissionid,
            'reviewerid'        => $reviewerid,
            'round'             => $round,
            'gradinginstanceid' => $gradinginstanceid,
            'grade'             => $grade,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ]);
    }

    /**
     * Records a decision for a submission in a given round. Each call
     * inserts a new row (confprogram_decision is an append-only log), so
     * re-deciding a round is possible and simply adds another entry; readers
     * that want "the" decision for a round should take the most recent by
     * timecreated (see \mod_confprogram\local\rounds::get_latest_decision()).
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @param string $decision One of accept, reject, resubmit, waitlist
     * @param int $round The review round this decision concludes
     * @param int $decidedby The user id making the decision
     * @return int The confprogram_decision id
     */
    public static function record_decision(
        int $confprogramid,
        int $submissionid,
        string $decision,
        int $round,
        int $decidedby
    ): int {
        global $DB;

        $id = $DB->insert_record('confprogram_decision', (object) [
            'confprogram'  => $confprogramid,
            'submissionid' => $submissionid,
            'decision'     => $decision,
            'round'        => $round,
            'decidedby'    => $decidedby,
            'timecreated'  => time(),
        ]);

        // Keep mod_confsubmissions's own confsubmissions_submission.status in sync
        // with Accept/Reject decisions -- but ONLY if this confprogram instance is
        // already in Display phase right now. A submitter's own "my submissions" view
        // in mod_confsubmissions shows this status directly, and the whole point of
        // the Display-phase embargo (see RELATIONS.md) is that accept/reject decisions
        // stay invisible to submitters until an organiser explicitly switches phase.
        // decisions.php (the only current caller of this method) only permits
        // recording a decision during Review phase in the first place, so this branch
        // is currently unreachable in practice -- kept anyway because this method,
        // not any one caller's own gating, is the actual contract (see RELATIONS.md's
        // "no shared library" section). Decisions made during Review phase are synced
        // later, in one batch, by sync_submission_statuses_to_confsubmissions() when
        // the organiser switches to Display -- see view.php's phase-toggle handler.
        $phase = $DB->get_field('confprogram', 'phase', ['id' => $confprogramid], MUST_EXIST);
        if ($phase === 'display') {
            self::sync_one_submission_status_to_confsubmissions($decision, $submissionid);

            // Same Display-phase embargo as the status sync just above (user
            // confirmed, 2026-07-05): a decision notification is never sent while
            // still in Review phase. Decisions made during Review phase are notified
            // later, in one batch, by send_pending_decision_notifications() when the
            // organiser switches to Display -- see view.php's phase-toggle handler.
            // Resubmit decisions are never notified at all (see notifier's docblock).
            if (in_array($decision, \mod_confprogram\local\notifier::NOTIFIABLE_DECISIONS, true)) {
                \mod_confprogram\local\notifier::notify_decision($confprogramid, $submissionid, $decision);
                $DB->set_field('confprogram_decision', 'notifiedtime', time(), ['id' => $id]);
            }
        }

        return $id;
    }

    /**
     * Maps a decision to a mod_confsubmissions status and writes it, if that decision
     * has a corresponding status there. Waitlist/Resubmit decisions deliberately do
     * NOT change status: mod_confsubmissions has no corresponding status value for
     * either yet (only submitted/accepted/rejected exist) -- introducing one is a
     * separately-scoped follow-up, since this fix specifically addresses the reported
     * "accepted or rejected" case.
     *
     * @param string $decision One of accept, reject, resubmit, waitlist
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @return void
     */
    private static function sync_one_submission_status_to_confsubmissions(string $decision, int $submissionid): void {
        if ($decision === 'accept') {
            submissions_api::set_status($submissionid, 'accepted');
        } else if ($decision === 'reject') {
            submissions_api::set_status($submissionid, 'rejected');
        }
    }

    /**
     * Pushes every submission's current Accept/Reject decision state for this
     * confprogram instance into mod_confsubmissions's own status column. Called only
     * when switching Review -> Display (see view.php's phase-toggle handler): any
     * decisions made while still in Review phase are deliberately NOT synced at the
     * moment they're recorded (record_decision() above), so this is what actually
     * lifts the embargo for them, in one batch, the instant Display phase begins.
     *
     * Known limitation (moodle-reviewer, 2026-07-05), same class of issue as
     * \mod_confprogram\api::is_favourited() not being confprogram-instance-scoped (see
     * RELATIONS.md): confsubmissions_submission.status is a single shared column, not
     * itself instance-scoped, so if the same globally-unique submissionid is ever
     * legitimately decided by two different confprogram instances (possible --
     * nothing prevents two instances sharing a confsubmissionscmid), whichever
     * instance syncs last wins and silently overwrites the other's already-synced
     * status. Not a phase-embargo leak (each write only ever happens once its OWN
     * instance is genuinely in Display phase) -- a same-submitter cross-instance
     * data-integrity quirk, left undocumented-but-unfixed here for the same reason
     * is_favourited()'s was: a real fix needs per-(confprogram, submission) status
     * tracking, a larger design change out of scope for this bug fix.
     *
     * Also note: there is deliberately no reverse sync if phase is later toggled back
     * from Display to Review -- once revealed, a status stays revealed. This project
     * treats the Display-phase reveal as one-way per decision, not a fully symmetric,
     * re-enterable embargo; see view.php's phase-toggle handler.
     *
     * @param int $confprogramid The confprogram instance id
     * @return void
     */
    public static function sync_submission_statuses_to_confsubmissions(int $confprogramid): void {
        global $DB;

        $submissionids = $DB->get_fieldset_select(
            'confprogram_decision',
            'DISTINCT submissionid',
            'confprogram = :confprogramid',
            ['confprogramid' => $confprogramid]
        );

        foreach ($submissionids as $submissionid) {
            $latest = rounds::get_latest_decision($confprogramid, (int) $submissionid);
            if ($latest) {
                self::sync_one_submission_status_to_confsubmissions($latest->decision, (int) $submissionid);
            }
        }
    }

    /**
     * Sends the deferred decision notification for every not-yet-notified
     * accept/reject/waitlist decision on this confprogram instance -- called only
     * when switching Review -> Display (see view.php's phase-toggle handler),
     * alongside sync_submission_statuses_to_confsubmissions() above.
     *
     * Unlike that method (which only cares about a submission's CURRENT/latest
     * decision), this notifies for EVERY individual not-yet-notified decision row:
     * a submission waitlisted then later accepted should tell its speakers about
     * both events, not just the final one -- each is a genuine status change worth
     * knowing about.
     *
     * @param int $confprogramid The confprogram instance id
     * @return void
     */
    public static function send_pending_decision_notifications(int $confprogramid): void {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(\mod_confprogram\local\notifier::NOTIFIABLE_DECISIONS);
        $params = array_merge([$confprogramid], $params);

        $pending = $DB->get_records_select(
            'confprogram_decision',
            "confprogram = ? AND notifiedtime = 0 AND decision $insql",
            $params
        );

        foreach ($pending as $decision) {
            \mod_confprogram\local\notifier::notify_decision(
                $confprogramid,
                (int) $decision->submissionid,
                $decision->decision
            );
            $DB->set_field('confprogram_decision', 'notifiedtime', time(), ['id' => $decision->id]);
        }
    }

    /**
     * Flags a submission as unvetted (exempt from review), e.g. a panel or
     * keynote added directly to the programme without going through review.
     * A no-op if already flagged.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @param int $setby The user id flagging the submission
     * @return void
     */
    public static function set_unvetted(int $confprogramid, int $submissionid, int $setby): void {
        global $DB;

        $alreadyunvetted = $DB->record_exists('confprogram_unvetted', [
            'confprogram'  => $confprogramid,
            'submissionid' => $submissionid,
        ]);
        if ($alreadyunvetted) {
            return;
        }

        $DB->insert_record('confprogram_unvetted', (object) [
            'confprogram'  => $confprogramid,
            'submissionid' => $submissionid,
            'setby'        => $setby,
            'timecreated'  => time(),
        ]);
    }

    /**
     * Removes the unvetted flag from a submission, returning it to the
     * normal review workflow.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @return void
     */
    public static function unset_unvetted(int $confprogramid, int $submissionid): void {
        global $DB;

        $DB->delete_records('confprogram_unvetted', [
            'confprogram'  => $confprogramid,
            'submissionid' => $submissionid,
        ]);
    }
}
