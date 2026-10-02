<?php
// This file is part of Moodle - https://moodle.org/.
// Licensed under the GNU GPL v3 or later: https://www.gnu.org/licenses/gpl-3.0.html

namespace block_learningstep\privacy;

defined('MOODLE_INTERNAL') || die();

/** Declare and export the site-wide preference; Moodle core handles its deletion. */
class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\user_preference_provider {
    /** Describe the only stored personal data. */
    public static function get_metadata(\core_privacy\local\metadata\collection $collection):
            \core_privacy\local\metadata\collection {
        $collection->add_user_preference(\block_learningstep\steps::PREFERENCE, 'privacy:progress');
        return $collection;
    }

    /** Include progress and the current answer in a user's privacy export. */
    public static function export_user_preferences(int $userid): void {
        $value = get_user_preferences(\block_learningstep\steps::PREFERENCE, null, $userid);
        if ($value !== null) {
            \core_privacy\local\request\writer::export_user_preference(
                'block_learningstep', \block_learningstep\steps::PREFERENCE, $value,
                get_string('privacy:progress', 'block_learningstep'));
        }
    }
}
