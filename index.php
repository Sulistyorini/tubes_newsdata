<?php
// =========================
// Konfigurasi API KEY
// =========================

// 1) Coba ambil dari environment (GitHub Actions / server)
$apiKey = getenv('API_KEY') ?: '';

// 2) Kalau kosong, coba ambil dari config lokal (hanya di laptop developer)
$configLocalPath = __DIR__ . '/config.local.php';

if (!$apiKey && file_exists($configLocalPath)) {
    $localConfig = require $configLocalPath;
    if (is_array($localConfig) && !empty($localConfig['API_KEY'])) {
        $apiKey = $localConfig['API_KEY'];
    }
}

// 3) Kalau tetap kosong → error
if (!$apiKey) {
    die("API Key tidak boleh kosong. Set ENV API_KEY atau buat config.local.php.\n");
}

// =========================
// Fungsi ambil berita dari NewsData.io
// =========================
function fetchNewsFromApi(?string $keyword, string $categoryUi): array
{
    global $apiKey;

    $params = [
        'apikey'   => $apiKey,
        'language' => 'id',
        'country'  => 'id'
    ];

    if ($keyword !== null && $keyword !== '') {
        $params['q'] = $keyword;
    }

    // Map kategori UI ke kategori API
    $mapCategory = [
        'politik'   => 'politics',
        'teknologi' => 'technology',
        'olahraga'  => 'sports',
        'bisnis'    => 'business'
    ];

    if ($categoryUi !== 'all' && isset($mapCategory[$categoryUi])) {
        $params['category'] = $mapCategory[$categoryUi];
    }

    $url = "https://newsdata.io/api/1/news?" . http_build_query($params);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20
    ]);

    $response = curl_exec($curl);

    if ($response === false) {
        $err = curl_error($curl);
        curl_close($curl);
        return [
            'error'   => "Gagal menghubungi API: $err",
            'results' => []
        ];
    }

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($status !== 200) {
        return [
            'error'   => "API mengembalikan HTTP Status $status",
            'results' => []
        ];
    }

    $json = json_decode($response, true);

    if (!is_array($json) || !isset($json['results'])) {
        return [
            'error'   => 'Response dari API tidak sesuai format yang diharapkan.',
            'results' => []
        ];
    }

    return [
        'error'   => null,
        'results' => $json['results']
    ];
}

// =========================
// Deteksi judul clickbait
// =========================
function isClickbait(string $title): bool
{
    $patterns = [
        '/heboh/i',
        '/wow/i',
        '/gempar/i',
        '/ternyata/i',
        '/menghebohkan/i',
        '/mengagetkan/i',
        '/tak disangka/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $title)) {
            return true;
        }
    }

    if (mb_strlen($title) > 110) {
        return true;
    }

    if (substr_count($title, '!') > 1) {
        return true;
    }

    return false;
}

// =========================
// Label kategori di UI
// =========================
function uiCategoryLabel(string $ui): string
{
    $labels = [
        'all'       => 'Semua',
        'politik'   => 'Politik',
        'teknologi' => 'Teknologi',
        'olahraga'  => 'Olahraga',
        'bisnis'    => 'Bisnis',
    ];
    return $labels[$ui] ?? $ui;
}

// =========================
// Proses input GET
// =========================
$category = $_GET['category'] ?? 'all';
$allowedCategories = ['all', 'politik', 'teknologi', 'olahraga', 'bisnis'];
if (!in_array($category, $allowedCategories, true)) {
    $category = 'all';
}

$keyword = trim($_GET['q'] ?? '');

// Jika tidak ada keyword dan kategori = all → default “Indonesia”
$effectiveKeyword = $keyword === '' && $category === 'all'
    ? 'Indonesia'
    : $keyword;

