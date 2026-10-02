<?php
// This file is part of Moodle - https://moodle.org/.
// Licensed under the GNU GPL v3 or later: https://www.gnu.org/licenses/gpl-3.0.html

require_once(__DIR__ . '/../../config.php');

require_login();
if (isguestuser()) {
    throw new moodle_exception('noguest');
}
require_capability('block/learningstep:use', context_system::instance());
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
require_sesskey();
$action = required_param('action', PARAM_ALPHA);
$step = required_param('step', PARAM_INT);
$answer = optional_param('answer', -1, PARAM_INT);

// Serialize writes for this user, including submissions from two browser tabs.
$factory = \core\lock\lock_config::get_lock_factory('block_learningstep');
$lock = $factory->get_lock('user_' . $USER->id, 5);
if (!$lock) {
    throw new moodle_exception('locktimeout');
}
try {
    // Bypass the session preference cache when handling concurrent submissions.
    $raw = $DB->get_field('user_preferences', 'value', [
        'userid' => $USER->id, 'name' => \block_learningstep\steps::PREFERENCE,
    ]);
    $state = \block_learningstep\steps::decode((string) $raw);
    $reset = $action === 'reset' && $step === $state['step'];
    $state = \block_learningstep\steps::apply($state, $action, $step, $answer < 0 ? null : $answer);
    if ($reset) {
        unset_user_preference(\block_learningstep\steps::PREFERENCE);
    } else {
        set_user_preference(\block_learningstep\steps::PREFERENCE, json_encode($state));
    }
} catch (\InvalidArgumentException $e) {
    throw new moodle_exception('invalidaction', 'block_learningstep');
} finally {
    $lock->release();
}
redirect(new moodle_url('/my/', [], 'learningstep'));
