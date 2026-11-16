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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \mod_confprogram\local\rubric_text: a bilingual (multilang) rubric shows the
 * current language as plain text instead of the raw <span> tags that core's rubric
 * renderer would otherwise print, since it escapes criterion and level text with s().
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(rubric_text::class)]
final class rubric_text_test extends advanced_testcase {
    /** @var string An English/Japanese criterion as the multilang filter expects it. */
    private const BILINGUAL = '<span lang="en" class="multilang">Originality</span>'
        . '<span lang="ja" class="multilang">独創性</span>';

    /**
     * With the multilang filter on, format() keeps only the current language as plain
     * text; plain text, including a bare "<", is returned unchanged.
     */
    public function test_format(): void {
        $this->resetAfterTest();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        $context = \context_system::instance();

        $this->assertSame('Originality', rubric_text::format(self::BILINGUAL, $context));
        $this->assertSame('Score < 3 & clear', rubric_text::format('Score < 3 & clear', $context));
    }

    /**
     * localise_definition() rewrites the controller's loaded criteria and levels, which is
     * the copy core's renderer prints, without changing the stored rubric.
     */
    public function test_localise_definition(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        filter_set_global_state('multilang', TEXTFILTER_ON);

        $course = $this->getDataGenerator()->create_course(['summary' => '']);
        $context = \context_course::instance($course->id);
        $novel = '<span lang="en" class="multilang">Novel</span><span lang="ja" class="multilang">新規</span>';
        /** @var \gradingform_rubric_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $controller = $generator->create_instance($context, 'mod_confprogram', 'review', 'Bilingual', '', [
            self::BILINGUAL => [
                'Weak' => 0,
                $novel => 2,
            ],
        ]);

        rubric_text::localise_definition($controller, $context);

        $criterion = reset($controller->get_definition()->rubric_criteria);
        $this->assertSame('Originality', $criterion['description']);
        $this->assertEqualsCanonicalizing(['Weak', 'Novel'], array_column($criterion['levels'], 'definition'));
        $this->assertSame(self::BILINGUAL, $DB->get_field('gradingform_rubric_criteria', 'description', []));

        // What a reviewer sees: core's renderer no longer prints escaped tags.
        $definition = $controller->get_definition();
        $html = $PAGE->get_renderer('gradingform_rubric')->display_rubric(
            $definition->rubric_criteria,
            $controller->get_options(),
            \gradingform_rubric_controller::DISPLAY_EVAL,
            'advancedgrading'
        );
        $this->assertStringContainsString('Originality', $html);
        $this->assertStringNotContainsString('&lt;span', $html);
        $this->assertStringNotContainsString('独創性', $html);
    }
}
