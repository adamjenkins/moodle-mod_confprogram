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

import Ajax from 'core/ajax';

/**
 * Thin wrapper around this plugin's send_pending_notifications AJAX external
 * function, used by amd/src/notifications_button.js -- mirrors
 * mod_confscheduler/repository.js's equivalent wrapper.
 *
 * @module     mod_confprogram/repository
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Sends every pending decision notification for a confprogram instance.
 *
 * @param {Number} cmid The confprogram course-module id
 * @return {Promise} Resolves to {sent: Number}
 */
export const sendPendingNotifications = (cmid) => Ajax.call([{
    methodname: 'mod_confprogram_send_pending_notifications',
    args: {cmid},
}])[0];
