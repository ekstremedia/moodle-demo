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

    public function testVisitorsCanOpenNorwegianSignupForm(): void {
        $browser = new MoodleBrowser();
        try {
            [$login, , $loginStatus] = $browser->get('/login/index.php');
            self::assertSame(200, $loginStatus);
            self::assertStringContainsString('/login/signup.php', $login);

            [$signup, $url, $signupStatus] = $browser->get('/login/signup.php');
            self::assertSame(200, $signupStatus);
            self::assertSame($browser->baseUrl() . '/login/signup.php', $url);
            self::assertMatchesRegularExpression('/<html[^>]+lang="nb"/', $signup);
            self::assertStringContainsString('name="email2"', $signup);
        } finally {
            $browser->close();
        }
    }

    public function testSignupEmailConfirmationAndLogin(): void {
        $username = 'signup_test_' . bin2hex(random_bytes(6));
        // Moodle deliberately skips outgoing mail to .invalid addresses.
        $email = $username . '@example.test';
        $password = 'SignupTest123!';
        $browser = new MoodleBrowser();
        try {
            [$signup, , $status] = $browser->get('/login/signup.php');
            self::assertSame(200, $status);

            $dom = new DOMDocument();
            $old = libxml_use_internal_errors(true);
            try {
                $dom->loadHTML($signup);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($old);
            }
            $form = [];
            foreach ((new DOMXPath($dom))->query('//form//input[@type="hidden"]') as $input) {
                $form[$input->getAttribute('name')] = $input->getAttribute('value');
            }
            $form += [
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'email2' => $email,
                'firstname' => 'Signup',
                'lastname' => 'Test',
                'city' => 'Oslo',
                'country' => 'NO',
                'submitbutton' => '1',
            ];
            [$response, , $postStatus] = $browser->post('/login/signup.php', $form);
            self::assertSame(200, $postStatus);
            self::assertStringNotContainsString('name="email2"', $response, 'Signup form was redisplayed after submission');

            $mail = false;
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $mail = @file_get_contents('http://localhost:8025/view/latest.txt');
                if ($mail !== false && str_contains($mail, $username)) {
                    break;
                }
                usleep(200000);
            }
            self::assertIsString($mail, 'Mailpit did not receive a confirmation email');
            self::assertStringContainsString($username, $mail);
            self::assertSame(1, preg_match('~https?://[^\s<>]+/login/confirm\.php\?data=[^\s<>]+~', $mail, $link));
            $path = parse_url(trim($link[0]), PHP_URL_PATH) . '?' . parse_url(trim($link[0]), PHP_URL_QUERY);
            [$confirmed, , $confirmStatus] = $browser->get($path);
            self::assertSame(200, $confirmStatus);
            self::assertStringContainsString('Signup Test', $confirmed);
            $newBrowser = new MoodleBrowser();
            try {
                [, $loginUrl, $loginStatus] = $newBrowser->login($username, $password);
                self::assertSame(200, $loginStatus);
                self::assertStringStartsWith($newBrowser->baseUrl() . '/my/', $loginUrl);
            } finally {
                $newBrowser->close();
            }
        } finally {
            $browser->close();
            $process = proc_open(
                ['docker', 'compose', 'exec', '-T', '-u', 'www-data', 'web', 'php', '/dev/stdin', $username],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                $pipes,
                dirname(__DIR__),
            );
            if (is_resource($process)) {
                fwrite($pipes[0], file_get_contents(__DIR__ . '/fixtures/delete-user.php'));
                fclose($pipes[0]);
                stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                if (proc_close($process) !== 0) {
                    throw new RuntimeException("Could not clean up test user: {$stderr}");
                }
            }
        }
    }
}
