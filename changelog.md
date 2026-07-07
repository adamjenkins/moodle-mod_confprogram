# Changelog

## Unreleased

- User request (2026-07-07): the Decision report's Title cell now shows an
  "Edit" link into `mod_confsubmissions`'s `edit.php` for anyone holding
  that plugin's new `mod/confsubmissions:editany` capability (granted by
  default to `editingteacher`/`manager` -- see that plugin's own
  changelog). Saving or cancelling returns to the Decision report with
  whatever track/decision-status filter was active, via `edit.php`'s new
  `returnurl` param. No other confprogram page changes.
- User request (2026-07-06): added `composer.json` so the plugin can be
  published on Packagist per the
  [Moodle Composer guide](https://moodledev.io/docs/5.2/guides/composer) --
  `moodle-mod` type, requires `moodle/moodle:^5.2` (matching this plugin's own
  `version.php` floor) and `moodle/composer-installer`. Also fixed the CI
  matrix: the push-triggered job was testing `MOODLE_500_STABLE`/
  `MOODLE_501_STABLE` alongside `MOODLE_502_STABLE`, but `version.php` has
  always required 5.2, so those older branches could never actually install
  the plugin -- narrowed to `MOODLE_502_STABLE` only, matching the PR matrix.
- User request (2026-07-06): redesign the Decision report as a filterable,
  bulk-capable table. `decisions.php` is rewritten from per-submission cards
  into a single `html_table` with track and decision-status filters (a plain
  GET form, matching `assign.php`'s existing track-filter pattern) and a
  row-checkbox column for bulk decisions -- pick a decision from a toolbar
  dropdown, select any number of rows (or use a header "select all"
  checkbox), and apply it to the whole batch in one POST, gated behind a
  confirm dialog naming the chosen decision and how many submissions it will
  touch. That confirm/select-all behaviour lives in a new
  `amd/src/decisions.js` -- this plugin's first AMD module, everything else
  here being plain server-rendered forms. Per-row single-decision controls
  remain alongside the bulk toolbar for one-at-a-time use. All the new
  server-side decorating/filtering/bulk-apply logic lives in a new
  `classes/local/decision_report.php` (`decorate_submissions()`,
  `filter_by_decision_status()`, `filter_resubmitted()`,
  `apply_bulk_decision()`), covered by 12 new tests in
  `tests/local/decision_report_test.php`.

  The report's old per-row "start new review round" navigation (one link per
  resubmitted submission) is replaced by a single bulk "Start a new round"
  link, shown only when at least one resubmit-decided submission exists. It
  lands on `assign.php`'s existing bulk-assign checkboxes via a new
  `?resubmitted=1` filter mode, pre-filtered to every resubmit-decided
  submission at once -- using the same `decision_report::filter_resubmitted()`
  shared by both pages.

  Two real bugs found and fixed along the way:

  1. **Silent "All tracks" filter bug**: `optional_param('trackid', '',
     PARAM_INT)` coerces an empty GET value -- present whenever the filter
     form is submitted at all, even with "All tracks" selected -- to `0`,
     not `''`; a strict `!== ''` guard then treated that coerced `0` as a
     real filter, producing `WHERE trackid = 0` and silently emptying the
     whole table. Fixed in `decisions.php` with a truthy check in place of
     the strict-empty-string check, and, since `assign.php`'s pre-existing
     track filter had the exact same bug (same `optional_param()` call,
     same strict guard), fixed there too opportunistically in the same
     pass.
  2. **Accessibility findings from a `moodle-reviewer` pass**: two Medium
     issues, both fixed -- the bulk-decision `<select>` had no persistent
     accessible name once a real option was chosen (its only label was the
     placeholder option text), fixed with a visually-hidden `<label>`; and
     the per-row checkboxes had no `aria-label` distinguishing one row from
     another for screen-reader table navigation, fixed with one built from
     each submission's title. A related Low finding was also fixed:
     `decisions.js`'s bulk-decision `<select>` lookup had no null-guard,
     inconsistent with the apply-button check three lines above it.

  12 new tests in `decision_report_test.php`, 90/90 PHPUnit passing (full
  suite), phpcs/moodlecheck clean, all behaviour independently re-verified
  live via Playwright.

- Bug-fix round (2026-07-06): three user-reported bugs, all root-caused before
  fixing and independently re-verified live.
  1. **Numeric submit buttons instead of language strings**: five
     `<input type="submit">` buttons in `assign.php` (remove-assignment,
     assign-individual, assign-group) and `unvetted.php` (mark/unmark unvetted)
     used the row's own id as their `value` attribute -- for a submit input,
     `value` is both the submitted data AND the visible label, so the button
     literally displayed a number. Fixed by converting all five to
     `<button type="submit" name="X" value="$id">{{label}}</button>`, which lets
     the submitted value and the displayed label differ; no POST-handling logic
     changed. New `assigngroup` lang string (EN+JA), the only one of the five
     without an existing short string to reuse.
  2. **`review.php` crashed on both Cancel and a successful Submit** (reproduced
     live with a full stack trace via Playwright): `echo $OUTPUT->header()` and
     page rendering happened before the review form's `is_cancelled()`/
     `get_data()` checks and their `redirect()` calls. `redirect()` cannot
     perform a real HTTP redirect once output has started, so it fatally erred
     with "You should really redirect before you start page output". Fixed by
     moving all cancel/submit/redirect handling above the `header()` call,
     matching the pattern every sibling page in this plugin (`assign.php`,
     `decisions.php`, `unvetted.php`) already used.
  3. **Track colour never reached a "pill" badge anywhere in this plugin** --
     track was plain text everywhere (the Display-phase list, the submission
     detail modal, `review.php`, `assign.php`). New
     `field_formatter::get_track_pill_html()` renders a coloured pill (mirroring
     `mod_confscheduler`'s identical `.mod_confscheduler-track-pill` visual
     language via a new `.mod_confprogram-track-pill` class), deliberately
     separate from `format_value('track', ...)` since that method's contract is
     "never returns HTML" (both the list and the modal escape its output) --
     the fix follows the exact precedent already used for the `title` field:
     excluded from the generic per-field loop, rendered in its own dedicated
     slot, still gated by the same show-in-list/show-in-modal visibility
     setting.
  - 78/78 PHPUnit passing (was 74, +4 new), phpcs/moodlecheck clean, all four
    fixes independently re-verified live via Playwright.

- User request (2026-07-06): "Also make sure backup/restore/reset all works fine
  with all plugins." `FEATURE_BACKUP_MOODLE2` flipped to true; new
  `backup/moodle2/*.php` step classes cover every table. Also correctly declares
  `FEATURE_ADVANCED_GRADING` (this plugin always used core's Advanced Grading API
  for rubric reviews, just never declared the feature) plus a new
  `classes/grades/gradeitems.php` implementing core's `itemnumber_mapping`/
  `advancedgrading_mapping` interfaces -- required for `\grading_manager` to
  discover this plugin's `'review'` gradable area at all (discovered live: without
  it, `get_available_areas()` returns null and core call sites like
  `course/modlib.php` `foreach()` over it). Cross-activity references
  (`confsubmissionscmid`, every `submissionid` column, and a review's
  `gradinginstanceid`) are resolved in `after_restore()`, since restore order
  across activities in the same course backup is not guaranteed until every
  activity's main structure step has completed. Also fixed, found while building
  this: `confprogram_delete_instance()` was missing `confprogram_review`/
  `confprogram_notiftemplate` cleanup. New `confprogram_reset_userdata()`/
  `_reset_course_form_definition()`/`_defaults()`: course reset deletes all
  reviewer assignments, reviews, decisions, favourites, and unvetted flags,
  switches phase back to Review, and clears this instance's `grading_instances`
  rows (best-effort -- see README's "Architecture notes" for what's traded off);
  Display-phase field settings and notification templates survive a reset
  unchanged. Verified with a real `backup_controller`/`restore_controller` cycle
  (`tests/backup/restore_confprogram_test.php`), including a genuine rubric
  grading instance round-trip, not just a unit test of the stepslib classes.
  74/74 PHPUnit passing (was 72, +2 new), phpcs/moodlecheck clean, EN/JA lang
  parity verified (142/142 keys).
