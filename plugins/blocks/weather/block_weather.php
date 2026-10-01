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
            $temperature = round($weather['temperature']) . ' °C';
            $this->content->text = html_writer::tag('p', s($temperature), ['class' => 'block-weather-temperature']);
            if ($weather['precipitation'] !== null) {
                $amount = number_format($weather['precipitation'], 1, ',', '');
                $this->content->text .= html_writer::tag('p',
                    get_string('precipitation', 'block_weather', $amount),
                    ['class' => 'block-weather-detail']);
            }
        }

        $forecasturl = new moodle_url('https://www.yr.no/nb/v%C3%A6rvarsel/daglig-tabell/1-2376/Norge/Agder/Kristiansand/Kristiansand');
        $licenseurl = new moodle_url('https://creativecommons.org/licenses/by/4.0/');
        $this->content->footer = html_writer::link($forecasturl, get_string('forecastlink', 'block_weather'))
            . html_writer::tag('small', get_string('attribution', 'block_weather') . ' · '
                . html_writer::link($licenseurl, 'CC BY 4.0'), ['class' => 'block-weather-source']);
        return $this->content;
    }
}
