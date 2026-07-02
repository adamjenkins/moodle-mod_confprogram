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

    return true;
}
