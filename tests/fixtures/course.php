<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

global $DB;

$action = $argv[1] ?? '';
if ($action === 'create') {
    $shortname = 'SMOKE' . ($argv[2] ?? '');
    $course = create_course((object) [
        'fullname' => 'Automated course smoke test',
        'shortname' => $shortname,
        'category' => 1,
        'format' => 'topics',
        'visible' => 1,
    ]);
    $user = $DB->get_record('user', ['username' => 'demo', 'mnethostid' => $CFG->mnet_localhost_id], '*', MUST_EXIST);
    $manual = enrol_get_plugin('manual');
    $instance = null;
    foreach (enrol_get_instances($course->id, true) as $candidate) {
        if ($candidate->enrol === 'manual') {
            $instance = $candidate;
            break;
        }
    }
    if (!$instance) {
        $id = $manual->add_instance($course);
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }
    $teacher = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
    $manual->enrol_user($instance, $user->id, $teacher->id);
    echo $course->id . "\n";
} else if ($action === 'delete') {
    $courseid = (int) ($argv[2] ?? 0);
    if ($courseid > 1 && $DB->record_exists('course', ['id' => $courseid])) {
        delete_course($courseid, false);
    }
} else {
    fwrite(STDERR, "Expected create or delete\n");
    exit(1);
}
