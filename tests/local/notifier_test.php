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

        // Notificationsenabled defaults to 0 (2026-07-09) -- explicitly enable it
        // since most tests below exercise actual sending; the test that specifically
        // covers the disabled case toggles it back off itself.
        $DB->set_field('confprogram', 'notificationsenabled', 1, ['id' => $confprogram->id]);

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
     * send_pending_decision_notifications() sends every pending accept/reject/
     * waitlist decision for the instance (simulating the Review -> Display phase
     * transition). A 'resubmit' decision is never found pending here because it
     * already sent immediately at record_decision() time (see the dedicated
     * resubmit test below).
     */
    public function test_send_pending_decision_notifications_sends_all_pending(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram('review');
        $speaker1 = $this->getDataGenerator()->create_user();
        $speaker2 = $this->getDataGenerator()->create_user();
        $submission1 = $this->create_submission_with_speaker($confsubmissionsid, $speaker1);
        $submission2 = $this->create_submission_with_speaker($confsubmissionsid, $speaker2);
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, $submission1, 'accept', 1, (int) $decider->id);

        // Resubmit sends immediately, not deferred -- capture and discard that send
        // so it doesn't confuse the assertions below, which are about the
        // Display-phase pending flush.
        $sink = $this->redirectMessages();
        api::record_decision($confprogramid, $submission2, 'resubmit', 1, (int) $decider->id);
        $this->assertCount(1, $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision'));
        $sink->clear();

        // Switch to Display phase (mirroring view.php's phase-toggle handler) and send.
        $DB->set_field('confprogram', 'phase', 'display', ['id' => $confprogramid]);

        $sent = api::send_pending_decision_notifications($confprogramid);
        $messages = $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision');

        $this->assertSame(1, $sent);
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame((int) $speaker1->id, (int) $message->useridto);

        // Calling it again is a no-op: nothing left pending.
        $sink->clear();
        api::send_pending_decision_notifications($confprogramid);
        $this->assertCount(0, $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision'));
    }

    /**
     * A resubmit decision sends its notification immediately when recorded, even
     * during Review phase -- unlike accept/reject/waitlist, which defer until
     * Display phase (2026-07-09 revision: resubmit's own feedbackurl only works
     * during Review phase, so deferring it would make the link dead on arrival).
     */
    public function test_resubmit_sends_immediately_during_review_phase(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram('review');
        $speaker = $this->getDataGenerator()->create_user();
        $submissionid = $this->create_submission_with_speaker($confsubmissionsid, $speaker);
        $decider = $this->getDataGenerator()->create_user();

        $sink = $this->redirectMessages();
        $decisionid = api::record_decision($confprogramid, $submissionid, 'resubmit', 1, (int) $decider->id);
        $messages = $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision');

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame((int) $speaker->id, (int) $message->useridto);
        $this->assertStringContainsString('feedback.php', $message->fullmessagehtml);

        $notifiedtime = $DB->get_field('confprogram_decision', 'notifiedtime', ['id' => $decisionid]);
        $this->assertGreaterThan(0, $notifiedtime);
    }

    /**
     * A submission waitlisted then later accepted results in only ONE pending
     * notification -- the newest (accept) -- not two: the dedup fix (user request,
     * 2026-07-09) supersedes the earlier still-unsent waitlist row the moment the
     * accept decision is recorded, so it is permanently excluded from sending.
     */
    public function test_newer_decision_supersedes_earlier_unsent_one(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram('review');
        $speaker = $this->getDataGenerator()->create_user();
        $submissionid = $this->create_submission_with_speaker($confsubmissionsid, $speaker);
        $decider = $this->getDataGenerator()->create_user();

        $waitlistid = api::record_decision($confprogramid, $submissionid, 'waitlist', 1, (int) $decider->id);
        $acceptid = api::record_decision($confprogramid, $submissionid, 'accept', 2, (int) $decider->id);

        $waitlistrow = $DB->get_record('confprogram_decision', ['id' => $waitlistid]);
        $this->assertSame(1, (int) $waitlistrow->superseded);
        $this->assertSame(0, (int) $waitlistrow->notifiedtime);

        $this->assertSame(1, api::count_pending_notifications($confprogramid));
        $pending = api::get_pending_decisions($confprogramid);
        $this->assertArrayHasKey($acceptid, $pending);
        $this->assertArrayNotHasKey($waitlistid, $pending);

        $DB->set_field('confprogram', 'phase', 'display', ['id' => $confprogramid]);

        $sink = $this->redirectMessages();
        $sent = api::send_pending_decision_notifications($confprogramid);
        $messages = $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision');

        $this->assertSame(1, $sent);
        $this->assertCount(1, $messages);
    }

    /**
     * dismiss_pending_decision() marks a row superseded without touching its
     * decision/round/decidedby/timecreated fields, and it disappears from
     * get_pending_decisions()/count_pending_notifications() afterward.
     */
    public function test_dismiss_pending_decision(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram('review');
        $speaker = $this->getDataGenerator()->create_user();
        $submissionid = $this->create_submission_with_speaker($confsubmissionsid, $speaker);
        $decider = $this->getDataGenerator()->create_user();

        $decisionid = api::record_decision($confprogramid, $submissionid, 'accept', 1, (int) $decider->id);
        $this->assertSame(1, api::count_pending_notifications($confprogramid));

        $before = $DB->get_record('confprogram_decision', ['id' => $decisionid]);
        api::dismiss_pending_decision($confprogramid, $decisionid);
        $after = $DB->get_record('confprogram_decision', ['id' => $decisionid]);

        $this->assertSame(1, (int) $after->superseded);
        $this->assertSame($before->decision, $after->decision);
        $this->assertSame($before->round, $after->round);
        $this->assertSame($before->decidedby, $after->decidedby);
        $this->assertSame($before->timecreated, $after->timecreated);

        $this->assertSame(0, api::count_pending_notifications($confprogramid));

        $DB->set_field('confprogram', 'phase', 'display', ['id' => $confprogramid]);
        $sink = $this->redirectMessages();
        $sent = api::send_pending_decision_notifications($confprogramid);
        $this->assertSame(0, $sent);
        $this->assertCount(0, $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision'));
    }

    /**
     * When an instance's notificationsenabled master switch (user request,
     * 2026-07-06) is off, a decision made while already in Display phase sends
     * nothing and leaves notifiedtime at 0 -- re-enabling and calling
     * send_pending_decision_notifications() then delivers it, since nothing was
     * silently marked "notified" while disabled.
     */
    public function test_master_switch_disables_notification_and_leaves_it_pending(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid, $confsubmissionsid] = $this->create_confprogram('display');
        $DB->set_field('confprogram', 'notificationsenabled', 0, ['id' => $confprogramid]);

        $speaker = $this->getDataGenerator()->create_user();
        $submissionid = $this->create_submission_with_speaker($confsubmissionsid, $speaker);
        $decider = $this->getDataGenerator()->create_user();

        $sink = $this->redirectMessages();
        $decisionid = api::record_decision($confprogramid, $submissionid, 'accept', 1, (int) $decider->id);

        $this->assertCount(0, $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision'));
        $this->assertSame(0, (int) $DB->get_field('confprogram_decision', 'notifiedtime', ['id' => $decisionid]));

        $DB->set_field('confprogram', 'notificationsenabled', 1, ['id' => $confprogramid]);
        api::send_pending_decision_notifications($confprogramid);

        $this->assertCount(1, $sink->get_messages_by_component_and_type('mod_confprogram', 'submissiondecision'));
        $this->assertGreaterThan(0, (int) $DB->get_field('confprogram_decision', 'notifiedtime', ['id' => $decisionid]));
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

        $default = notifier::default_template('accept');
        $template = notifier::get_template($confprogramid, 'accept');
        $this->assertSame($default['subject'], $template['subject']);

        $DB->insert_record('confprogram_notiftemplate', (object) [
            'confprogram'  => $confprogramid,
            'notiftype'    => 'accept',
            'subject'      => 'Custom subject [[decision]]',
            'body'         => 'Custom body',
            'bodyformat'   => FORMAT_HTML,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $template = notifier::get_template($confprogramid, 'accept');
        $this->assertSame('Custom subject [[decision]]', $template['subject']);
    }

    /**
     * Each decision type has its own independent template -- configuring 'accept'
     * does not affect 'reject'/'waitlist'/'resubmit', which still fall back to
     * their own defaults.
     */
    public function test_templates_are_independent_per_decision_type(): void {
        $this->resetAfterTest();
        global $DB;

        [, $confprogramid] = $this->create_confprogram();

        $DB->insert_record('confprogram_notiftemplate', (object) [
            'confprogram'  => $confprogramid,
            'notiftype'    => 'accept',
            'subject'      => 'Custom accept subject',
            'body'         => 'Custom accept body',
            'bodyformat'   => FORMAT_HTML,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $this->assertSame('Custom accept subject', notifier::get_template($confprogramid, 'accept')['subject']);
        $this->assertSame(
            notifier::default_template('reject')['subject'],
            notifier::get_template($confprogramid, 'reject')['subject']
        );
        $this->assertSame(
            notifier::default_template('resubmit')['subject'],
            notifier::get_template($confprogramid, 'resubmit')['subject']
        );
    }
}
