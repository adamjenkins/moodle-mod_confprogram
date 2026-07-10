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

namespace mod_confprogram\local;

use mod_confsubmissions\api as submissions_api;

/**
 * Sends this plugin's decision notification via Moodle's own core notification
 * system, with a separate organiser-editable template per decision type
 * (notifications.php, confprogram_notiftemplate, notiftype = 'accept'|'reject'|
 * 'waitlist'|'resubmit') -- same conventions as mod_confsubmissions\local\notifier
 * (built-in default fallback per type, a plain fixed `[[name]]` placeholder
 * delimiter).
 *
 * Timing differs by decision type (2026-07-09 revision):
 * - accept/reject/waitlist notifications are deferred until this confprogram
 *   instance reaches Display phase, the same embargo
 *   \mod_confprogram\api::record_decision() already applies to syncing
 *   confsubmissions_submission.status -- see that method's own docblock.
 * - resubmit notifications send IMMEDIATELY when the decision is recorded,
 *   regardless of phase: feedback.php (where the submitter reads reviewer
 *   feedback and resubmits) only works during Review phase, so deferring a
 *   resubmit notification to Display phase would make its own [[feedbackurl]]
 *   link dead on arrival, and the review round has already moved on by then
 *   (see classes/local/rounds.php). Because this is a real, immediate,
 *   user-visible side effect, decisions.php/decisions.js gate recording a
 *   resubmit decision behind its own confirm dialog before submitting.
 *
 * This class itself does not check phase; api::record_decision() and
 * api::send_pending_decision_notifications() are the two call sites responsible
 * for only ever calling notify_decision() when a decision is actually eligible to
 * be revealed.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /** @var string[] Decision values that generate a notification -- now all four possible decisions. */
    public const NOTIFIABLE_DECISIONS = ['accept', 'reject', 'waitlist', 'resubmit'];

    /**
     * The built-in fallback subject/body for one decision type's notification,
     * used until an organiser configures their own via notifications.php.
     *
     * @param string $decision One of self::NOTIFIABLE_DECISIONS
     * @return array{subject: string, body: string}
     */
    public static function default_template(string $decision): array {
        return [
            'subject' => get_string("notifdefaultsubject:{$decision}", 'mod_confprogram'),
            'body'    => get_string("notifdefaultbody:{$decision}", 'mod_confprogram'),
        ];
    }

    /**
     * The configured subject/body for this confprogram instance's notification
     * for one decision type, or default_template()'s fallback if unset/blank.
     *
     * @param int $confprogramid The confprogram instance id
     * @param string $decision One of self::NOTIFIABLE_DECISIONS
     * @return array{subject: string, body: string, bodyformat: int}
     */
    public static function get_template(int $confprogramid, string $decision): array {
        global $DB;

        $template = $DB->get_record('confprogram_notiftemplate', [
            'confprogram' => $confprogramid,
            'notiftype'   => $decision,
        ]);

        $default = self::default_template($decision);

        $subject = ($template && trim((string) $template->subject) !== '') ? $template->subject : $default['subject'];
        $body = ($template && trim((string) $template->body) !== '') ? $template->body : $default['body'];
        $bodyformat = $template->bodyformat ?? FORMAT_HTML;

        return ['subject' => $subject, 'body' => $body, 'bodyformat' => (int) $bodyformat];
    }

    /**
     * Substitutes every `[[name]]` placeholder in $text with its value in $context,
     * or '' if $context has no entry for that name.
     *
     * @param string $text The subject or body text
     * @param array $context Placeholder name => replacement value
     * @return string
     */
    public static function render(string $text, array $context): string {
        return preg_replace_callback(
            '/\[\[(\w+)\]\]/',
            static fn (array $matches): string => $context[$matches[1]] ?? '',
            $text
        );
    }

    /**
     * Notifies every real (userid-backed) speaker on a submission that a decision has
     * been made on it. The caller (api::record_decision()/
     * send_pending_decision_notifications()) is solely responsible for only calling
     * this once the decision is actually eligible to be revealed (Display phase) and
     * for $decision being one of self::NOTIFIABLE_DECISIONS.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $submissionid The mod_confsubmissions confsubmissions_submission id
     * @param string $decision One of self::NOTIFIABLE_DECISIONS
     * @return bool True if a send was attempted (the instance's master switch is on);
     *         false if it was skipped (no confprogram/submission record, or
     *         notificationsenabled is off). Callers use this to decide whether to mark
     *         notifiedtime -- see api::record_decision()/send_pending_decision_notifications().
     */
    public static function notify_decision(int $confprogramid, int $submissionid, string $decision): bool {
        global $DB;

        $confprogram = $DB->get_record('confprogram', ['id' => $confprogramid]);
        if (!$confprogram || !$confprogram->notificationsenabled) {
            return false;
        }

        $submission = submissions_api::get_submission($submissionid);
        if (!$submission) {
            return false;
        }

        $template = self::get_template($confprogramid, $decision);
        $course = get_course((int) $confprogram->course);

        // Raw (filtered but unescaped) values: the plain-text subject and a
        // FORMAT_PLAIN body are escaped exactly once at their own output boundary
        // (send() wraps a plain body in s()); only a FORMAT_HTML body -- sent
        // verbatim as fullmessagehtml -- gets pre-escaped values. Substituting
        // escaped values everywhere put literal entities ("D&#39;Arcy") in
        // subjects (FABLE.md review, 2026-07-09).
        $rawcontext = [
            'submissiontitle' => format_string($submission->title, true, ['escape' => false]),
            'coursename'      => format_string($course->fullname, true, ['escape' => false]),
            // Reuses this plugin's existing decision_accept/decision_reject/
            // decision_waitlist/decision_resubmit display strings (decisions.php
            // already uses the same 'decision_' . $decision key convention) rather
            // than inventing new ones.
            'decision'        => get_string('decision_' . $decision, 'mod_confprogram'),
        ];

        if ($decision === 'resubmit') {
            // Feedback.php is the real, existing page where a submitter reads
            // reviewer feedback and resubmits -- only meaningful (and only
            // accessible, see that page's own phase check) while this decision's
            // immediate send is actually happening, i.e. during Review phase.
            $cm = get_coursemodule_from_instance('confprogram', $confprogramid);
            if ($cm) {
                $rawcontext['feedbackurl'] = (new \moodle_url('/mod/confprogram/feedback.php', [
                    'id'           => $cm->id,
                    'submissionid' => $submissionid,
                ]))->out(false);
            }
        }

        foreach (submissions_api::get_speakers($submissionid) as $speaker) {
            if (empty($speaker->userid)) {
                continue;
            }
            $touser = \core_user::get_user((int) $speaker->userid);
            if (!$touser || $touser->deleted) {
                continue;
            }

            $speakerraw = $rawcontext + [
                'fullname' => format_string(fullname($touser), true, ['escape' => false]),
            ];
            $bodycontext = $template['bodyformat'] === FORMAT_HTML ? array_map('s', $speakerraw) : $speakerraw;

            self::send(
                $touser,
                self::render($template['subject'], $speakerraw),
                self::render($template['body'], $bodycontext),
                $template['bodyformat'],
                (int) $confprogram->course
            );
        }

        return true;
    }

    /**
     * Builds and sends one \core\message\message via message_send() -- see
     * \mod_confsubmissions\local\notifier::send()'s docblock for why this is what
     * makes "sent by email as well by default" free.
     *
     * @param \stdClass $touser The recipient user record (a FULL record, not a
     *        trimmed-down one from e.g. get_role_users() -- message_send() needs it)
     * @param string $subject Already placeholder-rendered
     * @param string $body Already placeholder-rendered
     * @param int $bodyformat FORMAT_HTML or FORMAT_PLAIN
     * @param int $courseid The course id, used to build the contexturl
     * @return void
     */
    private static function send(\stdClass $touser, string $subject, string $body, int $bodyformat, int $courseid): void {
        $bodyhtml = $bodyformat === FORMAT_HTML ? $body : nl2br(s($body));

        $message = new \core\message\message();
        $message->component = 'mod_confprogram';
        $message->name = 'submissiondecision';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $touser;
        $message->subject = $subject;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessage = html_to_text($bodyhtml);
        $message->fullmessagehtml = $bodyhtml;
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
        $message->contexturlname = get_string('pluginname', 'mod_confprogram');

        // Message_send() can fail (e.g. the site's mail transport isn't configured, a
        // real and common misconfiguration this plugin has no control over) via a
        // debugging() call that some error-handler configurations convert into a
        // thrown exception -- fatal if uncaught, as it is here, from view.php's
        // phase-toggle handler (or, for mod_confsubmissions's own notifier, from
        // edit.php/set_status()). A speaker/organiser's own real action (recording a
        // decision, submitting, withdrawing) must never be broken by a best-effort
        // notification failing to send, so any failure here is caught and swallowed
        // rather than allowed to propagate. Caught live (a real 500 on the actual
        // Review -> Display phase-toggle button, not just a theoretical risk) when
        // this environment's own missing sendmail binary reproduced it. Deliberately
        // does not call debugging() to record the failure: debugging() is the very
        // function whose exception-conversion this catch block exists to survive, so
        // calling it again here risks the same fatal exception all over again.
        try {
            message_send($message);
        } catch (\Throwable $e) {
            // phpcs:ignore moodle.PHP.ForbiddenFunctions.FoundWithAlternative
            error_log('mod_confprogram notification send failed: ' . $e->getMessage());
        }
    }
}
