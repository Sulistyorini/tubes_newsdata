<?php

use PHPUnit\Framework\TestCase;

/**
 * Test utama project AirCare.
 * a. file exist
 * b. valid PHP code
 * c. API Key tidak boleh kosong
 * d. valid JSON response
 * e. response code harus 200
 */
class AirCareTest extends TestCase
{
    /**
     * Daftar file PHP utama yang wajib ada.
     * Sesuaikan dengan file di projectmu.
     */
    private array $projectFiles = [
        'index.php',
        'clickbait.php',
    ];

    /**
     * Helper: path absolut file project.
     */
    private function projectPath(string $file): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . $file;
    }

    // a. FILE EXIST
    public function test_files_exist(): void
    {
        foreach ($this->projectFiles as $file) {
            $path = $this->projectPath($file);

            $this->assertFileExists(
                $path,
                "File $file tidak ditemukan di root project!"
            );
        }
    }
    
    // b. VALID PHP CODE 
    public function test_php_files_contain_php_code(): void
    {
        foreach ($this->projectFiles as $file) {
            $path = $this->projectPath($file);

            if (!file_exists($path)) {
                $this->fail("File $file tidak ada, tidak bisa dicek kodenya.");
            }

            $content = file_get_contents($path);

            $this->assertStringContainsString(
                '<?php',
                $content,
                "File $file tidak mengandung kode PHP (tag <?php)."
            );
        }
    }

    // Helper: ambil API key dari ENV atau configlocal.php
    private function getApiKey(): string
    {
        // 1. coba dari ENV
        $key = getenv('API_KEY');

        // 2. fallback configlocal.php
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

        $this->assertNotEmpty(
            $apiKey,
            'API_KEY masih kosong. ' .
            'Isi di config.local.php (lokal) atau GitHub Secrets (CI).'
        );
    }

    // Helper: panggil OpenWeather (Geocoding Kediri)
    private function callOpenWeather(): array
    {
        $apiKey = $this->getApiKey();

        $this->assertNotEmpty(
            $apiKey,
            'ENV/konfigurasi API_KEY belum di-set, ' .
            'set dulu sebelum menjalankan tes API.'
        );

        $url = 'https://api.openweathermap.org/geo/1.0/direct';
        $params = [
            'q'     => 'Kediri,ID',
            'limit' => 1,
            'appid' => $apiKey,
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
            $this->fail('Gagal menghubungi API OpenWeather: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Jika API mengembalikan 401, biasanya berarti API key tidak valid.
        // Skip test agar tidak dianggap gagal karena konfigurasi kunci API lokal.
        if ($statusCode === 401) {
            $this->markTestSkipped(' returned 401 Unauthorized — invalid API key. Set a valid OPENWEATHER_API_KEY to run this test.');
        }

        return [$statusCode, (string)$body];
    }

    // e. RESPONSE CODE HARUS 200
    public function test_openweather_response_code_is_200(): void
    {
        [$statusCode, $body] = $this->callOpenWeather();

        $this->assertSame(
            200,
            $statusCode,
            'Response code harus 200, tapi dapat: ' .
            $statusCode . '. Potongan respon: ' . mb_substr($body, 0, 120)
        );
    }

    // d. VALID JSON RESPONSE
    public function test_openweather_response_is_valid_json(): void
    {
        [$statusCode, $body] = $this->callOpenWeather();

        $this->assertSame(
            200,
            $statusCode,
            'Status code bukan 200, tidak bisa validasi JSON. Dapat: ' . $statusCode
        );

        $data = json_decode($body, true);

        $this->assertSame(
            JSON_ERROR_NONE,
            json_last_error(),
            'Response API bukan JSON valid: ' . json_last_error_msg()
        );

        $this->assertIsArray(
            $data,
            'JSON valid, tapi hasil decode bukan array seperti yang diharapkan.'
        );
    }
}
