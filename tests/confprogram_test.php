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

declare(strict_types=1);

namespace mod_confprogram;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Smoke tests for mod_confprogram: confirms the plugin installs cleanly and
 * that a course-module instance can be created via the standard data
 * generator, pointed at a mod_confsubmissions instance.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class confprogram_test extends advanced_testcase {
    /**
     * An activity instance can be added to a course via the data generator,
     * pointed at an existing mod_confsubmissions instance, and the resulting
     * row exists in the confprogram table with its schema defaults applied.
     */
    public function test_instance_can_be_added_via_generator(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', [
            'course' => $course->id,
        ]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'name'                => 'Test program',
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);

        $this->assertNotEmpty($confprogram->id);

        global $DB;
        $record = $DB->get_record('confprogram', ['id' => $confprogram->id]);
        $this->assertNotFalse($record);
        $this->assertSame('Test program', $record->name);
        $this->assertSame('review', $record->phase);
        $this->assertEquals($confsubmissionscm->id, $record->confsubmissionscmid);
    }

    /**
     * The privacy provider class exists and implements the expected interfaces.
     */
    public function test_privacy_provider_exists(): void {
        $this->assertTrue(class_exists(\mod_confprogram\privacy\provider::class));
        $this->assertInstanceOf(
            \core_privacy\local\metadata\provider::class,
            new \mod_confprogram\privacy\provider()
        );
        $this->assertInstanceOf(
            \core_privacy\local\request\plugin\provider::class,
            new \mod_confprogram\privacy\provider()
        );
        $this->assertInstanceOf(
            \core_privacy\local\request\core_userlist_provider::class,
            new \mod_confprogram\privacy\provider()
        );
    }

    /**
     * confprogram_supports() answers sensibly for the core feature constants used.
     */
    public function test_supports_returns_expected_values(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/confprogram/lib.php');

        $this->assertTrue(confprogram_supports(FEATURE_MOD_INTRO));
        $this->assertFalse(confprogram_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertSame(MOD_PURPOSE_OTHER, confprogram_supports(FEATURE_MOD_PURPOSE));
        $this->assertNull(confprogram_supports('some_unknown_feature'));
    }

    /**
     * api::get_phase() reads the phase field via the course-module id.
     */
    public function test_api_get_phase(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', [
            'course' => $course->id,
        ]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);
        $cm = get_coursemodule_from_instance('confprogram', $confprogram->id);

        $this->assertSame('review', api::get_phase((int) $cm->id));
    }
}
