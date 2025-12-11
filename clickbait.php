<?php
// =========================
// Deteksi Clickbait - Halaman Khusus
// =========================

/**
 * Fungsi untuk mendeteksi apakah judul termasuk clickbait
 * Mengembalikan array dengan hasil analisis detail
 */
function analyzeClickbait(string $title): array
{
    $results = [
        'isClickbait' => false,
        'score' => 0,
        'maxScore' => 100,
        'triggers' => [],
        'details' => []
    ];

    if (empty(trim($title))) {
        return $results;
    }

    // 1. Pattern kata-kata sensasional (bobot tinggi)
    $sensationalPatterns = [
        '/\bheboh\b/i' => ['word' => 'heboh', 'weight' => 15, 'desc' => 'Kata sensasional "heboh"'],
        '/\bwow\b/i' => ['word' => 'wow', 'weight' => 12, 'desc' => 'Ekspresi berlebihan "wow"'],
        '/\bgempar\b/i' => ['word' => 'gempar', 'weight' => 15, 'desc' => 'Kata sensasional "gempar"'],
        '/\bternyata\b/i' => ['word' => 'ternyata', 'weight' => 10, 'desc' => 'Kata pemancing rasa penasaran "ternyata"'],
        '/\bmenghebohkan\b/i' => ['word' => 'menghebohkan', 'weight' => 15, 'desc' => 'Kata sensasional "menghebohkan"'],
        '/\bmengagetkan\b/i' => ['word' => 'mengagetkan', 'weight' => 12, 'desc' => 'Kata pemancing emosi "mengagetkan"'],
        '/\btak disangka\b/i' => ['word' => 'tak disangka', 'weight' => 12, 'desc' => 'Frasa pemancing rasa penasaran'],
        '/\bbikin kaget\b/i' => ['word' => 'bikin kaget', 'weight' => 12, 'desc' => 'Frasa sensasional'],
        '/\bgak nyangka\b/i' => ['word' => 'gak nyangka', 'weight' => 12, 'desc' => 'Frasa informal sensasional'],
        '/\bviral\b/i' => ['word' => 'viral', 'weight' => 10, 'desc' => 'Kata pemancing ketertarikan "viral"'],
        '/\bsyok\b/i' => ['word' => 'syok', 'weight' => 12, 'desc' => 'Kata emosional "syok"'],
        '/\bshocking\b/i' => ['word' => 'shocking', 'weight' => 12, 'desc' => 'Kata emosional "shocking"'],
        '/\b(luar biasa|sangat mengejutkan)\b/i' => ['word' => 'luar biasa/sangat mengejutkan', 'weight' => 8, 'desc' => 'Frasa berlebihan'],
        '/\b(rahasia|tersembunyi)\b/i' => ['word' => 'rahasia/tersembunyi', 'weight' => 8, 'desc' => 'Kata pemancing rasa penasaran'],
        '/\b(terbongkar|terungkap)\b/i' => ['word' => 'terbongkar/terungkap', 'weight' => 10, 'desc' => 'Kata sensasional'],
        '/\bwajib (baca|tahu|lihat)\b/i' => ['word' => 'wajib baca/tahu/lihat', 'weight' => 15, 'desc' => 'Frasa memaksa pembaca'],
        '/\bharus (baca|tahu|lihat)\b/i' => ['word' => 'harus baca/tahu/lihat', 'weight' => 12, 'desc' => 'Frasa memaksa pembaca'],
        '/\btidak (akan )?percaya\b/i' => ['word' => 'tidak percaya', 'weight' => 12, 'desc' => 'Frasa berlebihan'],
        '/\bbikin (merinding|melongo|tercengang)\b/i' => ['word' => 'bikin merinding/melongo/tercengang', 'weight' => 14, 'desc' => 'Frasa emosional berlebihan'],
    ];

    foreach ($sensationalPatterns as $pattern => $info) {
        if (preg_match($pattern, $title)) {
            $results['score'] += $info['weight'];
            $results['triggers'][] = [
                'type' => 'sensational_word',
                'word' => $info['word'],
                'description' => $info['desc'],
                'weight' => $info['weight']
            ];
        }
    }

    // 2. Tanda baca berlebihan
    $exclamationCount = substr_count($title, '!');
    if ($exclamationCount > 0) {
        $exclamWeight = min($exclamationCount * 8, 20);
        $results['score'] += $exclamWeight;
        $results['triggers'][] = [
            'type' => 'punctuation',
            'word' => '!' . str_repeat('!', $exclamationCount - 1),
            'description' => "Tanda seru berlebihan ({$exclamationCount}x)",
            'weight' => $exclamWeight
        ];
    }

    $questionCount = substr_count($title, '?');
    if ($questionCount > 1) {
        $questWeight = min(($questionCount - 1) * 5, 15);
        $results['score'] += $questWeight;
        $results['triggers'][] = [
            'type' => 'punctuation',
            'word' => str_repeat('?', $questionCount),
            'description' => "Tanda tanya berlebihan ({$questionCount}x)",
            'weight' => $questWeight
        ];
    }

    // 3. Huruf kapital berlebihan
    $words = preg_split('/\s+/', $title);
    $capsWords = 0;
    foreach ($words as $word) {
        if (mb_strlen($word) > 2 && $word === mb_strtoupper($word) && preg_match('/[A-Z]/', $word)) {
            $capsWords++;
        }
    }
    if ($capsWords > 2) {
        $capsWeight = min($capsWords * 4, 15);
        $results['score'] += $capsWeight;
        $results['triggers'][] = [
            'type' => 'caps',
            'word' => "HURUF KAPITAL",
            'description' => "Penggunaan huruf kapital berlebihan ({$capsWords} kata)",
            'weight' => $capsWeight
        ];
    }

    // 4. Judul terlalu panjang
    $titleLength = mb_strlen($title);
    if ($titleLength > 120) {
        $lengthWeight = min(intval(($titleLength - 120) / 10) * 3, 15);
        $results['score'] += $lengthWeight;
        $results['triggers'][] = [
            'type' => 'length',
            'word' => "{$titleLength} karakter",
            'description' => "Judul terlalu panjang (>120 karakter)",
            'weight' => $lengthWeight
        ];
    }

    // 5. Angka clickbait (listicle berlebihan)
    if (preg_match('/\b(\d+)\s+(cara|tips|alasan|fakta|hal|rahasia)/i', $title, $matches)) {
        $num = intval($matches[1]);
        if ($num > 10) {
            $numWeight = 8;
            $results['score'] += $numWeight;
            $results['triggers'][] = [
                'type' => 'listicle',
                'word' => $matches[0],
                'description' => "Format listicle dengan angka tinggi",
                'weight' => $numWeight
            ];
        }
    }

    // 6. Emoji berlebihan
    $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
    preg_match_all($emojiPattern, $title, $emojiMatches);
    $emojiCount = count($emojiMatches[0]);
    if ($emojiCount > 2) {
        $emojiWeight = min($emojiCount * 4, 12);
        $results['score'] += $emojiWeight;
        $results['triggers'][] = [
            'type' => 'emoji',
            'word' => implode('', array_slice($emojiMatches[0], 0, 3)) . '...',
            'description' => "Emoji berlebihan ({$emojiCount}x)",
            'weight' => $emojiWeight
        ];
    }

    // Normalisasi skor (max 100)
    $results['score'] = min($results['score'], 100);
    
    // Tentukan apakah clickbait (threshold 30)
    $results['isClickbait'] = $results['score'] >= 30;

    // Kategorisasi
    if ($results['score'] < 20) {
        $results['category'] = 'safe';
        $results['categoryLabel'] = 'Aman';
        $results['categoryDesc'] = 'Judul terlihat informatif dan tidak sensasional.';
    } elseif ($results['score'] < 40) {
        $results['category'] = 'warning';
        $results['categoryLabel'] = 'Perlu Perhatian';
        $results['categoryDesc'] = 'Judul memiliki beberapa elemen yang perlu diperhatikan.';
    } elseif ($results['score'] < 60) {
        $results['category'] = 'suspicious';
        $results['categoryLabel'] = 'Mencurigakan';
        $results['categoryDesc'] = 'Judul mengandung cukup banyak indikator clickbait.';
    } else {
        $results['category'] = 'danger';
        $results['categoryLabel'] = 'Clickbait Tinggi';
        $results['categoryDesc'] = 'Judul sangat mungkin adalah clickbait!';
    }

    return $results;
}