// Ambil data API
$apiData      = fetchNewsFromApi($effectiveKeyword, $category);
$errorMessage = $apiData['error'];
$newsResults  = $apiData['results'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>NewsHub - Berita Terkini</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        /* NAVBAR ATAS */
        .topbar {
            height: 56px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .topbar-title {
            font-weight: 700;
            font-size: 20px;
            color: #1d4ed8;
        }

        .topbar-nav {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-link {
            font-size: 14px;
            text-decoration: none;
            color: #4b5563;
            padding: 6px 12px;
            border-radius: 999px;
        }

        .topbar-link:hover {
            background: #e5e7eb;
        }

        .container {
            max-width: 960px;
            margin: 16px auto 32px;
            padding: 0 16px;
        }

        /* HERO */
        .hero {
            background: linear-gradient(90deg, #1d4ed8, #2563eb);
            border-radius: 18px;
            padding: 20px 22px;
            color: #ffffff;
            margin-bottom: 16px;
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.35);
        }

        .hero-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .hero-subtitle {
            font-size: 13px;
            opacity: 0.95;
        }

        /* CATEGORY PILLS */
        .chips-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 16px 0 18px;
        }

        .chip {
            padding: 8px 16px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            font-size: 13px;
            color: #374151;
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        }

        .chip.active {
            background: #1d4ed8;
            color: #ffffff;
            border-color: #1d4ed8;
            font-weight: 600;
        }

        /* SEARCH */
        .search-box {
            background: #ffffff;
            border-radius: 14px;
            padding: 10px 12px;
            box-shadow: 0 2px 7px rgba(15, 23, 42, 0.06);
            margin-bottom: 14px;
        }

        .search-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .search-form input[type="text"] {
            flex: 1;
            border-radius: 999px;
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            font-size: 14px;
        }

        .search-form button {
            border-radius: 999px;
            border: none;
            padding: 8px 16px;
            font-size: 14px;
            background: #1d4ed8;
            color: #ffffff;
            cursor: pointer;
        }

        .search-form button:hover {
            background: #1e40af;
        }

        .search-hint {
            margin-top: 4px;
            font-size: 11px;
            color: #9ca3af;
        }

        /* ALERT */
        .alert {
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .alert.error {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* NEWS LIST */
        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin: 8px 0 12px;
        }

        .news-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .news-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 14px 16px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
        }

        .news-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .news-category-pill {
            display: inline-block;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #e5edff;
            color: #1d4ed8;
            font-weight: 600;
        }

        .news-title {
            font-size: 15px;
            font-weight: 600;
            margin: 6px 0 0;
        }

        .badge-clickbait {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #fee2e2;
            color: #b91c1c;
            font-weight: 600;
        }

        .badge-normal {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-weight: 600;
        }

        .news-source {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .news-desc {
            font-size: 13px;
            color: #374151;
            margin-top: 8px;
        }

        .news-actions {
            margin-top: 10px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .news-actions a {
            font-size: 13px;
            color: #2563eb;
            text-decoration: none;
        }

        .news-actions a:hover {
            text-decoration: underline;
        }

        .empty-text {
            font-size: 13px;
            color: #6b7280;
        }

        footer {
            text-align: center;
            font-size: 11px;
            color: #9ca3af;
            margin-top: 28px;
            margin-bottom: 10px;
        }

        @media (max-width: 640px) {
            .container {
                padding: 0 12px;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="topbar-title">NewsHub</div>
    <nav class="topbar-nav">
        <a href="#beranda" class="topbar-link">Beranda</a>
        <a href="#pencarian" class="topbar-link">Pencarian</a>
        <a href="#clickbait" class="topbar-link">Deteksi Clickbait</a>
    </nav>
</header>

<div class="container">
    <!-- BERANDA / HERO -->
    <section id="beranda">
        <div class="hero">
            <div class="hero-title">Berita Terkini</div>
            <div class="hero-subtitle">
                Dapatkan informasi terbaru dari berbagai sumber terpercaya.
            </div>
        </div>

        <!-- Kategori -->
        <div class="chips-row">
            <?php
            $cats = ['all', 'politik', 'teknologi', 'olahraga', 'bisnis'];
            foreach ($cats as $cat) {
                $isActive = $cat === $category;
                $class = 'chip' . ($isActive ? ' active' : '');
                $params = ['category' => $cat];
                if ($keyword !== '') {
                    $params['q'] = $keyword;
                }
                $url = '?' . http_build_query($params);
                ?>
                <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                   class="<?php echo $class; ?>">
                    <?php echo htmlspecialchars(uiCategoryLabel($cat), ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php } ?>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert error">
                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <!-- HASIL BERITA -->
        <section>
            <h2 class="section-title">
                Hasil Berita (<?php echo htmlspecialchars(uiCategoryLabel($category), ENT_QUOTES, 'UTF-8'); ?>)
            </h2>

            <?php if (!$errorMessage && empty($newsResults)): ?>
                <p class="empty-text">Tidak ada berita ditemukan. Coba kata kunci atau kategori lain.</p>
            <?php endif; ?>

            <?php if (!$errorMessage && !empty($newsResults)): ?>
                <div class="news-list">
                    <?php foreach ($newsResults as $news): ?>
                        <?php
                        $title   = $news['title'] ?? 'Tanpa judul';
                        $source  = $news['source_id'] ?? 'Sumber tidak diketahui';
                        $link    = $news['link'] ?? '#';
                        $pubDate = $news['pubDate'] ?? '';
                        $desc    = $news['description'] ?? '';

                        $catApi = '';
                        if (isset($news['category'])) {
                            if (is_array($news['category'])) {
                                $catApi = implode(', ', $news['category']);
                            } else {
                                $catApi = (string) $news['category'];
                            }
                        }

                        $isCb = isClickbait($title);
                        ?>
                        <article class="news-card">
                            <div class="news-card-header">
                                <div>
                                    <?php if ($catApi !== ''): ?>
                                        <span class="news-category-pill">
                                            <?php echo htmlspecialchars($catApi, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <h3 class="news-title">
                                        <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
                                    </h3>
                                    <div class="news-source">
                                        <?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($pubDate): ?>
                                            · <span><?php echo htmlspecialchars($pubDate, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($isCb): ?>
                                        <span class="badge-clickbait">⚠ Clickbait</span>
                                    <?php else: ?>
                                        <span class="badge-normal">Normal</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($desc): ?>
                                <p class="news-desc">
                                    <?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>

                            <div class="news-actions">
                                <a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>"
                                   target="_blank" rel="noopener noreferrer">
                                    Baca selengkapnya &raquo;
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </section>

    <footer>
        &copy; <?php echo date("Y"); ?> NewsHub • Dibuat dengan PHP &amp; NewsData.io API
    </footer>
</div>
</body>
</html>
