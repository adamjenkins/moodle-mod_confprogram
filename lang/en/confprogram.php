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

$string['anonymousreviewer'] = 'Reviewer {$a}';
$string['assignreviewer'] = 'Assign reviewer';
$string['assignreviewers'] = 'Assign reviewers';
$string['backtoall'] = 'Back to all submissions';
$string['blindreview'] = 'Blind review';
$string['blindreview_help'] = 'When enabled, submitter and reviewer identities are hidden from each other throughout the review workflow, unless a user holds the "View real identities" capability. This only affects what is displayed on screen; it does not restrict what is stored.';
$string['bulkassigngroup'] = 'Assign a reviewer group to selected submissions';
$string['completedreviews'] = 'Completed reviews';
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
$string['criterion'] = 'Criterion';
$string['currentphase'] = 'This program is currently in the {$a} phase.';
$string['currentreviewers'] = 'Assigned reviewers';
$string['decision_accept'] = 'Accept';
$string['decision_reject'] = 'Reject';
$string['decision_resubmit'] = 'Resubmit';
$string['decision_waitlist'] = 'Waitlist';
$string['decisionreport'] = 'Decision report';
$string['decisionsaved'] = 'Decision saved.';
$string['defaultmaxreviews'] = 'Default max reviews per reviewer';
$string['defaultmaxreviews_help'] = 'The maximum number of reviews a single reviewer may complete per round in this instance. 0 means unlimited. This can be overridden for an individual reviewer via a confprogram_reviewermax record.';
$string['editmysubmission'] = 'Edit my submission';
$string['editreview'] = 'Edit review';
$string['error:invalidconfsubmissionscmid'] = 'Choose a Conference Submissions activity from this course.';
$string['error:invalidnumber'] = 'Please enter a whole number of 0 or more.';
$string['error:noconfsubmissions'] = 'There are no Conference Submissions activities in this course yet. Add one first.';
$string['error:noreviewform'] = 'No review form has been configured for this instance yet.';
$string['error:notassigned'] = 'You are not assigned to review this submission.';
$string['error:notowner'] = 'You are not the owner of this submission.';
$string['error:reviewcapreached'] = 'You have reached your maximum number of reviews for this round.';
$string['error:unvetted'] = 'This submission has been flagged as unvetted and is exempt from review.';
$string['focusedsubmission'] = 'Showing only the resubmitted submission below, for re-assignment.';
$string['grade'] = 'Grade';
$string['groupreviewmode'] = 'Group review mode';
$string['groupreviewmode_help'] = 'When enabled, submissions may be assigned to a reviewer group (a standard course group) instead of, or as well as, individual reviewers. Any member of an assigned reviewer group may complete the review.';
$string['identityhidden'] = 'Submitter identity is hidden while blind review is on.';
$string['lastdecision'] = 'Last decision: {$a->decision} (round {$a->round})';
$string['level'] = 'Level';
$string['makedecision'] = 'Make a decision';
$string['markunvetted'] = 'Mark as unvetted';
$string['modulename'] = 'Conference Program';
$string['modulename_help'] = 'The Conference Program activity takes submissions from a Conference Submissions activity through a reviewer vetting workflow (Review phase), then displays the accepted submissions publicly (Display phase).';
$string['modulenameplural'] = 'Conference Programs';
$string['myfeedback'] = 'Feedback for: {$a}';
$string['myreviewqueue'] = 'My review queue';
$string['nocriteriondetail'] = 'A detailed per-criterion breakdown is not available for this review; see the grade above.';
$string['nofeedbackavailable'] = 'There is no feedback available for this submission at the moment.';
$string['noinstances'] = 'There are no Conference Program activities in this course yet.';
$string['noreviewersassigned'] = 'No reviewers assigned yet.';
$string['noreviewscompleted'] = 'You have not completed any reviews yet.';
$string['noreviewspending'] = 'You have no pending reviews.';
$string['noreviewsyet'] = 'No completed reviews yet for this round.';
$string['notinreviewphase'] = 'This instance is in the Display phase; Review phase screens are not available.';
$string['pendingreviews'] = 'Pending reviews';
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
$string['remark'] = 'Remark';
$string['resubmissionsneeded'] = 'Your submissions awaiting resubmission';
$string['review'] = 'Review';
$string['reviewer'] = 'Reviewer';
$string['reviewergroup'] = 'Group: {$a}';
$string['reviewsaved'] = 'Review saved.';
$string['reviewsettings'] = 'Review settings';
$string['round'] = 'Round';
$string['savedecision'] = 'Save decision';
$string['selectgroup'] = 'Select a reviewer group...';
$string['selectreviewer'] = 'Select a reviewer...';
$string['setupreviewform'] = 'Set up the review form';
$string['startnewreviewround'] = 'Start new review round';
$string['startreview'] = 'Start review';
$string['submitreview'] = 'Submit review';
$string['unmarkunvetted'] = 'Remove unvetted flag';
$string['unvetted'] = 'Unvetted';
$string['unvettedsubmissions'] = 'Unvetted submissions';
$string['unvettedsubmissions_help'] = 'Flag a submission as unvetted to exempt it from the review workflow entirely (e.g. an invited keynote or panel added directly to the programme). Unvetted submissions are hidden from the assignment screen and reviewers\' queues.';
$string['warningreviewercapreached'] = 'This reviewer has now reached (or already exceeded) their maximum reviews for this round.';
