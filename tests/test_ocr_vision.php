<?php
/**
 * =============================================================================
 * EduRadar THCS — Computer Vision & OCR Fuzzy Pipeline Test (test_ocr_vision.php)
 * Scenarios: Image deskew, noise filtering simulation, UTF-8 Levenshtein diacritics,
 *            fuzzy matching across handwriting distortions.
 * =============================================================================
 */

require_once __DIR__ . '/../includes/ocr_pipeline.php';

$passed = 0;
$failed = 0;

function assert_vision(string $title, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] $title\n";
    } else {
        $failed++;
        echo "  [FAIL] $title: $detail\n";
    }
}

echo "========================================================================\n";
echo "  EDURADAR THCS — COMPUTER VISION & OCR FUZZY PIPELINE TEST SUITE\n";
echo "========================================================================\n\n";

// --- SUITE 1: Vietnamese Text Normalization & Tone Stripping ---
echo "--- SUITE 1: Vietnamese Normalization & Tone Stripping ---\n";

$raw1 = "  NGUYỄN   Hoàng   LONG  ";
$norm1 = normalize_vietnamese_text($raw1, false);
assert_vision("Normalization: Whitespace & Case", $norm1 === "nguyễn hoàng long", "Got: '$norm1'");

$toneFree = normalize_vietnamese_text("Trần Thị Mai Anh", true);
assert_vision("Tone Stripping: Diacritics Removed", $toneFree === "tran thi mai anh", "Got: '$toneFree'");

$rawPunct = "Lê - Quốc * Bảo! (9A1)";
$cleanPunct = trim(preg_replace('/[^a-zA-Z0-9\s\p{L}]/u', ' ', $rawPunct));
$cleanPunct = preg_replace('/\s+/', ' ', $cleanPunct);
assert_vision("Sanitization: Special Characters Filtered", $cleanPunct === "Lê Quốc Bảo 9A1", "Got: '$cleanPunct'");


// --- SUITE 2: Multibyte UTF-8 Levenshtein Distance ---
echo "\n--- SUITE 2: Multibyte UTF-8 Levenshtein Distance ---\n";

// In multibyte UTF-8, 'Đạt' vs 'Dat': 'Đ'!='D' (1), 'ạ'!='a' (1) => Exactly 2 character edits
$d1 = mb_levenshtein("Đạt", "Dat");
assert_vision("Multibyte DP: 2 Character Accent Differences", $d1 === 2, "Expected 2, got $d1");

// Single accent change: 'Văn' vs 'Van' => 'ă' != 'a' => exactly 1 edit
$d_single = mb_levenshtein("Văn", "Van");
assert_vision("Multibyte DP: Single Character Accent Difference", $d_single === 1, "Expected 1, got $d_single");

// Transposition of 2 characters: 'Nguyễn' vs 'Ngyuễn'
$d2 = mb_levenshtein("Nguyễn", "Ngyuễn");
assert_vision("Multibyte DP: Transposition Distance", $d2 <= 2, "Expected <= 2, got $d2");

// Exact match
$d3 = mb_levenshtein("Phạm Minh Đức", "Phạm Minh Đức");
assert_vision("Multibyte DP: Identical Match Distance 0", $d3 === 0, "Expected 0, got $d3");


// --- SUITE 3: Weighted Similarity & Diacritics Recovery ---
echo "\n--- SUITE 3: Weighted Similarity & Diacritics Recovery ---\n";

// Completely unaccented vs accented
$sim1 = calculate_levenshtein_similarity("Nguyen Hoang Long", "Nguyễn Hoàng Long");
assert_vision("Similarity: Unaccented to Accented >= 90%", $sim1['similarity_percent'] >= 90.0, "Got: {$sim1['similarity_percent']}%");

// Exact match
$sim2 = calculate_levenshtein_similarity("Trần Thị Mai Anh", "Trần Thị Mai Anh");
assert_vision("Similarity: Exact Match 100%", $sim2['similarity_percent'] === 100.0, "Got: {$sim2['similarity_percent']}%");

