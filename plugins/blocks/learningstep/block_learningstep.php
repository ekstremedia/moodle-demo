<?php
// This file is part of Moodle - https://moodle.org/.
// Licensed under the GNU GPL v3 or later: https://www.gnu.org/licenses/gpl-3.0.html

defined('MOODLE_INTERNAL') || die();

/** A dashboard block for short, private practice in accessible course design. */
class block_learningstep extends block_base {
    /** Set the translated block title. */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_learningstep');
    }

    /** Keep the shared learning sequence on personal dashboards. */
    public function applicable_formats(): array {
        return ['my' => true, 'all' => false];
    }

    /** Render the current question or its explanation using escaped Mustache values. */
    public function get_content(): stdClass {
        global $OUTPUT;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object) ['text' => '', 'footer' => ''];
        if (!isloggedin() || isguestuser()
                || !has_capability('block/learningstep:use', context_system::instance())) {
            return $this->content;
        }
        $state = \block_learningstep\steps::decode(
            (string) get_user_preferences(\block_learningstep\steps::PREFERENCE, ''));
        $step = $state['step'];
        $done = $step === count(\block_learningstep\steps::KEYS);
        $answered = $state['answer'] !== null;
        $data = [
            'actionurl' => (new moodle_url('/blocks/learningstep/action.php'))->out(false),
            'sesskey' => sesskey(),
            'step' => $step,
            'done' => $done,
            'laststep' => $step === count(\block_learningstep\steps::KEYS) - 1,
            'answered' => $answered,
            'questionvisible' => !$done && !$answered,
            'progress' => get_string('progress', 'block_learningstep', (object) [
                'done' => $step + (int) $answered, 'total' => count(\block_learningstep\steps::KEYS),
            ]),
        ];
        if ($done) {
            foreach (\block_learningstep\steps::KEYS as $key) {
                $data['topics'][] = ['label' => get_string('topic' . $key, 'block_learningstep')];
            }
        }
        if (!$done) {
            $key = \block_learningstep\steps::KEYS[$step];
            $data['question'] = get_string($key . '_question', 'block_learningstep');
            if ($answered) {
                $correct = $state['answer'] === \block_learningstep\steps::ANSWERS[$step];
                $data['feedback'] = get_string($correct ? 'correct' : 'consider', 'block_learningstep');
                $data['selected'] = get_string($key . '_option' . $state['answer'], 'block_learningstep');
                $data['explanation'] = get_string($key . '_explanation', 'block_learningstep');
            } else {
                for ($i = 0; $i < 3; $i++) {
                    $data['options'][] = ['value' => $i,
                        'label' => get_string($key . '_option' . $i, 'block_learningstep')];
                }
            }
        }
        $this->page->requires->js_call_amd('block_learningstep/interaction', 'init');
        $this->content->text = $OUTPUT->render_from_template('block_learningstep/content', $data);
        return $this->content;
    }
}
