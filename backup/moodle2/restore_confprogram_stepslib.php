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
 * Defines the restore structure for mod_confprogram.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one confprogram activity.
 *
 * Cross-activity references (confsubmissionscmid, and every submissionid column across
 * assignments/reviews/decisions/favourites/unvetted flags) point at mod_confsubmissions,
 * a SIBLING activity in the same course backup -- NOT a value this step can resolve
 * during its own main structure processing, since restore does not guarantee
 * mod_confsubmissions has already been restored by the time this step's process_*()
 * methods run (activities are restored in whatever order the backup file lists them,
 * not in dependency order). Every activity's main structure step completes before ANY
 * activity's after_restore() runs, so that IS the safe place to resolve them -- this
 * class inserts every affected row with its OLD (unmapped) cross-activity value during
 * the main pass, then fixes them all up in after_restore() below.
 *
 * process_confprogram_review() also defines the restore_gradingform_plugin
 * itemid-mapping core's own restore_activity_grading_structure_step needs to correctly
 * restore this activity's rubric grading instances (mod_confprogram declares
 * FEATURE_ADVANCED_GRADING, so that generic core step runs automatically as part of
 * this same activity's restore, AFTER this step per restore_activity_task::build()'s
 * fixed step order -- see that class if this ordering assumption ever needs re-checking).
 */
