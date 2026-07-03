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

namespace mod_confprogram\local;

use advanced_testcase;
use mod_confprogram\api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \mod_confprogram\local\display_list: the accept-decision +
 * instance-scoping filter behind the Display-phase accepted-submissions list, plus
 * its sorting and day-grouping helpers.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(display_list::class)]
final class display_list_test extends advanced_testcase {
    /**
     * Creates a course, a confsubmissions instance, and a confprogram instance
     * pointed at it.
     *
     * @return array{0: \stdClass, 1: int, 2: int} The course, confsubmissions instance id, confprogram instance id
     */
    private function create_confprogram(): array {
        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);

        return [$course, (int) $confsubmissions->id, (int) $confprogram->id];
    }

    /**
     * Creates a bare confsubmissions_submission row directly (no need to go through
     * the submission form for these filter-logic tests).
     *
     * @param int $confsubmissionsid
     * @param string $title
     * @return \stdClass
     */
    private function create_submission(int $confsubmissionsid, string $title): \stdClass {
        global $DB;

        $userid = $this->getDataGenerator()->create_user()->id;
        $id = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissionsid,
            'userid'          => $userid,
            'title'           => $title,
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        return $DB->get_record('confsubmissions_submission', ['id' => $id]);
    }

    /**
     * filter_accepted() keeps only submissions whose most recent decision in this
     * confprogram instance is 'accept', excluding submissions with no decision, a
     * non-accept decision, and submissions belonging to a different confprogram
     * instance's decision history.
     */
    public function test_filter_accepted_keeps_only_currently_accepted(): void {
        $this->resetAfterTest();

        [, $confsubmissionsid, $confprogramid] = $this->create_confprogram();
        $decider = $this->getDataGenerator()->create_user();

        $accepted = $this->create_submission($confsubmissionsid, 'Accepted talk');
        $rejected = $this->create_submission($confsubmissionsid, 'Rejected talk');
        $undecided = $this->create_submission($confsubmissionsid, 'Undecided talk');
        $resubmitted = $this->create_submission($confsubmissionsid, 'Resubmitted talk');

        api::record_decision($confprogramid, (int) $accepted->id, 'accept', 1, (int) $decider->id);
        api::record_decision($confprogramid, (int) $rejected->id, 'reject', 1, (int) $decider->id);
        api::record_decision($confprogramid, (int) $resubmitted->id, 'resubmit', 1, (int) $decider->id);

        $result = display_list::filter_accepted(
            [$accepted, $rejected, $undecided, $resubmitted],
            $confprogramid
        );

        $this->assertCount(1, $result);
        $this->assertSame((int) $accepted->id, (int) reset($result)->id);
    }

    /**
     * filter_accepted() is scoped by confprogramid: an accept decision recorded
     * against one confprogram instance does not make the submission appear as
     * accepted when filtering for a different confprogram instance.
     */
    public function test_filter_accepted_is_scoped_by_confprogramid(): void {
        $this->resetAfterTest();

        [$course, $confsubmissionsid, $confprogramid1] = $this->create_confprogram();
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissionsid);
        $confprogram2 = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);
        $decider = $this->getDataGenerator()->create_user();

        $submission = $this->create_submission($confsubmissionsid, 'Talk');
        api::record_decision($confprogramid1, (int) $submission->id, 'accept', 1, (int) $decider->id);

        $this->assertCount(1, display_list::filter_accepted([$submission], $confprogramid1));
        $this->assertCount(0, display_list::filter_accepted([$submission], (int) $confprogram2->id));
    }

    /**
     * get_accepted_submissions() end-to-end: only accept-decided submissions
     * belonging to the given mod_confsubmissions instance are returned.
     */
    public function test_get_accepted_submissions_end_to_end(): void {
        $this->resetAfterTest();

        [, $confsubmissionsid, $confprogramid] = $this->create_confprogram();
        $decider = $this->getDataGenerator()->create_user();

        $accepted = $this->create_submission($confsubmissionsid, 'Accepted talk');
        $undecided = $this->create_submission($confsubmissionsid, 'Undecided talk');
        api::record_decision($confprogramid, (int) $accepted->id, 'accept', 1, (int) $decider->id);

        $result = display_list::get_accepted_submissions($confprogramid, $confsubmissionsid);

        $this->assertCount(1, $result);
        $this->assertSame((int) $accepted->id, (int) reset($result)->id);
    }

    /**
     * sort_by_schedule_then_title() falls back to alphabetical-by-title when no row
     * has schedule info (the schedule_info::get_for_submission() null case), which is
     * the state of every row in this environment since mod_confscheduler is absent.
     */
    public function test_sort_falls_back_to_title_when_unscheduled(): void {
        $b = (object) ['submission' => (object) ['title' => 'Bravo talk'], 'schedule' => null];
        $a = (object) ['submission' => (object) ['title' => 'Alpha talk'], 'schedule' => null];

        $sorted = display_list::sort_by_schedule_then_title([$b, $a]);

        $this->assertSame('Alpha talk', $sorted[0]->submission->title);
        $this->assertSame('Bravo talk', $sorted[1]->submission->title);
    }

    /**
     * sort_by_schedule_then_title() puts scheduled rows first (chronologically),
     * unscheduled rows last.
     */
    public function test_sort_puts_scheduled_rows_before_unscheduled(): void {
        $unscheduled = (object) ['submission' => (object) ['title' => 'Aardvark talk'], 'schedule' => null];
        $later = (object) [
            'submission' => (object) ['title' => 'Zzz talk'],
            'schedule'   => ['starttime' => 2000, 'endtime' => 2600, 'room' => 'B'],
        ];
        $earlier = (object) [
            'submission' => (object) ['title' => 'Yyy talk'],
            'schedule'   => ['starttime' => 1000, 'endtime' => 1600, 'room' => 'A'],
        ];

        $sorted = display_list::sort_by_schedule_then_title([$unscheduled, $later, $earlier]);

        $this->assertSame('Yyy talk', $sorted[0]->submission->title);
        $this->assertSame('Zzz talk', $sorted[1]->submission->title);
        $this->assertSame('Aardvark talk', $sorted[2]->submission->title);
    }

    /**
     * group_by_day() puts everything into a single 'unscheduled' bucket when no row
     * has schedule info -- the common case in this environment.
     */
    public function test_group_by_day_single_bucket_when_all_unscheduled(): void {
        $rows = [
            (object) ['submission' => (object) ['title' => 'A'], 'schedule' => null],
            (object) ['submission' => (object) ['title' => 'B'], 'schedule' => null],
        ];

        $groups = display_list::group_by_day($rows);

        $this->assertSame(['unscheduled'], array_keys($groups));
        $this->assertCount(2, $groups['unscheduled']);
    }

    /**
     * default_day_key() picks the real day key nearest to now, never 'unscheduled',
     * when at least one real day key exists.
     */
    public function test_default_day_key_picks_nearest_to_now(): void {
        $today = userdate(time(), '%Y-%m-%d');
        $farfuture = userdate(strtotime('+30 days'), '%Y-%m-%d');

        $groups = [
            $farfuture     => [],
            $today         => [],
            'unscheduled'  => [],
        ];

        $this->assertSame($today, display_list::default_day_key($groups));
    }

    /**
     * default_day_key() falls back to 'unscheduled' when there are no real day keys.
     */
    public function test_default_day_key_falls_back_to_unscheduled(): void {
        $this->assertSame('unscheduled', display_list::default_day_key(['unscheduled' => []]));
    }

    /**
     * filter_by_track() is a no-op (returns the input unchanged) when given a
     * trackid of 0, which is how view.php represents "no track filter selected"
     * (Revision round 1, 2026-07-03).
     */
    public function test_filter_by_track_is_noop_for_zero_trackid(): void {
        $rows = [
            (object) ['submission' => (object) ['title' => 'A', 'trackid' => 1]],
            (object) ['submission' => (object) ['title' => 'B', 'trackid' => 2]],
        ];

        $this->assertSame($rows, display_list::filter_by_track($rows, 0));
    }

    /**
     * filter_by_track() keeps only rows whose submission has the given trackid.
     */
    public function test_filter_by_track_keeps_only_matching_rows(): void {
        $matching = (object) ['submission' => (object) ['title' => 'Matches', 'trackid' => 5]];
        $other = (object) ['submission' => (object) ['title' => 'Different track', 'trackid' => 6]];
        $rows = [$matching, $other];

        $result = display_list::filter_by_track($rows, 5);

        $this->assertCount(1, $result);
        $this->assertSame('Matches', reset($result)->submission->title);
    }

    /**
     * filter_by_track() treats a submission with no trackid property (or a null/0
     * trackid) as belonging to no track, so it never matches a real (positive)
     * trackid filter.
     */
    public function test_filter_by_track_excludes_untracked_submissions(): void {
        $untracked = (object) ['submission' => (object) ['title' => 'No track']];
        $nulltrack = (object) ['submission' => (object) ['title' => 'Null track', 'trackid' => null]];

        $result = display_list::filter_by_track([$untracked, $nulltrack], 5);

        $this->assertCount(0, $result);
    }
}
