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

/**
 * Makes multilang rubric text display in the reader's language.
 *
 * Core's rubric renderer prints every criterion description and level definition
 * through s() (grade/grading/form/rubric/renderer.php), so a bilingual criterion saved
 * as <span lang="en" class="multilang">...</span><span lang="ja" ...>...</span> (or the
 * {mlang} syntax) shows its tags literally on the review form. Instead of overriding
 * core's renderer, the rubric text is run through the context's text filters and
 * reduced to plain text before it reaches the renderer; s() then escapes plain text,
 * which is harmless. Nothing is written back to the database.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rubric_text {
    /**
     * Returns rubric text as plain text in the current language.
     *
     * Only text carrying multilang markup (a <span>/<lang> tag or {mlang}) is filtered;
     * anything else is returned unchanged, so a plain criterion such as "Score < 3" is
     * never mangled by strip_tags().
     *
     * @param string $text A criterion description or level definition as stored
     * @param \context $context The confprogram module context (decides the active filters)
     * @return string Plain text, still to be escaped by the caller
     */
    public static function format(string $text, \context $context): string {
        if (!preg_match('/<\s*(span|lang)\b|\{mlang/i', $text)) {
            return $text;
        }
        $filtered = \filter_manager::instance()->filter_text($text, $context);
        return trim(html_entity_decode(strip_tags($filtered), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Rewrites the criterion and level texts of a controller's loaded rubric definition
     * in place, so the core renderer (which reads the same cached definition) prints the
     * current language only. Call it after the grading instance is created and before
     * the form is rendered; it only changes this request's in-memory copy.
     *
     * @param \gradingform_controller $controller The controller whose definition is rendered
     * @param \context $context The confprogram module context
     */
    public static function localise_definition(\gradingform_controller $controller, \context $context): void {
        $definition = $controller->get_definition();
        if (!$definition || empty($definition->rubric_criteria)) {
            return;
        }
        foreach ($definition->rubric_criteria as $criterionid => $criterion) {
            $definition->rubric_criteria[$criterionid]['description'] =
                self::format((string) $criterion['description'], $context);
            foreach ($criterion['levels'] ?? [] as $levelid => $level) {
                $definition->rubric_criteria[$criterionid]['levels'][$levelid]['definition'] =
                    self::format((string) $level['definition'], $context);
            }
        }
    }
}
