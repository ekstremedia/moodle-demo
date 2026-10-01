<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/MoodleBrowser.php';

final class MoodleWeatherTest extends TestCase {
    public function testDashboardShowsKristiansandWeatherBlockForLoggedInUser(): void {
        $browser = new MoodleBrowser();
        try {
            [$login, $url, $status] = $browser->login('demo', 'Demo123!');
            self::assertSame(200, $status);
            self::assertStringStartsWith($browser->baseUrl() . '/my/', $url);
            self::assertStringContainsString('Vær i Kristiansand', $login);
            self::assertStringContainsString('Hele varselet på Yr', $login);
            self::assertStringContainsString('Utdrag av værdata: MET Norway', $login);
            self::assertStringContainsString('1-2376', $login);
        } finally {
            $browser->close();
        }
    }

    public function testForecastParserHandlesValidAndMissingData(): void {
        $process = proc_open(
            ['docker', 'compose', 'exec', '-T', '-u', 'www-data', 'web', 'php', '/dev/stdin'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            dirname(__DIR__),
        );
        self::assertIsResource($process);
        fwrite($pipes[0], file_get_contents(__DIR__ . '/fixtures/weather.php'));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        self::assertSame([
            'valid' => ['temperature' => 9.5, 'precipitation' => 0],
            'missingrain' => ['temperature' => -2.1, 'precipitation' => null],
            'invalid' => null,
        ], json_decode($stdout, true, 512, JSON_THROW_ON_ERROR));
    }
}
