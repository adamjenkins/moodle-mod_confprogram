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

namespace mod_confprogram\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_confprogram.
 *
 * Personal data is stored in this plugin's own tables only:
 * - confprogram_assignment: the reviewer (reviewerid) assigned to review a submission.
 *   reviewergroupid is a group id, not personal data of an individual, so it is not
 *   covered here.
 * - confprogram_reviewermax: a per-reviewer (userid) override of the max-reviews cap.
 * - confprogram_decision: the user who made the Accept/Reject/Resubmit/Waitlist call
 *   (decidedby).
 * - confprogram_favourite: the user (userid) who favourited a submission.
 * - confprogram_unvetted: the user (setby) who flagged a submission as unvetted.
 * confprogram_notiftemplate (organiser-authored decision-notification subject/body
 * text) is NOT covered here -- it is instance configuration, not personal data,
 * matching mod_confcheckin's own confcheckin_template exclusion.
 *
 * mod_confsubmissions's own tables (the submissions being vetted) are that plugin's
 * privacy responsibility, not this one's; a submissionid stored on these rows is a
 * cross-plugin reference, not personal data owned by this plugin. Rubric review
 * comments/scores are stored by core's advanced grading API (grading_areas /
 * grading_definitions / grading_instances) once the grading area is registered in a
 * follow-up task, and are that core subsystem's privacy responsibility.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata describing the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('confprogram_assignment', [
            'reviewerid'  => 'privacy:metadata:confprogram_assignment:reviewerid',
            'timecreated' => 'privacy:metadata:confprogram_assignment:timecreated',
        ], 'privacy:metadata:confprogram_assignment');

        $collection->add_database_table('confprogram_reviewermax', [
            'userid'     => 'privacy:metadata:confprogram_reviewermax:userid',
            'maxreviews' => 'privacy:metadata:confprogram_reviewermax:maxreviews',
        ], 'privacy:metadata:confprogram_reviewermax');

        $collection->add_database_table('confprogram_decision', [
            'decidedby'    => 'privacy:metadata:confprogram_decision:decidedby',
            'decision'     => 'privacy:metadata:confprogram_decision:decision',
            'round'        => 'privacy:metadata:confprogram_decision:round',
            'timecreated'  => 'privacy:metadata:confprogram_decision:timecreated',
            // Notifiedtime is deliberately NOT declared here (moodle-reviewer finding,
            // 2026-07-06): it is an operational record of whether/when the schedule-
            // change notification was dispatched, not personal data about the decision
            // itself -- same reasoning that already excludes confprogram_notiftemplate
            // entirely (see this class's docblock). A field declared here but never
            // exported/deleted alongside it would be a worse inconsistency than simply
            // not declaring it.
        ], 'privacy:metadata:confprogram_decision');

        $collection->add_database_table('confprogram_favourite', [
            'userid'      => 'privacy:metadata:confprogram_favourite:userid',
            'timecreated' => 'privacy:metadata:confprogram_favourite:timecreated',
        ], 'privacy:metadata:confprogram_favourite');

        $collection->add_database_table('confprogram_unvetted', [
            'setby'       => 'privacy:metadata:confprogram_unvetted:setby',
            'timecreated' => 'privacy:metadata:confprogram_unvetted:timecreated',
        ], 'privacy:metadata:confprogram_unvetted');

        return $collection;
    }

    /**
     * Returns the list of contexts that contain personal data for the given user.
     *
     * A user has data in a context if they appear as a reviewer, a reviewermax
     * override subject, a decider, a favouriter, or the setter of an unvetted flag,
     * on any row belonging to the confprogram instance in that context.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = 'confprogram'
            INNER JOIN {confprogram} cp ON cp.id = cm.instance
                 WHERE EXISTS (
                        SELECT 1 FROM {confprogram_assignment} a
                         WHERE a.confprogram = cp.id AND a.reviewerid = :userid1
                       )
                    OR EXISTS (
                        SELECT 1 FROM {confprogram_reviewermax} rm
                         WHERE rm.confprogram = cp.id AND rm.userid = :userid2
                       )
                    OR EXISTS (
                        SELECT 1 FROM {confprogram_decision} d
                         WHERE d.confprogram = cp.id AND d.decidedby = :userid3
                       )
                    OR EXISTS (
                        SELECT 1 FROM {confprogram_favourite} f
                         WHERE f.confprogram = cp.id AND f.userid = :userid4
                       )
                    OR EXISTS (
                        SELECT 1 FROM {confprogram_unvetted} u
                         WHERE u.confprogram = cp.id AND u.setby = :userid5
                       )";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid1'      => $userid,
            'userid2'      => $userid,
            'userid3'      => $userid,
            'userid4'      => $userid,
            'userid5'      => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Gets the list of users within the specified context who have personal data.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('confprogram', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userlist->add_from_sql(
            'reviewerid',
            "SELECT reviewerid FROM {confprogram_assignment}
              WHERE confprogram = :instanceid1 AND reviewerid IS NOT NULL",
            ['instanceid1' => $cm->instance]
        );

        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {confprogram_reviewermax} WHERE confprogram = :instanceid2",
            ['instanceid2' => $cm->instance]
        );

        $userlist->add_from_sql(
            'decidedby',
            "SELECT decidedby FROM {confprogram_decision} WHERE confprogram = :instanceid3",
            ['instanceid3' => $cm->instance]
        );

        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {confprogram_favourite} WHERE confprogram = :instanceid4",
            ['instanceid4' => $cm->instance]
        );

        $userlist->add_from_sql(
            'setby',
            "SELECT setby FROM {confprogram_unvetted} WHERE confprogram = :instanceid5",
            ['instanceid5' => $cm->instance]
        );
    }

    /**
     * Exports personal data for the approved contexts belonging to the user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('confprogram', $context->instanceid);
            $confprogram = $DB->get_record('confprogram', ['id' => $cm->instance]);
            if (!$confprogram) {
                continue;
            }

            $assignments = $DB->get_records(
                'confprogram_assignment',
                ['confprogram' => $confprogram->id, 'reviewerid' => $userid]
            );
            if ($assignments) {
                $data = array_map(fn($a) => (object) [
                    'submissionid' => $a->submissionid,
                    'timecreated'  => \core_privacy\local\request\transform::datetime($a->timecreated),
                ], array_values($assignments));
                writer::with_context($context)->export_data(['reviewer_assignments'], (object) ['assignments' => $data]);
            }

            $reviewermax = $DB->get_record(
                'confprogram_reviewermax',
                ['confprogram' => $confprogram->id, 'userid' => $userid]
            );
            if ($reviewermax) {
                writer::with_context($context)->export_data(['reviewer_max_override'], (object) [
                    'maxreviews' => $reviewermax->maxreviews,
                ]);
            }

            $decisions = $DB->get_records(
                'confprogram_decision',
                ['confprogram' => $confprogram->id, 'decidedby' => $userid]
            );
            if ($decisions) {
                $data = array_map(fn($d) => (object) [
                    'submissionid' => $d->submissionid,
                    'decision'     => $d->decision,
                    'round'        => $d->round,
                    'timecreated'  => \core_privacy\local\request\transform::datetime($d->timecreated),
                ], array_values($decisions));
                writer::with_context($context)->export_data(['decisions_made'], (object) ['decisions' => $data]);
            }

            $favourites = $DB->get_records(
                'confprogram_favourite',
                ['confprogram' => $confprogram->id, 'userid' => $userid]
            );
            if ($favourites) {
                $data = array_map(fn($f) => (object) [
                    'submissionid' => $f->submissionid,
                    'timecreated'  => \core_privacy\local\request\transform::datetime($f->timecreated),
                ], array_values($favourites));
                writer::with_context($context)->export_data(['favourites'], (object) ['favourites' => $data]);
            }

            $unvetted = $DB->get_records(
                'confprogram_unvetted',
                ['confprogram' => $confprogram->id, 'setby' => $userid]
            );
            if ($unvetted) {
                $data = array_map(fn($u) => (object) [
                    'submissionid' => $u->submissionid,
                    'timecreated'  => \core_privacy\local\request\transform::datetime($u->timecreated),
                ], array_values($unvetted));
                writer::with_context($context)->export_data(['unvetted_flags_set'], (object) ['flags' => $data]);
            }
        }
    }

    /**
     * Deletes all personal data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('confprogram', $context->instanceid);
        $confprogram = $DB->get_record('confprogram', ['id' => $cm->instance]);
        if (!$confprogram) {
            return;
        }

        $DB->delete_records('confprogram_assignment', ['confprogram' => $confprogram->id]);
        $DB->delete_records('confprogram_reviewermax', ['confprogram' => $confprogram->id]);
        $DB->delete_records('confprogram_decision', ['confprogram' => $confprogram->id]);
        $DB->delete_records('confprogram_favourite', ['confprogram' => $confprogram->id]);
        $DB->delete_records('confprogram_unvetted', ['confprogram' => $confprogram->id]);
    }

    /**
     * Deletes all personal data for the specified user in the given contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('confprogram', $context->instanceid);
            $confprogram = $DB->get_record('confprogram', ['id' => $cm->instance]);
            if (!$confprogram) {
                continue;
            }

            $DB->delete_records('confprogram_assignment', [
                'confprogram' => $confprogram->id,
                'reviewerid'  => $userid,
            ]);
            $DB->delete_records('confprogram_reviewermax', [
                'confprogram' => $confprogram->id,
                'userid'      => $userid,
            ]);
            $DB->delete_records('confprogram_decision', [
                'confprogram' => $confprogram->id,
                'decidedby'   => $userid,
            ]);
            $DB->delete_records('confprogram_favourite', [
                'confprogram' => $confprogram->id,
                'userid'      => $userid,
            ]);
            $DB->delete_records('confprogram_unvetted', [
                'confprogram' => $confprogram->id,
                'setby'       => $userid,
            ]);
        }
    }

    /**
     * Deletes personal data for the given users in the specified context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('confprogram', $context->instanceid);
        $confprogram = $DB->get_record('confprogram', ['id' => $cm->instance]);
        if (!$confprogram) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids);

        $DB->delete_records_select(
            'confprogram_assignment',
            "confprogram = ? AND reviewerid $insql",
            array_merge([$confprogram->id], $params)
        );
        $DB->delete_records_select(
            'confprogram_reviewermax',
            "confprogram = ? AND userid $insql",
            array_merge([$confprogram->id], $params)
        );
        $DB->delete_records_select(
            'confprogram_decision',
            "confprogram = ? AND decidedby $insql",
            array_merge([$confprogram->id], $params)
        );
        $DB->delete_records_select(
            'confprogram_favourite',
            "confprogram = ? AND userid $insql",
            array_merge([$confprogram->id], $params)
        );
        $DB->delete_records_select(
            'confprogram_unvetted',
            "confprogram = ? AND setby $insql",
            array_merge([$confprogram->id], $params)
        );
    }
}
