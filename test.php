<?php

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

// Data ujicoba
$testTitles = [
    'HEBOH! Artis Terkenal Ternyata Menyimpan Rahasia Mengejutkan, Bikin Netizen Melongo!!',
    'Pemerintah Umumkan Kebijakan Baru Terkait Subsidi Energi',
    'Wajib Baca! 50 Cara Menghasilkan Uang yang Tidak Akan Kamu Percaya!',
    'Viral! Video Ini Bikin Merinding, Terungkap Fakta Mengagetkan!!!'
];

// Menjalankan tes
echo "<h1>Hasil Tes Deteksi Clickbait</h1>";
echo "<table border='1' cellpadding='10'>";
echo "<thead><tr><th>Judul</th><th>Skor</th><th>Kategori</th><th>Status</th></tr></thead>";
echo "<tbody>";

foreach ($testTitles as $title) {
    $result = analyzeClickbait($title);
    
    // Styling
    $color = 'green';
    if ($result['category'] == 'danger') $color = 'red';
    if ($result['category'] == 'suspicious') $color = 'orange';
    if ($result['category'] == 'warning') $color = '#d4d400';

    echo "<tr>";
    echo "<td>" . htmlspecialchars($title) . "</td>";
    echo "<td>" . $result['score'] . "</td>";
    echo "<td style='color:$color; font-weight:bold'>" . $result['categoryLabel'] . "</td>";
    echo "<td>" . ($result['isClickbait'] ? 'CLICKBAIT' : 'AMAN') . "</td>";
    echo "</tr>";
}

echo "</tbody></table>";
