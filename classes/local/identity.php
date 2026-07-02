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

/**
 * Blind-review identity helper.
 *
 * This is a rendering-time concern only, by design: callers must avoid even
 * *fetching* submitter/reviewer identity data (speaker names/emails, reviewer
 * names) from mod_confsubmissions or user records when blinded, rather than
 * fetching it and merely not displaying it. That is defence in depth: it
 * means a stray var_dump(), debugging() call, or future template change
 * cannot leak identity that was never loaded into scope in the first place.
 * This class only answers the yes/no question; it is up to each caller (the
 * review-taking screen, the decision report, the submitter feedback screen)
 * to gate their own data fetching on the answer.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class identity {
    /**
     * Whether the given user may see real submitter/reviewer identities in a
     * confprogram instance, i.e. whether blind review does NOT apply to them.
     *
     * True when blind review is switched off for the instance, or when the
     * user holds mod/confprogram:viewidentity (which bypasses blinding
     * regardless of the setting).
     *
     * @param \context $context The confprogram course-module context
     * @param int|null $userid The user to check; defaults to the current user
     * @return bool
     */
    public static function can_view_identity(\context $context, ?int $userid = null): bool {
        global $DB, $USER;

        $userid = $userid ?? (int) $USER->id;

        if (has_capability('mod/confprogram:viewidentity', $context, $userid)) {
            return true;
        }

        $cm = get_coursemodule_from_id('confprogram', $context->instanceid, 0, false, MUST_EXIST);
        $blindreview = $DB->get_field('confprogram', 'blindreview', ['id' => $cm->instance], MUST_EXIST);

        return !$blindreview;
    }
}
