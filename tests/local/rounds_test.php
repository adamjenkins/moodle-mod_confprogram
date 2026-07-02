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
 * Tests for \mod_confprogram\local\rounds: the review-round derivation state
 * machine documented in detail on that class.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(rounds::class)]
final class rounds_test extends advanced_testcase {
    /**
     * Creates a course, a confsubmissions instance, and a confprogram instance
     * pointed at it.
     *
     * @return int The confprogram instance id
     */
    private function create_confprogram(): int {
        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);

        return (int) $confprogram->id;
    }

    /**
     * With no decision at all, a submission is in round 1.
     */
    public function test_no_decision_is_round_one(): void {
        $this->resetAfterTest();

        $confprogramid = $this->create_confprogram();

        $this->assertSame(1, rounds::get_current_round($confprogramid, 42));
        $this->assertNull(rounds::get_latest_decision($confprogramid, 42));
        $this->assertFalse(rounds::is_awaiting_resubmission($confprogramid, 42));
    }

    /**
     * A non-resubmit decision (accept/reject/waitlist) leaves the submission's
     * round at that decision's own round: the review cycle is final.
     */
    public function test_non_resubmit_decision_stays_at_its_round(): void {
        $this->resetAfterTest();

        $confprogramid = $this->create_confprogram();
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, 42, 'accept', 1, (int) $decider->id);

        $this->assertSame(1, rounds::get_current_round($confprogramid, 42));
        $this->assertFalse(rounds::is_awaiting_resubmission($confprogramid, 42));
    }

    /**
     * A resubmit decision immediately advances the current round to
     * decision-round + 1, per the documented rule (the round advances the
     * moment the decision is saved, without waiting for the submitter to
     * actually revise their submission in mod_confsubmissions).
     */
    public function test_resubmit_decision_advances_round(): void {
        $this->resetAfterTest();

        $confprogramid = $this->create_confprogram();
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, 42, 'resubmit', 1, (int) $decider->id);

        $this->assertSame(2, rounds::get_current_round($confprogramid, 42));
        $this->assertTrue(rounds::is_awaiting_resubmission($confprogramid, 42));

        $latest = rounds::get_latest_decision($confprogramid, 42);
        $this->assertSame('resubmit', $latest->decision);
        $this->assertSame(1, (int) $latest->round);
    }

    /**
     * Multiple resubmit cycles keep advancing the round, one at a time.
     */
    public function test_multiple_resubmit_cycles_advance_round_each_time(): void {
        $this->resetAfterTest();

        $confprogramid = $this->create_confprogram();
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, 42, 'resubmit', 1, (int) $decider->id);
        $this->assertSame(2, rounds::get_current_round($confprogramid, 42));

        api::record_decision($confprogramid, 42, 'resubmit', 2, (int) $decider->id);
        $this->assertSame(3, rounds::get_current_round($confprogramid, 42));

        // Finally accepted at round 3: round stays at 3, no longer awaiting resubmission.
        api::record_decision($confprogramid, 42, 'accept', 3, (int) $decider->id);
        $this->assertSame(3, rounds::get_current_round($confprogramid, 42));
        $this->assertFalse(rounds::is_awaiting_resubmission($confprogramid, 42));
    }

    /**
     * get_latest_decision() picks the highest round, and ties within the same
     * round are broken by most recent timecreated (confprogram_decision is an
     * append-only log, so re-deciding a round is possible).
     */
    public function test_latest_decision_breaks_ties_by_timecreated(): void {
        global $DB;
        $this->resetAfterTest();

        $confprogramid = $this->create_confprogram();
        $decider = $this->getDataGenerator()->create_user();

        $firstid = api::record_decision($confprogramid, 42, 'waitlist', 1, (int) $decider->id);
        // Force distinct timecreated values deterministically rather than relying on real
        // wall-clock time passing between two record_decision() calls in the same test.
        $DB->set_field('confprogram_decision', 'timecreated', 1000, ['id' => $firstid]);

        $secondid = api::record_decision($confprogramid, 42, 'accept', 1, (int) $decider->id);
        $DB->set_field('confprogram_decision', 'timecreated', 2000, ['id' => $secondid]);

        $latest = rounds::get_latest_decision($confprogramid, 42);
        $this->assertSame($secondid, (int) $latest->id);
        $this->assertSame('accept', $latest->decision);
    }

    /**
     * Round derivation is scoped by confprogramid: two different confprogram
     * instances vetting a same-numbered submissionid (an edge case, but not
     * impossible for a cross-plugin reference) do not interfere with each
     * other's round state.
     */
    public function test_scoped_by_confprogramid(): void {
        $this->resetAfterTest();

        $confprogramid1 = $this->create_confprogram();
        $confprogramid2 = $this->create_confprogram();
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid1, 99, 'resubmit', 1, (int) $decider->id);

        $this->assertSame(2, rounds::get_current_round($confprogramid1, 99));
        $this->assertSame(1, rounds::get_current_round($confprogramid2, 99));
    }
}
