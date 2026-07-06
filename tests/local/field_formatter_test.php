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

namespace mod_confprogram\local;

use advanced_testcase;
use mod_confsubmissions\api as submissions_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \mod_confprogram\local\field_formatter, in particular the fix for a
 * cross-plugin contract break: mod_confsubmissions moved optional fields to a fully
 * dynamic, organiser-defined system (fields identified by fieldid, freely named, no
 * fixed lang-string vocabulary), but this class still assumed the old fixed
 * three-checkbox model (get_string('field_' . $fieldname, ...), values keyed by
 * fieldname) -- which fatally errored the entire Display-phase submission list/modal
 * and the review page for any site using both plugins. This class previously had zero
 * test coverage.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(field_formatter::class)]
final class field_formatter_test extends advanced_testcase {
    /**
     * Creates a bare confsubmissions_submission row directly.
     *
     * @param int $confsubmissionsid
     * @return \stdClass
     */
    private function create_submission(int $confsubmissionsid, ?int $trackid = null): \stdClass {
        global $DB;

        $userid = $this->getDataGenerator()->create_user()->id;
        $id = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissionsid,
            'userid'          => $userid,
            'trackid'         => $trackid,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        return $DB->get_record('confsubmissions_submission', ['id' => $id]);
    }

    /**
     * get_label() for a fixed field still returns mod_confsubmissions's own lang string.
     */
    public function test_get_label_for_fixed_field(): void {
        $this->resetAfterTest();

        $this->assertSame(get_string('title', 'mod_confsubmissions'), field_formatter::get_label('title'));
    }

    /**
     * get_label() for an optional field returns the field's own organiser-chosen name
     * directly -- NOT a get_string('field_' . name, ...) lookup, which is exactly the
     * assumption that made this fatal before the fix (an arbitrary organiser-typed name
     * has no corresponding lang string).
     */
    public function test_get_label_for_optional_field_uses_its_own_name(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $fieldid = submissions_api::add_field((int) $confsubmissions->id, 'Preferred room', 'text', null, false);

        $label = field_formatter::get_label(field_settings::OPTIONAL_FIELD_PREFIX . $fieldid);

        $this->assertSame('Preferred room', $label);
    }

    /**
     * get_label() falls back to a generic string, rather than erroring, if the field
     * has since been deleted from mod_confsubmissions.
     */
    public function test_get_label_for_deleted_field_falls_back(): void {
        $this->resetAfterTest();

        $label = field_formatter::get_label(field_settings::OPTIONAL_FIELD_PREFIX . '999999');

        $this->assertSame(get_string('deletedfield', 'mod_confprogram'), $label);
    }

    /**
     * format_value() for the 'title' fixed field formats the submission's own title.
     */
    public function test_format_value_for_title(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $submission = $this->create_submission((int) $confsubmissions->id);

        $this->assertSame('A Test Talk', field_formatter::format_value('title', $submission));
    }

    /**
     * format_value() for an optional field reads the submitter's answer keyed by
     * fieldid (submissions_api::get_optional_field_values()'s actual key since the
     * fieldid-based schema migration), not by fieldname.
     */
    public function test_format_value_for_optional_field_reads_by_fieldid(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $fieldid = submissions_api::add_field((int) $confsubmissions->id, 'Preferred room', 'text', null, false);
        $submission = $this->create_submission((int) $confsubmissions->id);

        $DB->insert_record('confsubmissions_fieldval', (object) [
            'submissionid' => $submission->id,
            'fieldid'      => $fieldid,
            'value'        => 'Main Hall please',
        ]);

        $value = field_formatter::format_value(field_settings::OPTIONAL_FIELD_PREFIX . $fieldid, $submission);

        $this->assertSame('Main Hall please', $value);
    }

    /**
     * format_value() for an optional field with no submitted answer returns '', not a
     * PHP warning/notice.
     */
    public function test_format_value_for_optional_field_with_no_answer(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $fieldid = submissions_api::add_field((int) $confsubmissions->id, 'Preferred room', 'text', null, false);
        $submission = $this->create_submission((int) $confsubmissions->id);

        $value = field_formatter::format_value(field_settings::OPTIONAL_FIELD_PREFIX . $fieldid, $submission);

        $this->assertSame('', $value);
    }

    /**
     * A submission with a coloured track gets a pill with that colour as its
     * background, and white text (the track's colour, #3366cc, is dark enough
     * by the YIQ formula that white text is the correct contrast pick).
     */
    public function test_get_track_pill_html_with_colour(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $trackid = submissions_api::add_track((int) $confsubmissions->id, 'AI & Machine Learning', '#3366cc');
        $submission = $this->create_submission((int) $confsubmissions->id, $trackid);

        $html = field_formatter::get_track_pill_html($submission);

        $this->assertStringContainsString('mod_confprogram-track-pill', $html);
        $this->assertStringContainsString('background-color:#3366cc', $html);
        $this->assertStringContainsString('color:#ffffff', $html);
        // HTML-escaped once, not double-escaped: a literal '&' stays '&amp;' once.
        $this->assertStringContainsString('AI &amp; Machine Learning', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * A track with no configured colour gets the pill markup with no inline
     * background-color style at all, so the plugin's own default CSS colour applies.
     */
    public function test_get_track_pill_html_without_colour(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $trackid = submissions_api::add_track((int) $confsubmissions->id, 'Uncoloured Track');
        $submission = $this->create_submission((int) $confsubmissions->id, $trackid);

        $html = field_formatter::get_track_pill_html($submission);

        $this->assertStringContainsString('mod_confprogram-track-pill', $html);
        $this->assertStringNotContainsString('background-color', $html);
        $this->assertStringContainsString('Uncoloured Track', $html);
    }

    /**
     * A submission with no track at all falls back to the existing plain
     * "notrack" string, not an empty/broken pill.
     */
    public function test_get_track_pill_html_no_track(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $submission = $this->create_submission((int) $confsubmissions->id);

        $html = field_formatter::get_track_pill_html($submission);

        $this->assertSame(get_string('notrack', 'mod_confsubmissions'), $html);
    }
}
