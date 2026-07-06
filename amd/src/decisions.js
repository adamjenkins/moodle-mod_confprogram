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
 * Decision report (decisions.php): a select-all checkbox for the row
 * checkboxes, and a confirm-before-submit gate on the bulk-apply button.
 * This plugin's first AMD module -- everything else in mod_confprogram is
 * plain server-rendered forms.
 *
 * @module     mod_confprogram/decisions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {getString, getStrings} from 'core/str';

/**
 * Toggles every row checkbox to match the header "select all" checkbox.
 *
 * @param {HTMLElement} root The decisions form element
 */
const wireSelectAll = (root) => {
    const selectAll = root.querySelector('.mod_confprogram-select-all');
    if (!selectAll) {
        return;
    }

    selectAll.addEventListener('change', () => {
        root.querySelectorAll('.mod_confprogram-row-checkbox').forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
    });
};

/**
 * Gates the bulk-apply button behind a confirm dialog naming the chosen
 * decision and how many submissions it will touch. A synthetic second click
 * (not form.submit(), so the button's own name=applybulkdecision/value=1
 * pair is still included in the POST body) re-enters this same listener and
 * lets the real submission through once confirmed.
 *
 * @param {HTMLElement} root The decisions form element
 * @param {Object} strings Preloaded {applybulkdecision, cancel} strings
 */
const wireBulkApply = (root, strings) => {
    const applyButton = root.querySelector('.mod_confprogram-apply-bulk-decision');
    if (!applyButton) {
        return;
    }

    applyButton.addEventListener('click', (event) => {
        if (applyButton.dataset.confirmed === '1') {
            applyButton.dataset.confirmed = '';
            return;
        }

        event.preventDefault();

        const decisionSelect = root.querySelector('[name=bulkdecision]');
        const checked = root.querySelectorAll('.mod_confprogram-row-checkbox:checked');

        if (!decisionSelect.value || checked.length === 0) {
            return;
        }

        const decisionLabel = decisionSelect.options[decisionSelect.selectedIndex].text;

        getString('confirmbulkdecision', 'mod_confprogram', {
            decision: decisionLabel,
            count: checked.length,
        }).then((message) => {
            Notification.confirm(
                strings.applybulkdecision,
                message,
                strings.applybulkdecision,
                strings.cancel,
                () => {
                    applyButton.dataset.confirmed = '1';
                    applyButton.click();
                }
            );
            return null;
        }).catch(Notification.exception);
    });
};

/**
 * Initialises the Decision report's select-all checkbox and bulk-apply
 * confirm dialog. Called from decisions.php.
 *
 * @return {Promise}
 */
export const init = async() => {
    const root = document.getElementById('mod_confprogram-decisions-form');
    if (!root) {
        return;
    }

    const [applybulkdecision, cancel] = await getStrings([
        {key: 'applybulkdecision', component: 'mod_confprogram'},
        {key: 'cancel', component: 'core'},
    ]);

    wireSelectAll(root);
    wireBulkApply(root, {applybulkdecision, cancel});
};
