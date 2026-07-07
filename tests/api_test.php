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

declare(strict_types=1);

namespace mod_confprogram;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Review phase write operations on \mod_confprogram\api:
 * reviewer/reviewer-group assignment, review upserts, decisions, and the
 * unvetted flag.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(api::class)]
final class api_test extends advanced_testcase {
    /**
     * Creates a course, a confsubmissions instance, and a confprogram instance
     * pointed at it.
     *
     * @return array{0: \stdClass, 1: int, 2: int} [$course, $confprogramid, $confsubmissionsid]
     */
    private function create_confprogram(): array {
        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);

        return [$course, (int) $confprogram->id, (int) $confsubmissions->id];
    }

    /**
     * Creates a bare confsubmissions_submission row directly, belonging to the given
     * confsubmissions instance.
     *
     * @param int $confsubmissionsid
     * @return \stdClass
     */
    private function create_submission(int $confsubmissionsid): \stdClass {
        global $DB;

        $userid = $this->getDataGenerator()->create_user()->id;
        $id = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissionsid,
            'userid'          => $userid,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        return $DB->get_record('confsubmissions_submission', ['id' => $id]);
    }

    /**
     * assign_reviewer() is idempotent: assigning the same reviewer to the same
     * submission twice does not create a duplicate row.
     */
    public function test_assign_reviewer_is_idempotent(): void {
        $this->resetAfterTest();

        [, $confprogramid] = $this->create_confprogram();
        $reviewer = $this->getDataGenerator()->create_user();

        $id1 = api::assign_reviewer($confprogramid, 1, (int) $reviewer->id);
        $id2 = api::assign_reviewer($confprogramid, 1, (int) $reviewer->id);

        $this->assertSame($id1, $id2);
        $this->assertCount(1, api::get_assignments($confprogramid, 1));
    }

    /**
     * is_user_assigned() is true both for a direct individual assignment and
     * for membership of an assigned reviewer group, and false otherwise.
     */
    public function test_is_user_assigned_direct_and_via_group(): void {
        $this->resetAfterTest();

        [$course, $confprogramid] = $this->create_confprogram();
        $direct = $this->getDataGenerator()->create_user();
        $groupmember = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($groupmember->id, $course->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group->id, $groupmember->id);

        api::assign_reviewer($confprogramid, 1, (int) $direct->id);
        api::assign_reviewer_group($confprogramid, 1, (int) $group->id);

        $this->assertTrue(api::is_user_assigned($confprogramid, 1, (int) $direct->id));
        $this->assertTrue(api::is_user_assigned($confprogramid, 1, (int) $groupmember->id));
        $this->assertFalse(api::is_user_assigned($confprogramid, 1, (int) $outsider->id));
    }

    /**
     * upsert_review() inserts a new row on first save, then updates the same
     * row (rather than inserting a second one) on a subsequent save for the
     * same confprogram+submission+reviewer+round.
     */
    public function test_upsert_review_inserts_then_updates(): void {
        $this->resetAfterTest();

        [, $confprogramid] = $this->create_confprogram();
        $reviewer = $this->getDataGenerator()->create_user();

        $id1 = api::upsert_review($confprogramid, 1, (int) $reviewer->id, 1, 0, null);
        $review = api::get_review($confprogramid, 1, (int) $reviewer->id, 1);
        $this->assertSame(0, (int) $review->gradinginstanceid);
        $this->assertNull($review->grade);

        $id2 = api::upsert_review($confprogramid, 1, (int) $reviewer->id, 1, 555, 82.5);
        $this->assertSame($id1, $id2);

        $review = api::get_review($confprogramid, 1, (int) $reviewer->id, 1);
        $this->assertSame(555, (int) $review->gradinginstanceid);
        $this->assertEqualsWithDelta(82.5, (float) $review->grade, 0.0001);
    }

    /**
     * get_reviews_for_round() excludes placeholder rows (gradinginstanceid = 0).
     */
    public function test_get_reviews_for_round_excludes_placeholders(): void {
        $this->resetAfterTest();

        [, $confprogramid] = $this->create_confprogram();
        $reviewera = $this->getDataGenerator()->create_user();
        $reviewerb = $this->getDataGenerator()->create_user();

        api::upsert_review($confprogramid, 1, (int) $reviewera->id, 1, 0, null);
        api::upsert_review($confprogramid, 1, (int) $reviewerb->id, 1, 999, 60.0);

        $reviews = api::get_reviews_for_round($confprogramid, 1, 1);
        $this->assertCount(1, $reviews);
        $this->assertSame((int) $reviewerb->id, (int) reset($reviews)->reviewerid);
    }

    /**
     * set_unvetted()/unset_unvetted() toggle is_unvetted() and set_unvetted()
     * is idempotent (does not error, and does not create duplicate rows, on a
     * submission already flagged).
     */
    public function test_unvetted_toggle(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid] = $this->create_confprogram();
        $setter = $this->getDataGenerator()->create_user();

        $this->assertFalse(api::is_unvetted(1));

        api::set_unvetted($confprogramid, 1, (int) $setter->id);
        $this->assertTrue(api::is_unvetted(1));

        api::set_unvetted($confprogramid, 1, (int) $setter->id);
        $this->assertSame(1, $DB->count_records('confprogram_unvetted', ['confprogram' => $confprogramid, 'submissionid' => 1]));

        api::unset_unvetted($confprogramid, 1);
        $this->assertFalse(api::is_unvetted(1));
    }

    /**
     * record_decision() appends rows rather than overwriting, and
     * get_decision() (the pre-existing, unscoped convenience read) returns
     * the most recent one by round then timecreated.
     */
    public function test_record_decision_and_get_decision(): void {
        $this->resetAfterTest();

        [, $confprogramid] = $this->create_confprogram();
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, 1, 'resubmit', 1, (int) $decider->id);
        api::record_decision($confprogramid, 1, 'accept', 2, (int) $decider->id);

        $decision = api::get_decision(1);
        $this->assertSame('accept', $decision->decision);
        $this->assertSame(2, (int) $decision->round);
    }

    /**
     * record_decision() does NOT push an Accept/Reject decision into
     * mod_confsubmissions's own status column while still in Review phase -- the
     * whole point of the Display-phase embargo is that a submitter's own "my
     * submissions" view (which shows this status directly) must not reveal the
     * decision early. This is the critical security property of the fix for the
     * reported "status doesn't update" bug: it must not overcorrect into leaking
     * embargoed decisions.
     */
    public function test_record_decision_does_not_sync_status_during_review_phase(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $this->assertSame('review', $DB->get_field('confprogram', 'phase', ['id' => $confprogramid]));

        $submission = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, (int) $submission->id, 'accept', 1, (int) $decider->id);

        $this->assertSame('submitted', $DB->get_field('confsubmissions_submission', 'status', ['id' => $submission->id]));
    }

    /**
     * record_decision() DOES push the status immediately when the confprogram
     * instance is already in Display phase (e.g. an organiser re-decides a
     * submission after switching) -- both accept -> accepted and reject -> rejected.
     */
    public function test_record_decision_syncs_status_immediately_during_display_phase(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $DB->set_field('confprogram', 'phase', 'display', ['id' => $confprogramid]);

        $accepted = $this->create_submission($confsubmissionsid);
        $rejected = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, (int) $accepted->id, 'accept', 1, (int) $decider->id);
        api::record_decision($confprogramid, (int) $rejected->id, 'reject', 1, (int) $decider->id);

        $this->assertSame('accepted', $DB->get_field('confsubmissions_submission', 'status', ['id' => $accepted->id]));
        $this->assertSame('rejected', $DB->get_field('confsubmissions_submission', 'status', ['id' => $rejected->id]));
    }

    /**
     * record_decision() does not sync status for a submission already withdrawn, even
     * when called live during Display phase (e.g. an organiser re-decides a submission
     * that was accepted, then withdrawn) -- same guard, other call site, as
     * test_sync_submission_statuses_does_not_overwrite_withdrawn() below covers for
     * the batch phase-toggle sync path.
     */
    public function test_record_decision_does_not_sync_status_for_withdrawn_submission(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $DB->set_field('confprogram', 'phase', 'display', ['id' => $confprogramid]);

        $submission = $this->create_submission($confsubmissionsid);
        $DB->set_field('confsubmissions_submission', 'status', 'withdrawn', ['id' => $submission->id]);
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, (int) $submission->id, 'accept', 1, (int) $decider->id);

        $this->assertSame('withdrawn', $DB->get_field('confsubmissions_submission', 'status', ['id' => $submission->id]));
    }

    /**
     * record_decision() does not touch mod_confsubmissions status for Waitlist/
     * Resubmit decisions, even in Display phase -- mod_confsubmissions has no
     * corresponding status value for either yet (only submitted/accepted/rejected).
     */
    public function test_record_decision_does_not_sync_status_for_waitlist_or_resubmit(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $DB->set_field('confprogram', 'phase', 'display', ['id' => $confprogramid]);

        $waitlisted = $this->create_submission($confsubmissionsid);
        $resubmit = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, (int) $waitlisted->id, 'waitlist', 1, (int) $decider->id);
        api::record_decision($confprogramid, (int) $resubmit->id, 'resubmit', 1, (int) $decider->id);

        $this->assertSame('submitted', $DB->get_field('confsubmissions_submission', 'status', ['id' => $waitlisted->id]));
        $this->assertSame('submitted', $DB->get_field('confsubmissions_submission', 'status', ['id' => $resubmit->id]));
    }

    /**
     * sync_submission_statuses_to_confsubmissions() (called when switching Review ->
     * Display, see view.php) pushes each submission's LATEST decision for this
     * confprogram instance -- a submission resubmitted then later accepted ends up
     * 'accepted', not overwritten back by the earlier 'resubmit' round.
     */
    public function test_sync_submission_statuses_pushes_latest_decision_per_submission(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $decider = $this->getDataGenerator()->create_user();

        $resubmitted = $this->create_submission($confsubmissionsid);
        $rejected = $this->create_submission($confsubmissionsid);

        api::record_decision($confprogramid, (int) $resubmitted->id, 'resubmit', 1, (int) $decider->id);
        api::record_decision($confprogramid, (int) $resubmitted->id, 'accept', 2, (int) $decider->id);
        api::record_decision($confprogramid, (int) $rejected->id, 'reject', 1, (int) $decider->id);

        // Nothing synced yet: both decisions above were recorded while still in
        // Review phase (create_confprogram()'s instance defaults to 'review').
        $this->assertSame('submitted', $DB->get_field('confsubmissions_submission', 'status', ['id' => $resubmitted->id]));

        api::sync_submission_statuses_to_confsubmissions($confprogramid);

        $this->assertSame('accepted', $DB->get_field('confsubmissions_submission', 'status', ['id' => $resubmitted->id]));
        $this->assertSame('rejected', $DB->get_field('confsubmissions_submission', 'status', ['id' => $rejected->id]));
    }

    /**
     * sync_submission_statuses_to_confsubmissions() must never overwrite a submission
     * the submitter has since withdrawn (status 'withdrawn', set entirely inside
     * mod_confsubmissions) back to 'accepted'/'rejected' from this instance's own
     * still-recorded decision -- bug reported 2026-07-07: "cycling confprogram
     * through display/review phases unwithdraws withdrawn presentations". A withdrawn
     * submission must stay withdrawn until explicitly unwithdrawn (mod_confsubmissions
     * editany) or hard-deleted.
     */
    public function test_sync_submission_statuses_does_not_overwrite_withdrawn(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $decider = $this->getDataGenerator()->create_user();

        $submission = $this->create_submission($confsubmissionsid);
        api::record_decision($confprogramid, (int) $submission->id, 'accept', 1, (int) $decider->id);

        // The submitter withdraws after being accepted, entirely inside
        // mod_confsubmissions (mirrors view.php's own Withdraw action).
        $DB->set_field('confsubmissions_submission', 'status', 'withdrawn', ['id' => $submission->id]);

        // Simulates cycling this confprogram instance from Review to Display (or any
        // later re-sync): the instance's own decision for this submission is still
        // 'accept', but that must not resurrect the status.
        api::sync_submission_statuses_to_confsubmissions($confprogramid);

        $this->assertSame('withdrawn', $DB->get_field('confsubmissions_submission', 'status', ['id' => $submission->id]));
    }

    /**
     * sync_submission_statuses_to_confsubmissions() is instance-scoped: it only syncs
     * decisions recorded by the confprogram instance it's called for, not any other
     * instance's decision for the same (globally cross-plugin-shared) submissionid --
     * the same instance-scoping property \mod_confprogram\local\rounds::
     * get_latest_decision() already guarantees, which this method relies on.
     */
    public function test_sync_submission_statuses_is_scoped_to_the_confprogram_instance(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid1, $confsubmissionsid1] = $this->create_confprogram();
        [, $confprogramid2] = $this->create_confprogram();
        $decider = $this->getDataGenerator()->create_user();

        $submission = $this->create_submission($confsubmissionsid1);
        api::record_decision($confprogramid1, (int) $submission->id, 'accept', 1, (int) $decider->id);

        // Sync the OTHER instance, which never decided this submission.
        api::sync_submission_statuses_to_confsubmissions($confprogramid2);

        $this->assertSame('submitted', $DB->get_field('confsubmissions_submission', 'status', ['id' => $submission->id]));
    }

    /**
     * add_favourite()/remove_favourite()/is_favourited() round-trip: favouriting
     * flips is_favourited() to true, is idempotent (no duplicate rows), and
     * unfavouriting flips it back to false.
     */
    public function test_favourite_add_remove_round_trip(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid] = $this->create_confprogram();
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse(api::is_favourited((int) $user->id, 1));

        api::add_favourite($confprogramid, 1, (int) $user->id);
        $this->assertTrue(api::is_favourited((int) $user->id, 1));

        // Idempotent: favouriting an already-favourited submission does not create a
        // duplicate row.
        api::add_favourite($confprogramid, 1, (int) $user->id);
        $this->assertSame(1, $DB->count_records('confprogram_favourite', [
            'confprogram'  => $confprogramid,
            'submissionid' => 1,
            'userid'       => $user->id,
        ]));

        api::remove_favourite($confprogramid, 1, (int) $user->id);
        $this->assertFalse(api::is_favourited((int) $user->id, 1));

        // Unfavouriting something never favourited is a safe no-op.
        api::remove_favourite($confprogramid, 1, (int) $user->id);
        $this->assertFalse(api::is_favourited((int) $user->id, 1));
    }

    /**
     * count_favourites() returns the total number of distinct users who have
     * favourited a submission.
     */
    public function test_count_favourites(): void {
        $this->resetAfterTest();

        [, $confprogramid] = $this->create_confprogram();
        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();

        $this->assertSame(0, api::count_favourites(1));

        api::add_favourite($confprogramid, 1, (int) $userone->id);
        $this->assertSame(1, api::count_favourites(1));

        api::add_favourite($confprogramid, 1, (int) $usertwo->id);
        $this->assertSame(2, api::count_favourites(1));

        // A DIFFERENT submission's count is unaffected.
        $this->assertSame(0, api::count_favourites(2));

        api::remove_favourite($confprogramid, 1, (int) $userone->id);
        $this->assertSame(1, api::count_favourites(1));
    }

    /**
     * get_favourites() returns a user's favourites within a single confprogram
     * instance only, and add_favourite()/remove_favourite() do not affect other
     * users' favourites of the same submission.
     */
    public function test_get_favourites_scoped_per_instance_and_user(): void {
        $this->resetAfterTest();

        [$course, $confprogramid1] = $this->create_confprogram();
        $confsubmissions2 = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm2 = get_coursemodule_from_instance('confsubmissions', $confsubmissions2->id);
        $confprogram2 = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm2->id,
        ]);
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();

        api::add_favourite($confprogramid1, 1, (int) $usera->id);
        api::add_favourite($confprogramid1, 2, (int) $usera->id);
        api::add_favourite($confprogramid1, 1, (int) $userb->id);
        api::add_favourite((int) $confprogram2->id, 1, (int) $usera->id);

        $favouritesa = api::get_favourites((int) $usera->id, $confprogramid1);
        $favouritesb = api::get_favourites((int) $userb->id, $confprogramid1);

        $this->assertCount(2, $favouritesa);
        $this->assertCount(1, $favouritesb);
        $this->assertTrue(api::is_favourited((int) $usera->id, 1));
        $this->assertTrue(api::is_favourited((int) $userb->id, 1));
        $this->assertFalse(api::is_favourited((int) $userb->id, 2));
    }
}
