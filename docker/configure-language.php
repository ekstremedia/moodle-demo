<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/componentlib.class.php');

global $DB;

if (!is_file($CFG->dataroot . '/lang/nb/langconfig.php')) {
    make_temp_directory('');
    make_upload_directory('lang');
    $installer = new lang_installer('nb');
    $results = $installer->run();
    foreach ($results as $code => $result) {
        if (!in_array($result, [lang_installer::RESULT_INSTALLED, lang_installer::RESULT_UPTODATE], true)) {
            throw new RuntimeException("Could not install language pack {$code}: {$result}");
        }
    }
}

// Refresh the installed-language list before Moodle validates HTML lang attributes.
get_string_manager()->reset_caches();
if (!get_string_manager()->translation_exists('nb')) {
    throw new RuntimeException('Norwegian Bokmål language pack is unavailable');
}

set_config('lang', 'nb');
set_config('autolang', 0);
set_config('autolangusercreation', 0);

// Moodle keeps each existing account's language when the site default changes.
// Migrate English profiles once, then respect later user preference changes.
if (!get_config('core', 'local_setup_nb_migrated')) {
    $DB->set_field_select('user', 'lang', 'nb', 'lang = :english OR lang = :empty', [
        'english' => 'en',
        'empty' => '',
    ]);
    set_config('local_setup_nb_migrated', 1);
}

echo "Norwegian Bokmål is the site language.\n";
