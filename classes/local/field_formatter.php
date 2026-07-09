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
 * Formats a single mod_confsubmissions field's label/value for Display-phase output
 * (the accepted-submissions list and the submission detail modal), shared so the two
 * do not duplicate the track/speaker lookup logic.
 *
 * A fixed field's label is sourced from mod_confsubmissions's own language file
 * (title, abstract, track, speakers) since mod_confsubmissions already owns those
 * strings and mod_confprogram already depends on it. An optional field's label is its
 * own organiser-chosen name (free text, not a lang string -- see field_settings.php's
 * docblock for why this changed from the old fixed-vocabulary field_<name> lookup).
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_formatter {
    /**
     * @var array<int, \stdClass>|null Track rows keyed by id, warmed by
     * preload_for_submissions(); null until any preload has run this request.
     */
    private static ?array $trackcache = null;

    /** @var array<int, array<int, string>> Optional-field values keyed by submissionid, warmed by preload. */
    private static array $valuescache = [];

    /** @var array<int, string[]> Ready-to-join speaker display names keyed by submissionid, warmed by preload. */
    private static array $speakernamescache = [];

    /**
     * Warms request-scoped caches so the per-row accessors below (format_value(),
     * get_track_pill_html()) answer from memory instead of issuing one query per
     * row/field (FABLE.md review, 2026-07-09: the public Display-phase list paid
     * one track query per row, one optional-field-values query per row PER
     * configured field, and per-speaker user lookups -- 2,000+ queries on a
     * 300-submission programme). Callers rendering a LIST should call this once
     * with the full row set; single-submission callers (the detail modal) need
     * not bother -- every accessor still falls back to its original query when
     * the cache has no entry, with identical results either way.
     *
     * @param \stdClass[] $submissions The confsubmissions_submission records about to be rendered
     * @return void
     */
    public static function preload_for_submissions(array $submissions): void {
        global $DB;

        $submissionids = array_map(static fn($submission): int => (int) $submission->id, $submissions);
        if (!$submissionids) {
            return;
        }

        self::$trackcache = self::$trackcache ?? [];
        $trackids = array_diff(
            array_filter(array_unique(array_map(
                static fn($submission): int => (int) ($submission->trackid ?? 0),
                $submissions
            ))),
            array_keys(self::$trackcache)
        );
        if ($trackids) {
            self::$trackcache += $DB->get_records_list('confsubmissions_track', 'id', $trackids);
        }

        foreach (submissions_api::get_optional_field_values_for_submissions($submissionids) as $sid => $values) {
            self::$valuescache[$sid] = $values;
        }

        $speakersbysubmission = submissions_api::get_speakers_for_submissions($submissionids);
        $userids = [];
        foreach ($speakersbysubmission as $speakers) {
            foreach ($speakers as $speaker) {
                if (!empty($speaker->userid)) {
                    $userids[] = (int) $speaker->userid;
                }
            }
        }
        $namefields = implode(', ', array_merge(['id'], \core_user\fields::for_name()->get_required_fields()));
        $users = $userids
            ? $DB->get_records_list('user', 'id', array_unique($userids), '', $namefields)
            : [];
        foreach ($speakersbysubmission as $sid => $speakers) {
            $names = [];
            foreach ($speakers as $speaker) {
                if (!empty($speaker->userid)) {
                    if (isset($users[(int) $speaker->userid])) {
                        $names[] = fullname($users[(int) $speaker->userid]);
                    }
                } else if (!empty($speaker->name)) {
                    $names[] = format_string($speaker->name, true, ['escape' => false]);
                }
            }
            self::$speakernamescache[$sid] = $names;
        }
    }

    /**
     * Clears the request-scoped caches. Only needed by unit tests, which run many
     * "requests" in one PHP process.
     *
     * @return void
     */
    public static function reset_caches(): void {
        self::$trackcache = null;
        self::$valuescache = [];
        self::$speakernamescache = [];
    }

    /**
     * Returns the display label for a field.
     *
     * @param string $fieldkey A field key as returned by field_settings::get_available_fields()
     * @return string
     */
    public static function get_label(string $fieldkey): string {
        if (in_array($fieldkey, field_settings::FIXED_FIELDS, true)) {
            return get_string($fieldkey, 'mod_confsubmissions');
        }

        $fieldid = field_settings::optional_fieldid_from_key($fieldkey);
        $field = $fieldid !== null ? submissions_api::get_field($fieldid) : false;

        // A field's own name IS its label now (organiser free text, not a lang-string
        // lookup); '(deleted field)' is a defensive fallback for a field that has been
        // deleted from mod_confsubmissions since $fieldkey was resolved from a live
        // get_available_fields() call -- not expected to be reachable in practice
        // (every current caller derives $fieldkey from that same call immediately
        // beforehand) but cheaper to handle gracefully than to assume.
        return $field ? format_string($field->name, true, ['escape' => false]) : get_string('deletedfield', 'mod_confprogram');
    }

    /**
     * Returns the formatted value of a field for a submission. Never returns HTML: the
     * caller is responsible for escaping (e.g. via a Mustache {{ }} tag), since this
     * value may end up in either a server-rendered page or an AJAX-returned modal.
     *
     * @param string $fieldkey A field key as returned by field_settings::get_available_fields()
     * @param \stdClass $submission The confsubmissions_submission record
     * @return string The formatted value, or '' if the field has no value for this submission
     */
    public static function format_value(string $fieldkey, \stdClass $submission): string {
        global $DB;

        // Note: format_string() (below) HTML-entity-escapes by default, which would violate this method's
        // "never returns HTML" contract (every caller already escapes on output, e.g. s() or
        // an auto-escaping Mustache {{ }} tag) and produce visible double-escaping. Passing
        // escape => false keeps multilang filtering while returning plain text.
        $formatopts = ['escape' => false];

        switch ($fieldkey) {
            case 'title':
                return format_string($submission->title, true, $formatopts);

            case 'abstract':
                return (string) $submission->abstract;

            case 'track':
                if (empty($submission->trackid)) {
                    return get_string('notrack', 'mod_confsubmissions');
                }
                $track = self::fetch_track((int) $submission->trackid);
                return $track ? format_string($track->name, true, $formatopts) : get_string('notrack', 'mod_confsubmissions');

            case 'speakers':
                if (array_key_exists((int) $submission->id, self::$speakernamescache)) {
                    return implode(', ', self::$speakernamescache[(int) $submission->id]);
                }
                $names = [];
                foreach (submissions_api::get_speakers((int) $submission->id) as $speaker) {
                    if (!empty($speaker->userid)) {
                        $user = \core_user::get_user($speaker->userid);
                        if ($user) {
                            $names[] = fullname($user);
                        }
                    } else if (!empty($speaker->name)) {
                        $names[] = format_string($speaker->name, true, $formatopts);
                    }
                }
                return implode(', ', $names);

            default:
                $fieldid = field_settings::optional_fieldid_from_key($fieldkey);
                if ($fieldid === null) {
                    return '';
                }
                if (array_key_exists((int) $submission->id, self::$valuescache)) {
                    return (string) (self::$valuescache[(int) $submission->id][$fieldid] ?? '');
                }
                $values = submissions_api::get_optional_field_values((int) $submission->id);
                return (string) ($values[$fieldid] ?? '');
        }
    }

    /**
     * Fetches a track row through the preload cache when warm, falling back to a
     * direct query otherwise (single-submission callers that never preload).
     *
     * @param int $trackid The confsubmissions_track id
     * @return \stdClass|false The track record, or false if it no longer exists
     */
    private static function fetch_track(int $trackid) {
        global $DB;

        if (self::$trackcache !== null && array_key_exists($trackid, self::$trackcache)) {
            return self::$trackcache[$trackid];
        }

        return $DB->get_record('confsubmissions_track', ['id' => $trackid]);
    }

    /**
     * Returns a submission's track as trusted, ready-to-echo HTML: a coloured
     * "pill" badge (mirroring mod_confscheduler's identical
     * .mod_confscheduler-track-pill visual language, via this plugin's own
     * .mod_confprogram-track-pill CSS class), or the existing plain "no track"
     * string when the submission has none.
     *
     * Deliberately separate from format_value('track', ...): that method's
     * contract is "never returns HTML, caller escapes it" (the Display list
     * wraps it in s(), the modal template uses an auto-escaping {{ }} tag) --
     * a pill is real markup and returning it from format_value() would break
     * that contract for every other field sharing the same generic loop. This
     * follows the exact precedent already used for the 'title' field: excluded
     * from the generic per-field loop, rendered in its own dedicated slot,
     * still gated by the same show-in-list/show-in-modal visibility setting by
     * the caller.
     *
     * @param \stdClass $submission The confsubmissions_submission record
     * @return string Trusted HTML -- do not pass through s()/format_string() again
     */
    public static function get_track_pill_html(\stdClass $submission): string {
        if (empty($submission->trackid)) {
            return get_string('notrack', 'mod_confsubmissions');
        }

        $track = self::fetch_track((int) $submission->trackid);
        if (!$track) {
            return get_string('notrack', 'mod_confsubmissions');
        }

        // Deliberately WITHOUT escape => false here, unlike format_value()'s track case:
        // this method builds real HTML via html_writer::tag() below, which does not itself
        // escape its content argument -- format_string()'s own default HTML-entity escaping
        // (e.g. '&' -> '&amp;') is exactly what's needed for this to be valid HTML, not a
        // double-escape (that concern only applies when the result is ALSO passed through
        // an auto-escaping sink downstream, which this trusted-HTML return value never is).
        $name = format_string($track->name, true);
        $style = '';
        // Re-validate the hex shape here even though mod_confsubmissions\api::add_track()
        // already enforces it at write time (self::validate_colour()) -- this method has an
        // otherwise-implicit cross-plugin trust dependency on that upstream validation, and a
        // malformed value must never reach a raw style="" attribute (moodle-reviewer finding,
        // 2026-07-06).
        if (!empty($track->colour) && preg_match('/^#[0-9a-fA-F]{6}$/', $track->colour)) {
            $textcolour = self::contrast_text_colour($track->colour);
            $style = "background-color:{$track->colour};color:{$textcolour}";
        }

        // Fully-qualified: this file's namespace is mod_confprogram\local, and it has no
        // existing `use html_writer;` import (core classes resolve globally only when
        // referenced with a leading backslash from inside a namespace).
        return \html_writer::tag('span', $name, [
            'class' => 'mod_confprogram-track-pill',
            'style' => $style,
        ]);
    }

    /**
     * Picks black or white text to sit legibly on top of a given background hex
     * colour, using the classic YIQ "perceived brightness" formula. A PHP-side
     * duplicate of mod_confscheduler/amd/src/colour_utils.js's
     * contrastTextColour() (kept in sync by hand -- see that module's own
     * docblock for why this project duplicates small pure display logic across
     * the PHP/JS boundary rather than sharing it).
     *
     * @param string $hex A 6-digit hex colour, with or without a leading '#'
     * @return string '#000000' or '#ffffff'
     */
    private static function contrast_text_colour(string $hex): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return $brightness >= 128 ? '#000000' : '#ffffff';
    }
}
