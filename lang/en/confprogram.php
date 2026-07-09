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

$string['acceptedsubmissions'] = 'Accepted submissions';
$string['alldays'] = 'All days';
$string['alldecisionstatuses'] = 'All statuses';
$string['anonymousreviewer'] = 'Reviewer {$a}';
$string['applybulkdecision'] = 'Apply to selected';
$string['assigngroup'] = 'Assign group';
$string['assignreviewer'] = 'Assign reviewer';
$string['assignreviewers'] = 'Assign reviewers';
$string['backtoall'] = 'Back to all submissions';
$string['blindreview'] = 'Blind review';
$string['blindreview_help'] = 'When enabled, submitter and reviewer identities are hidden from each other throughout the review workflow, unless a user holds the "View real identities" capability. This only affects what is displayed on screen; it does not restrict what is stored.';
$string['bulkassigngroup'] = 'Assign a reviewer group to selected submissions';
$string['bulkdecisionsaved'] = '{$a} decision(s) saved.';
$string['clearfilter'] = 'Clear filter';
$string['completedreviews'] = 'Completed reviews';
$string['confirmbulkdecision'] = 'Apply {$a->decision} to {$a->count} submissions?';
$string['confirmbulkdecisionresubmit'] = 'Apply Resubmit to {$a} submission(s)? Their speakers will be emailed immediately with feedback and a resubmit link -- unlike other decisions, this is not deferred until Display phase.';
$string['confirmdismisspendingnotification'] = 'Dismiss this pending notification? It will never be sent.';
$string['confirmresubmitdecision'] = 'Recording this as Resubmit will immediately email the submitter with feedback and a resubmit link -- unlike other decisions, which wait until this instance switches to Display phase. Continue?';
$string['confirmsendpendingnotifications'] = 'Send {$a} pending notification(s) now?';
$string['confprogram:addinstance'] = 'Add a new Conference Program activity';
$string['confprogram:decide'] = 'Make Accept/Reject/Resubmit/Waitlist decisions';
$string['confprogram:favourite'] = 'Favourite a submission';
$string['confprogram:managenotifications'] = 'Manage the decision notification templates';
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
$string['day'] = 'Day';
$string['decision_accept'] = 'Accept';
$string['decision_reject'] = 'Reject';
$string['decision_resubmit'] = 'Resubmit';
$string['decision_waitlist'] = 'Waitlist';
$string['decisionreport'] = 'Decision report';
$string['decisionsaved'] = 'Decision saved.';
$string['decisionstatus'] = 'Decision status';
$string['defaultmaxreviews'] = 'Default max reviews per reviewer';
$string['defaultmaxreviews_help'] = 'The maximum number of reviews a single reviewer may complete per round in this instance. 0 means unlimited. This can be overridden for an individual reviewer via a confprogram_reviewermax record.';
$string['deletedfield'] = '(deleted field)';
$string['dismiss'] = 'Dismiss';
$string['dismissed'] = 'Dismissed.';
$string['displaysettings'] = 'Display field settings';
$string['displaysettingssaved'] = 'Display settings saved.';
$string['editmysubmission'] = 'Edit my submission';
$string['editreview'] = 'Edit review';
$string['editsubmissionlink'] = 'Edit {$a}';
$string['error:invalidconfsubmissionscmid'] = 'Choose a Conference Submissions activity from this course.';
$string['error:invalidnotiftype'] = 'That is not a recognised notification type.';
$string['error:invalidnumber'] = 'Please enter a whole number of 0 or more.';
$string['error:noconfsubmissions'] = 'There are no Conference Submissions activities in this course yet. Add one first.';
$string['error:noreviewform'] = 'No review form has been configured for this instance yet.';
$string['error:notassigned'] = 'You are not assigned to review this submission.';
$string['error:notowner'] = 'You are not the owner of this submission.';
$string['error:reviewcapreached'] = 'You have reached your maximum number of reviews for this round.';
$string['error:submissionnotavailable'] = 'This submission is not available.';
$string['error:unvetted'] = 'This submission has been flagged as unvetted and is exempt from review.';
$string['favourite'] = 'Favourite';
$string['favouritesonly'] = 'Favourites only';
$string['field'] = 'Field';
$string['filteredbytrack'] = 'Showing submissions filtered to track: {$a}.';
$string['focusedsubmission'] = 'Showing only the resubmitted submission below, for re-assignment.';
$string['grade'] = 'Grade';
$string['gradeitem:review'] = 'Review';
$string['groupreviewmode'] = 'Group review mode';
$string['groupreviewmode_help'] = 'When enabled, submissions may be assigned to a reviewer group (a standard course group) instead of, or as well as, individual reviewers. Any member of an assigned reviewer group may complete the review.';
$string['identityhidden'] = 'Submitter identity is hidden while blind review is on.';
$string['lastdecision'] = 'Last decision: {$a->decision} (round {$a->round})';
$string['lastdecisioncolumn'] = 'Latest decision';
$string['level'] = 'Level';
$string['makedecision'] = 'Make a decision';
$string['managenotifications'] = 'Manage notifications';
$string['managereviewform'] = 'Manage review form';
$string['markunvetted'] = 'Mark as unvetted';
$string['messageprovider:submissiondecision'] = 'A decision has been made on a submission you are a speaker on';
$string['modulename'] = 'Conference Program';
$string['modulename_help'] = 'The Conference Program activity takes submissions from a Conference Submissions activity through a reviewer vetting workflow (Review phase), then displays the accepted submissions publicly (Display phase).';
$string['modulenameplural'] = 'Conference Programs';
$string['myfeedback'] = 'Feedback for: {$a}';
$string['myreviewqueue'] = 'My review queue';
$string['myreviews'] = 'Reviews of my presentations';
$string['noacceptedsubmissions'] = 'No accepted submissions to display yet.';
$string['nocriteriondetail'] = 'A detailed per-criterion breakdown is not available for this review; see the grade above.';
$string['nodecisionyet'] = 'Not yet decided';
$string['nofeedbackavailable'] = 'There is no feedback available for this submission at the moment.';
$string['noinstances'] = 'There are no Conference Program activities in this course yet.';
$string['nopendingnotifications'] = 'There are no pending notifications.';
$string['noreviewersassigned'] = 'No reviewers assigned yet.';
$string['noreviewscompleted'] = 'You have not completed any reviews yet.';
$string['noreviewspending'] = 'You have no pending reviews.';
$string['noreviewsyet'] = 'No completed reviews yet for this round.';
$string['notifbody'] = 'Message';
$string['notifbody_help'] = 'The notification email body for this decision type, sent to every speaker via Moodle\'s own notification system (and by email by default). Use [[fullname]], [[submissiontitle]], [[coursename]], [[decision]] (Accept, Reject, Waitlist, or Resubmit). Accept/Reject/Waitlist notifications are deferred until this instance switches to Display phase if the decision was made during Review phase; Resubmit notifications (which may also use [[feedbackurl]]) send immediately instead, regardless of phase.';
$string['notifdefaultbody:accept'] = '<p>Hello [[fullname]],</p><p>Congratulations &mdash; your submission "[[submissiontitle]]" for [[coursename]] has been <strong>accepted</strong>.</p><p>Please claim your presenter ticket promptly to confirm your place at the conference.</p>';
$string['notifdefaultbody:reject'] = '<p>Hello [[fullname]],</p><p>Thank you for submitting "[[submissiontitle]]" to [[coursename]]. After careful review, we are unable to offer it a place in the programme this time.</p><p>We appreciate you taking the time to submit, and hope you will consider submitting again in future.</p>';
$string['notifdefaultbody:resubmit'] = '<p>Hello [[fullname]],</p><p>The reviewers for "[[submissiontitle]]" ([[coursename]]) have asked you to revise and resubmit. You can read their feedback and submit your revision here: [[feedbackurl]]</p>';
$string['notifdefaultbody:waitlist'] = '<p>Hello [[fullname]],</p><p>Your submission "[[submissiontitle]]" for [[coursename]] has been placed on the <strong>waitlist</strong>. It may still be accepted if space becomes available &mdash; please check back for an update.</p>';
$string['notifdefaultsubject:accept'] = 'Your submission has been accepted: [[submissiontitle]]';
$string['notifdefaultsubject:reject'] = 'Decision on your submission: [[submissiontitle]]';
$string['notifdefaultsubject:resubmit'] = 'Please revise and resubmit: [[submissiontitle]]';
$string['notifdefaultsubject:waitlist'] = 'Your submission is on the waitlist: [[submissiontitle]]';
$string['notificationsenabled'] = 'Enable notifications';
$string['notificationsenabled_help'] = 'Master switch for this activity: when unchecked, no decision notification is ever sent from this instance, regardless of the templates configured below.';
$string['notifplaceholders'] = 'Available placeholders: {$a}.';
$string['notifsubject'] = 'Subject';
$string['notifsubject_help'] = 'The notification email subject line. Same placeholders as the message body below.';
$string['notiftemplatesaved'] = 'Notification template saved.';
$string['notinreviewphase'] = 'This instance is in the Display phase; Review phase screens are not available.';
$string['notyetscheduled'] = 'Not yet scheduled';
$string['pendingnotifications'] = 'Pending notifications ({$a})';
$string['pendingnotificationsheading'] = 'Pending notifications';
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
$string['privacy:metadata:confprogram_review'] = 'A completed review of a submission by one reviewer, including the score they gave.';
$string['privacy:metadata:confprogram_review:grade'] = 'The numeric grade the reviewer gave the submission.';
$string['privacy:metadata:confprogram_review:reviewerid'] = 'The ID of the reviewer who reviewed the submission.';
$string['privacy:metadata:confprogram_review:round'] = 'The review round this review belongs to.';
$string['privacy:metadata:confprogram_review:timecreated'] = 'The time the review was first created.';
$string['privacy:metadata:confprogram_review:timemodified'] = 'The time the review was last modified.';
$string['privacy:metadata:confprogram_reviewermax'] = 'A per-reviewer override of the max-reviews cap used in group-review mode.';
$string['privacy:metadata:confprogram_reviewermax:maxreviews'] = 'The maximum number of reviews this reviewer may be assigned.';
$string['privacy:metadata:confprogram_reviewermax:userid'] = 'The ID of the reviewer this override applies to.';
$string['privacy:metadata:confprogram_unvetted'] = 'A submission flagged as exempt from review (e.g. a panel or keynote).';
$string['privacy:metadata:confprogram_unvetted:setby'] = 'The ID of the user who flagged the submission as unvetted.';
$string['privacy:metadata:confprogram_unvetted:timecreated'] = 'The time the submission was flagged as unvetted.';
$string['recipients'] = 'Recipients';
$string['remark'] = 'Remark';
$string['removereviews'] = 'Delete all reviews, decisions and favourites (reset to Review phase)';
$string['resubmissionsneeded'] = 'Your submissions awaiting resubmission';
$string['resubmittedbanner'] = 'Now showing: submissions awaiting a new round of review.';
$string['review'] = 'Review';
$string['reviewergroup'] = 'Group: {$a}';
$string['reviews'] = 'Reviews';
$string['reviewsaved'] = 'Review saved.';
$string['reviewsettings'] = 'Review settings';
$string['round'] = 'Round';
$string['savedecision'] = 'Save decision';
$string['selectall'] = 'Select all';
$string['selectgroup'] = 'Select a reviewer group...';
$string['selectreviewer'] = 'Select a reviewer...';
$string['selectsubmission'] = 'Select {$a}';
$string['sendnotificationsnonepending'] = 'There were no pending notifications to send.';
$string['sendnotificationssummary'] = '{$a} notification(s) sent.';
$string['sendpendingnotifications'] = 'Send pending notifications ({$a})';
$string['setupreviewform'] = 'Set up the review form';
$string['showallsubmissions'] = 'Show all submissions';
$string['showday'] = 'Show';
$string['showinlist'] = 'Show in list';
$string['showinmodal'] = 'Show in modal';
$string['startnewroundforresubmits'] = 'Start a new round for {$a} submission(s) awaiting one';
$string['startreview'] = 'Start review';
$string['submitreview'] = 'Submit review';
$string['switchtophase'] = 'Switch to {$a} phase';
$string['timeandroom'] = 'Time / room';
$string['unfavourite'] = 'Remove favourite';
$string['unmarkunvetted'] = 'Remove unvetted flag';
$string['unscheduled'] = 'Unscheduled';
$string['unvetted'] = 'Unvetted';
$string['unvettedsubmissions'] = 'Unvetted submissions';
$string['unvettedsubmissions_help'] = 'Flag a submission as unvetted to exempt it from the review workflow entirely (e.g. an invited keynote or panel added directly to the programme). Unvetted submissions are hidden from the assignment screen and reviewers\' queues.';
$string['warningreviewercapreached'] = 'This reviewer has now reached (or already exceeded) their maximum reviews for this round.';