class restore_confprogram_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the confprogram activity structure for restore.
     *
     * @return array The restore_path_element[] paths, wrapped into standard activity structure
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('confprogram', '/activity/confprogram');
        $paths[] = new restore_path_element(
            'confprogram_fieldsetting',
            '/activity/confprogram/fieldsettings/fieldsetting'
        );
        $paths[] = new restore_path_element(
            'confprogram_notiftemplate',
            '/activity/confprogram/notiftemplates/notiftemplate'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element('confprogram_assignment', '/activity/confprogram/assignments/assignment');
            $paths[] = new restore_path_element(
                'confprogram_reviewermax',
                '/activity/confprogram/reviewermaxes/reviewermax'
            );
            $paths[] = new restore_path_element('confprogram_review', '/activity/confprogram/reviews/review');
            $paths[] = new restore_path_element('confprogram_decision', '/activity/confprogram/decisions/decision');
            $paths[] = new restore_path_element('confprogram_favourite', '/activity/confprogram/favourites/favourite');
            $paths[] = new restore_path_element('confprogram_unvetted', '/activity/confprogram/unvetteds/unvetted');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the main confprogram instance record. confsubmissionscmid is left as its
     * old (unmapped) value here -- see this class's docblock -- and corrected in
     * after_restore().
     *
     * @param array|stdClass $data The parsed confprogram element
     * @return void
     */
    protected function process_confprogram($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('confprogram', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restores a Display-phase field visibility setting.
     *
     * @param array|stdClass $data The parsed fieldsetting element
     * @return void
     */
    protected function process_confprogram_fieldsetting($data) {
        global $DB;

        $data = (object) $data;
        $data->confprogram = $this->get_new_parentid('confprogram');

        $DB->insert_record('confprogram_fieldsetting', $data);
    }

    /**
     * Restores a notification template.
     *
     * @param array|stdClass $data The parsed notiftemplate element
     * @return void
     */
    protected function process_confprogram_notiftemplate($data) {
        global $DB;

        $data = (object) $data;
        $data->confprogram = $this->get_new_parentid('confprogram');

        $DB->insert_record('confprogram_notiftemplate', $data);
    }

    /**
     * Restores a reviewer (or reviewer group) assignment. submissionid is left as its old
     * (unmapped) value here -- see this class's docblock -- and corrected in after_restore().
     *
     * @param array|stdClass $data The parsed assignment element
     * @return void
     */
    protected function process_confprogram_assignment($data) {
        global $DB;

        $data = (object) $data;
        $data->confprogram = $this->get_new_parentid('confprogram');
        if (!empty($data->reviewerid)) {
            $data->reviewerid = $this->get_mappingid('user', $data->reviewerid) ?: null;
        }
        if (!empty($data->reviewergroupid)) {
            $data->reviewergroupid = $this->get_mappingid('group', $data->reviewergroupid) ?: null;
        }

        $DB->insert_record('confprogram_assignment', $data);
    }

    /**
     * Restores a per-reviewer max-review override.
     *
     * @param array|stdClass $data The parsed reviewermax element
     * @return void
     */
    protected function process_confprogram_reviewermax($data) {
        global $DB;

        $data = (object) $data;
        $data->confprogram = $this->get_new_parentid('confprogram');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $DB->insert_record('confprogram_reviewermax', $data);
    }

    /**
     * Restores a rubric review. submissionid is left as its old (unmapped) value here --
     * see this class's docblock -- and corrected in after_restore(). gradinginstanceid is
     * ALSO deliberately left as its old (unmapped) value -- the new grading_instances row
     * does not exist yet at this point (core's own restore_activity_grading_structure_step
     * runs AFTER this step within the same activity restore, per restore_activity_task's
     * fixed step order) -- and is corrected in after_restore() below, once that step has
     * also completed and set its own 'grading_instance' mapping.
     *
     * @param array|stdClass $data The parsed review element
     * @return void
     */
    protected function process_confprogram_review($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->confprogram = $this->get_new_parentid('confprogram');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid);

        $newitemid = $DB->insert_record('confprogram_review', $data);

        // Depended on by core's own restore_activity_grading_structure_step to correctly
        // pair this review row back up with its restored grading_instances row (only
        // meaningful when there was a real, non-placeholder gradinginstanceid to begin
        // with -- a placeholder review, per this plugin's own "gradinginstanceid = 0
        // means only a placeholder row exists" convention, never had a grading instance
        // to pair with in the first place).
        if ((int) $data->gradinginstanceid !== 0) {
            $this->set_mapping(\restore_gradingform_plugin::itemid_mapping('review'), $oldid, $newitemid);
        }
    }

    /**
     * Restores a decision. submissionid is left as its old (unmapped) value here -- see
     * this class's docblock -- and corrected in after_restore().
     *
     * @param array|stdClass $data The parsed decision element
     * @return void
     */
    protected function process_confprogram_decision($data) {
        global $DB;

        $data = (object) $data;
        $data->confprogram = $this->get_new_parentid('confprogram');
        $data->decidedby = $this->get_mappingid('user', $data->decidedby);

        $DB->insert_record('confprogram_decision', $data);
    }

    /**
     * Restores a favourite. submissionid is left as its old (unmapped) value here -- see
     * this class's docblock -- and corrected in after_restore().
     *
     * @param array|stdClass $data The parsed favourite element
     * @return void
     */
    protected function process_confprogram_favourite($data) {
        global $DB;

        $data = (object) $data;
        $data->confprogram = $this->get_new_parentid('confprogram');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $DB->insert_record('confprogram_favourite', $data);
    }

    /**
     * Restores an unvetted flag. submissionid is left as its old (unmapped) value here --
     * see this class's docblock -- and corrected in after_restore().
     *
     * @param array|stdClass $data The parsed unvetted element
     * @return void
     */
    protected function process_confprogram_unvetted($data) {
        global $DB;

        $data = (object) $data;
        $data->confprogram = $this->get_new_parentid('confprogram');
        $data->setby = $this->get_mappingid('user', $data->setby);

        $DB->insert_record('confprogram_unvetted', $data);
    }

    /**
     * Restores files attached to the confprogram intro.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_confprogram', 'intro', null);
    }

    /**
     * Fixes up every cross-activity reference into mod_confsubmissions now that ALL
     * activities in this course restore have completed their main structure step (see
     * this class's docblock for why this can only safely happen here).
     *
     * @return void
     */
    protected function after_restore() {
        global $DB;

        $confprogramid = $this->task->get_activityid();

        $confprogram = $DB->get_record('confprogram', ['id' => $confprogramid], '*', MUST_EXIST);
        $newcmid = $this->get_mappingid('course_module', $confprogram->confsubmissionscmid);
        // 0 (rather than leaving the old, no-longer-relevant cmid) when the linked
        // mod_confsubmissions instance wasn't included in this backup/restore --
        // deliberately a visibly BROKEN state (every page here MUST_EXIST-resolves this
        // cmid) rather than a silently WRONG one pointing at an unrelated activity that
        // happens to share the old numeric id in the destination site. An organiser must
        // re-link the setting to recover.
        $DB->set_field('confprogram', 'confsubmissionscmid', $newcmid ?: 0, ['id' => $confprogramid]);

        foreach (
            ['confprogram_assignment', 'confprogram_review', 'confprogram_decision',
                'confprogram_favourite', 'confprogram_unvetted', ] as $table
        ) {
            $rows = $DB->get_records($table, ['confprogram' => $confprogramid]);
            foreach ($rows as $row) {
                $newsubmissionid = $this->get_mappingid('confsubmissions_submission', $row->submissionid);
                if ($newsubmissionid) {
                    $DB->set_field($table, 'submissionid', $newsubmissionid, ['id' => $row->id]);
                } else {
                    // The submission this row references wasn't included in this backup/
                    // restore (mod_confsubmissions not selected, or a genuinely stale
                    // cross-plugin reference) -- delete rather than leave a NOTNULL
                    // submissionid silently pointing at an unrelated submission that
                    // happens to share the old numeric id.
                    $DB->delete_records($table, ['id' => $row->id]);
                }
            }
        }

        // Pair each review back up with its restored grading instance, now that core's
        // own restore_activity_grading_structure_step (which runs after this step, but
        // still within the same activity restore, well before after_restore()) has
        // finished restoring grading_instances and set its own 'grading_instance'
        // mapping. Each row's own gradinginstanceid column is still holding its OLD
        // (pre-restore) value at this point -- process_confprogram_review() deliberately
        // left it untouched, since the new grading_instances row didn't exist yet then.
        $reviews = $DB->get_records_select(
            'confprogram_review',
            'confprogram = :confprogram AND gradinginstanceid <> 0',
            ['confprogram' => $confprogramid]
        );
        foreach ($reviews as $review) {
            $newgradinginstanceid = $this->get_mappingid('grading_instance', $review->gradinginstanceid);
            // 0 (the plugin's own "placeholder, not yet graded" convention) when the
            // grading instance wasn't restored (e.g. 'userinfo' was off, in which case
            // this review row wouldn't exist at all either -- so in practice this only
            // happens if core's grading restore itself skipped/rejected the instance).
            $DB->set_field('confprogram_review', 'gradinginstanceid', $newgradinginstanceid ?: 0, ['id' => $review->id]);
        }
    }
}
