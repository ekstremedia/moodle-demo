<?php
declare(strict_types=1);

final class MoodleBrowser {
    private CurlHandle $curl;
    private string $baseUrl;

    public function __construct() {
        $this->baseUrl = rtrim(getenv('MOODLE_TEST_URL') ?: 'http://localhost:8080', '/');
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_HTTPHEADER => ['Accept-Language: en-US,en;q=0.9'],
            CURLOPT_TIMEOUT => 30,
        ]);
    }

    public function baseUrl(): string {
        return $this->baseUrl;
    }

    /** @return array{string, string, int} */
    public function get(string $path): array {
        curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        return $this->request($path);
    }

    /** @return array{string, string, int} */
    public function post(string $path, array $form): array {
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($form));
        return $this->request($path);
    }

    /** @return array{string, string, int} */
    public function login(string $username, string $password): array {
        [$page, , $status] = $this->get('/login/index.php');
        if ($status !== 200 || !preg_match('/name="logintoken" value="([^"]+)"/', $page, $matches)) {
            throw new RuntimeException('Could not load Moodle login form');
        }
        return $this->post('/login/index.php', [
            'username' => $username,
            'password' => $password,
            'logintoken' => $matches[1],
        ]);
    }

    public function close(): void {
        curl_close($this->curl);
    }

    /** @return array{string, string, int} */
    private function request(string $path): array {
        curl_setopt($this->curl, CURLOPT_URL, $this->baseUrl . $path);
        $body = curl_exec($this->curl);
        if ($body === false) {
            throw new RuntimeException(curl_error($this->curl));
        }
        return [$body, curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL), curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE)];
    }
}