- moodle-reviewer findings (2026-07-06), two fixes:
  1. `notifications.php`'s master-switch checkbox save did not flush any
     backlog of decisions that had been skipped while notifications were
     disabled -- only `view.php`'s Review-to-Display phase-toggle handler ever
     called `api::send_pending_decision_notifications()`. An instance already
     in Display phase with notifications re-enabled via `notifications.php`
     alone would leave its pending decisions stuck indefinitely (no automatic
     re-delivery). Fixed by calling `send_pending_decision_notifications()`
     from `notifications.php` itself when the switch is (re-)enabled while the
     instance is already in Display phase.
  2. `classes/local/notifier.php`'s `notify_decision()` interpolated a
     recipient's `fullname()` value unescaped into an HTML-format notification
     body (same gap found and fixed in the sibling `mod_confsubmissions` and
     `mod_confscheduler` notifiers in the same pass) -- fixed with
     `format_string()`, matching the escaping already applied to
     `submissiontitle`/`coursename`.
  3. `classes/privacy/provider.php` declared `confprogram_decision.notifiedtime`
     in `get_metadata()` but never included it in `export_user_data()`'s
     `decisions_made` export -- an inconsistent middle state. Resolved by
     removing the declaration (it is an operational dispatch record, not
     personal data about the decision, matching the existing
     `confprogram_notiftemplate` exclusion already documented in this class);
     the now-unused `privacy:metadata:confprogram_decision:notifiedtime` lang
     string was removed from both en/ja (141 -> 140/140 keys, verified in
     parity).
  72/72 PHPUnit passing, phpcs/moodlecheck clean.
