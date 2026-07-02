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

/**
 * External functions and service definitions for mod_confprogram.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_confprogram_get_submission_detail' => [
        'classname'   => 'mod_confprogram\external\get_submission_detail',
        'description' => 'Returns the modal title and pre-rendered detail body for an accepted submission, '
            . 'for the Display-phase accepted-submissions list.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'mod/confprogram:viewprogram',
    ],
    'mod_confprogram_toggle_favourite' => [
        'classname'   => 'mod_confprogram\external\toggle_favourite',
        'description' => 'Sets or unsets the current user\'s favourite of an accepted submission, '
            . 'for the Display-phase accepted-submissions list.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/confprogram:favourite',
    ],
];
