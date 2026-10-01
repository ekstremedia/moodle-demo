<?php
// This file is part of Moodle - https://moodle.org/.
// Licensed under the GNU GPL v3 or later: https://www.gnu.org/licenses/gpl-3.0.html

defined('MOODLE_INTERNAL') || die();

/** Reset practice progress when replacing the original exercise set. */
function xmldb_block_learningstep_upgrade(int $oldversion): bool {
    global $DB;
    if ($oldversion < 2026100201) {
        $name = 'block_learningstep_progress_v1';
        $preferences = $DB->get_records('user_preferences', ['name' => $name], '', 'id, userid');
        foreach ($preferences as $preference) {
            // Also invalidate cached preferences in existing user sessions.
            unset_user_preference($name, $preference->userid);
        }
        upgrade_block_savepoint(true, 2026100201, 'learningstep');
    }
    return true;
}
