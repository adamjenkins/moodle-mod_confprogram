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

/**
 * Language strings for mod_confprogram.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['confprogram:addinstance'] = 'Add a new Conference Program activity';
$string['confprogram:decide'] = 'Make Accept/Reject/Resubmit/Waitlist decisions';
$string['confprogram:favourite'] = 'Favourite a submission';
$string['confprogram:managereviewers'] = 'Manage reviewer assignments and review settings';
$string['confprogram:manageunvetted'] = 'Flag or unflag submissions as unvetted';
$string['confprogram:review'] = 'Submit a review for an assigned submission';
$string['confprogram:viewidentity'] = 'View real submitter/reviewer identities even when blind review is on';
$string['confprogram:viewprogram'] = 'View the conference program';
$string['confsubmissionscmid'] = 'Conference Submissions activity';
$string['confsubmissionscmid_help'] = 'The Conference Submissions activity in this course whose submissions this Conference Program instance will vet and display.';
$string['currentphase'] = 'This program is currently in the {$a} phase.';
$string['error:noconfsubmissions'] = 'There are no Conference Submissions activities in this course yet. Add one first.';
$string['modulename'] = 'Conference Program';
$string['modulename_help'] = 'The Conference Program activity takes submissions from a Conference Submissions activity through a reviewer vetting workflow (Review phase), then displays the accepted submissions publicly (Display phase).';
$string['modulenameplural'] = 'Conference Programs';
$string['noinstances'] = 'There are no Conference Program activities in this course yet.';
$string['phase'] = 'Phase';
$string['phase_display'] = 'Display';
$string['phase_review'] = 'Review';
$string['pluginadministration'] = 'Conference Program administration';
$string['pluginname'] = 'Conference Program';
$string['privacy:metadata:confprogram_assignment'] = 'A reviewer assigned to review a submission.';
$string['privacy:metadata:confprogram_assignment:reviewerid'] = 'The ID of the user assigned as a reviewer.';
$string['privacy:metadata:confprogram_assignment:timecreated'] = 'The time the assignment was created.';
$string['privacy:metadata:confprogram_decision'] = 'The accept/reject/resubmit/waitlist call made on a submission.';
$string['privacy:metadata:confprogram_decision:decidedby'] = 'The ID of the user who made the decision.';
$string['privacy:metadata:confprogram_decision:decision'] = 'The decision made (accept, reject, resubmit or waitlist).';
$string['privacy:metadata:confprogram_decision:round'] = 'The review round this decision belongs to.';
$string['privacy:metadata:confprogram_decision:timecreated'] = 'The time the decision was made.';
$string['privacy:metadata:confprogram_favourite'] = 'A submission a user has favourited during the Display phase.';
$string['privacy:metadata:confprogram_favourite:timecreated'] = 'The time the submission was favourited.';
$string['privacy:metadata:confprogram_favourite:userid'] = 'The ID of the user who favourited the submission.';
$string['privacy:metadata:confprogram_reviewermax'] = 'A per-reviewer override of the max-reviews cap used in group-review mode.';
$string['privacy:metadata:confprogram_reviewermax:maxreviews'] = 'The maximum number of reviews this reviewer may be assigned.';
$string['privacy:metadata:confprogram_reviewermax:userid'] = 'The ID of the reviewer this override applies to.';
$string['privacy:metadata:confprogram_unvetted'] = 'A submission flagged as exempt from review (e.g. a panel or keynote).';
$string['privacy:metadata:confprogram_unvetted:setby'] = 'The ID of the user who flagged the submission as unvetted.';
$string['privacy:metadata:confprogram_unvetted:timecreated'] = 'The time the submission was flagged as unvetted.';
