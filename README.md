# mod_confprogram

Conference Program (also known as the Vetting plugin) — a Moodle activity module that takes submissions from [mod_confsubmissions](https://github.com/adamjenkins/moodle-mod_confsubmissions) through a reviewer vetting workflow, then displays the accepted programme to attendees.

Part of the [Conference Tools](https://github.com/adamjenkins/moodle-conference-tools) suite:

- [mod_confsubmissions](https://github.com/adamjenkins/moodle-mod_confsubmissions) — call for abstracts / submissions
- **mod_confprogram** (this plugin) — reviewer vetting workflow + public program display
- [mod_confscheduler](https://github.com/adamjenkins/moodle-mod_confscheduler) — drag-and-drop block schedule / timetable
- [mod_confcheckin](https://github.com/adamjenkins/moodle-mod_confcheckin) — tickets, badges, QR check-in, certificates

## What it does

Operates in two phases, switchable in edit mode:

- **Review phase**: assign reviewers (individually or via reviewer groups) to submissions, review using a rubric (built on Moodle's core advanced grading API), optionally blind (hiding submitter/reviewer identities from each other), flag panels/keynotes as "unvetted" (excluded from review), and record a final Accept/Reject/Resubmit/Waitlist decision. "Resubmit" reopens the submission for editing with reviewer feedback visible, for a second review round.
- **Display phase**: a responsive, filterable, day-by-day list of accepted submissions with a "favourite" feature, syncing time/room/favourite state with mod_confscheduler. The list also accepts an optional `?trackid=X` query parameter (verified against this instance's own tracks before use) to filter to a single track -- the destination of mod_confscheduler's clickable track-pill badges.
- Decision notifications: every speaker on a submission is notified (via Moodle's own core notification system, email on by default) when an Accept/Reject/Waitlist decision is made -- but only once the instance reaches Display phase, the same embargo the accepted/rejected status sync to `mod_confsubmissions` already respects. Template is organiser-editable (`notifications.php`).

## Architecture notes

- **Decision notifications share the exact same Display-phase embargo as the `confsubmissions_submission.status` sync** -- a decision recorded during Review phase is deferred (`confprogram_decision.notifiedtime` tracks whether/when it was sent) and only actually sent, in one batch, the instant the instance switches to Display phase. A decision recorded when the instance is *already* in Display phase is notified immediately. Each individual decision is its own notifiable event -- a submission waitlisted then later accepted generates two separate notifications, not just one for the final state.
- **A failed notification send can never break the real action that triggered it.** `message_send()` failing (e.g. the site's mail transport isn't configured) is caught and swallowed inside the notifier's own `send()` method, rather than allowed to propagate -- a real 500 was caught live on the "Switch to Display phase" button before this fix, since that handler must not throw/emit output before its own `redirect()` call.
- **A per-instance notifications master switch** (`confprogram.notificationsenabled`, default on) overrides the decision notification template. `notifier::notify_decision()` returns a bool so callers only mark a decision's `notifiedtime` once a send was actually attempted -- a decision made while disabled stays pending (not silently marked "notified"), so re-enabling and calling `send_pending_decision_notifications()` still delivers it.

## Requirements

- Moodle 5.2 (`2026042000`) or later.
- mod_confsubmissions installed in the same course.

## Installation

```
git clone https://github.com/adamjenkins/moodle-mod_confprogram.git mod/confprogram
php admin/cli/upgrade.php
```

## License

GNU GPL v3 or later. See [LICENSE](LICENSE).

## Author

Adam Jenkins <adam@wisecat.net>
