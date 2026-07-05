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
 * Tests for \mod_confprogram\local\notifier and its Display-phase-embargoed hook
 * points in \mod_confprogram\api (record_decision(),
 * send_pending_decision_notifications()).
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(notifier::class)]
#[CoversClass(api::class)]
final class notifier_test extends advanced_testcase {
    /**
     * Creates a course, a confsubmissions instance, and a confprogram instance
     * pointed at it, defaulting to Review phase.
     *
     * @param string $phase 'review' or 'display'
     * @return array{0: \stdClass, 1: int, 2: int} [$course, $confprogramid, $confsubmissionsid]
     */
    private function create_confprogram(string $phase = 'review'): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);

        if ($phase === 'display') {
            $DB->set_field('confprogram', 'phase', 'display', ['id' => $confprogram->id]);
        }

        return [$course, (int) $confprogram->id, (int) $confsubmissions->id];
    }

    /**
     * Creates a submission with a real (userid-backed) speaker.
     *
     * @param int $confsubmissionsid
     * @param \stdClass $speaker
     * @return int The confsubmissions_submission id
     */
    private function create_submission_with_speaker(int $confsubmissionsid, \stdClass $speaker): int {
        global $DB;

        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissionsid,
            'userid'          => $speaker->id,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        \mod_confsubmissions\api::sync_speakers($submissionid, [['userid' => $speaker->id]]);

        return $submissionid;
    }

    /**
     * render() substitutes every recognised placeholder and drops (replaces with '')
     * any placeholder not present in the context.
     */
    public function test_render_substitutes_known_and_drops_unknown_placeholders(): void {
        $this->assertSame(
            'Decision: Accept, note: .',
            notifier::render('Decision: [[decision]], note: [[doesnotexist]].', ['decision' => 'Accept'])
        );
    }

    /**
     * A decision recorded while the instance is already in Display phase notifies
     * immediately and marks notifiedtime.
     */
    public function test_record_decision_notifies_immediately_when_already_display_phase(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram('display');
        $speaker = $this->getDataGenerator()->create_user();
        $submissionid = $this->create_submission_with_speaker($confsubmissionsid, $speaker);
        $decider = $this->getDataGenerator()->create_user();

        $sink = $this->redirectMessages();
        $decisionid = api::record_decision($confprogramid, $submissionid, 'accept', 1, (int) $decider->id);
        $messages = $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision');

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame((int) $speaker->id, (int) $message->useridto);

        $notifiedtime = $DB->get_field('confprogram_decision', 'notifiedtime', ['id' => $decisionid]);
        $this->assertGreaterThan(0, $notifiedtime);
    }

    /**
     * A decision recorded during Review phase is NOT notified immediately -- it is
     * deferred until the instance switches to Display phase.
     */
    public function test_record_decision_defers_notification_during_review_phase(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram('review');
        $speaker = $this->getDataGenerator()->create_user();
        $submissionid = $this->create_submission_with_speaker($confsubmissionsid, $speaker);
        $decider = $this->getDataGenerator()->create_user();

        $sink = $this->redirectMessages();
        $decisionid = api::record_decision($confprogramid, $submissionid, 'accept', 1, (int) $decider->id);
        $messages = $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision');

        $this->assertCount(0, $messages);
        $this->assertSame(0, (int) $DB->get_field('confprogram_decision', 'notifiedtime', ['id' => $decisionid]));
    }

    /**
     * send_pending_decision_notifications() sends every not-yet-notified
     * accept/reject/waitlist decision for the instance (simulating the Review ->
     * Display phase transition), and never sends a 'resubmit' decision.
     */
    public function test_send_pending_decision_notifications_sends_all_pending_and_skips_resubmit(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram('review');
        $speaker1 = $this->getDataGenerator()->create_user();
        $speaker2 = $this->getDataGenerator()->create_user();
        $submission1 = $this->create_submission_with_speaker($confsubmissionsid, $speaker1);
        $submission2 = $this->create_submission_with_speaker($confsubmissionsid, $speaker2);
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, $submission1, 'accept', 1, (int) $decider->id);
        api::record_decision($confprogramid, $submission2, 'resubmit', 1, (int) $decider->id);

        // Switch to Display phase (mirroring view.php's phase-toggle handler) and send.
        $DB->set_field('confprogram', 'phase', 'display', ['id' => $confprogramid]);

        $sink = $this->redirectMessages();
        api::send_pending_decision_notifications($confprogramid);
        $messages = $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision');

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame((int) $speaker1->id, (int) $message->useridto);

        // Calling it again is a no-op: nothing left pending.
        $sink->clear();
        api::send_pending_decision_notifications($confprogramid);
        $this->assertCount(0, $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision'));
    }

    /**
     * A submission waitlisted then later accepted generates TWO separate
     * notifications once sent -- each decision is its own notifiable event, not
     * just the final state.
     */
    public function test_multiple_decisions_on_one_submission_each_notify_once_sent(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram('review');
        $speaker = $this->getDataGenerator()->create_user();
        $submissionid = $this->create_submission_with_speaker($confsubmissionsid, $speaker);
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, $submissionid, 'waitlist', 1, (int) $decider->id);
        api::record_decision($confprogramid, $submissionid, 'accept', 2, (int) $decider->id);

        $DB->set_field('confprogram', 'phase', 'display', ['id' => $confprogramid]);

        $sink = $this->redirectMessages();
        api::send_pending_decision_notifications($confprogramid);
        $messages = $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision');

        $this->assertCount(2, $messages);
    }

    /**
     * get_template() falls back to default_template() when no
     * confprogram_notiftemplate row exists, and uses the configured row once one
     * does.
     */
    public function test_get_template_falls_back_to_default(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid] = $this->create_confprogram();

        $default = notifier::default_template();
        $template = notifier::get_template($confprogramid);
        $this->assertSame($default['subject'], $template['subject']);

        $DB->insert_record('confprogram_notiftemplate', (object) [
            'confprogram'  => $confprogramid,
            'notiftype'    => 'decision',
            'subject'      => 'Custom subject [[decision]]',
            'body'         => 'Custom body',
            'bodyformat'   => FORMAT_HTML,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $template = notifier::get_template($confprogramid);
        $this->assertSame('Custom subject [[decision]]', $template['subject']);
    }
}
