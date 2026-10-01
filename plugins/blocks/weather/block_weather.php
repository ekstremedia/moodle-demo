<?php
// This file is part of Moodle - https://moodle.org/.
// Moodle is free software: you can redistribute it and/or modify it under the
// terms of the GNU General Public License as published by the Free Software
// Foundation, either version 3 of the License, or (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

class block_weather extends block_base {
    public function init(): void {
        $this->title = get_string('pluginname', 'block_weather');
    }

    public function applicable_formats(): array {
        return ['my' => true, 'all' => false];
    }

    public function get_content(): stdClass {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = (object) ['text' => '', 'footer' => ''];
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $weather = \block_weather\forecast::current();
        if ($weather === null) {
            $this->content->text = html_writer::tag('p', get_string('unavailable', 'block_weather'));
        } else {
            $this->content->text = $this->render_weather($weather);
        }

        $forecasturl = new moodle_url('https://www.yr.no/nb/v%C3%A6rvarsel/daglig-tabell/1-2376/Norge/Agder/Kristiansand/Kristiansand');
        $licenseurl = new moodle_url('https://creativecommons.org/licenses/by/4.0/');
        $this->content->footer = html_writer::link($forecasturl, get_string('forecastlink', 'block_weather'))
            . html_writer::tag('small', get_string('attribution', 'block_weather') . ' · '
                . html_writer::link($licenseurl, 'CC BY 4.0') . ' · '
                . get_string('iconsource', 'block_weather'), ['class' => 'block-weather-source']);
        return $this->content;
    }

    private function render_weather(array $weather): string {
        $current = $weather['current'];
        $condition = $this->condition($current['symbol']);
        $now = $this->icon($current['symbol'], '')
            . html_writer::tag('div',
                html_writer::tag('div', get_string('now', 'block_weather'), ['class' => 'block-weather-eyebrow'])
                . html_writer::tag('div', s($condition), ['class' => 'block-weather-condition'])
                . html_writer::tag('div', s(round($current['temperature']) . '°'),
                    ['class' => 'block-weather-temperature'])
                . html_writer::tag('div', get_string('feelslike', 'block_weather',
                    round($current['feels_like']) . '°'), ['class' => 'block-weather-detail']),
                ['class' => 'block-weather-now-text']);
        $output = html_writer::tag('div', $now, ['class' => 'block-weather-now']);

        if ($current['precipitation'] !== null) {
            $output .= html_writer::tag('p',
                get_string('precipitation', 'block_weather', self::number($current['precipitation'])),
                ['class' => 'block-weather-detail']);
        }
        if ($current['wind'] !== null) {
            $wind = get_string('windspeed', 'block_weather', (object) [
                'speed' => self::number($current['wind']),
                'strength' => get_string('windscale' . self::beaufort($current['wind']), 'block_weather'),
            ]);
            if ($current['direction'] !== null) {
                $direction = (int) floor(fmod($current['direction'] + 22.5, 360) / 45);
                $wind .= get_string('winddirection', 'block_weather',
                    get_string('direction' . $direction, 'block_weather'));
            }
            if ($current['gust'] !== null) {
                $wind .= get_string('windgust', 'block_weather', self::number($current['gust']));
            }
            $output .= html_writer::tag('p', $wind, ['class' => 'block-weather-detail']);
        }

        if ($weather['periods']) {
            $rows = '';
            foreach ($weather['periods'] as $period) {
                $label = $this->date_label($period['date']) . ' · '
                    . get_string('part' . $period['part'], 'block_weather');
                $condition = $this->condition($period['symbol']);
                $top = html_writer::tag('strong', s($label), ['class' => 'block-weather-period-label'])
                    . $this->icon($period['symbol'], $condition)
                    . html_writer::tag('span', s(round($period['min']) . '–' . round($period['max']) . '°'),
                        ['class' => 'block-weather-range']);
                $details = [];
                if ($period['precipitation'] !== null) {
                    $details[] = get_string('periodprecipitation', 'block_weather',
                        self::number($period['precipitation']));
                }
                if ($period['wind'] !== null) {
                    $details[] = get_string('periodwind', 'block_weather', self::number($period['wind']));
                }
                $rows .= html_writer::tag('div', $top
                    . html_writer::tag('div', implode(' · ', $details), ['class' => 'block-weather-period-details']),
                    ['class' => 'block-weather-period']);
            }
            $output .= html_writer::tag('div', $rows, ['class' => 'block-weather-periods']);
        }
        return $output;
    }

    private function icon(?string $code, string $alt): string {
        if ($code === null || !preg_match('/^[a-z0-9_]+$/D', $code)
                || !is_file(__DIR__ . '/pix/symbols/' . $code . '.svg')) {
            return '';
        }
        return html_writer::empty_tag('img', [
            'src' => (new moodle_url('/blocks/weather/pix/symbols/' . $code . '.svg'))->out(false),
            'alt' => $alt,
            'class' => 'block-weather-icon',
            'loading' => 'lazy',
        ]);
    }

    private function condition(?string $code): string {
        $base = preg_replace('/_(day|night|polartwilight)$/', '', $code ?? '');
        $key = match (true) {
            $base === 'clearsky' => 'clearsky',
            $base === 'fair' => 'fair',
            $base === 'partlycloudy' => 'partlycloudy',
            $base === 'cloudy' => 'cloudy',
            $base === 'fog' => 'fog',
            str_contains($base, 'thunder') => 'thunder',
            str_contains($base, 'snow') => 'snow',
            str_contains($base, 'sleet') => 'sleet',
            str_contains($base, 'rainshowers') => 'rainshowers',
            str_contains($base, 'heavyrain') => 'heavyrain',
            str_contains($base, 'rain') => 'rain',
            default => 'unknown',
        };
        return get_string('condition' . $key, 'block_weather');
    }

    private static function beaufort(float $speed): int {
        foreach ([0.3, 1.6, 3.4, 5.5, 8.0, 10.8, 13.9, 17.2, 20.8, 24.5, 28.5, 32.7] as $level => $threshold) {
            if ($speed < $threshold) {
                return $level;
            }
        }
        return 12;
    }

    private static function number(float $value): string {
        $digits = $value == round($value) ? 0 : 1;
        return number_format($value, $digits, current_language() === 'nb' ? ',' : '.', '');
    }

    private function date_label(string $date): string {
        $locale = current_language() === 'nb' ? 'nb_NO' : 'en_GB';
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE,
            IntlDateFormatter::NONE, 'Europe/Oslo', IntlDateFormatter::GREGORIAN, 'd. MMM');
        $label = $formatter->format(new DateTimeImmutable($date, new DateTimeZone('Europe/Oslo')));
        return $label === false ? $date : $label;
    }
}