// Proses input
$inputTitle = '';
$analysisResult = null;

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $inputTitle = trim($_POST['title']);
    if ($inputTitle !== '') {
        $analysisResult = analyzeClickbait($inputTitle);
    }
}

// Contoh judul untuk demo
$exampleTitles = [
    'clickbait' => [
        'HEBOH! Artis Terkenal Ternyata Menyimpan Rahasia Mengejutkan, Bikin Netizen Melongo!!',
        'Wajib Baca! 50 Cara Menghasilkan Uang yang Tidak Akan Kamu Percaya!',
        'Viral! Video Ini Bikin Merinding, Terungkap Fakta Mengagetkan!!!',
    ],
    'normal' => [
        'Pemerintah Umumkan Kebijakan Baru Terkait Subsidi Energi',
        'Hasil Pertandingan Liga Champions: Real Madrid vs Barcelona 2-1',
        'Bank Indonesia Pertahankan Suku Bunga Acuan di Level 6 Persen',
    ]
];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deteksi Clickbait - NewsHub</title>
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
            transition: all 0.2s ease;
        }

        .topbar-link:hover {
            background: #e5e7eb;
        }

        .topbar-link.active {
            background: #1d4ed8;
            color: #ffffff;
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
            padding: 24px 28px;
            color: #ffffff;
            margin-bottom: 20px;
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.35);
        }

        .hero-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .hero-subtitle {
            font-size: 14px;
            opacity: 0.95;
            line-height: 1.5;
        }

        /* INPUT CARD */
        .input-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
        }

        .input-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 10px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-field {
            width: 100%;
            padding: 14px 16px;
            font-size: 15px;
            font-family: inherit;
            background: #f9fafb;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            color: #111827;
            transition: all 0.2s ease;
            resize: none;
        }

        .input-field:focus {
            outline: none;
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
            background: #ffffff;
        }

        .input-field::placeholder {
            color: #9ca3af;
        }

        .char-count {
            position: absolute;
            bottom: 10px;
            right: 14px;
            font-size: 11px;
            color: #9ca3af;
        }

        .btn-analyze {
            width: 100%;
            padding: 14px 24px;
            margin-top: 16px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            color: #ffffff;
            background: #1d4ed8;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-analyze:hover {
            background: #1e40af;
            box-shadow: 0 8px 20px rgba(29, 78, 216, 0.3);
        }

        /* RESULT CARD */
        .result-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .result-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }

        .result-badge {
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
        }

        .result-badge.safe {
            background: #dcfce7;
            color: #166534;
        }

        .result-badge.warning {
            background: #fef9c3;
            color: #854d0e;
        }

        .result-badge.suspicious {
            background: #ffedd5;
            color: #c2410c;
        }

        .result-badge.danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* SCORE METER */
        .score-section {
            margin-bottom: 24px;
        }

        .score-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .score-text {
            font-size: 13px;
            color: #6b7280;
        }

        .score-value {
            font-size: 24px;
            font-weight: 700;
        }

        .score-value.safe { color: #166534; }
        .score-value.warning { color: #854d0e; }
        .score-value.suspicious { color: #c2410c; }
        .score-value.danger { color: #b91c1c; }

        .score-bar-bg {
            height: 10px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }

        .score-bar-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 0.8s ease;
        }

        .score-bar-fill.safe { background: linear-gradient(90deg, #22c55e, #4ade80); }
        .score-bar-fill.warning { background: linear-gradient(90deg, #eab308, #facc15); }
        .score-bar-fill.suspicious { background: linear-gradient(90deg, #f97316, #fb923c); }
        .score-bar-fill.danger { background: linear-gradient(90deg, #dc2626, #ef4444); }

        .score-desc {
            margin-top: 10px;
            font-size: 13px;
            color: #6b7280;
        }

        /* TRIGGERS LIST */
        .triggers-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .triggers-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 14px;
            color: #374151;
        }

        .trigger-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            background: #f9fafb;
            border-radius: 12px;
            margin-bottom: 10px;
        }

        .trigger-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .trigger-icon.sensational_word { background: #fee2e2; }
        .trigger-icon.punctuation { background: #ffedd5; }
        .trigger-icon.caps { background: #fef9c3; }
        .trigger-icon.length { background: #e5edff; }
        .trigger-icon.listicle { background: #dbeafe; }
        .trigger-icon.emoji { background: #fce7f3; }

        .trigger-content {
            flex: 1;
        }

        .trigger-word {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 2px;
        }

        .trigger-desc {
            font-size: 12px;
            color: #6b7280;
        }

        .trigger-weight {
            font-size: 13px;
            font-weight: 700;
            color: #b91c1c;
            padding: 4px 10px;
            background: #fee2e2;
            border-radius: 6px;
        }

        .no-triggers {
            text-align: center;
            padding: 24px;
            color: #6b7280;
        }

        .no-triggers-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        /* EXAMPLES SECTION */
        .examples-section {
            margin-top: 32px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #111827;
        }

        .examples-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .examples-column {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
        }

        .examples-column h4 {
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 14px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .examples-column.clickbait h4 { color: #b91c1c; }
        .examples-column.normal h4 { color: #166534; }

        .example-item {
            padding: 12px 14px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 10px;
            font-size: 13px;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s ease;
            line-height: 1.5;
        }

        .example-item:hover {
            background: #e5edff;
            border-color: #1d4ed8;
            transform: translateX(4px);
        }

        .example-item:last-child {
            margin-bottom: 0;
        }

        /* INFO CARD */
        .info-card {
            background: #e5edff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .info-card-title {
            font-size: 14px;
            font-weight: 600;
            color: #1d4ed8;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card-text {
            font-size: 13px;
            color: #374151;
            line-height: 1.5;
        }

        /* FOOTER */
        footer {
            text-align: center;
            font-size: 11px;
            color: #9ca3af;
            margin-top: 28px;
            margin-bottom: 10px;
        }

        footer a {
            color: #1d4ed8;
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        /* RESPONSIVE */
        @media (max-width: 640px) {
            .container {
                padding: 0 12px;
            }

            .hero {
                padding: 20px;
            }

            .hero-title {
                font-size: 20px;
            }

            .input-card,
            .result-card,
            .examples-column {
                padding: 18px;
            }
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <header class="topbar">
        <div class="topbar-title">NewsHub</div>
        <nav class="topbar-nav">
            <a href="index.php" class="topbar-link">Beranda</a>
            <a href="#" class="topbar-link">Pencarian</a>
            <a href="clickbait.php" class="topbar-link active">Deteksi Clickbait</a>
        </nav>
    </header>

    <div class="container">
        <!-- HERO -->
        <div class="hero">
            <div class="hero-title">🔍 Deteksi Clickbait</div>
            <div class="hero-subtitle">
                Analisis judul berita secara instan untuk mengetahui apakah mengandung elemen clickbait.
                Dilengkapi dengan penjelasan detail setiap indikator yang terdeteksi.
            </div>
        </div>

        <!-- INFO CARD -->
        <div class="info-card">
            <div class="info-card-title">
                💡 Cara Menggunakan
            </div>
            <div class="info-card-text">
                Masukkan judul berita yang ingin Anda analisis pada kolom di bawah, lalu klik tombol "Analisis Sekarang".
                Sistem akan mendeteksi kata-kata sensasional, tanda baca berlebihan, dan indikator clickbait lainnya.
            </div>
        </div>

        <!-- INPUT FORM -->
        <div class="input-card">
            <form method="POST" action="">
                <label class="input-label" for="title">Masukkan Judul Berita</label>
                <div class="input-wrapper">
                    <textarea 
                        class="input-field" 
                        id="title" 
                        name="title" 
                        rows="3"
                        placeholder="Contoh: HEBOH! Artis Terkenal Ternyata Menyimpan Rahasia Mengejutkan!!"
                        oninput="updateCharCount(this)"
                    ><?php echo htmlspecialchars($inputTitle, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <span class="char-count" id="charCount">0 karakter</span>
                </div>
                <button type="submit" class="btn-analyze">
                    🔍 Analisis Sekarang
                </button>
            </form>
        </div>

        <!-- RESULT -->
        <?php if ($analysisResult !== null): ?>
        <div class="result-card">
            <div class="result-header">
                <h2 class="result-title">Hasil Analisis</h2>
                <span class="result-badge <?php echo $analysisResult['category']; ?>">
                    <?php 
                    $categoryIcons = [
                        'safe' => '✅',
                        'warning' => '⚠️',
                        'suspicious' => '🔶',
                        'danger' => '🚨'
                    ];
                    echo ($categoryIcons[$analysisResult['category']] ?? '') . ' ' . $analysisResult['categoryLabel']; 
                    ?>
                </span>
            </div>

            <!-- Score Meter -->
            <div class="score-section">
                <div class="score-label">
                    <span class="score-text">Skor Clickbait</span>
                    <span class="score-value <?php echo $analysisResult['category']; ?>">
                        <?php echo $analysisResult['score']; ?>/100
                    </span>
                </div>
                <div class="score-bar-bg">
                    <div 
                        class="score-bar-fill <?php echo $analysisResult['category']; ?>" 
                        style="width: <?php echo $analysisResult['score']; ?>%"
                    ></div>
                </div>
                <p class="score-desc">
                    <?php echo $analysisResult['categoryDesc']; ?>
                </p>
            </div>

            <!-- Triggers -->
            <div class="triggers-section">
                <h3 class="triggers-title">
                    📋 Indikator Terdeteksi (<?php echo count($analysisResult['triggers']); ?>)
                </h3>
                
                <?php if (empty($analysisResult['triggers'])): ?>
                <div class="no-triggers">
                    <div class="no-triggers-icon">✅</div>
                    <p>Tidak ada indikator clickbait yang terdeteksi</p>
                </div>
                <?php else: ?>
                    <?php 
                    $icons = [
                        'sensational_word' => '💥',
                        'punctuation' => '❗',
                        'caps' => '🔠',
                        'length' => '📏',
                        'listicle' => '🔢',
                        'emoji' => '😱'
                    ];
                    foreach ($analysisResult['triggers'] as $trigger): 
                    ?>
                    <div class="trigger-item">
                        <div class="trigger-icon <?php echo $trigger['type']; ?>">
                            <?php echo $icons[$trigger['type']] ?? '⚠️'; ?>
                        </div>
                        <div class="trigger-content">
                            <div class="trigger-word">"<?php echo htmlspecialchars($trigger['word'], ENT_QUOTES, 'UTF-8'); ?>"</div>
                            <div class="trigger-desc"><?php echo htmlspecialchars($trigger['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="trigger-weight">+<?php echo $trigger['weight']; ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- EXAMPLES -->
        <section class="examples-section">
            <h3 class="section-title">📝 Contoh Judul Berita</h3>
            <p style="font-size: 13px; color: #6b7280; margin-bottom: 16px;">
                Klik salah satu contoh di bawah untuk mencobanya langsung.
            </p>
            <div class="examples-grid">
                <div class="examples-column clickbait">
                    <h4>⚠️ Contoh Clickbait</h4>
                    <?php foreach ($exampleTitles['clickbait'] as $example): ?>
                    <div class="example-item" onclick="fillExample(this)">
                        <?php echo htmlspecialchars($example, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="examples-column normal">
                    <h4>✅ Contoh Normal</h4>
                    <?php foreach ($exampleTitles['normal'] as $example): ?>
                    <div class="example-item" onclick="fillExample(this)">
                        <?php echo htmlspecialchars($example, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <footer>
            &copy; <?php echo date("Y"); ?> NewsHub • <a href="index.php">Kembali ke Beranda</a>
        </footer>
    </div>

    <script>
        // Update character count
        function updateCharCount(textarea) {
            const count = textarea.value.length;
            document.getElementById('charCount').textContent = count + ' karakter';
        }

        // Fill example
        function fillExample(element) {
            const textarea = document.getElementById('title');
            textarea.value = element.textContent.trim();
            updateCharCount(textarea);
            textarea.focus();
            
            // Scroll to form
            document.querySelector('.input-card').scrollIntoView({ 
                behavior: 'smooth',
                block: 'center'
            });
        }

        // Initial char count
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('title');
            if (textarea) {
                updateCharCount(textarea);
            }
        });
    </script>
</body>
</html>
