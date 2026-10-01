<?php
// This file is part of Moodle - https://moodle.org/.
// Moodle is free software: you can redistribute it and/or modify it under the
// terms of the GNU General Public License as published by the Free Software
// Foundation, either version 3 of the License, or (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'block/weather:myaddinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['user' => CAP_ALLOW],
        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ],
    'block/weather:addinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => ['manager' => CAP_ALLOW],
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ],
];
