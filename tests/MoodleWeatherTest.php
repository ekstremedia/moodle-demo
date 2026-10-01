<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/MoodleBrowser.php';

final class MoodleWeatherTest extends TestCase {
    public function testDashboardShowsKristiansandWeatherBlockForLoggedInUser(): void {
        self::runFixture('weather-cache.php', 'seed');
        $browser = new MoodleBrowser();
        try {
            [$login, $url, $status] = $browser->login('demo', 'Demo123!');
            self::assertSame(200, $status);
            self::assertStringStartsWith($browser->baseUrl() . '/my/', $url);
            self::assertStringContainsString('Vær i Kristiansand', $login);
            self::assertStringContainsString('Hele varselet på Yr', $login);
            self::assertStringContainsString('Utdrag av værdata: MET Norway', $login);
            self::assertStringContainsString('1-2376', $login);
            self::assertStringContainsString('Været nå', $login);
            self::assertStringContainsString('Skyet', $login);
            self::assertStringContainsString('Føles som 16°', $login);
            self::assertStringContainsString('8 m/s frisk bris fra øst med vindkast på 13 m/s', $login);
            $document = new DOMDocument();
            @$document->loadHTML($login);
            $xpath = new DOMXPath($document);
            $layouts = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' block-weather-layout ')]");
            self::assertCount(1, $layouts);
            $layout = $layouts->item(0);
            self::assertCount(1, $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' block-weather-current ')]"));
            $outlooks = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' block-weather-outlook ')]");
            self::assertCount(1, $outlooks);
            $outlook = $outlooks->item(0);
            $dayparts = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' block-weather-daypart ')]", $outlook);
            self::assertGreaterThanOrEqual(2, $dayparts->length);
            self::assertCount($dayparts->length, $xpath->query(".//img[@width='28' and @height='28']", $outlook));
            $days = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' block-weather-day ')]", $outlook);
            self::assertGreaterThanOrEqual(1, $days->length);
            self::assertCount(3 * $days->length, $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' block-weather-metric ')]", $outlook));
            $footers = $xpath->query("./*[contains(concat(' ', normalize-space(@class), ' '), ' block-weather-footer ')]", $layout);
            self::assertCount(1, $footers);
            self::assertCount(1, $xpath->query(".//a[contains(@href, 'yr.no')]", $footers->item(0)));
            self::assertStringContainsString('Maks vind', $login);
            self::assertStringContainsString('cloudy.svg', $login);
            self::assertStringContainsString('rain.svg', $login);
        } finally {
            $browser->close();
            self::runFixture('weather-cache.php', 'clear');
        }
    }

    public function testForecastParserBuildsCurrentConditionsAndNextDayparts(): void {
        $stdout = self::runFixture('weather.php');
        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertNull($result['invalid']);
        self::assertSame('cloudy', $result['valid']['current']['symbol']);
        self::assertEquals(16, $result['valid']['current']['temperature']);
        self::assertEquals(16, $result['valid']['current']['feels_like']);
        self::assertEquals(8, $result['valid']['current']['wind']);
        self::assertEquals(13, $result['valid']['current']['gust']);
        self::assertEquals(90, $result['valid']['current']['direction']);
        self::assertEquals(0, $result['valid']['current']['precipitation']);
        self::assertCount(2, $result['valid']['periods']);
        self::assertSame('2026-10-01', $result['valid']['periods'][0]['date']);
        self::assertSame(2, $result['valid']['periods'][0]['part']);
        self::assertEquals(14, $result['valid']['periods'][0]['min']);
        self::assertEquals(18, $result['valid']['periods'][0]['max']);
        self::assertEqualsWithDelta(9.3, $result['valid']['periods'][0]['precipitation'], 0.01);
        self::assertEquals(8, $result['valid']['periods'][0]['wind']);
        self::assertSame('rain', $result['valid']['periods'][0]['symbol']);
        self::assertSame(3, $result['valid']['periods'][1]['part']);
        self::assertEqualsWithDelta(1.2, $result['valid']['periods'][1]['precipitation'], 0.01);
        self::assertEquals(9, $result['valid']['days']['2026-10-01']['min']);
        self::assertEquals(18, $result['valid']['days']['2026-10-01']['max']);
        self::assertEqualsWithDelta(10.5, $result['valid']['days']['2026-10-01']['precipitation'], 0.01);
        self::assertEquals(8, $result['valid']['days']['2026-10-01']['wind']);
        self::assertSame(3, $result['evening']['periods'][0]['part']);
        self::assertCount(2, $result['evening']['periods']);
        self::assertSame('2026-10-02', $result['evening']['periods'][1]['date']);
        self::assertSame(0, $result['evening']['periods'][1]['part']);
        self::assertEqualsWithDelta(0.8, $result['evening']['days']['2026-10-01']['precipitation'], 0.01);
        self::assertEquals(0, $result['evening']['days']['2026-10-02']['precipitation']);
        self::assertEquals(12, $result['sixhour']['periods'][0]['min']);
        self::assertEquals(18, $result['sixhour']['periods'][0]['max']);
        self::assertEqualsWithDelta(9.3, $result['sixhour']['periods'][0]['precipitation'], 0.01);
        self::assertSame('rain', $result['sixhour']['periods'][0]['symbol']);
        self::assertEqualsWithDelta(9.3, $result['sixhour']['days']['2026-10-03']['precipitation'], 0.01);
    }

    private static function runFixture(string $filename, ?string $action = null): string {
        $command = ['docker', 'compose', 'exec', '-T', '-u', 'www-data', 'web', 'php', '/dev/stdin'];
        if ($action !== null) {
            $command[] = $action;
        }
        $process = proc_open(
            $command,
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            dirname(__DIR__),
        );
        self::assertIsResource($process);
        fwrite($pipes[0], file_get_contents(__DIR__ . '/fixtures/' . $filename));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        return $stdout;
    }

    public function testWeatherSymbolsAreBundledWithTheirLicense(): void {
        $symbols = __DIR__ . '/../plugins/blocks/weather/pix/symbols/';
        foreach (['cloudy', 'rain', 'clearsky_day', 'snow'] as $symbol) {
            self::assertFileExists($symbols . $symbol . '.svg');
        }
        self::assertFileExists($symbols . 'LICENSE');
    }
}
