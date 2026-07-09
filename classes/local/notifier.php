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
 * Sends this plugin's decision notification (user request, 2026-07-05: "on the
 * submission being accepted, rejected, or waitlisted") via Moodle's own core
 * notification system, with an organiser-editable template
 * (notifications.php, confprogram_notiftemplate) -- same conventions as
 * mod_confsubmissions\local\notifier (built-in default fallback, a plain fixed
 * `[[name]]` placeholder delimiter).
 *
 * Deliberately excludes 'resubmit' decisions: the user's own request named only
 * "accepted, rejected, or waitlisted", and resubmit is a distinct workflow (the
 * submitter is expected to revise and resubmit, not simply be informed of a final
 * status) that was not asked for here.
 *
 * Timing is user-confirmed (2026-07-05 clarification): a decision notification is
 * deferred until this confprogram instance reaches Display phase, the same embargo
 * \mod_confprogram\api::record_decision() already applies to syncing
 * confsubmissions_submission.status -- see that method's own docblock. This class
 * itself does not check phase; api::record_decision() and
 * api::send_pending_decision_notifications() are the two call sites responsible
 * for only ever calling notify_decision() when a decision is actually eligible to
 * be revealed.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /** @var string[] Decision values that generate a notification (resubmit excluded, see class docblock). */
    public const NOTIFIABLE_DECISIONS = ['accept', 'reject', 'waitlist'];

    /**
     * The built-in fallback subject/body for the (single) 'decision' notification
     * type, used until an organiser configures their own via notifications.php.
     *
     * @return array{subject: string, body: string}
     */
    public static function default_template(): array {
        return [
            'subject' => get_string('notifdefaultsubject:decision', 'mod_confprogram'),
            'body'    => get_string('notifdefaultbody:decision', 'mod_confprogram'),
        ];
    }

    /**
     * The configured subject/body for this confprogram instance's decision
     * notification, or default_template()'s fallback if unset/blank.
     *
     * @param int $confprogramid The confprogram instance id
     * @return array{subject: string, body: string, bodyformat: int}
     */
    public static function get_template(int $confprogramid): array {
        global $DB;

        $template = $DB->get_record('confprogram_notiftemplate', [
            'confprogram' => $confprogramid,
            'notiftype'   => 'decision',
        ]);

        $default = self::default_template();

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

        $template = self::get_template($confprogramid);
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
            // decision_waitlist display strings (decisions.php already uses the same
            // 'decision_' . $decision key convention) rather than inventing new ones.
            'decision'        => get_string('decision_' . $decision, 'mod_confprogram'),
        ];

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
