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
use context_module;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \mod_confprogram\local\identity: the blind-review identity
 * helper used throughout the Review phase screens.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(identity::class)]
final class identity_test extends advanced_testcase {
    /**
     * Creates a course, a confsubmissions instance, and a confprogram instance
     * pointed at it with the given blindreview setting.
     *
     * @param bool $blindreview
     * @return array{0: \stdClass, 1: context_module} The course and the confprogram context
     */
    private function create_confprogram(bool $blindreview): array {
        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
            'blindreview'         => $blindreview ? 1 : 0,
        ]);
        $cm = get_coursemodule_from_instance('confprogram', $confprogram->id);

        return [$course, context_module::instance($cm->id)];
    }

    /**
     * When blind review is off, identity is visible to everyone, regardless
     * of capability.
     */
    public function test_identity_visible_when_blind_review_off(): void {
        $this->resetAfterTest();

        [, $context] = $this->create_confprogram(false);
        $user = $this->getDataGenerator()->create_user();

        $this->assertTrue(identity::can_view_identity($context, (int) $user->id));
    }

    /**
     * When blind review is on, a user with no special capability cannot see
     * identity.
     */
    public function test_identity_hidden_when_blind_review_on_without_capability(): void {
        $this->resetAfterTest();

        [, $context] = $this->create_confprogram(true);
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse(identity::can_view_identity($context, (int) $user->id));
    }

    /**
     * When blind review is on, a user holding mod/confprogram:viewidentity
     * (granted to the editingteacher archetype by default) can still see
     * identity: the capability bypasses the blind setting.
     */
    public function test_identity_visible_with_viewidentity_capability_even_when_blind(): void {
        $this->resetAfterTest();

        [$course, $context] = $this->create_confprogram(true);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->assertTrue(identity::can_view_identity($context, (int) $teacher->id));
    }

    /**
     * With no explicit userid, can_view_identity() falls back to the current
     * (globally set) user.
     */
    public function test_defaults_to_current_user(): void {
        $this->resetAfterTest();

        [, $context] = $this->create_confprogram(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse(identity::can_view_identity($context));
    }
}
