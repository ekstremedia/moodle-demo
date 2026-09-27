<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/user/lib.php');

global $DB;
$user = $DB->get_record('user', ['username' => 'demo', 'mnethostid' => $CFG->mnet_localhost_id]);
if (!$user) {
    $user = (object) [
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => 'demo',
        'password' => 'Demo123!',
        'firstname' => 'Demo',
        'lastname' => 'User',
        'email' => 'demo@example.invalid',
        'lang' => 'nb',
    ];
    $user->id = user_create_user($user);
}
echo "Demo user ready: {$user->id}\n";
