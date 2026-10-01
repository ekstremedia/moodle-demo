<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/SetupEnvironment.php';

final class SetupEnvironmentTest extends TestCase {
    public function testAdminPasswordCanBeReadAlongsideUnquotedWeatherAgent(): void {
        $path = tempnam(sys_get_temp_dir(), 'moodle-env-');
        self::assertIsString($path);
        try {
            file_put_contents($path, "ADMIN_PASSWORD=abc123\nYR_USER_AGENT=MoodleWeather/1.0 (https://ekstremedia.no)\n");
            self::assertSame('abc123', SetupEnvironment::adminPassword($path));
        } finally {
            unlink($path);
        }
    }
}