// Minor handwriting typo: 'Ngo Bao Chau' vs 'Ngô Bảo Châu'
$sim3 = calculate_levenshtein_similarity("Ngo Bao Chau", "Ngô Bảo Châu");
assert_vision("Similarity: Diacritics Tolerance >= 90%", $sim3['similarity_percent'] >= 90.0, "Got: {$sim3['similarity_percent']}%");


// --- SUITE 4: Class Roster Fuzzy Matching Engine ---
echo "\n--- SUITE 4: Class Roster Fuzzy Matching Engine ---\n";

$roster = [
    ['id' => 101, 'full_name' => 'Nguyễn Hoàng Long', 'student_code' => 'HS01'],
    ['id' => 102, 'full_name' => 'Trần Thị Mai Anh',   'student_code' => 'HS02'],
    ['id' => 103, 'full_name' => 'Lê Quốc Bảo',        'student_code' => 'HS03'],
    ['id' => 104, 'full_name' => 'Phạm Minh Đức',      'student_code' => 'HS04'],
    ['id' => 105, 'full_name' => 'Võ Thị Kim Ngân',    'student_code' => 'HS05'],
    ['id' => 106, 'full_name' => 'Đỗ Đăng Khoa',       'student_code' => 'HS06']
];

// Test 4.1: OCR text with missing tones
$match1 = match_student_fuzzy("Nguyen Hoang Long", $roster);
assert_vision("Roster Match: Missing Accents Recovery", ($match1['student_id'] ?? null) === 101, "Matched ID: " . ($match1['student_id'] ?? 'none'));

// Test 4.2: OCR text with swapped character in handwriting
$match2 = match_student_fuzzy("Tran Thi May Anh", $roster);
assert_vision("Roster Match: Handwriting Phonetic Typo", ($match2['student_id'] ?? null) === 102, "Matched ID: " . ($match2['student_id'] ?? 'none'));

// Test 4.3: Vietnamese 'Đ' / 'đ' recognition
$match3 = match_student_fuzzy("Do Dang Khoa", $roster);
assert_vision("Roster Match: 'Đ' to 'D' Transliteration Match", ($match3['student_id'] ?? null) === 106, "Matched ID: " . ($match3['student_id'] ?? 'none'));

// Test 4.4: Low similarity rejection threshold (< 72%)
$match4 = match_student_fuzzy("Hoàng Thùy Linh", $roster);
assert_vision("Roster Match: Out-of-Roster Rejection", $match4 === null, "Expected null, matched: " . ($match4['student_id'] ?? 'none'));


// --- SUITE 5: Vision Condition Simulation (Angles, Lighting, Noise) ---
echo "\n--- SUITE 5: Vision Condition Simulation ---\n";

$bm_optimal = evaluate_ocr_condition_benchmark('printed', 500, 0.0, $roster);
assert_vision("Vision Bench: Optimal Angle 90deg Accuracy >= 99%", $bm_optimal['simulated_raw_accuracy'] >= 99.0, "Got: {$bm_optimal['simulated_raw_accuracy']}%");

$bm_tilted = evaluate_ocr_condition_benchmark('printed', 500, 30.0, $roster);
assert_vision("Vision Bench: Tilted 30deg Match Rate == 100%", $bm_tilted['fuzzy_match_rate_percent'] === 100.0, "Got: {$bm_tilted['fuzzy_match_rate_percent']}%");

$bm_handwritten_dim = evaluate_ocr_condition_benchmark('handwritten', 150, 45.0, $roster);
assert_vision("Vision Bench: Adverse Conditions Match Rate >= 90%", $bm_handwritten_dim['fuzzy_match_rate_percent'] >= 90.0, "Got: {$bm_handwritten_dim['fuzzy_match_rate_percent']}%");

echo "\n========================================================================\n";
echo "  OCR VISION TEST SUMMARY: $passed PASSED, $failed FAILED\n";
echo "========================================================================\n";

exit($failed > 0 ? 1 : 0);
