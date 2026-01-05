<?php

use PHPUnit\Framework\TestCase;

class NewsDataTest extends TestCase
{
    private array $projectFiles = [
        'index.php',
        'clickbait.php',
        'search.php',
    ];

    private function projectPath(string $file): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . $file;
    }

    // a. FILE EXIST
    public function test_files_exist(): void
    {
        foreach ($this->projectFiles as $file) {
            $path = $this->projectPath($file);
            $this->assertFileExists($path, "File $file tidak ditemukan!");
        }
    }
    
    // b. VALID PHP CODE 
    public function test_php_files_contain_php_code(): void
    {
        foreach ($this->projectFiles as $file) {
            $path = $this->projectPath($file);
            if (!file_exists($path)) {
                $this->fail("File $file tidak ada.");
            }
            $content = file_get_contents($path);
            $this->assertStringContainsString('<?php', $content, "File $file tidak mengandung PHP.");
        }
    }

    private function getApiKey(): string
    {
        $key = getenv('API_KEY');
        if ($key === false || trim((string)$key) === '') {
            $configPath = dirname(__DIR__) . '/config.local.php';
            if (file_exists($configPath)) {
                $config = require $configPath;
                if (is_array($config) && isset($config['API_KEY'])) {
                    $key = $config['API_KEY'];
                }
            }
        }
        return trim((string)$key);
    }

    // c. API KEY TIDAK BOLEH KOSONG
    public function test_api_key_is_not_empty(): void
    {
        $apiKey = $this->getApiKey();
        $this->assertNotEmpty($apiKey, 'API_KEY masih kosong.');
    }

    private function callNewsData(): array
    {
        $apiKey = $this->getApiKey();
        $this->assertNotEmpty($apiKey, 'API_KEY belum di-set.');

        $url = 'https://newsdata.io/api/1/news';
        $params = [
            'apikey'   => $apiKey,
            'language' => 'id',
            'country'  => 'id',
        ];
        $fullUrl = $url . '?' . http_build_query($params);

        $ch = curl_init($fullUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->fail('Gagal menghubungi API: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$statusCode, (string)$body];
    }

    // d. RESPONSE CODE HARUS 200
    public function test_newsdata_response_code_is_200(): void
    {
        [$statusCode, $body] = $this->callNewsData();
        $this->assertSame(200, $statusCode, 'Response code harus 200, dapat: ' . $statusCode);
    }

    // e. VALID JSON RESPONSE
    public function test_newsdata_response_is_valid_json(): void
    {
        [$statusCode, $body] = $this->callNewsData();
        $this->assertSame(200, $statusCode, 'Status code bukan 200.');

        $data = json_decode($body, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Response bukan JSON valid.');
        $this->assertIsArray($data, 'JSON bukan array.');
    }
}
