<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/MoodleBrowser.php';
require_once __DIR__ . '/Support/SetupEnvironment.php';

final class MoodleAppTest extends TestCase {
    private static int $courseId = 0;

    public static function setUpBeforeClass(): void {
        $output = self::runFixture('create', bin2hex(random_bytes(4)));
        if (!preg_match('/(\d+)\s*$/', $output, $matches)) {
            throw new RuntimeException("Course fixture did not return an ID: {$output}");
        }
        self::$courseId = (int) $matches[1];
    }

    public static function tearDownAfterClass(): void {
        if (self::$courseId > 0) {
            self::runFixture('delete', (string) self::$courseId);
        }
    }

    public function testGuestsAreSentToLoginBeforeViewingCourse(): void {
        $browser = new MoodleBrowser();
        try {
            [, $url, $status] = $browser->get('/course/view.php?id=' . self::$courseId);
            self::assertSame(200, $status);
            self::assertStringContainsString('/login/index.php', $url);
        } finally {
            $browser->close();
        }
    }

    public function testDemoTeacherCanViewCourseAndTurnOnEditing(): void {
        $browser = new MoodleBrowser();
        try {
            [, $loginUrl, $loginStatus] = $browser->login('demo', 'Demo123!');
            self::assertSame(200, $loginStatus);
            self::assertStringStartsWith($browser->baseUrl() . '/my/', $loginUrl);

            $path = '/course/view.php?id=' . self::$courseId;
            [$course, , $courseStatus] = $browser->get($path);
            self::assertSame(200, $courseStatus);
            self::assertStringContainsString('Automated course smoke test', $course);
            self::assertMatchesRegularExpression('/<html[^>]+lang="nb"/', $course);
            self::assertSame(1, preg_match('~<form action="[^"]*/editmode\.php".*?</form>~s', $course, $forms));
            $form = $forms[0];
            self::assertSame(1, preg_match('/name="sesskey" value="([^"]+)"/', $form, $sesskey));
            self::assertSame(1, preg_match('/name="context" value="([^"]+)"/', $form, $context));

            [$editing, $url, $editStatus] = $browser->post('/editmode.php', [
                'setmode' => '1',
                'sesskey' => $sesskey[1],
                'pageurl' => $browser->baseUrl() . $path,
                'context' => $context[1],
            ]);
            self::assertSame(200, $editStatus);
            self::assertSame($browser->baseUrl() . $path, $url);
            self::assertMatchesRegularExpression('/name="setmode" checked/', $editing);
        } finally {
            $browser->close();
        }
    }

    public function testAdminCanOpenCourseSettings(): void {
        $browser = new MoodleBrowser();
        try {
            [, $loginUrl, $loginStatus] = $browser->login('admin', SetupEnvironment::adminPassword());
            self::assertSame(200, $loginStatus);
            self::assertStringStartsWith($browser->baseUrl() . '/my/', $loginUrl);
            [$settingsPage, $url, $status] = $browser->get('/course/edit.php?id=' . self::$courseId);
            self::assertSame(200, $status);
            self::assertStringContainsString('/course/edit.php?', $url);
            self::assertStringContainsString('name="fullname"', $settingsPage);
        } finally {
            $browser->close();
        }
    }

    private static function runFixture(string $action, string $argument): string {
        $process = proc_open(
            ['docker', 'compose', 'exec', '-T', '-u', 'www-data', 'web', 'php', '/dev/stdin', $action, $argument],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            dirname(__DIR__),
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start course fixture');
        }
        fwrite($pipes[0], file_get_contents(__DIR__ . '/fixtures/course.php'));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException("Course fixture failed: {$stderr}");
        }
        return $stdout;
    }
}