- User request (2026-07-06): "Each of the four plugins should have a
  notifications master switch to disable all notifications generated by
  that plugin." New `confprogram.notificationsenabled` (default 1,
  enabled) checkbox on the existing notifications.php template screen.
  `notifier::notify_decision()` now returns a bool (true only if a send was
  actually attempted); `api::record_decision()` and
  `api::send_pending_decision_notifications()` only mark a decision's
  `notifiedtime` when it returns true, so a decision made while
  notifications are disabled is never silently marked "notified" without
  actually being sent -- a later re-enable still delivers it via the
  existing pending-notification batch path. 72/72 PHPUnit passing (was
  71), phpcs/moodlecheck clean, EN/JA lang parity verified (141/141 keys).
- Added `api::count_favourites()`, the total number of users who have
  favourited a submission (not instance-scoped, matching `is_favourited()`'s
  own existing signature/known limitation). Consumed by
  `mod_confscheduler`'s new room-capacity overbooking warning (user request,
  2026-07-05) -- see that plugin's own changelog for the full feature. 71/71
  PHPUnit passing (was 70).
- User feedback (2026-07-05): "on the submission being accepted, rejected, or
  waitlisted" a notification should be sent to every presenter. Added a
  decision notification, sent via Moodle's own core notification system
  (email on by default) to every real (userid-backed) speaker on a
  submission -- but, per an explicit follow-up confirmation, only once this
  instance reaches the Display phase, the same embargo
  `confsubmissions_submission.status` syncing already respects. A decision
  recorded while already in Display phase notifies immediately
  (`record_decision()`); one made during Review phase is deferred (new
  `confprogram_decision.notifiedtime` column tracks this) and sent in one
  batch, alongside the existing status sync, the moment the instance switches
  to Display (`api::send_pending_decision_notifications()`, called from
  `view.php`'s phase-toggle handler). Each individual decision is its own
  notifiable event: a submission waitlisted then later accepted generates two
  separate notifications, not just one for the final state. `'resubmit'`
  decisions are deliberately excluded (the request named only "accepted,
  rejected, or waitlisted"). Organiser-editable template
  (`notifications.php`, new `confprogram_notiftemplate` table,
  `mod/confprogram:managenotifications` capability), mirroring
  `mod_confsubmissions`'s own notification-template pattern.
  **Bug caught and fixed live** (a real 500 on the actual "Switch to Display
  phase" button, not just a theoretical risk): `message_send()` failing (e.g.
  this environment's missing `sendmail` binary) could throw an uncaught
  exception from inside `view.php`'s phase-toggle handler, which must not
  emit any output/throw before its own `redirect()` call -- fixed by wrapping
  `message_send()` in a try/catch in both this plugin's and
  `mod_confsubmissions`'s notifier, so a best-effort notification failing to
  send can never break the real action (a decision, a submission, a
  withdrawal) that triggered it. 70/70 PHPUnit passing (was 64, plus 1
  pre-existing unrelated skip), phpcs/moodlecheck clean, EN/JA lang parity
  verified (139/139 keys), live-verified end-to-end via Playwright and a
  direct DB check: a decision recorded in Review phase correctly deferred,
  then correctly notified (and `notifiedtime` set) the instant Display phase
  began.
- Added a Japanese (`lang/ja/confprogram.php`) language pack, translating every
  string in `lang/en/confprogram.php` (verified live: every key present in both,
  no extras or omissions on either side).
- **Bug fix** (user feedback, 2026-07-05): "the status of a submission in
  confsubmissions doesn't appear to be updated when it gets accepted or rejected."
  Confirmed: `record_decision()` only ever wrote to `confprogram_decision`, never
  back to `mod_confsubmissions`'s own `confsubmissions_submission.status` — so a
  submitter's own "my submissions" view always showed "Submitted", regardless of any
  decision. Fixed with a new `mod_confsubmissions\api::set_status()` call, but
  **carefully phase-gated, not unconditional**: syncing status the moment a decision
  is recorded would leak an Accept/Reject decision to the submitter during Review
  phase, before an organiser switches to Display — exactly the embargo this project's
  Display-phase gating exists to enforce (see `RELATIONS.md`). `record_decision()`
  now only syncs immediately if the instance is already in Display phase; a decision
  recorded during Review phase is synced later, in one batch, by a new
  `api::sync_submission_statuses_to_confsubmissions()`, called from `view.php`'s
  phase-toggle handler exactly when switching Review → Display. Waitlist/Resubmit
  decisions deliberately don't change status yet — `mod_confsubmissions` has no
  corresponding status value for either (only submitted/accepted/rejected), and this
  fix specifically targets the reported accepted/rejected case. New tests cover: no
  leak during Review phase, immediate sync during Display phase, waitlist/resubmit
  leaving status untouched, latest-decision-wins across multiple rounds, and
  instance-scoping (one confprogram instance's sync never touches a submission only
  decided by a different instance). **Also added a one-time `db/upgrade.php` backfill
  step** (`2026070502`): confirmed live on the demo site itself that this exact bug had
  already left real submissions stuck at `status = 'submitted'` despite an Accept
  decision in a confprogram instance already sitting in Display phase — the
  forward-looking fix above does nothing for that already-existing case (it only hooks
  the moment a decision is recorded and the moment phase switches), so the upgrade step
  runs `sync_submission_statuses_to_confsubmissions()` once for every confprogram
  instance already in Display phase, backfilling exactly this situation.
- **Critical fix** (found via live bug-hunt testing, 2026-07-05): the Display-phase
  accepted-submissions list, its AJAX detail modal, and the review page all fatally
  errored (`Call to undefined method mod_confsubmissions\api::get_enabled_fieldnames()`)
  on any site where the linked `mod_confsubmissions` instance uses its (now-only)
  dynamic, organiser-defined optional-field system -- that method was removed when
  `mod_confsubmissions` migrated off the old fixed three-checkbox model, and this
  plugin was never updated to match, a cross-plugin contract break of exactly the
  kind `RELATIONS.md` warns about. Fixed by reworking `classes/local/field_settings.php`
  and `classes/local/field_formatter.php` to key optional fields by their
  `confsubmissions_field` id (`field_settings::OPTIONAL_FIELD_PREFIX . $fieldid`, e.g.
  `'opt5'`) instead of by name -- a field's organiser-chosen name is now free text, not
  a fixed, unique, lang-string-backed vocabulary, so it can no longer double as a
  stable key. `field_formatter::get_label()` now uses the field's own name directly as
  its label (instead of a `get_string('field_' . $fieldname, ...)` lookup that could
  never have resolved for an arbitrary organiser-typed name) and falls back to a new
  `deletedfield` string if the field has since been deleted. `review.php`'s own
  optional-field rendering loop (which called the same removed method directly, plus
  had an independent second bug reading submitted values by the wrong array key) was
  fixed the same way. This subsystem had zero PHPUnit coverage before this fix --
  `tests/local/field_settings_test.php` and `tests/local/field_formatter_test.php`
  are new. **`moodle-reviewer` caught a real High-severity follow-on bug in the fix
  itself**: a confprogram instance that had already saved `displaysettings.php`
  *before* its linked `mod_confsubmissions` instance migrated has leftover
  `confprogram_fieldsetting` rows keyed by the old raw field names, which no longer
  match anything -- left in place, these are not harmlessly ignored, they'd silently
  hide every optional field an organiser had previously made visible (since "this
  instance has any fieldsetting row" flips `get_settings_with_defaults()`'s default
  from "show" to "hide" for anything unmatched). Fixed with a new `db/upgrade.php`
  step (`2026070305`) that deletes exactly those stale rows, restoring the documented
  fresh-instance defaults instead of a silent regression.
- Revision round 1 (small, narrowly-scoped addition requested by
  `mod_confscheduler`, 2026-07-03): the Display-phase accepted-submissions
  list (`view.php`) now accepts an optional `?trackid=X` query parameter,
  following the exact pattern its existing `day` parameter already uses --
  it only narrows the already-instance-scoped list via a new, unit-tested
  `display_list::filter_by_track()`. Since a trackid (like a submissionid)
  is a globally-unique id, not scoped per course, an invalid or
  foreign-course trackid is verified against `mod_confsubmissions\api::get_tracks()`
  (itself instance-scoped) and silently ignored (falls back to "no filter")
  rather than trusted -- this matters specifically because the matched
  track's name is echoed back on the page as a "filtered by track: X"
  indicator, so an unverified id could otherwise leak another course's
  track name. No existing capability/phase-embargo check in this file was
  weakened; this is purely a new filter on top of the same already-reviewed
  code path. `mod_confscheduler`'s track pill badges link here with the new
  parameter -- see that plugin's own changelog entry for the full feature.
- Initial scaffold: schema (assignments, reviewer-max overrides, decisions,
  favourites, unvetted flags, display-phase field settings), capabilities,
  full privacy provider, and a `classes/api.php` integration surface.
- Added `confprogram_review` table to persist per-reviewer rubric scores.
- Review Phase: reviewer/reviewer-group assignment (`assign.php`), a rubric
  review screen built on Moodle's core advanced grading API (`review.php`),
  blind review (submitter/reviewer identity hiding), an unvetted-submission
  flag exempting panels/keynotes from review (`unvetted.php`), a decision
  report with Accept/Reject/Resubmit/Waitlist (`decisions.php`), and a
  resubmission flow showing feedback and linking back to
  `mod_confsubmissions`'s edit form (`feedback.php`).
- Security fixes from review: instance-scoped every cross-plugin
  `submissionid` lookup (was previously trusted unchecked in several write
  paths, a cross-course IDOR), scoped `unassign()` deletion to its own
  confprogram instance, reordered the unvetted/assignment checks in
  `review.php` to close a status-enumeration oracle, moved phase gates
  above POST handling so assignments/decisions/unvetted flags can no longer
  be mutated after switching to Display phase, and server-validated
  `confsubmissionscmid` in the settings form.
- Fixed a real Moodle-core-adjacent bug: the "grading" form element's base
  class was never loaded when it was the very first element added to a
  form (core's own usage always incidentally loads it via an earlier
  element); added an explicit `require_once` with a comment explaining why.
- Display Phase: phase switch (edit mode), a per-field list/modal
  visibility matrix (`displaysettings.php`), a responsive accepted-
  submissions list with a day selector, an AJAX detail modal, and an AJAX
  favourite-star toggle. Includes a soft, defensively-detected integration
  point (`classes/local/schedule_info.php`) for a future `mod_confscheduler`
  plugin's time/room data, and the `api::add_favourite()`/`remove_favourite()`
  write-side contract that plugin will call directly.
- Security fixes from review: added the missing `db/upgrade.php` step for
  the two new Display-phase tables (a real site upgrading from the Review-
  phase release would otherwise hit "table does not exist" errors), added
  phase-embargo enforcement to both AJAX endpoints (`get_submission_detail`,
  `toggle_favourite` — previously enforced only in `view.php`'s rendering,
  so a guessed `submissionid` could probe accept-decision state before an
  instance was switched to Display phase), and fixed a double-HTML-escaping
  bug in `field_formatter::format_value()`'s track/speaker-name branches.
