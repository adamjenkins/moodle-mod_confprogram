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

namespace mod_confprogram\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use mod_confprogram\api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Behavioural tests for the privacy provider (FABLE.md review, 2026-07-09).
 *
 * The provider had already regressed once before these existed
 * (confprogram_review was entirely missing from every entry point), and the
 * same review changed the deletion posture for decision/unvetted provenance
 * (anonymise decidedby/setby, never delete the workflow record -- deleting a
 * decider's rows emptied the live accepted programme). Both behaviours are
 * locked in here.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /**
     * Creates a course + linked confsubmissions/confprogram pair, one submission,
     * and one of everything user-related: an assignment, a review, a decision,
     * a favourite, an unvetted flag and a reviewermax row.
     *
     * @return array{context: \context_module, confprogramid: int, submissionid: int,
     *         reviewer: \stdClass, decider: \stdClass, fan: \stdClass}
     */
    private function create_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);
        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);
        $cm = get_coursemodule_from_instance('confprogram', $confprogram->id);
        $context = \context_module::instance($cm->id);

        $submitter = $this->getDataGenerator()->create_user();
        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => $submitter->id,
            'title'           => 'Privacy Talk',
            'abstract'        => 'Abstract.',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $reviewer = $this->getDataGenerator()->create_user();
        $decider = $this->getDataGenerator()->create_user();
        $fan = $this->getDataGenerator()->create_user();

        api::assign_reviewer((int) $confprogram->id, $submissionid, (int) $reviewer->id);
        $DB->insert_record('confprogram_review', (object) [
            'confprogram'       => $confprogram->id,
            'submissionid'      => $submissionid,
            'reviewerid'        => $reviewer->id,
            'round'             => 1,
            'gradinginstanceid' => 0,
            'grade'             => 7.5,
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
        $DB->insert_record('confprogram_reviewermax', (object) [
            'confprogram' => $confprogram->id,
            'userid'      => $reviewer->id,
            'maxreviews'  => 3,
        ]);
        api::record_decision((int) $confprogram->id, $submissionid, 'accept', 1, (int) $decider->id);
        api::set_unvetted((int) $confprogram->id, $submissionid, (int) $decider->id);
        api::add_favourite((int) $confprogram->id, $submissionid, (int) $fan->id);

        return [
            'context'       => $context,
            'confprogramid' => (int) $confprogram->id,
            'submissionid'  => $submissionid,
            'reviewer'      => $reviewer,
            'decider'       => $decider,
            'fan'           => $fan,
        ];
    }

    /**
     * Every user with data in the instance is discovered by both directions
     * (contexts-for-user and users-in-context), including the reviewer via
     * their confprogram_review row.
     */
    public function test_context_and_user_discovery(): void {
        $this->resetAfterTest();
        $fixture = $this->create_fixture();

        foreach (['reviewer', 'decider', 'fan'] as $who) {
            $contextlist = provider::get_contexts_for_userid((int) $fixture[$who]->id);
            $this->assertContains(
                $fixture['context']->id,
                array_map('intval', $contextlist->get_contextids()),
                "$who not discovered by get_contexts_for_userid()"
            );
        }

        $userlist = new \core_privacy\local\request\userlist($fixture['context'], 'mod_confprogram');
        provider::get_users_in_context($userlist);
        $userids = array_map('intval', $userlist->get_userids());
        foreach (['reviewer', 'decider', 'fan'] as $who) {
            $this->assertContains((int) $fixture[$who]->id, $userids, "$who not in get_users_in_context()");
        }
    }

    /**
     * A reviewer's export contains their review data (the table the provider
     * once omitted entirely).
     */
    public function test_export_includes_reviews(): void {
        $this->resetAfterTest();
        $fixture = $this->create_fixture();

        $this->export_context_data_for_user((int) $fixture['reviewer']->id, $fixture['context'], 'mod_confprogram');
        $writer = writer::with_context($fixture['context']);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Erasing the decider anonymises decision/unvetted provenance (decidedby/
     * setby -> 0) but KEEPS the workflow records: the accepted programme must
     * not empty when an organiser account is deleted. The reviewer's own rows
     * (review, assignment, reviewermax) are genuinely personal and ARE deleted.
     */
    public function test_delete_data_for_user_anonymises_decisions_and_deletes_reviews(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_fixture();

        // Erase the decider.
        $contextlist = new approved_contextlist($fixture['decider'], 'mod_confprogram', [$fixture['context']->id]);
        provider::delete_data_for_user($contextlist);

        $decision = $DB->get_record('confprogram_decision', ['submissionid' => $fixture['submissionid']]);
        $this->assertNotFalse($decision, 'decision row must survive the decider\'s erasure');
        $this->assertSame(0, (int) $decision->decidedby);
        $unvetted = $DB->get_record('confprogram_unvetted', ['submissionid' => $fixture['submissionid']]);
        $this->assertNotFalse($unvetted, 'unvetted row must survive the setter\'s erasure');
        $this->assertSame(0, (int) $unvetted->setby);

        // Erase the reviewer: their personal rows go entirely.
        $contextlist = new approved_contextlist($fixture['reviewer'], 'mod_confprogram', [$fixture['context']->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse($DB->record_exists('confprogram_review', ['reviewerid' => $fixture['reviewer']->id]));
        $this->assertFalse($DB->record_exists('confprogram_assignment', ['reviewerid' => $fixture['reviewer']->id]));
        $this->assertFalse($DB->record_exists('confprogram_reviewermax', ['userid' => $fixture['reviewer']->id]));
    }

    /**
     * The userlist (bulk) delete path applies the same anonymise-vs-delete split.
     */
    public function test_delete_data_for_users_matches_single_user_semantics(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_fixture();

        $userlist = new approved_userlist($fixture['context'], 'mod_confprogram', [
            (int) $fixture['decider']->id,
            (int) $fixture['reviewer']->id,
            (int) $fixture['fan']->id,
        ]);
        provider::delete_data_for_users($userlist);

        $decision = $DB->get_record('confprogram_decision', ['submissionid' => $fixture['submissionid']]);
        $this->assertNotFalse($decision);
        $this->assertSame(0, (int) $decision->decidedby);
        $this->assertFalse($DB->record_exists('confprogram_review', ['reviewerid' => $fixture['reviewer']->id]));
        $this->assertFalse($DB->record_exists('confprogram_favourite', ['userid' => $fixture['fan']->id]));
    }
}
