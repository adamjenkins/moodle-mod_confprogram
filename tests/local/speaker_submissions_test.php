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
 * Tests for \mod_confprogram\local\speaker_submissions.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(speaker_submissions::class)]
final class speaker_submissions_test extends advanced_testcase {
    /**
     * Inserts a submission and sets its speaker list.
     *
     * @param int $confsubmissionsid
     * @param \stdClass $owner The submission's owner (confsubmissions_submission.userid)
     * @param array $speakers Passed straight to \mod_confsubmissions\api::sync_speakers()
     * @return int The confsubmissions_submission id
     */
    private function create_submission(int $confsubmissionsid, \stdClass $owner, array $speakers): int {
        global $DB;

        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissionsid,
            'userid'          => $owner->id,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        \mod_confsubmissions\api::sync_speakers($submissionid, $speakers);

        return $submissionid;
    }

    /**
     * A user is matched both as the submission's owner/primary speaker AND as a
     * co-presenter on someone else's submission, but not on a submission they have no
     * speaker row on at all.
     */
    public function test_matches_primary_and_co_presenter_but_not_unrelated_submissions(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);

        $user = $this->getDataGenerator()->create_user();
        $otherowner = $this->getDataGenerator()->create_user();
        $unrelated = $this->getDataGenerator()->create_user();

        // The $user is the owner and sole (primary) speaker.
        $ownsubmissionid = $this->create_submission((int) $confsubmissions->id, $user, [
            ['userid' => $user->id],
        ]);

        // The $user is a co-presenter on a submission owned by someone else.
        $cosubmissionid = $this->create_submission((int) $confsubmissions->id, $otherowner, [
            ['userid' => $otherowner->id],
            ['userid' => $user->id],
        ]);

        // The $user appears nowhere on this one -- a guest (no userid) speaker plus the
        // unrelated owner as primary.
        $this->create_submission((int) $confsubmissions->id, $unrelated, [
            ['userid' => $unrelated->id],
            ['name' => 'Guest Speaker', 'email' => 'guest@example.com'],
        ]);

        $result = speaker_submissions::get_for_user((int) $confsubmissions->id, (int) $user->id);
        $resultids = array_map(fn($submission) => (int) $submission->id, $result);
        sort($resultids);

        $this->assertSame([$ownsubmissionid, $cosubmissionid], $resultids);
    }

    /**
     * A user with no speaking submissions at all in the instance gets an empty array,
     * not null or a warning.
     */
    public function test_returns_empty_array_when_user_speaks_on_nothing(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);

        $owner = $this->getDataGenerator()->create_user();
        $this->create_submission((int) $confsubmissions->id, $owner, [['userid' => $owner->id]]);

        $bystander = $this->getDataGenerator()->create_user();

        $this->assertSame([], speaker_submissions::get_for_user((int) $confsubmissions->id, (int) $bystander->id));
    }

    /**
     * A different mod_confsubmissions instance's submissions never match, even for a
     * user who genuinely speaks on something with the same title/shape elsewhere.
     */
    public function test_scoped_to_the_given_confsubmissions_instance(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissionsa = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionsb = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);

        $user = $this->getDataGenerator()->create_user();
        $this->create_submission((int) $confsubmissionsa->id, $user, [['userid' => $user->id]]);

        $this->assertSame([], speaker_submissions::get_for_user((int) $confsubmissionsb->id, (int) $user->id));
    }
}
