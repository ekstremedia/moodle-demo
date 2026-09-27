<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/MoodleBrowser.php';

final class MoodleSetupTest extends TestCase {
    public function testLoginPageUsesNorwegianEvenWithEnglishBrowserPreference(): void {
        $browser = new MoodleBrowser();
        try {
            [$html, $url, $status] = $browser->get('/login/index.php');
            self::assertSame(200, $status);
            self::assertSame($browser->baseUrl() . '/login/index.php', $url);
            self::assertMatchesRegularExpression('/<html[^>]+lang="nb"/', $html);
            self::assertStringContainsString('Logg inn', $html);
        } finally {
            $browser->close();
        }
    }

    public function testDemoAndAdminCanLogInWithNorwegianPages(): void {
        $settings = parse_ini_file(__DIR__ . '/../.env');
        self::assertIsArray($settings);
        self::assertNotEmpty($settings['ADMIN_PASSWORD'] ?? null);

        foreach (['demo' => 'Demo123!', 'admin' => $settings['ADMIN_PASSWORD']] as $username => $password) {
            $browser = new MoodleBrowser();
            try {
                [$dashboard, $url, $status] = $browser->login($username, $password);
                self::assertSame(200, $status);
                self::assertStringStartsWith($browser->baseUrl() . '/my/', $url, "{$username} login failed");
                self::assertMatchesRegularExpression('/<html[^>]+lang="nb"/', $dashboard);
            } finally {
                $browser->close();
            }
        }
    }
}
