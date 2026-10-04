# Changes

## Unreleased

- The Japanese language pack (lang/ja) is no longer included: releases ship the English strings
  only, as the Moodle Plugins directory expects. Japanese is provided through Moodle's language
  packs.
- Checkbox and date answers to optional submission fields show as Yes/No and a date in the programme
  list and detail window, as in Conference Submissions, instead of the stored 1/0 or timestamp.

## v0.1.1

First tagged release (the in-development v0.1.0 was never tagged).

Conference Program (the vetting plugin): a Moodle activity that takes
submissions from `mod_confsubmissions` through a reviewer workflow, then
publishes the accepted programme. Part of the Conference Tools suite.

- Review phase: assign reviewers individually or by group, review with a
  rubric (optionally blind), mark keynotes/panels unvetted, and record
  Accept / Reject / Resubmit / Waitlist decisions one at a time or in bulk on a
  filterable Decision report.
- Display phase: a filterable list of accepted submissions with favourites,
  showing time and room from Conference Scheduler.
- Editable decision notification templates, switchable per activity.
- Backup/restore and course reset.
- Declare Moodle 5.3 support (supported on Moodle 5.2–5.3).
- Readable in Boost dark colour mode (Moodle 5.3): programme list backgrounds,
  muted text and borders follow the colour mode.
- Installable with Composer (`adamjenkins/moodle-mod_confprogram`, which also
  requires `adamjenkins/moodle-mod_confsubmissions`).
- Releases are published to the camp registry.

See `changelog.md` for the full development history of this version.
