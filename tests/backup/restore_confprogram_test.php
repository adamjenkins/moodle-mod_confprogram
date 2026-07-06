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

namespace mod_confprogram\backup;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');
require_once($CFG->dirroot . '/mod/confprogram/backup/moodle2/restore_confprogram_stepslib.php');

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Backup/restore tests for mod_confprogram (user request, 2026-07-06: "Also make sure
 * backup/restore/reset all works fine with all plugins").
 *
 * The critical thing this exercises, beyond mod_confsubmissions's own equivalent test:
 * cross-ACTIVITY reference remapping (confsubmissionscmid, and every submissionid column)
 * resolved in after_restore() rather than during the main structure step, plus this
 * plugin's use of core's Advanced Grading API (FEATURE_ADVANCED_GRADING) -- a rubric
 * review's own gradinginstanceid must end up pointing at the RESTORED grading_instances
 * row, not the original one.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\restore_confprogram_activity_structure_step::class)]
final class restore_confprogram_test extends \restore_date_testcase {
    /**
     * A full backup/restore round-trip correctly reconstructs a confprogram instance's
     * data, with every cross-activity reference (confsubmissionscmid, and every
     * submissionid column) pointing at the RESTORED copies, not the originals -- and a
     * rubric review's gradinginstanceid pointing at its own restored grading instance.
     */
    public function test_backup_and_restore_remaps_cross_activity_references(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['startdate' => $this->startdate]);

        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);
        $confprogramcm = get_coursemodule_from_instance('confprogram', $confprogram->id);
        $context = \context_module::instance($confprogramcm->id);

        $speaker = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $decider = $this->getDataGenerator()->create_user();

        $now = time();
        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => $speaker->id,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => $now,
            'timemodified'    => $now,
        ]);

        \mod_confprogram\api::assign_reviewer((int) $confprogram->id, $submissionid, (int) $reviewer->id);
        \mod_confprogram\api::add_favourite((int) $confprogram->id, $submissionid, (int) $speaker->id);
        \mod_confprogram\api::set_unvetted((int) $confprogram->id, $submissionid, (int) $decider->id);
        \mod_confprogram\api::record_decision((int) $confprogram->id, $submissionid, 'accept', 1, (int) $decider->id);

        $areaid = (int) $DB->insert_record('grading_areas', (object) [
            'contextid'    => $context->id,
            'component'    => 'mod_confprogram',
            'areaname'     => 'review',
            'activemethod' => 'rubric',
        ]);
        $definitionid = (int) $DB->insert_record('grading_definitions', (object) [
            'areaid'            => $areaid,
            'method'            => 'rubric',
            'name'              => 'Test rubric',
            'status'            => 20, // Gradingform_controller::DEFINITION_STATUS_READY.
            'timecreated'       => $now,
            'usercreated'       => $decider->id,
            'timemodified'      => $now,
            'usermodified'      => $decider->id,
        ]);
        $reviewid = (int) \mod_confprogram\api::upsert_review(
            (int) $confprogram->id,
            $submissionid,
            (int) $reviewer->id,
            1,
            0,
            null
        );
        $gradinginstanceid = (int) $DB->insert_record('grading_instances', (object) [
            'definitionid' => $definitionid,
            'raterid'      => $reviewer->id,
            'itemid'       => $reviewid,
            'rawgrade'     => 85.0,
            'status'       => 1, // Gradingform_instance::INSTANCE_STATUS_ACTIVE.
            'timemodified' => $now,
        ]);
        \mod_confprogram\api::upsert_review(
            (int) $confprogram->id,
            $submissionid,
            (int) $reviewer->id,
            1,
            $gradinginstanceid,
            85.0
        );

        $newcourseid = $this->backup_and_restore($course);

        $newconfsubmissions = $DB->get_record('confsubmissions', ['course' => $newcourseid], '*', MUST_EXIST);
        $newconfsubmissionscm = get_coursemodule_from_instance('confsubmissions', $newconfsubmissions->id);
        $newconfprogram = $DB->get_record('confprogram', ['course' => $newcourseid], '*', MUST_EXIST);
        $newsubmission = $DB->get_record(
            'confsubmissions_submission',
            ['confsubmissions' => $newconfsubmissions->id],
            '*',
            MUST_EXIST
        );

        // The critical cross-activity checks.
        $this->assertSame((int) $newconfsubmissionscm->id, (int) $newconfprogram->confsubmissionscmid);

        $newassignment = $DB->get_record('confprogram_assignment', ['confprogram' => $newconfprogram->id], '*', MUST_EXIST);
        $this->assertSame((int) $newsubmission->id, (int) $newassignment->submissionid);

        $newfavourite = $DB->get_record('confprogram_favourite', ['confprogram' => $newconfprogram->id], '*', MUST_EXIST);
        $this->assertSame((int) $newsubmission->id, (int) $newfavourite->submissionid);

        $newunvetted = $DB->get_record('confprogram_unvetted', ['confprogram' => $newconfprogram->id], '*', MUST_EXIST);
        $this->assertSame((int) $newsubmission->id, (int) $newunvetted->submissionid);

        $newdecision = $DB->get_record('confprogram_decision', ['confprogram' => $newconfprogram->id], '*', MUST_EXIST);
        $this->assertSame((int) $newsubmission->id, (int) $newdecision->submissionid);

        $newreview = $DB->get_record('confprogram_review', ['confprogram' => $newconfprogram->id], '*', MUST_EXIST);
        $this->assertSame((int) $newsubmission->id, (int) $newreview->submissionid);

        // The grading-instance pairing check: the restored review's gradinginstanceid
        // must point at a REAL, restored grading_instances row whose own itemid points
        // right back at this same restored review -- not the original ids.
        $this->assertNotSame(0, (int) $newreview->gradinginstanceid);
        $this->assertNotSame($gradinginstanceid, (int) $newreview->gradinginstanceid);
        $newgradinginstance = $DB->get_record(
            'grading_instances',
            ['id' => $newreview->gradinginstanceid],
            '*',
            MUST_EXIST
        );
        $this->assertSame((int) $newreview->id, (int) $newgradinginstance->itemid);
        $this->assertEqualsWithDelta(85.0, (float) $newgradinginstance->rawgrade, 0.001);
    }
}
