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
 * Defines the backup structure for mod_confprogram.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the complete confprogram structure for backup, with file and id annotations.
 *
 * Instance CONFIGURATION (per-field Display-phase visibility settings, notification
 * templates) is always included, regardless of the 'userinfo' setting. Everything else
 * -- reviewer assignments, per-reviewer max-review overrides, rubric reviews, decisions,
 * favourites, and unvetted flags -- is workflow/user data tied to specific people and
 * submissions, and is only included when 'userinfo' is on, matching core's own
 * `backup_activity_grading_structure_step` convention this activity's rubric reviews
 * ride on (grading areas/definitions are unconditional; grading instances -- the actual
 * filled-in scores -- are userinfo-gated, exactly like this plugin's own tables below).
 *
 * confsubmissionscmid and every submissionid column reference ANOTHER activity
 * (mod_confsubmissions) in the same course. Backup stores them as plain (old, unmapped)
 * values -- see restore_confprogram_stepslib.php's docblock for why the remapping must
 * happen in after_restore(), not here or in restore's own process_*() methods.
 */
class backup_confprogram_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the confprogram activity structure for backup.
     *
     * @return backup_nested_element The root element, wrapped into standard activity structure
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $confprogram = new backup_nested_element('confprogram', ['id'], [
            'name', 'intro', 'introformat', 'confsubmissionscmid', 'phase',
            'blindreview', 'groupreviewmode', 'defaultmaxreviews', 'notificationsenabled',
            'timecreated', 'timemodified',
        ]);

        $fieldsettings = new backup_nested_element('fieldsettings');
        $fieldsetting = new backup_nested_element('fieldsetting', ['id'], [
            'fieldname', 'showinlist', 'showinmodal',
        ]);

        $notiftemplates = new backup_nested_element('notiftemplates');
        $notiftemplate = new backup_nested_element('notiftemplate', ['id'], [
            'notiftype', 'subject', 'body', 'bodyformat', 'timecreated', 'timemodified',
        ]);

        $assignments = new backup_nested_element('assignments');
        $assignment = new backup_nested_element('assignment', ['id'], [
            'submissionid', 'reviewerid', 'reviewergroupid', 'timecreated',
        ]);

        $reviewermaxes = new backup_nested_element('reviewermaxes');
        $reviewermax = new backup_nested_element('reviewermax', ['id'], [
            'userid', 'maxreviews',
        ]);

        $reviews = new backup_nested_element('reviews');
        $review = new backup_nested_element('review', ['id'], [
            'submissionid', 'reviewerid', 'round', 'gradinginstanceid', 'grade',
            'timecreated', 'timemodified',
        ]);

        $decisions = new backup_nested_element('decisions');
        $decision = new backup_nested_element('decision', ['id'], [
            'submissionid', 'decision', 'round', 'decidedby', 'notifiedtime', 'timecreated',
        ]);

        $favourites = new backup_nested_element('favourites');
        $favourite = new backup_nested_element('favourite', ['id'], [
            'submissionid', 'userid', 'timecreated',
        ]);

        $unvetteds = new backup_nested_element('unvetteds');
        $unvetted = new backup_nested_element('unvetted', ['id'], [
            'submissionid', 'setby', 'timecreated',
        ]);

        // Build the tree.
        $confprogram->add_child($fieldsettings);
        $fieldsettings->add_child($fieldsetting);

        $confprogram->add_child($notiftemplates);
        $notiftemplates->add_child($notiftemplate);

        $confprogram->add_child($assignments);
        $assignments->add_child($assignment);

        $confprogram->add_child($reviewermaxes);
        $reviewermaxes->add_child($reviewermax);

        $confprogram->add_child($reviews);
        $reviews->add_child($review);

        $confprogram->add_child($decisions);
        $decisions->add_child($decision);

        $confprogram->add_child($favourites);
        $favourites->add_child($favourite);

        $confprogram->add_child($unvetteds);
        $unvetteds->add_child($unvetted);

        // Define sources.
        $confprogram->set_source_table('confprogram', ['id' => backup::VAR_ACTIVITYID]);
        $fieldsetting->set_source_table('confprogram_fieldsetting', ['confprogram' => backup::VAR_PARENTID]);
        $notiftemplate->set_source_table('confprogram_notiftemplate', ['confprogram' => backup::VAR_PARENTID]);

        // The rest only happen if we are including user info.
        if ($userinfo) {
            $assignment->set_source_table('confprogram_assignment', ['confprogram' => backup::VAR_PARENTID]);
            $reviewermax->set_source_table('confprogram_reviewermax', ['confprogram' => backup::VAR_PARENTID]);
            $review->set_source_table('confprogram_review', ['confprogram' => backup::VAR_PARENTID]);
            $decision->set_source_table('confprogram_decision', ['confprogram' => backup::VAR_PARENTID]);
            $favourite->set_source_table('confprogram_favourite', ['confprogram' => backup::VAR_PARENTID]);
            $unvetted->set_source_table('confprogram_unvetted', ['confprogram' => backup::VAR_PARENTID]);
        }

        // Define id annotations.
        $assignment->annotate_ids('user', 'reviewerid');
        $assignment->annotate_ids('group', 'reviewergroupid');
        $reviewermax->annotate_ids('user', 'userid');
        $review->annotate_ids('user', 'reviewerid');
        $decision->annotate_ids('user', 'decidedby');
        $favourite->annotate_ids('user', 'userid');
        $unvetted->annotate_ids('user', 'setby');

        // Define file annotations.
        $confprogram->annotate_files('mod_confprogram', 'intro', null);

        // Return the root element, wrapped into standard activity structure.
        return $this->prepare_activity_structure($confprogram);
    }
}
