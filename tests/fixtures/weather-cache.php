<?php
declare(strict_types=1);

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

$cache = \cache::make('block_weather', 'forecast');
if (($argv[1] ?? '') === 'clear') {
    $cache->delete('kristiansand-v3');
    exit;
}

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$hour = $now->setTime((int) $now->format('G'), 0);
$entries = [];
for ($i = 0; $i < 25; $i++) {
    $entries[] = [
        'time' => $hour->modify("+{$i} hours")->format('Y-m-d\TH:i:s\Z'),
        'data' => [
            'instant' => ['details' => [
                'air_temperature' => 16,
                'wind_speed' => 8,
                'wind_speed_of_gust' => 13,
                'wind_from_direction' => 90,
                'relative_humidity' => 60,
            ]],
            'next_1_hours' => [
                'summary' => ['symbol_code' => $i === 0 ? 'cloudy' : 'rain'],
                'details' => ['precipitation_amount' => $i === 0 ? 0 : 1],
            ],
        ],
    ];
}
$cache->set('kristiansand-v3', [
    'body' => json_encode(['properties' => ['timeseries' => $entries]], JSON_THROW_ON_ERROR),
    'expires' => time() + 300,
    'fetched' => time(),
    'lastmodified' => null,
]);
