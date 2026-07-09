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

import Notification from 'core/notification';
import {getString} from 'core/str';
import * as Repository from 'mod_confprogram/repository';

/**
 * The "Send pending notifications" button on view.php's organiser controls
 * (2026-07-09, replacing the automatic Review -> Display phase-toggle send) --
 * mirrors mod_confscheduler's own send button: a confirm dialog naming the
 * pending count, an AJAX send, an alert summarising how many were sent, then a
 * plain page reload so the button's own count/label reflect the send. Unlike
 * mod_confscheduler's grid, this page is not a stateful SPA-like view, so a
 * reload is the simplest way to bring every affected count back in sync (the
 * decision report, the pending-notifications page link, and this button all
 * read their counts fresh on load).
 *
 * @module     mod_confprogram/notifications_button
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Click handler: confirms, sends, summarises, reloads.
 *
 * @param {HTMLElement} button The clicked button, carrying data-cmid/data-pending
 * @return {Promise}
 */
const onClick = async(button) => {
    const cmid = Number(button.dataset.cmid);
    const pending = Number(button.dataset.pending);

    const [heading, cancel] = await Promise.all([
        getString('sendpendingnotifications', 'mod_confprogram', pending),
        getString('cancel', 'core'),
    ]);

    if (pending === 0) {
        const none = await getString('sendnotificationsnonepending', 'mod_confprogram');
        Notification.alert(heading, none);
        return null;
    }

    const confirmmessage = await getString('confirmsendpendingnotifications', 'mod_confprogram', pending);

    Notification.confirm(heading, confirmmessage, heading, cancel, () => {
        Repository.sendPendingNotifications(cmid).then(async(result) => {
            const summary = await getString('sendnotificationssummary', 'mod_confprogram', result.sent);
            await Notification.alert(heading, summary);
            window.location.reload();
            return null;
        }).catch(Notification.exception);
    });

    return null;
};

/**
 * Wires the delegated click listener. Safe to call once per page load; the
 * button itself is only ever rendered once, inside view.php's capability-gated
 * organiser controls block.
 */
export const init = () => {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('.mod_confprogram-send-notifications');
        if (button && !button.disabled) {
            onClick(button).catch(Notification.exception);
        }
    });
};
