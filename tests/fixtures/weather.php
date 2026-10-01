<?php
declare(strict_types=1);

use block_weather\forecast;

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

echo json_encode([
    'valid' => forecast::parse('{"properties":{"timeseries":[{"data":{"instant":{"details":{"air_temperature":9.5}},"next_1_hours":{"details":{"precipitation_amount":0}}}}]}}'),
    'missingrain' => forecast::parse('{"properties":{"timeseries":[{"data":{"instant":{"details":{"air_temperature":-2.1}}}}]}}'),
    'invalid' => forecast::parse('{"properties":{"timeseries":[]}}'),
], JSON_THROW_ON_ERROR);
