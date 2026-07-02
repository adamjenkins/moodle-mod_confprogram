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

namespace mod_confprogram\output;

use renderable;
use renderer_base;
use templatable;

/**
 * Body content of the Display-phase submission detail modal
 * (amd/src/programlist.js, backed by \mod_confprogram\external\get_submission_detail).
 *
 * Field values are passed through already formatted (see
 * \mod_confprogram\local\field_formatter::format_value()) but NOT html-escaped; the
 * template outputs them via an escaped {{ }} tag, so this class must never be handed
 * pre-escaped or raw-HTML content.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_modal implements renderable, templatable {
    /** @var array{label: string, value: string}[] The visible (showinmodal) fields, in order */
    protected $fields;

    /** @var string The formatted schedule text, including the "not yet scheduled" fallback */
    protected $scheduletext;

    /**
     * Constructor.
     *
     * @param array $fields The visible (showinmodal) fields, each ['label' => ..., 'value' => ...]
     * @param string $scheduletext The formatted schedule text
     */
    public function __construct(array $fields, string $scheduletext) {
        $this->fields = $fields;
        $this->scheduletext = $scheduletext;
    }

    /**
     * Exports data for the submission_modal.mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'scheduletext' => $this->scheduletext,
            'hasfields'    => !empty($this->fields),
            'fields'       => $this->fields,
        ];
    }
}
