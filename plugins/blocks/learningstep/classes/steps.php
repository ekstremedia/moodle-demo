<?php
// This file is part of Moodle - https://moodle.org/.
// Licensed under the GNU GPL v3 or later: https://www.gnu.org/licenses/gpl-3.0.html

namespace block_learningstep;

defined('MOODLE_INTERNAL') || die();

/** Five short exercises. IDs and order are stable because progress refers to them. */
final class steps {
    public const KEYS = ['communication', 'predictability', 'participation', 'hearing', 'voice'];
    public const ANSWERS = [2, 1, 0, 2, 1];
    public const PREFERENCE = 'block_learningstep_progress_v1';

    /** Read stored progress defensively; damaged or obsolete values start a new round. */
    public static function decode(string $raw): array {
        $state = json_decode($raw, true);
        if (!is_array($state) || !isset($state['step']) || !is_int($state['step'])
                || $state['step'] < 0 || $state['step'] > count(self::KEYS)
                || !array_key_exists('answer', $state)
                || ($state['answer'] !== null && (!is_int($state['answer'])
                    || $state['answer'] < 0 || $state['answer'] > 2))
                || ($state['step'] === count(self::KEYS) && $state['answer'] !== null)) {
            return ['step' => 0, 'answer' => null];
        }
        return ['step' => $state['step'], 'answer' => $state['answer']];
    }

    /** Apply an explicit action; stale forms cannot advance or overwrite another step. */
    public static function apply(array $state, string $action, int $step, ?int $answer): array {
        if ($step !== $state['step']) {
            return $state;
        }
        if ($action === 'reset') {
            return ['step' => 0, 'answer' => null];
        }
        if ($state['step'] >= count(self::KEYS)) {
            throw new \InvalidArgumentException('Round already complete');
        }
        if ($action === 'answer' && $answer !== null && $answer >= 0 && $answer <= 2) {
            return ['step' => $step, 'answer' => $state['answer'] ?? $answer];
        }
        if ($action === 'next' && $state['answer'] !== null) {
            return ['step' => $step + 1, 'answer' => null];
        }
        throw new \InvalidArgumentException('Invalid learning step action');
    }
}
