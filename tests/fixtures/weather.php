<?php
declare(strict_types=1);

use block_weather\forecast;

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

$entries = [];
for ($hour = 13; $hour <= 23; $hour++) {
    $afternoon = $hour < 18;
    $temperatures = [13 => 16, 14 => 17, 15 => 18, 16 => 15, 17 => 14,
        18 => 14, 19 => 13, 20 => 12, 21 => 11, 22 => 10, 23 => 9];
    $rain = [13 => 0, 14 => 0, 15 => 4.1, 16 => 5.2, 17 => 0,
        18 => 0.2, 19 => 0.2, 20 => 0.2, 21 => 0.2, 22 => 0.2, 23 => 0.2];
    $entries[] = [
        'time' => sprintf('2026-10-01T%02d:00:00Z', $hour - 2),
        'data' => [
            'instant' => ['details' => [
                'air_temperature' => $temperatures[$hour],
                'wind_speed' => $afternoon ? 8 : 4,
                'wind_speed_of_gust' => 13,
                'wind_from_direction' => 90,
                'relative_humidity' => 60,
            ]],
            'next_1_hours' => [
                'summary' => ['symbol_code' => $hour >= 15 && $hour < 18 ? 'rain' : 'cloudy'],
                'details' => ['precipitation_amount' => $rain[$hour]],
            ],
        ],
    ];
}
for ($hour = 0; $hour < 6; $hour++) {
    $time = (new DateTimeImmutable('2026-10-02T00:00:00+02:00'))
        ->modify("+{$hour} hours")->setTimezone(new DateTimeZone('UTC'));
    $entries[] = [
        'time' => $time->format('Y-m-d\TH:i:s\Z'),
        'data' => [
            'instant' => ['details' => ['air_temperature' => 8 - $hour, 'wind_speed' => 3]],
            'next_1_hours' => [
                'summary' => ['symbol_code' => 'clearsky_night'],
                'details' => ['precipitation_amount' => 0],
            ],
        ],
    ];
}
$json = json_encode(['properties' => ['timeseries' => $entries]], JSON_THROW_ON_ERROR);
$now = new DateTimeImmutable('2026-10-01T13:00:00+02:00');
$sixhour = json_encode(['properties' => ['timeseries' => [[
    'time' => '2026-10-03T10:00:00Z',
    'data' => [
        'instant' => ['details' => ['air_temperature' => 16, 'wind_speed' => 8]],
        'next_6_hours' => [
            'summary' => ['symbol_code' => 'rain'],
            'details' => [
                'precipitation_amount' => 9.3,
                'air_temperature_min' => 12,
                'air_temperature_max' => 18,
            ],
        ],
    ],
]]]], JSON_THROW_ON_ERROR);

echo json_encode([
    'valid' => forecast::parse($json, $now),
    'evening' => forecast::parse($json, new DateTimeImmutable('2026-10-01T20:00:00+02:00')),
    'sixhour' => forecast::parse($sixhour, new DateTimeImmutable('2026-10-03T12:00:00+02:00')),
    'invalid' => forecast::parse('{"properties":{"timeseries":[]}}'),
], JSON_THROW_ON_ERROR);
