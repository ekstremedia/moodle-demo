<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/user/lib.php');

$username = $argv[1] ?? '';
if (!preg_match('/^signup_test_[a-f0-9]{12}$/', $username)) {
    throw new RuntimeException('Invalid test username');
}
$user = $DB->get_record('user', ['username' => $username]);
if ($user && !delete_user($user)) {
    throw new RuntimeException('Could not delete test user');
}
