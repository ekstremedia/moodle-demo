<?php
declare(strict_types=1);

final class SetupEnvironment {
    public static function adminPassword(?string $path = null): string {
        $contents = @file_get_contents($path ?? __DIR__ . '/../../.env');
        if ($contents === false || preg_match('/^ADMIN_PASSWORD=([^\r\n]*)/m', $contents, $matches) !== 1) {
            throw new RuntimeException('ADMIN_PASSWORD is missing from .env');
        }

        $password = $matches[1];
        if (strlen($password) >= 2 &&
                (($password[0] === '"' && str_ends_with($password, '"')) ||
                 ($password[0] === "'" && str_ends_with($password, "'")))) {
            $password = substr($password, 1, -1);
        }
        if ($password === '') {
            throw new RuntimeException('ADMIN_PASSWORD is empty in .env');
        }
        return $password;
    }
}
