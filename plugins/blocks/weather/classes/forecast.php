<?php
// This file is part of Moodle - https://moodle.org/.
// Moodle is free software: you can redistribute it and/or modify it under the
// terms of the GNU General Public License as published by the Free Software
// Foundation, either version 3 of the License, or (at your option) any later version.

namespace block_weather;

defined('MOODLE_INTERNAL') || die();

/** A shared, conditional-request client for MET Norway's Locationforecast API. */
final class forecast {
    private const URL = 'https://api.met.no/weatherapi/locationforecast/2.0/compact?lat=58.1467&lon=7.9956';

    /** @return array{temperature: float, precipitation: float|null}|null */
    public static function current(): ?array {
        global $CFG;

        $cache = \cache::make('block_weather', 'forecast');
        $stored = $cache->get('kristiansand');
        if (is_array($stored) && ($stored['expires'] ?? 0) > time()) {
            return $stored['weather'] ?? null;
        }

        require_once($CFG->libdir . '/filelib.php');
        $client = new \curl();
        $agent = getenv('YR_USER_AGENT') ?: 'MoodleWeather/1.0 (https://ekstremedia.no)';
        // Moodle's curl class sets User-Agent from this option during the request.
        $client->setopt(['CURLOPT_USERAGENT' => $agent]);
        $client->setHeader('Accept: application/json');
        if (is_array($stored) && !empty($stored['lastmodified'])) {
            $client->setHeader('If-Modified-Since: ' . $stored['lastmodified']);
        }

        try {
            $body = $client->get(self::URL, [], ['CURLOPT_TIMEOUT' => 5]);
            $status = (int) ($client->get_info()['http_code'] ?? 0);
            $headers = array_change_key_case($client->getResponse(), CASE_LOWER);
        } catch (\Throwable $e) {
            $body = false;
            $status = 0;
            $headers = [];
        }
        $expires = isset($headers['expires']) ? strtotime((string) $headers['expires']) : false;
        $retryat = max(time() + 300, $expires ?: 0);

        if ($status === 304 && is_array($stored)) {
            $stored['expires'] = $retryat;
            $stored['fetched'] = time();
            $cache->set('kristiansand', $stored);
            return $stored['weather'] ?? null;
        }

        if (($status === 200 || $status === 203) && is_string($body) && ($weather = self::parse($body)) !== null) {
            $record = [
                'weather' => $weather,
                'expires' => $retryat,
                'fetched' => time(),
                'lastmodified' => $headers['last-modified'] ?? null,
            ];
            $cache->set('kristiansand', $record);
            return $weather;
        }

        // A brief outage may reuse a recent forecast; old weather is misleading.
        $weather = is_array($stored) && ($stored['fetched'] ?? 0) > time() - 21600
            ? ($stored['weather'] ?? null) : null;
        $cache->set('kristiansand', [
            'weather' => $weather,
            'expires' => time() + ($status === 429 ? 3600 : 300),
            'fetched' => is_array($stored) ? ($stored['fetched'] ?? 0) : 0,
            'lastmodified' => is_array($stored) ? ($stored['lastmodified'] ?? null) : null,
        ]);
        return $weather;
    }

    /** @return array{temperature: float, precipitation: float|null}|null */
    public static function parse(string $json): ?array {
        $data = json_decode($json, true);
        $period = $data['properties']['timeseries'][0]['data'] ?? null;
        $temperature = $period['instant']['details']['air_temperature'] ?? null;
        $precipitation = $period['next_1_hours']['details']['precipitation_amount'] ?? null;
        if (!is_numeric($temperature) || ($precipitation !== null && !is_numeric($precipitation))) {
            return null;
        }
        return [
            'temperature' => (float) $temperature,
            'precipitation' => $precipitation === null ? null : (float) $precipitation,
        ];
    }
}
