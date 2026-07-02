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
     * @return array{0: \stdClass, 1: int} The course record and the confprogram instance id
     */
    private function create_confprogram(): array {
        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);

        return [$course, (int) $confprogram->id];
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
}
