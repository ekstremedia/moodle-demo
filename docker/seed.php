<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

global $DB;
$course = $DB->get_record('course', ['shortname' => 'DEMO101']);
if (!$course) {
    $course = create_course((object) [
        'fullname' => 'Moodle interview demo',
        'shortname' => 'DEMO101',
        'category' => 1,
        'summary' => 'A small course for exploring Moodle teaching tools.',
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'visible' => 1,
    ]);
}
$user = $DB->get_record('user', ['username' => 'demo', 'mnethostid' => $CFG->mnet_localhost_id]);
if (!$user) {
    $user = (object) [
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => 'demo',
        'password' => 'Demo123!',
        'firstname' => 'Demo',
        'lastname' => 'Teacher',
        'email' => 'demo@example.invalid',
    ];
    $user->id = user_create_user($user);
}
$manual = enrol_get_plugin('manual');
$instances = enrol_get_instances($course->id, true);
$instance = null;
foreach ($instances as $candidate) {
    if ($candidate->enrol === 'manual') {
        $instance = $candidate;
        break;
    }
}
if (!$instance) {
    $instanceid = $manual->add_instance($course);
    $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
}
$teacher = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id])) {
    $manual->enrol_user($instance, $user->id, $teacher->id);
}
echo "Demo ready: course {$course->id}, teacher {$user->id}\n";
