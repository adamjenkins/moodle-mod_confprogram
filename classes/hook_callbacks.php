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

namespace mod_confprogram;

use core\hook\output\before_footer_html_generation;

/**
 * Hook callbacks for mod_confprogram.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /** @var string[] Core's rubric pages that print criterion/level text with its raw multilang tags. */
    private const RUBRIC_PAGES = [
        '/grade/grading/manage.php',
        '/grade/grading/form/rubric/edit.php',
        '/grade/grading/form/rubric/preview.php',
    ];

    /**
     * On core's rubric editor and preview pages for a confprogram review rubric, loads
     * amd/src/rubric_multilang.js, which shows multilang criterion/level text as reviewers
     * see it, with a "multilang" button that shows the saved source.
     *
     * @param before_footer_html_generation $hook
     */
    public static function add_rubric_multilang(before_footer_html_generation $hook): void {
        global $PAGE;

        if (during_initial_install() || !$PAGE->has_set_url() || !$PAGE->cm || $PAGE->cm->modname !== 'confprogram') {
            return;
        }
        $path = strtok($PAGE->url->out_as_local_url(false), '?');
        if (!in_array($path, self::RUBRIC_PAGES, true)) {
            return;
        }
        $PAGE->requires->js_call_amd('mod_confprogram/rubric_multilang', 'init', [(int) $PAGE->cm->id]);
    }
}
