<?php
declare(strict_types=1);

use block_learningstep\steps;
use PHPUnit\Framework\TestCase;

// The state machine has no Moodle runtime dependency.
defined('MOODLE_INTERNAL') || define('MOODLE_INTERNAL', true);
require_once __DIR__ . '/../plugins/blocks/learningstep/classes/steps.php';

final class LearningStepStateTest extends TestCase {
    public function testEveryStepCanBeAnsweredAndCompleted(): void {
        $state = steps::decode('');
        foreach (steps::ANSWERS as $index => $answer) {
            self::assertSame($index, $state['step']);
            $state = steps::apply($state, 'answer', $index, $answer);
            self::assertSame($answer, $state['answer']);
            self::assertSame($state, steps::decode(json_encode($state)));
            $state = steps::apply($state, 'next', $index, null);
        }
        self::assertSame(['step' => 5, 'answer' => null], $state);
        self::assertSame(['step' => 0, 'answer' => null], steps::apply($state, 'reset', 5, null));
    }

    public function testWrongAnswerStillAllowsLearningFromExplanation(): void {
        $state = steps::apply(steps::decode(''), 'answer', 0, 0);
        self::assertSame(0, $state['answer']);
        self::assertSame(1, steps::apply($state, 'next', 0, null)['step']);
    }

    public function testStaleAndDuplicateSubmissionsDoNotSkipOrOverwriteSteps(): void {
        $state = steps::apply(steps::decode(''), 'answer', 0, 1);
        self::assertSame($state, steps::apply($state, 'answer', 0, 2));
        $state = steps::apply($state, 'next', 0, null);
        foreach (['answer', 'next', 'reset'] as $action) {
            self::assertSame($state, steps::apply($state, $action, 0, 2));
        }
    }

    public function testInvalidStoredValuesRecoverSafely(): void {
        foreach (['bad json', 'null', '[]', '{"step":-1,"answer":null}',
                '{"step":6,"answer":null}', '{"step":0,"answer":9}',
                '{"step":"0","answer":null}', '{"step":5,"answer":0}'] as $raw) {
            self::assertSame(['step' => 0, 'answer' => null], steps::decode($raw));
        }
    }

    public function testCannotSkipAnUnansweredStep(): void {
        $this->expectException(InvalidArgumentException::class);
        steps::apply(steps::decode(''), 'next', 0, null);
    }

    public function testRejectsOutOfRangeAnswer(): void {
        $this->expectException(InvalidArgumentException::class);
        steps::apply(steps::decode(''), 'answer', 0, 3);
    }

    public function testRejectsMissingAnswer(): void {
        $this->expectException(InvalidArgumentException::class);
        steps::apply(steps::decode(''), 'answer', 0, null);
    }

    public function testRejectsUnknownAction(): void {
        $this->expectException(InvalidArgumentException::class);
        steps::apply(steps::decode(''), 'skip', 0, null);
    }

    public function testCannotAnswerAfterCompletion(): void {
        $this->expectException(InvalidArgumentException::class);
        steps::apply(['step' => 5, 'answer' => null], 'answer', 5, 0);
    }
}
