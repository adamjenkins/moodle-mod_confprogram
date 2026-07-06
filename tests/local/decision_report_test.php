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
 * Tests for \mod_confprogram\local\decision_report: the data layer behind
 * decisions.php's table and assign.php's "resubmitted" filter mode.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(decision_report::class)]
final class decision_report_test extends advanced_testcase {
    /**
     * Creates a course, a confsubmissions instance, and a confprogram instance
     * pointed at it. Mirrors \mod_confprogram\local\rounds_test's own helper.
     *
     * @return array{0: int, 1: int} [confprogramid, confsubmissionsid]
     */
    private function create_confprogram(): array {
        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);

        return [(int) $confprogram->id, (int) $confsubmissions->id];
    }

    /**
     * Inserts a bare confsubmissions_submission row directly (mirrors
     * \mod_confprogram\local\field_formatter_test's own helper).
     */
    private function create_submission(int $confsubmissionsid): \stdClass {
        global $DB;

        $userid = $this->getDataGenerator()->create_user()->id;
        $id = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissionsid,
            'userid'          => $userid,
            'trackid'         => null,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        return $DB->get_record('confsubmissions_submission', ['id' => $id]);
    }

    /**
     * A submission with no decision yet: round 1, null latestdecision, empty reviews.
     */
    public function test_decorate_submission_with_no_decision(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $submission = $this->create_submission($confsubmissionsid);

        $decorated = decision_report::decorate_submissions($confprogramid, [$submission->id => $submission]);

        $this->assertCount(1, $decorated);
        $row = $decorated[$submission->id];
        $this->assertSame($submission->id, $row->submission->id);
        $this->assertSame(1, $row->round);
        $this->assertNull($row->latestdecision);
        $this->assertSame([], $row->reviews);
    }

    /**
     * A submission decided 'resubmit' at round 1 is decorated as round 2, with
     * latestdecision populated -- matches \mod_confprogram\local\rounds's own
     * documented round-advancement rule.
     */
    public function test_decorate_submission_after_resubmit_decision(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $submission = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, (int) $submission->id, 'resubmit', 1, (int) $decider->id);

        $decorated = decision_report::decorate_submissions($confprogramid, [$submission->id => $submission]);

        $row = $decorated[$submission->id];
        $this->assertSame(2, $row->round);
        $this->assertNotNull($row->latestdecision);
        $this->assertSame('resubmit', $row->latestdecision->decision);
    }

    /**
     * Multiple submissions are each decorated independently, keyed by their own id.
     */
    public function test_decorate_multiple_submissions_independently(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $submission1 = $this->create_submission($confsubmissionsid);
        $submission2 = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, (int) $submission1->id, 'accept', 1, (int) $decider->id);

        $decorated = decision_report::decorate_submissions($confprogramid, [
            $submission1->id => $submission1,
            $submission2->id => $submission2,
        ]);

        $this->assertCount(2, $decorated);
        $this->assertSame('accept', $decorated[$submission1->id]->latestdecision->decision);
        $this->assertNull($decorated[$submission2->id]->latestdecision);
    }

    /**
     * An empty status filters nothing -- returns every row unchanged.
     */
    public function test_filter_by_decision_status_empty_returns_all(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $submission = $this->create_submission($confsubmissionsid);
        $decorated = decision_report::decorate_submissions($confprogramid, [$submission->id => $submission]);

        $filtered = decision_report::filter_by_decision_status($decorated, '');

        $this->assertCount(1, $filtered);
    }

    /**
     * 'none' keeps only submissions with no decision recorded at all.
     */
    public function test_filter_by_decision_status_none(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $undecided = $this->create_submission($confsubmissionsid);
        $decided = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();
        api::record_decision($confprogramid, (int) $decided->id, 'accept', 1, (int) $decider->id);

        $decorated = decision_report::decorate_submissions($confprogramid, [
            $undecided->id => $undecided,
            $decided->id   => $decided,
        ]);

        $filtered = decision_report::filter_by_decision_status($decorated, 'none');

        $this->assertCount(1, $filtered);
        $this->assertArrayHasKey($undecided->id, $filtered);
    }

    /**
     * A real decision value keeps only submissions whose latest decision matches it.
     */
    public function test_filter_by_decision_status_specific_decision(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $accepted = $this->create_submission($confsubmissionsid);
        $rejected = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();
        api::record_decision($confprogramid, (int) $accepted->id, 'accept', 1, (int) $decider->id);
        api::record_decision($confprogramid, (int) $rejected->id, 'reject', 1, (int) $decider->id);

        $decorated = decision_report::decorate_submissions($confprogramid, [
            $accepted->id => $accepted,
            $rejected->id => $rejected,
        ]);

        $filtered = decision_report::filter_by_decision_status($decorated, 'accept');

        $this->assertCount(1, $filtered);
        $this->assertArrayHasKey($accepted->id, $filtered);
    }
}
