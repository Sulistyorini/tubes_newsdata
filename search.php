<?php
// =========================
// Konfigurasi API KEY (sama seperti index.php)
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
$searchPerformed = isset($_GET['q']) || isset($_GET['category']);

// Ambil data API hanya jika ada pencarian
$errorMessage = null;
$newsResults = [];

if ($searchPerformed && $keyword !== '') {
    $apiData = fetchNewsFromApi($keyword, $category);
    $errorMessage = $apiData['error'];
    $newsResults = $apiData['results'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencarian Berita - NewsHub</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #f8f9fd 0%, #fef4f8 100%);
            color: #2d3748;
            min-height: 100vh;
        }

        /* NAVBAR ATAS */
        .topbar {
            height: 64px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(236, 72, 153, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 4px 20px rgba(236, 72, 153, 0.08);
        }

        .topbar-title {
            font-weight: 800;
            font-size: 24px;
            background: linear-gradient(135deg, #ec4899, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        .topbar-nav {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .topbar-link {
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            color: #64748b;
            padding: 8px 16px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .topbar-link:hover {
            background: rgba(236, 72, 153, 0.1);
            color: #ec4899;
        }

        .topbar-link.active {
            background: linear-gradient(135deg, #ec4899, #8b5cf6);
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3);
        }

        .container {
            max-width: 980px;
            margin: 24px auto 40px;
            padding: 0 20px;
        }

        /* HERO PENCARIAN */
        .search-hero {
            background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 50%, #06b6d4 100%);
            border-radius: 24px;
            padding: 48px 32px;
            color: #ffffff;
            margin-bottom: 28px;
            box-shadow: 0 20px 60px rgba(236, 72, 153, 0.35);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .search-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .search-hero-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .search-hero-subtitle {
            font-size: 15px;
            opacity: 0.95;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }

        /* SEARCH BOX */
        .search-box {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(236, 72, 153, 0.12);
            margin-bottom: 24px;
            border: 2px solid rgba(236, 72, 153, 0.1);
        }

        .search-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .search-form input[type="text"] {
            flex: 1;
            min-width: 280px;
            border-radius: 16px;
            border: 2px solid #f3e8ff;
            padding: 14px 20px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .search-form input[type="text"]:focus {
            outline: none;
            border-color: #ec4899;
            box-shadow: 0 0 0 4px rgba(236, 72, 153, 0.1);
            background: #ffffff;
        }

        .search-form button {
            border-radius: 16px;
            border: none;
            padding: 14px 32px;
            font-size: 15px;
            font-weight: 700;
            background: linear-gradient(135deg, #ec4899, #8b5cf6);
            color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(236, 72, 153, 0.3);
        }

        .search-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(236, 72, 153, 0.4);
        }

        .search-form button:active {
            transform: translateY(0);
        }

        /* CATEGORY PILLS */
        .chips-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .chip {
            padding: 10px 20px;
            border-radius: 20px;
            background: #faf5ff;
            border: 2px solid #f3e8ff;
            font-size: 13px;
            font-weight: 600;
            color: #7c3aed;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .chip:hover {
            background: #f3e8ff;
            border-color: #e9d5ff;
            transform: translateY(-1px);
        }

        .chip.active {
            background: linear-gradient(135deg, #ec4899, #8b5cf6);
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3);
        }

        .search-hint {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 12px;
        }

        /* ALERT */
        .alert {
            padding: 16px 20px;
            border-radius: 16px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .alert.error {
            background: linear-gradient(135deg, #fce7f3, #fef2f2);
            color: #be123c;
            border: 2px solid #fda4af;
        }

        .alert.info {
            background: linear-gradient(135deg, #fef3c7, #ddd6fe);
            color: #6d28d9;
            border: 2px solid #e9d5ff;
        }

        /* SECTION TITLE */
        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin: 20px 0 20px;
            color: #1e293b;
        }

        .result-count {
            font-size: 15px;
            color: #94a3b8;
            font-weight: 500;
        }

        /* NEWS LIST */
        .news-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .news-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 20px 22px;
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .news-card:hover {
            box-shadow: 0 12px 40px rgba(236, 72, 153, 0.15);
            transform: translateY(-4px);
            border-color: rgba(236, 72, 153, 0.2);
        }

        .news-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .news-category-pill {
            display: inline-block;
            font-size: 11px;
            padding: 6px 12px;
            border-radius: 12px;
            background: linear-gradient(135deg, #fae8ff, #ddd6fe);
            color: #7c3aed;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .news-title {
            font-size: 17px;
            font-weight: 700;
            margin: 10px 0 0;
            line-height: 1.5;
            color: #1e293b;
        }

        .badge-clickbait {
            font-size: 11px;
            padding: 6px 14px;
            border-radius: 12px;
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
            font-weight: 700;
            white-space: nowrap;
            border: 2px solid #fca5a5;
        }

        .badge-normal {
            font-size: 11px;
            padding: 6px 14px;
            border-radius: 12px;
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #059669;
            font-weight: 700;
            white-space: nowrap;
            border: 2px solid #86efac;
        }

        .news-source {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 8px;
            font-weight: 500;
        }

        .news-desc {
            font-size: 14px;
            color: #475569;
            margin-top: 12px;
            line-height: 1.6;
        }

        .news-actions {
            margin-top: 14px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 10px;
        }

        .news-actions a {
            font-size: 14px;
            font-weight: 600;
            background: linear-gradient(135deg, #ec4899, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
        }

        .news-actions a:hover {
            opacity: 0.7;
        }

        .empty-state {
            text-align: center;
            padding: 60px 32px;
            color: #94a3b8;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.08);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .empty-state-text {
            font-size: 15px;
            color: #64748b;
        }

        footer {
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            margin-top: 40px;
            padding: 20px;
        }

        @media (max-width: 640px) {
            .container {
                padding: 0 16px;
            }

            .search-hero {
                padding: 32px 20px;
            }

            .search-hero-title {
                font-size: 26px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-form input[type="text"] {
                min-width: 100%;
            }

            .search-form button {
                width: 100%;
            }

            .topbar {
                padding: 0 16px;
            }

            .topbar-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a href="index.php" class="topbar-title">NewsHub</a>
    <nav class="topbar-nav">
        <a href="index.php" class="topbar-link">Beranda</a>
        <a href="search.php" class="topbar-link active">Pencarian</a>
        <a href="clickbait.php" class="topbar-link">Deteksi Clickbait</a>
    </nav>
</header>

<div class="container">
    <!-- HERO PENCARIAN -->
    <div class="search-hero">
        <div class="search-hero-title">🔍 Cari Berita</div>
        <div class="search-hero-subtitle">
            Temukan berita yang Anda cari dengan mudah dan cepat
        </div>
    </div>

    <!-- SEARCH BOX -->
    <div class="search-box">
        <form method="GET" action="search.php" class="search-form">
            <input 
                type="text" 
                name="q" 
                placeholder="Masukkan kata kunci pencarian..." 
                value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>"
                required
            >
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit">Cari Berita</button>
        </form>

        <div class="search-hint">
            💡 Tips: Gunakan kata kunci spesifik untuk hasil yang lebih akurat
        </div>

        <!-- Kategori Filter -->
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
                $url = 'search.php?' . http_build_query($params);
                ?>
                <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                   class="<?php echo $class; ?>">
                    <?php echo htmlspecialchars(uiCategoryLabel($cat), ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php } ?>
        </div>
    </div>

    <!-- HASIL PENCARIAN -->
    <?php if ($errorMessage): ?>
        <div class="alert error">
            ⚠️ <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($searchPerformed && $keyword !== ''): ?>
        <section>
            <h2 class="section-title">
                Hasil Pencarian 
                <span class="result-count">
                    (<?php echo count($newsResults); ?> berita ditemukan untuk "<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>")
                </span>
            </h2>

            <?php if (!$errorMessage && empty($newsResults)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📰</div>
                    <div class="empty-state-title">Tidak ada hasil ditemukan</div>
                    <p class="empty-state-text">
                        Coba gunakan kata kunci lain atau ubah filter kategori
                    </p>
                </div>
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
                                <div style="flex: 1;">
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
                                        <span class="badge-normal">✓ Normal</span>
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
    <?php else: ?>
        <div class="alert info">
            ℹ️ Masukkan kata kunci untuk mulai mencari berita
        </div>
        
        <div class="empty-state">
            <div class="empty-state-icon">🔎</div>
            <div class="empty-state-title">Siap untuk mencari berita?</div>
            <p class="empty-state-text">
                Gunakan form pencarian di atas untuk menemukan berita yang Anda inginkan
            </p>
        </div>
    <?php endif; ?>

    <footer>
        &copy; <?php echo date("Y"); ?> NewsHub • Dibuat dengan PHP &amp; NewsData.io API
    </footer>
</div>
</body>
</html>