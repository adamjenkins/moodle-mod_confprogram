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
 * Upgrade steps for mod_confprogram.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs upgrade steps between versions.
 *
 * No upgrade steps exist yet: this is the initial scaffold release. Add
 * xmldb_table/xmldb_field steps here, each guarded by "if ($oldversion < ...)"
 * and closed with a matching upgrade_mod_savepoint() call, as the schema
 * evolves. Example:
 *
 *   if ($oldversion < 2026080100) {
 *       $dbman = $DB->get_manager();
 *       // ... xmldb_table / xmldb_field changes ...
 *       upgrade_mod_savepoint(true, 2026080100, 'confprogram');
 *   }
 *
 * @param int $oldversion Plugin version being upgraded from
 * @return bool
 */
function xmldb_confprogram_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026070301) {
        $table = new xmldb_table('confprogram_review');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('confprogram', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('submissionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reviewerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('round', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('gradinginstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('grade', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('confprogram', XMLDB_KEY_FOREIGN, ['confprogram'], 'confprogram', ['id']);
        $table->add_key('reviewerid', XMLDB_KEY_FOREIGN, ['reviewerid'], 'user', ['id']);
        $table->add_key(
            'confprogram-submissionid-reviewerid-round',
            XMLDB_KEY_UNIQUE,
            ['confprogram', 'submissionid', 'reviewerid', 'round']
        );

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026070301, 'confprogram');
    }

    if ($oldversion < 2026070302) {
        $table = new xmldb_table('confprogram_favourite');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('confprogram', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('submissionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('confprogram', XMLDB_KEY_FOREIGN, ['confprogram'], 'confprogram', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('confprogram-submissionid-userid', XMLDB_KEY_UNIQUE, ['confprogram', 'submissionid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('confprogram_fieldsetting');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('confprogram', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('fieldname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('showinlist', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('showinmodal', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('confprogram', XMLDB_KEY_FOREIGN, ['confprogram'], 'confprogram', ['id']);
        $table->add_key('confprogram-fieldname', XMLDB_KEY_UNIQUE, ['confprogram', 'fieldname']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026070302, 'confprogram');
    }

    if ($oldversion < 2026070305) {
        // Optional-field-key encoding change (2026-07-05, fixing a cross-plugin
        // contract break -- see classes/local/field_settings.php's docblock and
        // changelog.md): confprogram_fieldsetting.fieldname now stores
        // OPTIONAL_FIELD_PREFIX . fieldid (e.g. 'opt5') for an optional field,
        // instead of the field's own (now organiser-free-text, non-unique,
        // renamable) name.
        //
        // A confprogram instance that had already saved displaysettings.php BEFORE
        // its linked mod_confsubmissions instance migrated to the dynamic-field
        // system has rows keyed by the old raw field names, which no longer match
        // anything get_available_fields() returns. Left in place, these are NOT
        // harmlessly ignored: get_settings_with_defaults() treats "this instance has
        // ANY fieldsetting row at all" as "no more fresh-instance defaults, treat
        // anything unmatched as hidden" -- so every optional field an organiser had
        // previously made visible on the Display-phase list/modal would silently
        // disappear (no error, just vanish) the instant the site migrates. Found by
        // a moodle-reviewer pass on the field-key fix itself, not by manual testing.
        // Deleting the stale rows restores the documented "no rows yet" defaults for
        // those fields instead of this silent regression.
        $prefix = \mod_confprogram\local\field_settings::OPTIONAL_FIELD_PREFIX;
        $fixedfields = \mod_confprogram\local\field_settings::FIXED_FIELDS;
        $optionalkeypattern = '/^' . preg_quote($prefix, '/') . '[0-9]+$/';

        $stalerowids = [];
        $rs = $DB->get_recordset('confprogram_fieldsetting');
        foreach ($rs as $record) {
            $isfixed = in_array($record->fieldname, $fixedfields, true);
            $isoptionalkey = (bool) preg_match($optionalkeypattern, $record->fieldname);
            if (!$isfixed && !$isoptionalkey) {
                $stalerowids[] = $record->id;
            }
        }
        $rs->close();

        if ($stalerowids) {
            [$insql, $params] = $DB->get_in_or_equal($stalerowids, SQL_PARAMS_QM);
            $DB->delete_records_select('confprogram_fieldsetting', "id $insql", $params);
        }

        upgrade_mod_savepoint(true, 2026070305, 'confprogram');
    }

    if ($oldversion < 2026070502) {
        // Status-sync bug fix (user feedback, 2026-07-05): mod_confsubmissions's
        // confsubmissions_submission.status was never updated on Accept/Reject --
        // see classes/api.php::record_decision()'s docblock and changelog.md for the
        // full fix. That fix is forward-looking only (it hooks the moment a decision
        // is recorded, and the moment phase switches Review -> Display): it does
        // nothing for a site that already has confprogram instances sitting in
        // Display phase with pre-existing Accept/Reject decisions from before this
        // fix existed -- those submissions' statuses would stay stuck at 'submitted'
        // forever without this one-time backfill. Safe to re-run (set_status() is
        // idempotent) and cheap (only instances already in Display phase are
        // touched).
        $displayconfprogramids = $DB->get_fieldset_select('confprogram', 'id', 'phase = :phase', ['phase' => 'display']);
        foreach ($displayconfprogramids as $confprogramid) {
            \mod_confprogram\api::sync_submission_statuses_to_confsubmissions((int) $confprogramid);
        }

        upgrade_mod_savepoint(true, 2026070502, 'confprogram');
    }

    if ($oldversion < 2026070503) {
        // Notifications (user request, 2026-07-05): every speaker on a submission is
        // notified when an accept/reject/waitlist decision is recorded, but -- per an
        // explicit decision -- deferred until the instance reaches Display phase, the
        // same embargo confsubmissions_submission.status syncing already respects (see
        // api::record_decision()/send_pending_decision_notifications()).
        $table = new xmldb_table('confprogram_decision');
        $field = new xmldb_field('notifiedtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'decidedby');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        if (!$dbman->table_exists('confprogram_notiftemplate')) {
            $table = new xmldb_table('confprogram_notiftemplate');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('confprogram', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('notiftype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('body', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('bodyformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('confprogram', XMLDB_KEY_FOREIGN, ['confprogram'], 'confprogram', ['id']);
            $table->add_index('confprogramtype', XMLDB_INDEX_UNIQUE, ['confprogram', 'notiftype']);
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026070503, 'confprogram');
    }

    if ($oldversion < 2026070601) {
        // Notifications master switch (user request, 2026-07-06): a single
        // instance-level on/off toggle that overrides the decision notification
        // template. Defaults to 1 (enabled) so existing instances keep sending
        // exactly as they do today until an organiser explicitly turns it off.
        $table = new xmldb_table('confprogram');
        $field = new xmldb_field(
            'notificationsenabled',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'defaultmaxreviews'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026070601, 'confprogram');
    }

    return true;
}
