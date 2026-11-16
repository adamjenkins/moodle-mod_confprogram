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

namespace mod_confprogram\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;
use mod_confprogram\local\rubric_text;

/**
 * AJAX-only external function for amd/src/rubric_multilang.js: returns rubric
 * criterion/level texts as the reader would see them (multilang filtered to the
 * current language, plain text), for core's rubric editor and preview pages, which
 * print the raw multilang tags. Texts are not read from or written to the database:
 * the editor sends what is typed, including unsaved changes.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_rubric_text extends external_api {
    /** @var int Most texts one call formats (a rubric rarely has more than a few dozen cells). */
    private const MAX_TEXTS = 500;

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'  => new external_value(PARAM_INT, 'The confprogram course-module id'),
            'texts' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'A criterion description or level definition as typed')
            ),
        ]);
    }

    /**
     * Formats each text for display in the current language.
     *
     * @param int $cmid The confprogram course-module id
     * @param string[] $texts Criterion descriptions / level definitions, raw
     * @return string[] The plain-text display form of each, in the same order
     */
    public static function execute(int $cmid, array $texts): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'texts' => $texts]);

        $cm = get_coursemodule_from_id('confprogram', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        if (!has_any_capability(['moodle/grade:managegradingforms', 'mod/confprogram:review'], $context)) {
            require_capability('moodle/grade:managegradingforms', $context);
        }
        if (count($params['texts']) > self::MAX_TEXTS) {
            throw new \invalid_parameter_exception('Too many texts');
        }

        return array_map(
            static fn(string $text): string => rubric_text::format($text, $context),
            $params['texts']
        );
    }

    /**
     * Returns description of method result value.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_value(PARAM_RAW, 'The text as the reader sees it, plain text')
        );
    }
}
