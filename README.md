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
