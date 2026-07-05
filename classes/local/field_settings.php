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

namespace mod_confprogram\local;

use mod_confsubmissions\api as submissions_api;

/**
 * Display-phase per-field visibility configuration (confprogram_fieldsetting).
 *
 * "Available fields" for an instance are the fixed set (title, abstract, track,
 * speakers) plus whatever optional fields exist on the linked mod_confsubmissions
 * instance. Visibility (show in list / show in modal) is configured per confprogram
 * instance via displaysettings.php and stored as one confprogram_fieldsetting row per
 * field.
 *
 * Field keys (Revision round 1 follow-up, 2026-07-05 -- fixing a cross-plugin
 * contract break): a fixed field is keyed by its own name ('title', 'abstract', etc.),
 * which is stable and has its own lang string. An optional mod_confsubmissions field is
 * keyed by OPTIONAL_FIELD_PREFIX . its confsubmissions_field id (e.g. 'opt5'), NOT by
 * its name -- since mod_confsubmissions moved to a fully dynamic, organiser-defined
 * field system (see that plugin's commit introducing confsubmissions_field.name/type),
 * a field's name is now free text (not unique, not a fixed vocabulary with its own lang
 * string, and renamable), so it can no longer double as a stable identifier the way it
 * could when only three fixed-name optional fields existed. This module and
 * field_formatter.php are the only two places that need to know about this encoding;
 * every caller of get_available_fields()/get_visible_fieldnames() still just gets back
 * opaque string keys to store/pass through and hand to field_formatter for display.
 *
 * Nothing here writes to the database except upsert(): the defaults documented on
 * get_settings_with_defaults() are applied in memory only, at render time, so that
 * a confprogram instance with no fieldsetting rows yet (the common case before an
 * organiser has ever visited displaysettings.php) still renders something sensible,
 * without silently persisting rows the organiser never explicitly saved.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_settings {
    /** @var string[] The fixed fields every mod_confsubmissions instance has. */
    public const FIXED_FIELDS = ['title', 'abstract', 'track', 'speakers'];

    /** @var string Prefix distinguishing an optional mod_confsubmissions field's key (e.g. 'opt5') from a fixed field's own key -- see this class's docblock. */
    public const OPTIONAL_FIELD_PREFIX = 'opt';

    /** @var string[] Fixed fields shown in the list by default, before any explicit save. */
    private const DEFAULT_LIST_FIELDS = ['title', 'track', 'speakers'];

    /**
     * Returns the available fields for an instance: the fixed set plus every optional
     * field configured on the linked mod_confsubmissions instance, in a stable order
     * (fixed fields first, then optional fields in their configured sort order).
     *
     * @param int $confsubmissionsid The mod_confsubmissions instance id
     * @return string[] Field keys -- see this class's docblock for the encoding
     */
    public static function get_available_fields(int $confsubmissionsid): array {
        $optionalkeys = array_map(
            fn($field) => self::OPTIONAL_FIELD_PREFIX . $field->id,
            array_values(submissions_api::get_fields($confsubmissionsid))
        );

        return array_merge(self::FIXED_FIELDS, $optionalkeys);
    }

    /**
     * Decodes the confsubmissions_field id encoded in an optional-field key (e.g.
     * 'opt5' -> 5).
     *
     * @param string $fieldkey A field key as returned by get_available_fields()
     * @return int|null The field id, or null if $fieldkey is one of FIXED_FIELDS (not an optional field)
     */
    public static function optional_fieldid_from_key(string $fieldkey): ?int {
        if (!str_starts_with($fieldkey, self::OPTIONAL_FIELD_PREFIX)) {
            return null;
        }

        return (int) substr($fieldkey, strlen(self::OPTIONAL_FIELD_PREFIX));
    }

    /**
     * Returns the explicitly configured (saved) settings for an instance, keyed by
     * fieldname. Fields with no saved row are simply absent from the returned array;
     * see get_settings_with_defaults() for the version callers normally want.
     *
     * @param int $confprogramid The confprogram instance id
     * @return \stdClass[] Objects with showinlist/showinmodal bool properties, keyed by fieldname
     */
    public static function get_settings(int $confprogramid): array {
        global $DB;

        $records = $DB->get_records(
            'confprogram_fieldsetting',
            ['confprogram' => $confprogramid],
            '',
            'fieldname, showinlist, showinmodal'
        );

        $settings = [];
        foreach ($records as $fieldname => $record) {
            $settings[$fieldname] = (object) [
                'showinlist'  => (bool) $record->showinlist,
                'showinmodal' => (bool) $record->showinmodal,
            ];
        }

        return $settings;
    }

    /**
     * Returns settings for every given available field, filling in defaults where no
     * row has been explicitly saved yet.
     *
     * Two different default rules apply, matching the spec exactly:
     * - If this confprogram instance has NO fieldsetting rows at all yet (i.e.
     *   displaysettings.php has never been saved), every field gets the documented
     *   starting defaults: title/track/speakers checked for list, everything checked
     *   for modal.
     * - Otherwise (at least one row has been saved), any field still missing a row
     *   (e.g. a newly enabled optional field on mod_confsubmissions since the last
     *   save) defaults to hidden (false/false) until the organiser explicitly turns
     *   it on.
     *
     * @param int $confprogramid The confprogram instance id
     * @param string[] $availablefields Field names, as returned by get_available_fields()
     * @return \stdClass[] Objects with showinlist/showinmodal bool properties, keyed by fieldname
     */
    public static function get_settings_with_defaults(int $confprogramid, array $availablefields): array {
        global $DB;

        $configured = self::get_settings($confprogramid);
        $hasany = $DB->record_exists('confprogram_fieldsetting', ['confprogram' => $confprogramid]);

        $result = [];
        foreach ($availablefields as $fieldname) {
            if (isset($configured[$fieldname])) {
                $result[$fieldname] = $configured[$fieldname];
            } else if (!$hasany) {
                $result[$fieldname] = (object) [
                    'showinlist'  => in_array($fieldname, self::DEFAULT_LIST_FIELDS, true),
                    'showinmodal' => true,
                ];
            } else {
                $result[$fieldname] = (object) ['showinlist' => false, 'showinmodal' => false];
            }
        }

        return $result;
    }

    /**
     * Returns the subset of available fields visible in a given mode ('list' or
     * 'modal'), in the same order as $availablefields.
     *
     * @param int $confprogramid The confprogram instance id
     * @param string[] $availablefields Field names, as returned by get_available_fields()
     * @param string $mode Either 'list' or 'modal'
     * @return string[] Visible field names, in order
     */
    public static function get_visible_fieldnames(int $confprogramid, array $availablefields, string $mode): array {
        $settings = self::get_settings_with_defaults($confprogramid, $availablefields);
        $property = $mode === 'modal' ? 'showinmodal' : 'showinlist';

        return array_values(array_filter(
            $availablefields,
            fn($fieldname) => !empty($settings[$fieldname]->$property)
        ));
    }

    /**
     * Saves (upserts) the visibility settings for a set of fields. Only ever called
     * from displaysettings.php's POST handler, i.e. only on an explicit save.
     *
     * @param int $confprogramid The confprogram instance id
     * @param array $settings Settings keyed by fieldname, each an array with
     *                         'showinlist' and 'showinmodal' bool-ish values
     * @return void
     */
    public static function upsert(int $confprogramid, array $settings): void {
        global $DB;

        foreach ($settings as $fieldname => $values) {
            $existing = $DB->get_record('confprogram_fieldsetting', [
                'confprogram' => $confprogramid,
                'fieldname'   => $fieldname,
            ]);

            $record = (object) [
                'confprogram' => $confprogramid,
                'fieldname'   => $fieldname,
                'showinlist'  => !empty($values['showinlist']) ? 1 : 0,
                'showinmodal' => !empty($values['showinmodal']) ? 1 : 0,
            ];

            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('confprogram_fieldsetting', $record);
            } else {
                $DB->insert_record('confprogram_fieldsetting', $record);
            }
        }
    }
}
