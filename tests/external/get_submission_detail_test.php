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

namespace mod_confprogram\external;

use advanced_testcase;
use mod_confprogram\api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the get_submission_detail AJAX external function: this is a
 * public-facing endpoint (requires only the broad, guest-inclusive
 * mod/confprogram:viewprogram capability), so its IDOR/embargo hardening is
 * the thing under test here, not the happy path alone.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_submission_detail::class)]
final class get_submission_detail_test extends advanced_testcase {
    /**
     * Creates a course with a confsubmissions instance, a confprogram instance
     * pointed at it (in Display phase by default), and one submission.
     *
     * @param string $phase 'display' or 'review'
     * @return array{0: \stdClass, 1: int, 2: int, 3: int} course, cmid, submissionid, confprogramid
     */
    private function create_fixture(string $phase = 'display'): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
            'phase'               => $phase,
        ]);
        $cm = get_coursemodule_from_instance('confprogram', $confprogram->id);

        $submitter = $this->getDataGenerator()->create_user();
        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => $submitter->id,
            'title'           => 'A Test Talk',
            'abstract'        => 'An abstract.',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        return [$course, (int) $cm->id, $submissionid, (int) $confprogram->id];
    }

    /**
     * An accepted submission in Display phase is served successfully.
     */
    public function test_accepted_submission_in_display_phase_succeeds(): void {
        $this->resetAfterTest();

        [$course, $cmid, $submissionid, $confprogramid] = $this->create_fixture('display');
        $decider = $this->getDataGenerator()->create_user();
        api::record_decision($confprogramid, $submissionid, 'accept', 1, (int) $decider->id);

        $viewer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($viewer);

        $result = get_submission_detail::execute($cmid, $submissionid);
        $this->assertSame('A Test Talk', $result['title']);
        $this->assertStringContainsString('An abstract', $result['html']);
    }

    /**
     * A submission with no decision (or a non-accept decision) is rejected.
     */
    public function test_unaccepted_submission_is_rejected(): void {
        $this->resetAfterTest();

        [$course, $cmid, $submissionid] = $this->create_fixture('display');
        $viewer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($viewer);

        $this->expectException(\invalid_parameter_exception::class);
        get_submission_detail::execute($cmid, $submissionid);
    }

    /**
     * A submissionid belonging to a different mod_confsubmissions instance is
     * rejected with the exact same exception as "not accepted" -- the two
     * failure cases must not be distinguishable to the caller.
     */
    public function test_cross_instance_submission_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $cmid] = $this->create_fixture('display');

        $othercourse = $this->getDataGenerator()->create_course();
        $otherconfsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $othercourse->id]);
        $othersubmitter = $this->getDataGenerator()->create_user();
        $othersubmissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $otherconfsubmissions->id,
            'userid'          => $othersubmitter->id,
            'title'           => 'Foreign Talk',
            'abstract'        => 'Should never be visible from the other instance.',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $viewer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($viewer);

        try {
            get_submission_detail::execute($cmid, $othersubmissionid);
            $this->fail('Expected invalid_parameter_exception.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString(
                get_string('error:submissionnotavailable', 'mod_confprogram'),
                $e->getMessage()
            );
        }
    }

    /**
     * Even an accepted submission is not served while the instance is still in
     * Review phase -- the AJAX endpoint must enforce the same public/private
     * embargo as the Display-phase list page, not rely on the page alone.
     */
    public function test_accepted_submission_hidden_while_in_review_phase(): void {
        $this->resetAfterTest();

        [$course, $cmid, $submissionid, $confprogramid] = $this->create_fixture('review');
        $decider = $this->getDataGenerator()->create_user();
        api::record_decision($confprogramid, $submissionid, 'accept', 1, (int) $decider->id);

        $viewer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($viewer);

        $this->expectException(\invalid_parameter_exception::class);
        get_submission_detail::execute($cmid, $submissionid);
    }
}
