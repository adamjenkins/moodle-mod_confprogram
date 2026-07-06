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
- Full course backup/restore support, and course reset (deletes all reviewer assignments, rubric reviews, decisions, favourites and unvetted flags, and switches the instance back to Review phase; Display-phase field visibility settings and notification templates survive a reset unchanged).

## Architecture notes

- **Decision notifications share the exact same Display-phase embargo as the `confsubmissions_submission.status` sync** -- a decision recorded during Review phase is deferred (`confprogram_decision.notifiedtime` tracks whether/when it was sent) and only actually sent, in one batch, the instant the instance switches to Display phase. A decision recorded when the instance is *already* in Display phase is notified immediately. Each individual decision is its own notifiable event -- a submission waitlisted then later accepted generates two separate notifications, not just one for the final state.
- **A failed notification send can never break the real action that triggered it.** `message_send()` failing (e.g. the site's mail transport isn't configured) is caught and swallowed inside the notifier's own `send()` method, rather than allowed to propagate -- a real 500 was caught live on the "Switch to Display phase" button before this fix, since that handler must not throw/emit output before its own `redirect()` call.
- **A per-instance notifications master switch** (`confprogram.notificationsenabled`, default on) overrides the decision notification template. `notifier::notify_decision()` returns a bool so callers only mark a decision's `notifiedtime` once a send was actually attempted -- a decision made while disabled stays pending (not silently marked "notified"), so re-enabling and calling `send_pending_decision_notifications()` still delivers it.
- **This plugin now correctly declares `FEATURE_ADVANCED_GRADING`** (user request, 2026-07-06) -- it always used core's Advanced Grading API for rubric reviews (`get_grading_manager($context, 'mod_confprogram', 'review')`), but never declared the feature, which is what makes core's own backup/restore machinery (`backup_activity_grading_structure_step`/`restore_activity_grading_structure_step`) include/restore this activity's grading data automatically. Declaring it also requires a `classes/grades/gradeitems.php` implementing core's `itemnumber_mapping`/`advancedgrading_mapping` interfaces (discovered live: without it, `\grading_manager::get_available_areas()` returns null and several core call sites, e.g. `course/modlib.php`'s `add_moduleinfo()`/`update_moduleinfo()`, `foreach()` over it and emit a PHP warning) -- declares the single `'review'` area, matching every existing `get_grading_manager()` call site in this plugin.
- **Cross-activity references (`confsubmissionscmid`, and every `submissionid` column across assignments/reviews/decisions/favourites/unvetted flags) are resolved in `after_restore()`, not during the main restore structure step** -- restore does not guarantee `mod_confsubmissions` (a sibling activity in the same course backup) has already been restored by the time this plugin's own structure step runs; every activity's main structure step completes before any activity's `after_restore()` runs, which is what makes that the only safe place. A `confprogram_review`'s `gradinginstanceid` is resolved the same way, once core's own grading restore step (which runs after this plugin's own step, but still before `after_restore()`) has restored the corresponding `grading_instances` row. A cross-activity reference that wasn't included in the same backup/restore is handled by deleting the now-dangling row (assignments/reviews/decisions/favourites/unvetted) or zeroing `confsubmissionscmid` (a visibly broken, MUST_EXIST-failing state an organiser must manually re-link) -- never silently left pointing at an unrelated activity/submission that happens to share the old numeric id in the destination site.
- **`confprogram_delete_instance()` was missing `confprogram_review`/`confprogram_notiftemplate` cleanup** (found while building this feature) -- fixed. Grading data itself (`grading_areas`/`grading_definitions`/`grading_instances`) is correctly NOT deleted there: core's own context deletion, invoked as part of the standard `course_delete_module()` flow, already calls `\grading_manager::delete_all_for_context()` for this instance's own module context.
- **Course reset's grading-data cleanup is best-effort, not exhaustive**: every `grading_instances` row scoped to the instance's own module context is deleted directly (safe -- a module context's grading data is exclusively that instance's own), but a grading-method-specific table (e.g. `gradingform_rubric_fillings`) is not also cleaned up via that method's own `gradingform_instance::cancel()`, unlike a full instance deletion. This leaves a harmless orphaned row behind that is never surfaced anywhere (core always joins FROM `grading_instances`, never the reverse) -- traded off against the cost of dispatching to every active grading method's own per-instance cleanup API individually during a reset. Rubric criteria/levels (the `grading_definitions` themselves) are never touched, matching the "config survives a reset" convention.

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
