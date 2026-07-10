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

namespace mod_confprogram;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Output-level regression coverage for review.php's blind-review gating.
 *
 * tests/local/identity_test.php only covers the pure identity::can_view_identity()
 * helper in isolation -- it would not catch a regression where review.php's own
 * `if ($canviewidentity)` branches were flipped, or where a new block (like the
 * custom-field loop fixed 2026-07-10) was added without the same gate. This test
 * renders the actual page output and asserts on it directly.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class review_page_test extends advanced_testcase {
    /**
     * Sets up a course with confsubmissions + confprogram (blind review on),
     * one accepted-for-review submission with a real-user speaker and a custom
     * field value, and a reviewer assigned to it. Returns everything a test
     * needs to render review.php as that reviewer.
     *
     * @return array{cm: \stdClass, submissionid: int, reviewer: \stdClass, speaker: \stdClass, fieldvalue: string}
     */
    private function setup_scenario(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
            'blindreview'         => 1,
            'phase'               => 'review',
        ]);
        $cm = get_coursemodule_from_instance('confprogram', $confprogram->id);

        $speaker = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);

        $submissionid = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => $speaker->id,
            'title'           => 'A Very Testable Talk',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        \mod_confsubmissions\api::sync_speakers((int) $submissionid, [
            ['userid' => $speaker->id],
        ]);

        $fieldid = \mod_confsubmissions\api::add_field(
            (int) $confsubmissions->id,
            'Author bio',
            'text',
            null,
            false
        );
        \mod_confsubmissions\api::sync_optional_fields((int) $submissionid, [
            $fieldid => 'Secretly identifying biography text',
        ]);

        $reviewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($reviewer->id, $course->id, 'teacher');
        \mod_confprogram\api::assign_reviewer((int) $confprogram->id, (int) $submissionid, (int) $reviewer->id);

        return [
            'course'       => $course,
            'cm'           => $cm,
            'submissionid' => (int) $submissionid,
            'reviewer'     => $reviewer,
            'speaker'      => $speaker,
            'fieldvalue'   => 'Secretly identifying biography text',
        ];
    }

    /**
     * Renders review.php for the given cm/submission as the current user and
     * returns the captured HTML output.
     *
     * @param \stdClass $cm
     * @param int $submissionid
     * @return string
     */
    private function render_review_page(\stdClass $cm, int $submissionid): string {
        // Review.php's own require_once('../../config.php') is a no-op here (already
        // loaded by the PHPUnit bootstrap), so the "global $x;" declarations inside
        // config.php that normally wire these up never re-run in THIS method's local
        // scope -- declare them directly so review.php's bare $DB/$USER/etc. resolve.
        global $CFG, $DB, $USER, $COURSE, $SITE, $PAGE, $OUTPUT;

        $_GET['id'] = $cm->id;
        $_GET['submissionid'] = $submissionid;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Fresh $PAGE/$OUTPUT per render, same as a real new HTTP request would get --
        // review.php calls $PAGE->set_url()/set_context() etc. on the shared global.
        $PAGE = new \moodle_page();
        $OUTPUT = new \core_renderer($PAGE, RENDERER_TARGET_GENERAL);

        // Review.php requires '../../config.php' relative to its own directory, not
        // via __DIR__ -- chdir() there first so that resolves the same way a real
        // request (cwd = the script's own directory) would.
        $previouscwd = getcwd();
        chdir($CFG->dirroot . '/mod/confprogram');
        ob_start();
        require($CFG->dirroot . '/mod/confprogram/review.php');
        $html = ob_get_clean();
        chdir($previouscwd);

        unset($_GET['id'], $_GET['submissionid']);

        return $html;
    }

    /**
     * When blind review is on and the reviewer has no viewidentity capability,
     * neither the speaker's name NOR the custom field's name/value appear in
     * the rendered page.
     */
    public function test_blind_review_hides_speaker_and_custom_fields(): void {
        $this->resetAfterTest();

        $scenario = $this->setup_scenario();
        $this->setUser($scenario['reviewer']);

        $html = $this->render_review_page($scenario['cm'], $scenario['submissionid']);

        $this->assertStringNotContainsString(fullname($scenario['speaker']), $html);
        $this->assertStringNotContainsString('Author bio', $html);
        $this->assertStringNotContainsString($scenario['fieldvalue'], $html);
        $this->assertStringContainsString(get_string('identityhidden', 'mod_confprogram'), $html);
        $this->assertStringContainsString(get_string('fieldshidden', 'mod_confprogram'), $html);
    }

    /**
     * A manager (who holds mod/confprogram:viewidentity, the blind-review
     * bypass capability) sees both the speaker's name and the custom field.
     */
    public function test_manager_bypass_shows_speaker_and_custom_fields(): void {
        $this->resetAfterTest();

        $scenario = $this->setup_scenario();
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $scenario['course']->id, 'manager');
        \mod_confprogram\api::assign_reviewer(
            (int) $scenario['cm']->instance,
            $scenario['submissionid'],
            (int) $manager->id
        );
        $this->setUser($manager);

        $html = $this->render_review_page($scenario['cm'], $scenario['submissionid']);

        $this->assertStringContainsString(fullname($scenario['speaker']), $html);
        $this->assertStringContainsString('Author bio', $html);
        $this->assertStringContainsString($scenario['fieldvalue'], $html);
    }
}
