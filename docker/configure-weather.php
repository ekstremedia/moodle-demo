<?php
declare(strict_types=1);

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

// A system block in user contexts also reaches dashboards created before installation.
$context = context_system::instance();
if (!$DB->record_exists('block_instances', [
    'blockname' => 'weather',
    'parentcontextid' => $context->id,
    'pagetypepattern' => 'my-index',
    'subpagepattern' => null,
])) {
    $now = time();
    $id = $DB->insert_record('block_instances', (object) [
        'blockname' => 'weather',
        'parentcontextid' => $context->id,
        'showinsubcontexts' => 1,
        'pagetypepattern' => 'my-index',
        'subpagepattern' => null,
        'defaultregion' => 'content',
        'defaultweight' => 10,
        'configdata' => '',
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
    context_block::instance($id);
    echo "Added weather block to dashboards.\n";
}
