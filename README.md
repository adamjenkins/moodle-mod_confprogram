# mod_confprogram

**Conference Program** (the vetting plugin) — a Moodle activity that takes submissions from [mod_confsubmissions](https://github.com/adamjenkins/moodle-mod_confsubmissions) through a reviewer workflow, then publishes the accepted programme.

*Documentation: English (this file) · [日本語](README.ja.md)*

Part of the [Conference Tools](https://github.com/adamjenkins/moodle-conference-tools) suite:

- [mod_confsubmissions](https://github.com/adamjenkins/moodle-mod_confsubmissions) — call for abstracts
- **mod_confprogram** (this plugin) — reviewer vetting + public program
- [mod_confscheduler](https://github.com/adamjenkins/moodle-mod_confscheduler) — drag-and-drop block schedule
- [mod_confcheckin](https://github.com/adamjenkins/moodle-mod_confcheckin) — tickets, badges, QR check-in

## What it does

The activity runs in two phases, switched from edit mode.

**Review phase**

- **Assign reviewers** to each submission individually, or a whole reviewer group at once in group-review mode.
- **Review with a rubric** built on Moodle's advanced grading API, optionally **blind** (reviewer and author identities hidden from each other, including any organiser-defined submission field that might identify the author). By default only the *Manager* role bypasses blind review; grant `mod/confprogram:viewidentity` explicitly to any other role that should see identities while reviewing.
- **Mark keynotes/panels *unvetted*** to exclude them from review.
- **Record decisions** — Accept / Reject / Resubmit / Waitlist — on the filterable **Decision report**, one at a time or in bulk. *Resubmit* reopens the submission for a fresh round with reviewer feedback visible; a **Start a new round** link jumps straight to bulk re-assignment.
- The Decision report links into `mod_confsubmissions`' editor so a viewer with *Edit any submission* can fix a submission's details without leaving the workflow.

**Display phase**

- A responsive, filterable list of accepted submissions with a **favourite** star, kept in sync with Conference Scheduler's time/room and "my timetable" state. Multiple days render as a single accessible table with consistent, sensibly-proportioned column widths and date-band divider rows, collapsing to a mobile-friendly two-row layout on narrow screens. Accepts `?trackid=X` to filter to one track (the target of the scheduler's track pills).
- **Display settings** (reachable from edit mode in either phase) choose which submission fields appear in the list and in the detail modal.

**Both phases**

- **Decision notifications** email each speaker on Accept/Reject/Waitlist — but only once the instance reaches Display phase (the same embargo used for syncing status back to `mod_confsubmissions`). Templates are editable and can be switched off per activity.
- **Backup/restore & course reset** — fully supported. Reset clears reviews, decisions, favourites and unvetted flags and returns the instance to Review phase; display settings and templates survive.

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
