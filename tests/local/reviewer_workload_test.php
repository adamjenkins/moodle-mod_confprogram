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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \mod_confprogram\local\reviewer_workload: the max-reviews cap
 * logic used both as a soft warning on assign.php and a hard block on
 * review.php.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reviewer_workload::class)]
final class reviewer_workload_test extends advanced_testcase {
    /**
     * Creates a course, a confsubmissions instance, and a confprogram instance
     * pointed at it, with the given defaultmaxreviews.
     *
     * @param int $defaultmaxreviews
     * @return \stdClass The confprogram instance record (id cast to int)
     */
    private function create_confprogram(int $defaultmaxreviews = 0): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
            'defaultmaxreviews'   => $defaultmaxreviews,
        ]);
        $confprogram->id = (int) $confprogram->id;

        return $confprogram;
    }

    /**
     * Inserts a confprogram_review row directly (bypassing the grading API,
     * which is not needed for this class's pure counting logic).
     *
     * @param int $confprogramid
     * @param int $submissionid
     * @param int $reviewerid
     * @param int $round
     * @param int $gradinginstanceid 0 means "placeholder, not actually submitted"
     * @return void
     */
    private function insert_review(
        int $confprogramid,
        int $submissionid,
        int $reviewerid,
        int $round,
        int $gradinginstanceid
    ): void {
        global $DB;

        $DB->insert_record('confprogram_review', (object) [
            'confprogram'       => $confprogramid,
            'submissionid'      => $submissionid,
            'reviewerid'        => $reviewerid,
            'round'             => $round,
            'gradinginstanceid' => $gradinginstanceid,
            'grade'             => $gradinginstanceid ? 75.0 : null,
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * get_max() returns defaultmaxreviews when there is no per-reviewer override.
     */
    public function test_get_max_uses_default_when_no_override(): void {
        $this->resetAfterTest();

        $confprogram = $this->create_confprogram(5);
        $reviewer = $this->getDataGenerator()->create_user();

        $this->assertSame(5, reviewer_workload::get_max($confprogram->id, (int) $reviewer->id));
    }

    /**
     * get_max() prefers a confprogram_reviewermax override over the instance default.
     */
    public function test_get_max_prefers_override(): void {
        global $DB;
        $this->resetAfterTest();

        $confprogram = $this->create_confprogram(5);
        $reviewer = $this->getDataGenerator()->create_user();

        $DB->insert_record('confprogram_reviewermax', (object) [
            'confprogram' => $confprogram->id,
            'userid'      => $reviewer->id,
            'maxreviews'  => 2,
        ]);

        $this->assertSame(2, reviewer_workload::get_max($confprogram->id, (int) $reviewer->id));
    }

    /**
     * completed_count() only counts rows with a real gradinginstanceid, excluding
     * placeholder rows created when a reviewer opens (but has not submitted) a
     * review; it is also scoped correctly by round.
     */
    public function test_completed_count_excludes_placeholders_and_scopes_by_round(): void {
        $this->resetAfterTest();

        $confprogram = $this->create_confprogram(0);
        $reviewer = $this->getDataGenerator()->create_user();

        $this->insert_review($confprogram->id, 101, (int) $reviewer->id, 1, 555); // Completed, round 1.
        $this->insert_review($confprogram->id, 102, (int) $reviewer->id, 1, 0);   // Placeholder only, round 1.
        $this->insert_review($confprogram->id, 103, (int) $reviewer->id, 2, 556); // Completed, round 2.

        $this->assertSame(1, reviewer_workload::completed_count($confprogram->id, (int) $reviewer->id, 1));
        $this->assertSame(1, reviewer_workload::completed_count($confprogram->id, (int) $reviewer->id, 2));
        $this->assertSame(0, reviewer_workload::completed_count($confprogram->id, (int) $reviewer->id, 3));
    }

    /**
     * A cap of 0 means unlimited: has_capacity() is always true and remaining()
     * is always null, no matter how many reviews have been completed.
     */
    public function test_zero_cap_is_unlimited(): void {
        $this->resetAfterTest();

        $confprogram = $this->create_confprogram(0);
        $reviewer = $this->getDataGenerator()->create_user();

        for ($i = 0; $i < 10; $i++) {
            $this->insert_review($confprogram->id, 200 + $i, (int) $reviewer->id, 1, 1000 + $i);
        }

        $this->assertTrue(reviewer_workload::has_capacity($confprogram->id, (int) $reviewer->id, 1));
        $this->assertNull(reviewer_workload::remaining($confprogram->id, (int) $reviewer->id, 1));
    }

    /**
     * A positive cap correctly reports remaining capacity and flips
     * has_capacity() to false once the cap is reached.
     */
    public function test_positive_cap_tracks_remaining_and_capacity(): void {
        $this->resetAfterTest();

        $confprogram = $this->create_confprogram(2);
        $reviewer = $this->getDataGenerator()->create_user();

        $this->assertTrue(reviewer_workload::has_capacity($confprogram->id, (int) $reviewer->id, 1));
        $this->assertSame(2, reviewer_workload::remaining($confprogram->id, (int) $reviewer->id, 1));

        $this->insert_review($confprogram->id, 301, (int) $reviewer->id, 1, 1001);
        $this->assertTrue(reviewer_workload::has_capacity($confprogram->id, (int) $reviewer->id, 1));
        $this->assertSame(1, reviewer_workload::remaining($confprogram->id, (int) $reviewer->id, 1));

        $this->insert_review($confprogram->id, 302, (int) $reviewer->id, 1, 1002);
        $this->assertFalse(reviewer_workload::has_capacity($confprogram->id, (int) $reviewer->id, 1));
        $this->assertSame(0, reviewer_workload::remaining($confprogram->id, (int) $reviewer->id, 1));

        // A different round is unaffected.
        $this->assertTrue(reviewer_workload::has_capacity($confprogram->id, (int) $reviewer->id, 2));
    }
}
