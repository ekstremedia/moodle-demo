<?php
// Dedicated users keep these tests independent of the demo account's learning progress.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/user/lib.php');
$action = $argv[1] ?? '';
$username = $argv[2] ?? '';
if (!preg_match('/^test-learningstep-[a-f0-9]+$/D', $username)) {
    throw new invalid_parameter_exception('Expected a learningstep test username');
}
if ($action === 'create') {
    $id = user_create_user((object) [
        'username' => $username, 'password' => 'Learning123!', 'auth' => 'manual',
        'firstname' => 'Læring', 'lastname' => 'Test', 'email' => $username . '@example.invalid',
        'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id, 'lang' => 'nb',
    ]);
    // Test the exercise without Moodle's first-visit tours covering its controls.
    foreach (\tool_usertours\helper::get_tours() as $tour) {
        // Moodle requires completion to be strictly later than the tour's last update.
        $completed = max(time(), (int) $tour->get_config('majorupdatetime', 0) + 1);
        set_user_preference(\tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER . $tour->get_id(), $completed, $id);
    }
    echo $id;
} else {
    $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
    if ($action === 'delete') {
        delete_user($user);
    } else if ($action === 'state') {
        echo get_user_preferences(\block_learningstep\steps::PREFERENCE, '', $user->id);
    } else if ($action === 'deny') {
        // A dedicated system role lets the HTTP test exercise the capability boundary.
        $shortname = $username . '-denied';
        $roleid = create_role('Learning test denied', $shortname, 'Temporary test role');
        assign_capability('block/learningstep:use', CAP_PROHIBIT, $roleid, context_system::instance()->id);
        role_assign($roleid, $user->id, context_system::instance()->id);
    }
    if ($action === 'delete') {
        $role = $DB->get_record('role', ['shortname' => $username . '-denied']);
        if ($role) {
            delete_role($role->id);
        }
    }
}
