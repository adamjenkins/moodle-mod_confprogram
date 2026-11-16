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
 * Shows multilang rubric text as reviewers see it on core's rubric editor and preview
 * pages, for a confprogram review rubric.
 *
 * Core's rubric renderer prints criterion and level text with s(), so bilingual text
 * shows its raw <span lang> / {mlang} tags there. This module asks the server to
 * filter each such text (mod_confprogram_format_rubric_text, the same filtering the
 * review form uses), shows the result instead, and adds a "multilang" button after it
 * that opens the saved source with one row per language. In the editor only the
 * read-only display of each cell changes; the textarea keeps the raw text, so editing
 * and saving work as before. A MutationObserver re-applies this whenever the editor
 * redraws a cell (after an edit, or a new criterion or level).
 *
 * @module     mod_confprogram/rubric_multilang
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';
import {getStrings} from 'core/str';

/** @type {RegExp} Text carrying multilang markup, as rubric_text::format() detects it. */
const MARKUP = /<\s*(span|lang)\b|\{mlang/i;

/** @type {string} Marks a cell already shown in its display form; holds the raw text. */
const RAW = 'confprogramMultilangRaw';

/** @type {Map<string, string>} Raw text => display text, so each text is filtered once. */
const cache = new Map();

let cmid = 0;
let strings = null;
let busy = false;
let again = false;

/**
 * The elements that hold criterion/level text as read-only text: the cells on preview
 * pages, and the editor's "plainvalue" display next to each (hidden) textarea.
 *
 * @return {HTMLElement[]}
 */
const textElements = () => {
    const found = [];
    document.querySelectorAll('.gradingform_rubric td.description, .gradingform_rubric .level .definition')
        .forEach((el) => {
            if (!el.querySelector('textarea')) {
                found.push(el);
            }
        });
    document.querySelectorAll('.gradingform_rubric .plainvalue .textvalue').forEach((el) => {
        if (!el.closest('.score')) {
            found.push(el);
        }
    });
    return found;
};

/**
 * Splits multilang source into its languages, for the source dialogue.
 *
 * @param {string} raw
 * @return {Array<{lang: string, text: string}>}
 */
const languages = (raw) => {
    const out = [];
    const spans = /<span\s+[^>]*lang=["']?([\w-]+)["']?[^>]*>([\s\S]*?)<\/span>/gi;
    const mlang = /\{mlang\s+([\w-]+)\}([\s\S]*?)\{mlang\}/gi;
    let match;
    while ((match = spans.exec(raw)) !== null) {
        out.push({lang: match[1], text: match[2]});
    }
    while ((match = mlang.exec(raw)) !== null) {
        out.push({lang: match[1], text: match[2]});
    }
    return out;
};

/**
 * Builds the source dialogue body; every value goes in through textContent.
 *
 * @param {string} raw
 * @return {string} HTML
 */
const sourceBody = (raw) => {
    const wrap = document.createElement('div');
    const langs = languages(raw);
    if (langs.length) {
        const table = document.createElement('table');
        table.className = 'table table-sm';
        const head = table.createTHead().insertRow();
        [strings.language, strings.text].forEach((label) => {
            const th = document.createElement('th');
            th.scope = 'col';
            th.textContent = label;
            head.appendChild(th);
        });
        const body = table.createTBody();
        langs.forEach(({lang, text}) => {
            const row = body.insertRow();
            row.insertCell().textContent = lang;
            row.insertCell().textContent = text;
        });
        wrap.appendChild(table);
    }
    const label = document.createElement('p');
    label.className = 'fw-bold mb-1';
    label.textContent = strings.saved;
    const pre = document.createElement('pre');
    pre.className = 'border rounded p-2 mb-0';
    pre.style.whiteSpace = 'pre-wrap';
    pre.textContent = raw;
    wrap.append(label, pre);
    return wrap.innerHTML;
};

/**
 * Shows one element's text in its display form, with the multilang button after it.
 *
 * @param {HTMLElement} el
 * @param {string} raw
 * @param {string} shown
 */
const apply = (el, raw, shown) => {
    el.dataset[RAW] = raw;
    el.textContent = shown;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline-secondary btn-sm py-0 px-1 ms-1 mod_confprogram-multilang-button';
    button.textContent = strings.multilang;
    button.title = raw;
    button.setAttribute('aria-label', strings.showsource);
    // The editor turns a cell into its textarea on click; the button must not do that.
    ['mousedown', 'click'].forEach((type) => button.addEventListener(type, (e) => e.stopPropagation()));
    button.addEventListener('click', (e) => {
        e.preventDefault();
        Modal.create({title: strings.source, body: sourceBody(raw), show: true, removeOnClose: true})
            .catch(Notification.exception);
    });
    el.appendChild(button);

    // Core's screen-reader labels on the cells carry the raw text too.
    [el, el.closest('td')].forEach((labelled) => {
        const label = labelled?.getAttribute('aria-label');
        if (label && label.includes(raw)) {
            labelled.setAttribute('aria-label', label.split(raw).join(shown));
        }
    });
};

/**
 * Finds every multilang text not yet shown in its display form, filters the new ones on
 * the server in one call, and applies them.
 */
const refresh = async() => {
    if (busy) {
        again = true;
        return;
    }
    busy = true;
    try {
        const pending = textElements()
            .map((el) => ({el, raw: el.textContent}))
            .filter(({el, raw}) => el.dataset[RAW] !== raw && MARKUP.test(raw));
        const unknown = [...new Set(pending.map(({raw}) => raw).filter((raw) => !cache.has(raw)))];
        if (unknown.length) {
            const shown = await Ajax.call([{
                methodname: 'mod_confprogram_format_rubric_text',
                args: {cmid, texts: unknown},
            }])[0];
            unknown.forEach((raw, i) => cache.set(raw, shown[i]));
        }
        pending.forEach(({el, raw}) => {
            // Skip a cell the editor redrew while the request was out; the observer comes back for it.
            if (el.isConnected && el.textContent === raw) {
                apply(el, raw, cache.get(raw));
            }
        });
    } catch (error) {
        Notification.exception(error);
    } finally {
        busy = false;
        if (again) {
            again = false;
            refresh();
        }
    }
};

/**
 * Starts the module.
 *
 * @param {Number} confprogramcmid The confprogram course-module id
 */
export const init = async(confprogramcmid) => {
    cmid = confprogramcmid;
    const [multilang, source, showsource, language, text, saved] = await getStrings([
        {key: 'multilangbutton', component: 'mod_confprogram'},
        {key: 'multilangsource', component: 'mod_confprogram'},
        {key: 'multilangshowsource', component: 'mod_confprogram'},
        {key: 'multilanglanguage', component: 'mod_confprogram'},
        {key: 'multilangtext', component: 'mod_confprogram'},
        {key: 'multilangsaved', component: 'mod_confprogram'},
    ]);
    strings = {multilang, source, showsource, language, text, saved};

    refresh();
    let timer = null;
    new MutationObserver(() => {
        clearTimeout(timer);
        timer = setTimeout(refresh, 150);
    }).observe(document.body, {childList: true, subtree: true, characterData: true});
};
