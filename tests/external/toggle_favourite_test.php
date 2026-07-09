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
 * Tests for the toggle_favourite AJAX external function.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(toggle_favourite::class)]
final class toggle_favourite_test extends advanced_testcase {
    /**
     * Creates a course with a confsubmissions instance, a confprogram instance
     * (in Display phase by default) pointed at it, and one accepted submission.
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

        $decider = $this->getDataGenerator()->create_user();
        api::record_decision((int) $confprogram->id, $submissionid, 'accept', 1, (int) $decider->id);

        return [$course, (int) $cm->id, $submissionid, (int) $confprogram->id];
    }

    /**
     * An enrolled user can favourite, then unfavourite, an accepted submission
     * in Display phase; the returned state matches api::is_favourited().
     */
    public function test_favourite_and_unfavourite_round_trip(): void {
        $this->resetAfterTest();

        [$course, $cmid, $submissionid] = $this->create_fixture('display');
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        $result = \core_external\external_api::clean_returnvalue(
            toggle_favourite::execute_returns(),
            toggle_favourite::execute($cmid, $submissionid, true)
        );
        $this->assertTrue($result['favourited']);
        $this->assertTrue(api::is_favourited((int) $user->id, $submissionid));

        $result = \core_external\external_api::clean_returnvalue(
            toggle_favourite::execute_returns(),
            toggle_favourite::execute($cmid, $submissionid, false)
        );
        $this->assertFalse($result['favourited']);
        $this->assertFalse(api::is_favourited((int) $user->id, $submissionid));
    }

    /**
     * A submissionid belonging to a different mod_confsubmissions instance is
     * rejected and never favourited.
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
            'abstract'        => 'Should never be favouritable from the other instance.',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        $this->expectException(\invalid_parameter_exception::class);
        toggle_favourite::execute($cmid, $othersubmissionid, true);

        $this->assertFalse(api::is_favourited((int) $user->id, $othersubmissionid));
    }

    /**
     * A submission cannot be favourited while the instance is still in Review
     * phase, even if it is (or later becomes) accept-decided.
     */
    public function test_favouriting_hidden_while_in_review_phase(): void {
        $this->resetAfterTest();

        [$course, $cmid, $submissionid] = $this->create_fixture('review');
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        $this->expectException(\invalid_parameter_exception::class);
        toggle_favourite::execute($cmid, $submissionid, true);
    }

    /**
     * A user enrolled with a freshly-created, capability-less role (so
     * mod/confprogram:favourite is not granted, while validate_context()'s
     * login/enrolment check still passes, since a real enrolment exists)
     * cannot call this endpoint.
     */
    public function test_requires_favourite_capability(): void {
        $this->resetAfterTest();

        [$course, $cmid, $submissionid] = $this->create_fixture('display');
        $bareroleid = $this->getDataGenerator()->create_role();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $bareroleid);

        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        toggle_favourite::execute($cmid, $submissionid, true);
    }
}
