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

namespace mod_confprogram\external;

use advanced_testcase;
use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the format_rubric_text AJAX external function used by
 * amd/src/rubric_multilang.js on core's rubric editor and preview pages.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(format_rubric_text::class)]
final class format_rubric_text_test extends advanced_testcase {
    /**
     * Creates a course with a confprogram instance and returns its cmid.
     *
     * @return array{0: \stdClass, 1: int} course, cmid
     */
    private function create_fixture(): array {
        $course = $this->getDataGenerator()->create_course(['summary' => '']);
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);
        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);
        return [$course, (int) get_coursemodule_from_instance('confprogram', $confprogram->id)->id];
    }

    /**
     * An editing teacher gets each text back in the current language, in order; plain
     * text comes back unchanged.
     */
    public function test_formats_texts_in_order(): void {
        $this->resetAfterTest();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        [$course, $cmid] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $bilingual = '<span lang="en" class="multilang">Originality</span><span lang="ja" class="multilang">独創性</span>';
        $result = external_api::clean_returnvalue(
            format_rubric_text::execute_returns(),
            format_rubric_text::execute($cmid, [$bilingual, 'Score < 3'])
        );

        $this->assertSame(['Originality', 'Score < 3'], $result);
    }

    /**
     * A student, who neither manages grading forms nor reviews, is refused.
     */
    public function test_student_is_refused(): void {
        $this->resetAfterTest();
        [$course, $cmid] = $this->create_fixture();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        format_rubric_text::execute($cmid, ['x']);
    }
}
