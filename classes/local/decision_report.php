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

use mod_confprogram\api;

/**
 * Data layer behind decisions.php's table and assign.php's "resubmitted"
 * filter mode. Kept out of both page scripts so it's independently
 * unit-testable, matching this plugin's existing display_list.php/
 * grid_data.php convention.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decision_report {
    /**
     * Decorates each submission with its current round, latest decision, and
     * this round's completed reviews -- everything decisions.php's table needs
     * per row, computed once so callers don't repeat the round/decision/review
     * lookups themselves.
     *
     * @param int $confprogramid The confprogram instance id
     * @param array $submissions Id-keyed raw submission objects
     * @return array Id-keyed \stdClass rows: ->submission, ->round, ->latestdecision, ->reviews
     */
    public static function decorate_submissions(int $confprogramid, array $submissions): array {
        $result = [];
        foreach ($submissions as $id => $submission) {
            $round = rounds::get_current_round($confprogramid, (int) $id);
            $result[$id] = (object) [
                'submission'     => $submission,
                'round'          => $round,
                'latestdecision' => rounds::get_latest_decision($confprogramid, (int) $id),
                'reviews'        => api::get_reviews_for_round($confprogramid, (int) $id, $round),
            ];
        }
        return $result;
    }
}
