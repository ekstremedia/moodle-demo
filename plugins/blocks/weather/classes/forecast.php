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

    /** @return array|null Weather now and the remaining dayparts for today. */
    public static function current(): ?array {
        global $CFG;

        $cache = \cache::make('block_weather', 'forecast');
        $stored = $cache->get('kristiansand-v3');
        if (is_array($stored) && ($stored['expires'] ?? 0) > time()) {
            return is_string($stored['body'] ?? null) ? self::parse($stored['body']) : null;
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
            $cache->set('kristiansand-v3', $stored);
            return is_string($stored['body'] ?? null) ? self::parse($stored['body']) : null;
        }

        if (($status === 200 || $status === 203) && is_string($body) && ($weather = self::parse($body)) !== null) {
            $record = [
                'body' => $body,
                'expires' => $retryat,
                'fetched' => time(),
                'lastmodified' => $headers['last-modified'] ?? null,
            ];
            $cache->set('kristiansand-v3', $record);
            return $weather;
        }

        // A brief outage may reuse a recent forecast; old weather is misleading.
        $body = is_array($stored) && ($stored['fetched'] ?? 0) > time() - 21600
            ? ($stored['body'] ?? null) : null;
        if (!is_string($body)) {
            $body = null;
        }
        $cache->set('kristiansand-v3', [
            'body' => $body,
            'expires' => time() + ($status === 429 ? 3600 : 300),
            'fetched' => is_array($stored) ? ($stored['fetched'] ?? 0) : 0,
            'lastmodified' => $body !== null && is_array($stored) ? ($stored['lastmodified'] ?? null) : null,
        ]);
        return $body !== null ? self::parse($body) : null;
    }

    /** @return array|null Weather now and the remaining dayparts for today. */
    public static function parse(string $json, ?\DateTimeImmutable $now = null): ?array {
        $data = json_decode($json, true);
        $entries = $data['properties']['timeseries'] ?? null;
        if (!is_array($entries) || !$entries) {
            return null;
        }

        $zone = new \DateTimeZone('Europe/Oslo');
        $localnow = ($now ?? new \DateTimeImmutable('now'))->setTimezone($zone);
        $cutoff = $localnow->setTime((int) $localnow->format('G'), 0);
        $current = null;
        foreach ($entries as $entry) {
            if (empty($entry['time'])) {
                continue;
            }
            try {
                $entrytime = new \DateTimeImmutable($entry['time']);
                if ($entrytime >= $cutoff) {
                    $current = $entry;
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        if ($current === null) {
            return null;
        }
        $instant = $current['data']['instant']['details'] ?? [];
        $temperature = $instant['air_temperature'] ?? null;
        if (!is_numeric($temperature)) {
            return null;
        }
        $wind = self::number($instant['wind_speed'] ?? null);
        $humidity = self::number($instant['relative_humidity'] ?? null);
        $next = $current['data']['next_1_hours'] ?? [];
        $start = $localnow->setTime(intdiv((int) $localnow->format('G'), 6) * 6, 0);
        $today = $localnow->format('Y-m-d');
        $periods = [];
        while ($start->format('Y-m-d') === $today) {
            $end = $start->modify('+6 hours');
            $period = self::period($entries, $start, $end, $localnow);
            if ($period !== null) {
                $periods[] = $period;
            }
            $start = $end;
        }
        $days = [];
        foreach ($periods as $period) {
            $date = $period['date'];
            if (!isset($days[$date])) {
                $days[$date] = [
                    'min' => $period['min'],
                    'max' => $period['max'],
                    'precipitation' => $period['precipitation'],
                    'wind' => $period['wind'],
                ];
                continue;
            }
            $days[$date]['min'] = min($days[$date]['min'], $period['min']);
            $days[$date]['max'] = max($days[$date]['max'], $period['max']);
            if ($period['precipitation'] !== null) {
                $days[$date]['precipitation'] = ($days[$date]['precipitation'] ?? 0) + $period['precipitation'];
            }
            if ($period['wind'] !== null) {
                $days[$date]['wind'] = max($days[$date]['wind'] ?? 0, $period['wind']);
            }
        }

        return [
            'current' => [
                'temperature' => (float) $temperature,
                'feels_like' => self::feels_like((float) $temperature, $wind, $humidity),
                'precipitation' => self::number($next['details']['precipitation_amount'] ?? null),
                'wind' => $wind,
                'gust' => self::number($instant['wind_speed_of_gust'] ?? null),
                'direction' => self::number($instant['wind_from_direction'] ?? null),
                'symbol' => $next['summary']['symbol_code']
                    ?? $current['data']['next_6_hours']['summary']['symbol_code']
                    ?? null,
            ],
            'periods' => $periods,
            'days' => $days,
        ];
    }

    private static function number(mixed $value): ?float {
        return is_numeric($value) ? (float) $value : null;
    }

    /** Match the wind-chill and heat-index calculations used by laravel-yr. */
    private static function feels_like(float $temperature, ?float $wind, ?float $humidity): float {
        if ($temperature <= 10 && $wind !== null && $wind > 1.34) {
            $kmh = $wind * 3.6;
            return round(13.12 + 0.6215 * $temperature - 11.37 * pow($kmh, 0.16)
                + 0.3965 * $temperature * pow($kmh, 0.16), 1);
        }
        if ($temperature >= 27 && $humidity !== null) {
            return round(-8.78469475556 + 1.61139411 * $temperature
                + 2.33854883889 * $humidity - 0.14611605 * $temperature * $humidity
                - 0.012308094 * $temperature ** 2 - 0.0164248277778 * $humidity ** 2
                + 0.002211732 * $temperature ** 2 * $humidity
                + 0.00072546 * $temperature * $humidity ** 2
                - 0.000003582 * $temperature ** 2 * $humidity ** 2, 1);
        }
        return $temperature;
    }

    /** Aggregate a local daypart using hourly rain, or one six-hour forecast when hourly data has ended. */
    private static function period(array $entries, \DateTimeImmutable $start, \DateTimeImmutable $end,
            \DateTimeImmutable $now): ?array {
        $temperatures = [];
        $minimums = [];
        $maximums = [];
        $winds = [];
        $rain = 0.0;
        $hasrain = false;
        $symbols = [];
        $sixhoursymbol = null;
        $cutoff = $now->setTime((int) $now->format('G'), 0);

        foreach ($entries as $entry) {
            if (empty($entry['time'])) {
                continue;
            }
            try {
                $time = (new \DateTimeImmutable($entry['time']))->setTimezone($start->getTimezone());
            } catch (\Exception $e) {
                continue;
            }
            if ($time < $start || $time >= $end || $time < $cutoff) {
                continue;
            }
            $details = $entry['data']['instant']['details'] ?? [];
            $temperature = self::number($details['air_temperature'] ?? null);
            if ($temperature !== null) {
                $temperatures[] = $temperature;
            }
            $wind = self::number($details['wind_speed'] ?? null);
            if ($wind !== null) {
                $winds[] = $wind;
            }
            $hour = $entry['data']['next_1_hours'] ?? null;
            $sixhour = $entry['data']['next_6_hours'] ?? null;
            if ($sixhour !== null && $time->modify('+6 hours') <= $end) {
                $min = self::number($sixhour['details']['air_temperature_min'] ?? null);
                $max = self::number($sixhour['details']['air_temperature_max'] ?? null);
                if ($min !== null) {
                    $minimums[] = $min;
                }
                if ($max !== null) {
                    $maximums[] = $max;
                }
            }
            if ($hour !== null && $time->modify('+1 hour') <= $end) {
                $amount = self::number($hour['details']['precipitation_amount'] ?? null);
            } else if ($sixhour !== null && $time->modify('+6 hours') <= $end) {
                $amount = self::number($sixhour['details']['precipitation_amount'] ?? null);
            } else {
                $amount = null;
            }
            if ($amount !== null) {
                $rain += $amount;
                $hasrain = true;
            }
            if ($time == $start && !empty($sixhour['summary']['symbol_code'])) {
                $sixhoursymbol = $sixhour['summary']['symbol_code'];
            }
            $symbol = $hour['summary']['symbol_code']
                ?? $sixhour['summary']['symbol_code'] ?? null;
            if (is_string($symbol)) {
                $symbols[] = $symbol;
            }
        }

        if (!$temperatures) {
            return null;
        }
        return [
            'date' => $start->format('Y-m-d'),
            'part' => intdiv((int) $start->format('G'), 6),
            'min' => min(array_merge($temperatures, $minimums)),
            'max' => max(array_merge($temperatures, $maximums)),
            'precipitation' => $hasrain ? round($rain, 1) : null,
            'wind' => $winds ? max($winds) : null,
            'symbol' => $sixhoursymbol ?? ($symbols ? $symbols[intdiv(count($symbols), 2)] : null),
        ];
    }
}
