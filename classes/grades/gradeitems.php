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

namespace mod_confprogram\grades;

use core_grades\local\gradeitem\itemnumber_mapping;
use core_grades\local\gradeitem\advancedgrading_mapping;

/**
 * Grade item mappings for mod_confprogram, required by core's Advanced Grading API now
 * that this plugin declares FEATURE_ADVANCED_GRADING (2026-07-06) -- without this class,
 * \grading_manager::get_available_areas()/available_areas() has no way to discover this
 * plugin's single gradable area ('review', used throughout review.php/feedback.php's own
 * get_grading_manager($context, 'mod_confprogram', 'review') calls), and callers like
 * course/modlib.php's add_moduleinfo()/update_moduleinfo() would otherwise foreach() over
 * a null result.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradeitems implements advancedgrading_mapping, itemnumber_mapping {
    /**
     * Returns the list of grade item mappings for this plugin.
     *
     * confprogram has no core gradebook item of its own (FEATURE_GRADE_HAS_GRADE is
     * false; reviews feed a rubric score shown only within the activity, never pushed to
     * the gradebook), so there is no itemnumber to map here -- only the advanced grading
     * area mapping below is actually needed.
     *
     * @return array
     */
    public static function get_itemname_mapping_for_component(): array {
        return [];
    }

    /**
     * Returns the list of advanced grading item (area) names for this component.
     *
     * @return array
     */
    public static function get_advancedgrading_itemnames(): array {
        return [
            'review',
        ];
    }
}
