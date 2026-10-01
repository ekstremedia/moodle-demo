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
            $this->content->footer = $this->render_footer();
        } else {
            $this->content->text = $this->render_weather($weather);
        }
        return $this->content;
    }

    private function render_footer(): string {
        $forecasturl = new moodle_url('https://www.yr.no/nb/v%C3%A6rvarsel/daglig-tabell/1-2376/Norge/Agder/Kristiansand/Kristiansand');
        $licenseurl = new moodle_url('https://creativecommons.org/licenses/by/4.0/');
        return html_writer::link($forecasturl, get_string('forecastlink', 'block_weather'))
            . html_writer::tag('small', get_string('attribution', 'block_weather') . ' · '
                . html_writer::link($licenseurl, 'CC BY 4.0') . ' · '
                . get_string('iconsource', 'block_weather'), ['class' => 'block-weather-source']);
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
        $currentcolumn = html_writer::tag('div', $now, ['class' => 'block-weather-now']);

        if ($current['precipitation'] !== null) {
            $currentcolumn .= html_writer::tag('p',
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
            $currentcolumn .= html_writer::tag('p', $wind, ['class' => 'block-weather-detail']);
        }

        $outlook = '';
        if ($weather['periods']) {
            $groups = [];
            foreach ($weather['periods'] as $period) {
                $condition = $this->condition($period['symbol']);
                $groups[$period['date']][] = html_writer::tag('div',
                    $this->icon($period['symbol'], $condition, 28)
                    . html_writer::tag('span', get_string('part' . $period['part'], 'block_weather'),
                        ['class' => 'block-weather-period-label']),
                    ['class' => 'block-weather-daypart']);
            }
            foreach ($groups as $date => $symbols) {
                $day = $weather['days'][$date];
                $metrics = $this->metric('temperaturelabel', round($day['min']) . '–' . round($day['max']) . '°');
                if ($day['precipitation'] !== null) {
                    $metrics .= $this->metric('precipitationlabel', self::number($day['precipitation']) . ' mm');
                }
                if ($day['wind'] !== null) {
                    $metrics .= $this->metric('windlabel', self::number($day['wind']) . ' m/s');
                }
                $outlook .= html_writer::tag('div',
                    html_writer::tag('strong', s($this->date_label($date)), ['class' => 'block-weather-date'])
                    . html_writer::tag('div', implode('', $symbols), ['class' => 'block-weather-symbols'])
                    . html_writer::tag('div', $metrics, ['class' => 'block-weather-metrics']),
                    ['class' => 'block-weather-day']);
            }
        }
        return html_writer::tag('div',
            html_writer::tag('div', $currentcolumn, ['class' => 'block-weather-current'])
            . html_writer::tag('div', $outlook, ['class' => 'block-weather-outlook'])
            . html_writer::tag('div', $this->render_footer(), ['class' => 'block-weather-footer']),
            ['class' => 'block-weather-layout']);
    }

    private function metric(string $label, string $value): string {
        return html_writer::tag('div',
            html_writer::tag('strong', s($value), ['class' => 'block-weather-metric-value'])
            . html_writer::tag('span', get_string($label, 'block_weather'), ['class' => 'block-weather-metric-label']),
            ['class' => 'block-weather-metric']);
    }

    private function icon(?string $code, string $alt, int $size = 56): string {
        if ($code === null || !preg_match('/^[a-z0-9_]+$/D', $code)
                || !is_file(__DIR__ . '/pix/symbols/' . $code . '.svg')) {
            return '';
        }
        return html_writer::empty_tag('img', [
            'src' => (new moodle_url('/blocks/weather/pix/symbols/' . $code . '.svg'))->out(false),
            'alt' => $alt,
            'class' => 'block-weather-icon',
            'width' => $size,
            'height' => $size,
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
