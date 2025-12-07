<?php

use PHPUnit\Framework\TestCase;

/**
 * Test untuk integrasi NewsData.io dan file index.php
 *
 * Test yang dicek:
 * 1. File index.php harus ada
 * 2. Syntax index.php valid (php -l)
 * 3. API Key tidak boleh kosong
 * 4. Response dari API harus JSON valid
 * 5. HTTP status code dari API harus 200
 */
class NewsDataTest extends TestCase
{
    private string $endpoint = 'https://newsdata.io/api/1/news';
    private string $apiKey = '';

    private static ?string $rawResponse = null;
    private static ?int $httpStatus = null;

    protected function setUp(): void
    {
        $this->apiKey = $this->loadApiKey();
    }

    /**
     * Ambil API key dari ENV atau config.local.php
     */
    private function loadApiKey(): string
    {
        // 1) Dari environment (GitHub Actions / server)
        $apiKey = getenv('API_KEY') ?: '';

        // 2) Dari config lokal (developer)
        if ($apiKey === '') {
            $configPath = __DIR__ . '/../config.local.php';
            if (file_exists($configPath)) {
                $localConfig = require $configPath;
                if (is_array($localConfig) && !empty($localConfig['API_KEY'])) {
                    $apiKey = $localConfig['API_KEY'];
                }
            }
        }

        return $apiKey;
    }

    // =========================================
    // 1. File index.php harus ada
    // =========================================
    public function testIndexFileExists(): void
    {
        $path = __DIR__ . '/../index.php';
        $this->assertFileExists($path, 'File index.php tidak ditemukan di root project.');
    }

    // =========================================
    // 2. Syntax index.php harus valid
    //    (php -l index.php)
    // =========================================
    public function testIndexPhpHasValidSyntax(): void
    {
        $file = __DIR__ . '/../index.php';
        $cmd  = 'php -l ' . escapeshellarg($file);

        $output   = [];
        $exitCode = 0;

        exec($cmd, $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            "Terdapat error syntax di index.php:\n" . implode("\n", $output)
        );
    }

    // =========================================
    // 3. API Key tidak boleh kosong
    // =========================================
    public function testApiKeyIsNotEmpty(): void
    {
        $this->assertNotSame(
            '',
            $this->apiKey,
            "API_KEY kosong. Set ENV API_KEY (GitHub Secret) atau buat config.local.php di lokal."
        );
    }

    /**
     * Panggil API sekali saja, simpan hasilnya di static property
     * agar bisa dipakai oleh beberapa test (hemat rate-limit).
     */
    private function callApiOnce(): void
    {
        if (self::$rawResponse !== null && self::$httpStatus !== null) {
            return;
        }

        if ($this->apiKey === '') {
            $this->markTestSkipped('API key tidak di-set, melewati test HTTP.');
        }

        $url = $this->endpoint . '?' . http_build_query([
            'apikey'   => $this->apiKey,
            'q'        => 'indonesia',
            'language' => 'id',
            'country'  => 'id',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);

        $response = curl_exec($ch);

        $this->assertNotFalse(
            $response,
            'Curl gagal dieksekusi: ' . curl_error($ch)
        );

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        self::$rawResponse = $response;
        self::$httpStatus  = $statusCode;
    }

    // =========================================
    // 4. Response harus JSON valid
    // =========================================
    public function testApiReturnsValidJson(): void
    {
        $this->callApiOnce();

        $this->assertNotNull(self::$rawResponse, 'Response kosong.');

        $json = json_decode(self::$rawResponse, true);

        $this->assertIsArray($json, 'Response bukan JSON yang valid.');
        $this->assertArrayHasKey('status', $json, 'JSON tidak memiliki field "status".');
    }

    // =========================================
    // 5. HTTP Response Code harus 200
    // =========================================
    public function testApiResponseCodeIs200(): void
    {
        $this->callApiOnce();

        $this->assertSame(
            200,
            self::$httpStatus,
            "HTTP status code tidak 200, melainkan " . self::$httpStatus
        );
    }
}
