<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/MoodleBrowser.php';

final class MoodleLearningStepTest extends TestCase {
    private string $username;
    private MoodleBrowser $browser;

    protected function setUp(): void {
        $this->username = 'test-learningstep-' . bin2hex(random_bytes(6));
        $this->fixture('create');
        $this->browser = new MoodleBrowser();
    }

    protected function tearDown(): void {
        $this->browser->close();
        $this->fixture('delete');
    }

    public function testAnswerPersistsAcrossLoginAndCanBeReset(): void {
        [$html] = $this->browser->login($this->username, 'Learning123!');
        self::assertStringContainsString('0 av 5 steg gjennomgått', $html);
        $form = $this->form($html);
        $form += ['action' => 'answer', 'answer' => '2'];
        [$html, $url, $status] = $this->browser->post('/blocks/learningstep/action.php', $form);
        self::assertSame(200, $status);
        self::assertStringContainsString('/my/', $url);
        self::assertStringContainsString('Ja, dette gjør innholdet lettere å bruke.', $html);
        self::assertStringContainsString('1 av 5 steg gjennomgått', $html);
        self::assertSame(['step' => 0, 'answer' => 2], json_decode($this->fixture('state'), true));
        $this->browser->close();
        $this->browser = new MoodleBrowser();
        [$html] = $this->browser->login($this->username, 'Learning123!');
        self::assertStringContainsString('Du valgte:', $html);
        [$html] = $this->browser->post('/blocks/learningstep/action.php', $this->form($html) + ['action' => 'next']);
        self::assertStringContainsString('Et diagram viser', $html);
        // An old answer form must not answer the diagram question.
        $this->browser->post('/blocks/learningstep/action.php', $form);
        self::assertSame(['step' => 1, 'answer' => null], json_decode($this->fixture('state'), true));
        [$html] = $this->browser->post('/blocks/learningstep/action.php', $this->form($html) + ['action' => 'reset']);
        self::assertStringContainsString('0 av 5 steg gjennomgått', $html);
        self::assertSame('', $this->fixture('state'));
    }

    public function testGuardsRejectGuestsGetInvalidAnswersAndMissingCsrfToken(): void {
        [, $url] = $this->browser->get('/blocks/learningstep/action.php');
        self::assertStringContainsString('/login/index.php', $url);
        [$html] = $this->browser->login($this->username, 'Learning123!');
        $form = $this->form($html);
        [, , $status] = $this->browser->get('/blocks/learningstep/action.php?action=reset');
        self::assertSame(405, $status);
        $this->browser->post('/blocks/learningstep/action.php', ['step' => 0, 'action' => 'answer', 'answer' => 2]);
        self::assertSame('', $this->fixture('state'));
        $this->browser->post('/blocks/learningstep/action.php', $form + ['action' => 'answer', 'answer' => 99]);
        self::assertSame('', $this->fixture('state'));
        $this->browser->post('/blocks/learningstep/action.php', $form + ['action' => 'next']);
        self::assertSame('', $this->fixture('state'));
    }

    public function testProgressBelongsToTheSessionUserOnly(): void {
        $othername = 'test-learningstep-' . bin2hex(random_bytes(6));
        $otherid = (int) $this->fixture('create', $othername);
        $otherbrowser = new MoodleBrowser();
        try {
            [$html] = $this->browser->login($this->username, 'Learning123!');
            $this->browser->post('/blocks/learningstep/action.php', $this->form($html)
                + ['action' => 'answer', 'answer' => 2, 'userid' => $otherid]);
            self::assertSame('', $this->fixture('state', $othername));
            [$otherpage] = $otherbrowser->login($othername, 'Learning123!');
            self::assertStringContainsString('0 av 5 steg gjennomgått', $otherpage);
            self::assertSame(2, json_decode($this->fixture('state'), true)['answer']);
        } finally {
            $otherbrowser->close();
            $this->fixture('delete', $othername);
        }
    }

    public function testCapabilityDenialHidesBlockAndPreventsSubmission(): void {
        [$html] = $this->browser->login($this->username, 'Learning123!');
        $form = $this->form($html);
        $this->fixture('deny');
        [$html] = $this->browser->get('/my/');
        self::assertStringNotContainsString('class="learningstep"', $html);
        $this->browser->post('/blocks/learningstep/action.php', $form + ['action' => 'answer', 'answer' => 2]);
        self::assertSame('', $this->fixture('state'));
    }

    /** Extract only our block's fields, ignoring Moodle's other dashboard forms. */
    private function form(string $html): array {
        $document = new DOMDocument();
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $forms = $xpath->query('//form[contains(@action, "/blocks/learningstep/action.php")]');
        self::assertGreaterThan(0, $forms->length);
        $result = [];
        foreach (['sesskey', 'step'] as $name) {
            $result[$name] = $xpath->evaluate('string(.//input[@name="' . $name . '"]/@value)', $forms->item(0));
            self::assertNotSame('', $result[$name]);
        }
        return $result;
    }

    private function fixture(string $action, ?string $username = null): string {
        $process = proc_open(['docker', 'compose', 'exec', '-T', '-u', 'www-data', 'web',
            'php', '/dev/stdin', $action, $username ?? $this->username],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__));
        self::assertIsResource($process);
        fwrite($pipes[0], file_get_contents(__DIR__ . '/fixtures/learningstep.php'));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error);
        return $output;
    }
}
