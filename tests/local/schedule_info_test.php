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
 * Tests for \mod_confprogram\local\schedule_info: the soft, optional integration
 * point with mod_confscheduler.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(schedule_info::class)]
final class schedule_info_test extends advanced_testcase {
    /**
     * get_for_submission() returns null, without any fatal error or warning, when
     * mod_confscheduler is not installed, proving the graceful-degradation contract
     * this class exists for. This originally exercised the real "absent" case in this
     * shared dev environment; mod_confscheduler was later actually built and installed
     * here, so the true-absence path can no longer be exercised live -- skip rather
     * than assert something now false, since forcing "absence" would require faking
     * core_component's real filesystem-backed component lookup.
     */
    public function test_get_for_submission_returns_null_when_confscheduler_absent(): void {
        $this->resetAfterTest();

        if (\core_component::get_component_directory('mod_confscheduler') !== null) {
            $this->markTestSkipped(
                'mod_confscheduler is installed in this environment; the true-absence ' .
                'path this test exercises cannot be reproduced here. See this method\'s ' .
                'docblock.'
            );
        }

        $this->assertFalse(class_exists('\mod_confscheduler\api'));

        $this->assertNull(schedule_info::get_for_submission(1));
    }

    /**
     * format_for_display() falls back to the "not yet scheduled" string for both a
     * genuinely null schedule and a malformed one missing 'starttime'.
     */
    public function test_format_for_display_handles_missing_schedule(): void {
        $this->resetAfterTest();

        $notscheduled = get_string('notyetscheduled', 'mod_confprogram');

        $this->assertSame($notscheduled, schedule_info::format_for_display(null));
        $this->assertSame($notscheduled, schedule_info::format_for_display(['room' => 'Room A']));
    }

    /**
     * format_for_display() renders a complete schedule array without erroring, and
     * does not fall back to the "not yet scheduled" text.
     */
    public function test_format_for_display_renders_complete_schedule(): void {
        $this->resetAfterTest();

        $text = schedule_info::format_for_display([
            'starttime' => strtotime('2026-09-01 10:00:00'),
            'endtime'   => strtotime('2026-09-01 11:00:00'),
            'room'      => 'Room A',
        ]);

        $this->assertNotSame(get_string('notyetscheduled', 'mod_confprogram'), $text);
        $this->assertStringContainsString('Room A', $text);
    }
}
