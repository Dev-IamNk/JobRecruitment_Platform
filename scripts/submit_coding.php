<?php
// FILE: scripts/submit_coding.php
require_once '../config/db.php';          // db.php already calls session_start()
require_once '../config/functions.php';   // needed for redirectIfNotLoggedIn() and getUserType()
require_once '../scripts/email_helper.php'; // ✅ NEW: for email queue

redirectIfNotLoggedIn();

if (getUserType() != 'candidate') {
    header('Location: /recruitment_rpa/pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /recruitment_rpa/pages/candidate_dashboard.php');
    exit;
}

$candidate_id   = $_SESSION['user_id'];
$application_id = intval($_POST['application_id'] ?? 0);
$attempt_id     = intval($_POST['attempt_id'] ?? 0);
$job_id         = intval($_POST['job_id'] ?? 0);
$problem_ids    = $_POST['problem_ids'] ?? [];
$codes          = $_POST['codes']       ?? [];
$languages      = $_POST['languages']   ?? [];

// Basic validation
if ($application_id <= 0 || $attempt_id <= 0) {
    die("Invalid request.");
}

// Verify application ownership
$app_q = $pdo->prepare("SELECT * FROM applications WHERE id = ? AND candidate_id = ?");
$app_q->execute([$application_id, $candidate_id]);
$application = $app_q->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    die("Unauthorized.");
}

// ─────────────────────────────────────────────────────────────────────────────
// Process each coding submission
// ─────────────────────────────────────────────────────────────────────────────
$total_marks  = 0;
$scored_marks = 0;

foreach ($problem_ids as $index => $problem_id) {
    $problem_id = intval($problem_id);
    $code       = trim($codes[$index]     ?? '');
    $language   = trim($languages[$index] ?? 'python');

    // Get problem details
    $prob_q = $pdo->prepare("SELECT * FROM coding_problems WHERE id = ?");
    $prob_q->execute([$problem_id]);
    $problem = $prob_q->fetch(PDO::FETCH_ASSOC);

    if (!$problem) {
        continue;
    }

    // Marks by difficulty
    $marks = match ($problem['difficulty']) {
        'easy'   => 10,
        'medium' => 20,
        'hard'   => 30,
        default  => 10
    };

    $total_marks += $marks;

    // Auto-score by keyword matching
    $auto_score = 0;
    if (!empty($code)) {
        $auto_score = calculateCodingScore($code, $problem, $marks);
    }

    $scored_marks += $auto_score;

    // Save to coding_submissions
    $ins = $pdo->prepare("
        INSERT INTO coding_submissions 
            (attempt_id, problem_id, code, language, score, submitted_at) 
        VALUES 
            (?, ?, ?, ?, ?, NOW())
    ");
    $ins->execute([$attempt_id, $problem_id, $code, $language, $auto_score]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Score calculations
// ─────────────────────────────────────────────────────────────────────────────

// Coding percentage
$coding_percentage = $total_marks > 0
    ? round(($scored_marks / $total_marks) * 100, 2)
    : 0;

// Update coding attempt score
$upd_attempt = $pdo->prepare("
    UPDATE test_attempts 
    SET total_score  = ?,
        max_score    = ?,
        completed_at = NOW()
    WHERE id = ?
");
$upd_attempt->execute([$scored_marks, $total_marks, $attempt_id]);

// Get aptitude/technical score
$apt_q = $pdo->prepare("
    SELECT total_score, max_score 
    FROM test_attempts 
    WHERE application_id = ? AND round_type = 'aptitude_technical'
    ORDER BY started_at DESC 
    LIMIT 1
");
$apt_q->execute([$application_id]);
$apt_result = $apt_q->fetch(PDO::FETCH_ASSOC);

$apt_percentage = 0;
if ($apt_result && $apt_result['max_score'] > 0) {
    $apt_percentage = round(($apt_result['total_score'] / $apt_result['max_score']) * 100, 2);
}

// Get resume score
$resume_score = floatval($application['score'] ?? 0);

// Final combined score: Resume 40% + Aptitude/Tech 20% + Coding 40%
$final_score = round(
    ($resume_score      * 0.40) +
    ($apt_percentage    * 0.20) +
    ($coding_percentage * 0.40),
    2
);

// Update applications table
$upd_app = $pdo->prepare("
    UPDATE applications 
    SET aptitude_tech_score = ?,
        coding_score        = ?,
        final_score         = ?,
        coding_status       = 'completed',
        status              = 'test_completed'
    WHERE id = ?
");
$upd_app->execute([$apt_percentage, $coding_percentage, $final_score, $application_id]);

// ✅ NEW: Queue test-cleared email (sends interview link to candidate)
// Only send if candidate scored above 40% overall (configurable threshold)
if ($final_score >= 40) {
    queueTestClearedEmail($pdo, $application_id);
}

// ─────────────────────────────────────────────────────────────────────────────
// Redirect to success page
// ─────────────────────────────────────────────────────────────────────────────
$base = dirname(dirname($_SERVER['PHP_SELF']));
header("Location: {$base}/pages/coding_results.php?application_id={$application_id}");
exit;


// ─────────────────────────────────────────────────────────────────────────────
// Auto-scoring: keyword-based
// ─────────────────────────────────────────────────────────────────────────────
function calculateCodingScore(string $code, array $problem, int $maxMarks): float
{
    $code_lower = strtolower($code);

    // Count meaningful lines only
    $meaningful = array_filter(
        explode("\n", $code),
        function ($line) {
            $trimmed = trim($line);
            return strlen($trimmed) > 3
                && !str_starts_with($trimmed, '//')
                && !str_starts_with($trimmed, '#')
                && !str_starts_with($trimmed, '*');
        }
    );

    $line_count = count($meaningful);

    if ($line_count === 0) {
        return 0;
    }

    $score = 0;

    // Effort-based score
    if ($line_count >= 3)  $score += $maxMarks * 0.30;
    if ($line_count >= 6)  $score += $maxMarks * 0.20;
    if ($line_count >= 10) $score += $maxMarks * 0.10;

    // Topic keyword matching
    $topic_keywords = getTopicKeywords($problem['topic'] ?? '');
    $matched = 0;

    foreach ($topic_keywords as $kw) {
        if (str_contains($code_lower, strtolower($kw))) {
            $matched++;
        }
    }

    $keyword_ratio = count($topic_keywords) > 0
        ? $matched / count($topic_keywords)
        : 0;

    $score += $maxMarks * 0.40 * $keyword_ratio;

    return min(round($score, 2), $maxMarks);
}

function getTopicKeywords(string $topic): array
{
    $map = [
        'strings'   => ['for', 'while', 'return', 'length', 'char', 'string', 'index', 'reverse', 'split', 'join'],
        'arrays'    => ['for', 'while', 'return', 'array', 'list', 'index', 'append', 'push', 'length', 'max', 'min', 'sum'],
        'recursion' => ['return', 'function', 'def', 'if', 'call'],
        'sorting'   => ['for', 'swap', 'compare', 'return', 'while', 'if', 'temp'],
        'searching' => ['return', 'while', 'for', 'if', 'mid', 'low', 'high', '-1'],
    ];

    return $map[$topic] ?? ['return', 'for', 'if', 'function', 'def'];
}
?>
