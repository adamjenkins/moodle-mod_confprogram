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
import Modal from 'core/modal';
import Notification from 'core/notification';

/**
 * Wires up the Display-phase accepted-submissions list (view.php): the favourite-star
 * toggle (mod_confprogram_toggle_favourite), the "open submission detail" modal
 * (mod_confprogram_get_submission_detail), rendered via core/modal, and the day-selector
 * form's auto-submit-on-change (user request, 2026-07-07 -- replaces a separate "Show"
 * submit button; view.php still renders a <noscript> fallback button for JS-disabled
 * browsers).
 *
 * All three are delegated on document.body so this works for any number of rows (or, for
 * the day selector, regardless of whether it exists on the current page at all) without
 * per-element listener setup, and continues to work if the list is ever re-rendered
 * without a page reload in future.
 *
 * @module     mod_confprogram/programlist
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    FAVOURITE_TOGGLE: '.confprogram-favourite-toggle',
    OPEN_DETAIL: '.confprogram-open-detail',
    DAY_SELECT: '#confprogram-day',
};

/**
 * Toggles a submission's favourited state for the current user, updating the button
 * in place once the server confirms the new state.
 *
 * @param {HTMLElement} button The favourite-toggle button that was clicked
 * @return {Promise}
 */
const toggleFavourite = (button) => {
    const cmid = button.dataset.cmid;
    const submissionid = button.dataset.submissionid;
    const target = button.dataset.favourited !== '1';

    button.disabled = true;

    return Ajax.call([{
        methodname: 'mod_confprogram_toggle_favourite',
        args: {
            cmid: cmid,
            submissionid: submissionid,
            favourited: target,
        },
    }])[0].then((result) => {
        button.dataset.favourited = result.favourited ? '1' : '0';
        button.setAttribute('aria-pressed', result.favourited ? 'true' : 'false');
        button.classList.toggle('confprogram-favourited', result.favourited);
        return result;
    }).catch(Notification.exception).finally(() => {
        button.disabled = false;
    });
};

/**
 * Fetches a submission's detail and shows it in a modal.
 *
 * @param {HTMLElement} link The row title/link that was clicked
 * @return {Promise}
 */
const openDetail = (link) => {
    const cmid = link.dataset.cmid;
    const submissionid = link.dataset.submissionid;

    return Ajax.call([{
        methodname: 'mod_confprogram_get_submission_detail',
        args: {
            cmid: cmid,
            submissionid: submissionid,
        },
    }])[0].then((result) => Modal.create({
        title: result.title,
        body: result.html,
        show: true,
        removeOnClose: true,
        // Core's own modal.mustache hard-codes a Bootstrap text-truncate class on
        // the title, ellipsizing a long presentation title instead of wrapping it
        // (user report, 2026-07-09). Core cannot be patched, so this scopes an
        // override via the template's own overridable {{classes}} block -- see
        // the matching CSS rule in styles.css.
        templateContext: {classes: 'mod_confprogram-submission-modal'},
    })).catch(Notification.exception);
};

/**
 * Initialises the delegated click/change handlers. Safe to call once per page (see this
 * module's own docblock -- view.php calls it exactly once, unconditionally, per page load).
 */
export const init = () => {
    document.addEventListener('click', (event) => {
        const favouriteButton = event.target.closest(SELECTORS.FAVOURITE_TOGGLE);
        if (favouriteButton) {
            event.preventDefault();
            toggleFavourite(favouriteButton);
            return;
        }

        const openLink = event.target.closest(SELECTORS.OPEN_DETAIL);
        if (openLink) {
            event.preventDefault();
            openDetail(openLink);
        }
    });

    document.addEventListener('change', (event) => {
        if (event.target.matches(SELECTORS.DAY_SELECT)) {
            event.target.form.submit();
        }
    });
};
