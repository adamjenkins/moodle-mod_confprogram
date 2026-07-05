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
 * Tests for \mod_confprogram\local\field_settings, in particular the
 * fieldid-keyed encoding of optional-field keys (Revision round 1 follow-up,
 * 2026-07-05) that replaced the old fieldname-keyed one after
 * mod_confsubmissions moved to a fully dynamic, organiser-defined field
 * system. This class previously had zero test coverage, which is exactly how
 * the cross-plugin contract break (mod_confsubmissions\api::get_enabled_fieldnames()
 * removed, mod_confprogram never updated) shipped undetected -- see
 * SUMMARY.md/RELATIONS.md.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(field_settings::class)]
final class field_settings_test extends advanced_testcase {
    /**
     * Creates a confsubmissions instance with two organiser-defined optional fields.
     *
     * @return array{0: int, 1: int, 2: int} [$confsubmissionsid, $firstfieldid, $secondfieldid]
     */
    private function create_confsubmissions_with_fields(): array {
        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);

        $firstfieldid = submissions_api::add_field((int) $confsubmissions->id, 'Company/affiliation', 'text', null, false);
        $secondfieldid = submissions_api::add_field((int) $confsubmissions->id, 'Preferred room', 'text', null, false);

        return [(int) $confsubmissions->id, $firstfieldid, $secondfieldid];
    }

    /**
     * get_available_fields() returns the four fixed fields, followed by one
     * OPTIONAL_FIELD_PREFIX-prefixed key per optional field, in sort order --
     * NOT the field's own (organiser-renamable, non-unique) name.
     */
    public function test_get_available_fields_includes_fixed_and_fieldid_keyed_optional_fields(): void {
        $this->resetAfterTest();

        [$confsubmissionsid, $firstfieldid, $secondfieldid] = $this->create_confsubmissions_with_fields();

        $fields = field_settings::get_available_fields($confsubmissionsid);

        $this->assertSame(
            array_merge(
                field_settings::FIXED_FIELDS,
                [
                    field_settings::OPTIONAL_FIELD_PREFIX . $firstfieldid,
                    field_settings::OPTIONAL_FIELD_PREFIX . $secondfieldid,
                ]
            ),
            $fields
        );
    }

    /**
     * get_available_fields() returns just the fixed fields when the linked
     * confsubmissions instance has no optional fields configured at all.
     */
    public function test_get_available_fields_with_no_optional_fields(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);

        $this->assertSame(field_settings::FIXED_FIELDS, field_settings::get_available_fields((int) $confsubmissions->id));
    }

    /**
     * optional_fieldid_from_key() decodes an optional-field key back to its
     * confsubmissions_field id, and returns null for every fixed field's own key.
     */
    public function test_optional_fieldid_from_key(): void {
        $this->assertSame(5, field_settings::optional_fieldid_from_key(field_settings::OPTIONAL_FIELD_PREFIX . '5'));

        foreach (field_settings::FIXED_FIELDS as $fixedfield) {
            $this->assertNull(field_settings::optional_fieldid_from_key($fixedfield));
        }
    }

    /**
     * upsert()/get_settings() round-trip using an optional field's fieldid-keyed key,
     * and get_settings_with_defaults() applies the documented "no rows yet" defaults.
     */
    public function test_upsert_and_get_settings_with_defaults(): void {
        $this->resetAfterTest();

        [$confsubmissionsid, $firstfieldid] = $this->create_confsubmissions_with_fields();
        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $this->getDataGenerator()->create_course()->id,
            'confsubmissionscmid' => get_coursemodule_from_instance('confsubmissions', $confsubmissionsid)->id,
        ]);

        $availablefields = field_settings::get_available_fields($confsubmissionsid);
        $optionalkey = field_settings::OPTIONAL_FIELD_PREFIX . $firstfieldid;

        // Before any explicit save: title/track/speakers default to shown-in-list, and
        // every available field (including the optional one) defaults to shown-in-modal.
        $defaults = field_settings::get_settings_with_defaults((int) $confprogram->id, $availablefields);
        $this->assertTrue($defaults['title']->showinlist);
        $this->assertFalse($defaults['abstract']->showinlist);
        $this->assertTrue($defaults[$optionalkey]->showinmodal);

        // After an explicit save, only the saved fields' rows exist; anything else
        // available now defaults to hidden.
        field_settings::upsert((int) $confprogram->id, [
            $optionalkey => ['showinlist' => true, 'showinmodal' => false],
        ]);

        $aftersave = field_settings::get_settings_with_defaults((int) $confprogram->id, $availablefields);
        $this->assertTrue($aftersave[$optionalkey]->showinlist);
        $this->assertFalse($aftersave[$optionalkey]->showinmodal);
        $this->assertFalse($aftersave['title']->showinlist);
    }
}
