# Changelog

## Unreleased

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
