<?php
require_once '../config/db.php';
require_once '../scripts/generate_test.php';

redirectIfNotLoggedIn();

if (getUserType() != 'candidate') {
    header('Location: login.php');
    exit;
}

$application_id = intval($_GET['app_id'] ?? 0);
$candidate_id   = $_SESSION['user_id'];

if (!$application_id) {
    header('Location: candidate_dashboard.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.*, j.title as job_title, j.id as job_id 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE a.id = ? AND a.candidate_id = ?
");
$stmt->execute([$application_id, $candidate_id]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    die("Application not found or doesn't belong to you.");
}

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM test_configs WHERE job_id = ?");
$stmt->execute([$application['job_id']]);
$test_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($test_count == 0) {
    die("No test configured for this job. Please contact the recruiter.");
}

$stmt = $pdo->prepare("
    SELECT * FROM test_attempts 
    WHERE application_id = ? AND round_type = 'aptitude_technical'
    ORDER BY started_at DESC LIMIT 1
");
$stmt->execute([$application_id]);
$existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_attempt && $existing_attempt['status'] == 'completed') {
    $stmt2 = $pdo->prepare("SELECT coding_status FROM applications WHERE id = ?");
    $stmt2->execute([$application_id]);
    $app_status = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($app_status && $app_status['coding_status'] === 'completed') {
        die("You have already completed both rounds. Please check your dashboard.");
    } else {
        header("Location: coding_test.php?application_id=" . $application_id);
        exit();
    }
}

$test_data = generateTest($application_id);

if (isset($test_data['error'])) {
    die("Error generating test: " . $test_data['error']);
}

if (!$existing_attempt) {
    $stmt = $pdo->prepare("SELECT id FROM test_configs WHERE job_id = ? LIMIT 1");
    $stmt->execute([$application['job_id']]);
    $test_config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($test_config) {
        $attempt_id = createTestAttempt($application_id, $test_config['id']);
        foreach ($test_data['test_structure'] as $section) {
            saveTestQuestions($attempt_id, $section['questions']);
        }
    } else {
        die("Test configuration not found.");
    }
}

$test_json = json_encode($test_data);
if ($test_json === false) {
    die("Failed to load test data. Please try again.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Assessment — <?php echo htmlspecialchars($application['job_title']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:         #0d0d0f;
            --surface:    #141418;
            --surface-2:  #1a1a1f;
            --border:     rgba(255,255,255,0.07);
            --accent:     #c8a96e;
            --accent-dim: rgba(200,169,110,0.1);
            --text:       #f0ece4;
            --muted:      #7a7670;
            --green:      #5cad82;
            --green-dim:  rgba(92,173,130,0.1);
            --red:        #e05c5c;
            --red-dim:    rgba(224,92,92,0.1);
        }

        html, body {
            min-height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-weight: 300;
            overflow-x: hidden;
        }

        body {
            background-image:
                radial-gradient(ellipse 70% 40% at 80% 5%, rgba(200,169,110,0.05) 0%, transparent 55%),
                radial-gradient(ellipse 40% 60% at 5% 95%, rgba(80,60,130,0.04) 0%, transparent 55%);
        }

        body::before {
            content: '';
            position: fixed; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none; z-index: 0;
        }

        /* ── SUBMITTING OVERLAY ── */
        .submitting-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.82);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
            padding: 24px;
            backdrop-filter: blur(6px);
        }
        .submitting-overlay.show { display: flex; }

        .overlay-spinner {
            width: 52px; height: 52px;
            border: 2px solid rgba(200,169,110,0.2);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-bottom: 24px;
        }

        .overlay-title {
            font-family: 'Playfair Display', serif;
            font-size: 22px; font-weight: 400;
            color: var(--text); margin-bottom: 8px;
        }
        .overlay-msg {
            font-size: 13px; color: var(--muted); line-height: 1.6;
        }

        /* ── NAV / HEADER ── */
        .test-header {
            position: sticky; top: 0; z-index: 100;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .header-content {
            max-width: 1200px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 28px; height: 62px; gap: 16px;
        }

        .header-left { display: flex; align-items: center; gap: 14px; }

        .logo-mark {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 15px; font-weight: 600; color: #0d0d0f;
            flex-shrink: 0;
        }

        .header-titles {}
        .header-titles .main-title {
            font-family: 'Playfair Display', serif;
            font-size: 15px; font-weight: 400; color: var(--text);
            line-height: 1.2;
        }
        .header-titles .sub-title {
            font-size: 11px; color: var(--muted);
            letter-spacing: 0.04em; margin-top: 2px;
        }

        /* Timer */
        .timer-wrap {
            display: flex; align-items: center; gap: 10px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 16px;
            transition: border-color 0.3s, background 0.3s;
        }
        .timer-wrap.warning {
            border-color: rgba(224,92,92,0.4);
            background: rgba(224,92,92,0.08);
            animation: pulse 1s ease-in-out infinite;
        }
        .timer-icon { font-size: 13px; }
        .timer-display {
            font-family: 'DM Mono', monospace;
            font-size: 18px; letter-spacing: 0.06em;
            color: var(--accent);
        }
        .timer-wrap.warning .timer-display { color: #e87878; }
        .timer-label {
            font-size: 10px; color: var(--muted);
            letter-spacing: 0.08em; text-transform: uppercase;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.65; }
        }

        /* ── LAYOUT ── */
        .page-wrap {
            position: relative; z-index: 1;
            display: grid;
            grid-template-columns: 1fr 240px;
            gap: 24px;
            max-width: 1200px; margin: 0 auto;
            padding: 32px 24px 72px;
            align-items: start;
        }

        /* ── SECTION HEADER ── */
        .section-banner {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px 24px;
            margin-bottom: 20px;
            position: relative; overflow: hidden;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        .section-banner::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: var(--accent); opacity: 0.5;
        }

        .eyebrow {
            font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--accent); margin-bottom: 6px;
        }

        .section-name {
            font-family: 'Playfair Display', serif;
            font-size: 18px; font-weight: 400; color: var(--text);
            margin-bottom: 14px;
        }

        /* Progress */
        .progress-row {
            display: flex; align-items: center; gap: 12px;
        }
        .progress-bar-bg {
            flex: 1; height: 3px;
            background: rgba(255,255,255,0.05); border-radius: 2px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%; width: 0%;
            background: linear-gradient(90deg, var(--accent), #e8c98e);
            border-radius: 2px;
            transition: width 0.4s cubic-bezier(0.22,1,0.36,1);
        }
        .progress-label {
            font-family: 'DM Mono', monospace;
            font-size: 11px; color: var(--muted); white-space: nowrap;
        }

        /* ── QUESTION CARD ── */
        .question-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px; overflow: hidden;
            margin-bottom: 20px;
            display: none;
            animation: fadeUp 0.35s cubic-bezier(0.22,1,0.36,1) both;
        }
        .question-card.active { display: block; }

        .question-meta {
            padding: 18px 24px 0;
            font-size: 10px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted);
        }

        .question-text {
            padding: 14px 24px 22px;
            font-family: 'Playfair Display', serif;
            font-size: 17px; font-weight: 400; line-height: 1.65;
            color: var(--text);
        }

        /* Options */
        .options {
            display: flex; flex-direction: column; gap: 0;
            border-top: 1px solid var(--border);
        }

        .option {
            display: flex; align-items: center; gap: 16px;
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.18s;
            position: relative;
        }
        .option:last-child { border-bottom: none; }
        .option:hover { background: rgba(200,169,110,0.04); }

        .option.selected {
            background: var(--accent-dim);
        }
        .option.selected .option-key {
            background: var(--accent);
            color: #0d0d0f;
            border-color: var(--accent);
        }
        .option.selected .option-label { color: var(--text); }

        .option input[type="radio"] { display: none; }

        .option-key {
            width: 28px; height: 28px; border-radius: 7px;
            border: 1px solid var(--border);
            background: var(--surface-2);
            display: flex; align-items: center; justify-content: center;
            font-family: 'DM Mono', monospace;
            font-size: 11px; color: var(--muted);
            flex-shrink: 0;
            transition: background 0.18s, color 0.18s, border-color 0.18s;
        }

        .option-label {
            font-size: 14px; font-weight: 300; color: var(--muted);
            transition: color 0.18s; line-height: 1.5;
        }

        /* ── NAVIGATION ── */
        .nav-row {
            display: flex; justify-content: space-between; align-items: center;
            gap: 12px; margin-top: 4px;
        }

        .btn-nav {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif; font-weight: 500;
            padding: 10px 20px; border-radius: 9px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            color: var(--muted); cursor: pointer;
            transition: color 0.2s, border-color 0.2s, background 0.2s, transform 0.15s;
        }
        .btn-nav:hover:not(:disabled) {
            color: var(--text); border-color: rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.05);
            transform: translateY(-1px);
        }
        .btn-nav:active { transform: translateY(0); }
        .btn-nav:disabled { opacity: 0.3; cursor: not-allowed; }

        .btn-submit {
            display: none;
            width: 100%; align-items: center; justify-content: center; gap: 10px;
            font-size: 14px; font-family: 'DM Sans', sans-serif; font-weight: 500;
            padding: 14px 24px; border-radius: 11px;
            border: 1px solid rgba(92,173,130,0.35);
            background: rgba(92,173,130,0.12);
            color: #6ec99a; cursor: pointer;
            transition: background 0.2s, transform 0.15s, opacity 0.2s;
            margin-top: 16px;
        }
        .btn-submit:hover:not(:disabled) {
            background: rgba(92,173,130,0.2);
            transform: translateY(-1px);
        }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-submit.visible { display: inline-flex; }

        /* ── PALETTE SIDEBAR ── */
        .sidebar { position: sticky; top: 94px; }

        .palette-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            animation: fadeUp 0.5s 0.1s cubic-bezier(0.22,1,0.36,1) both;
        }

        .palette-title {
            font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 14px;
        }

        .palette-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
            margin-bottom: 18px;
        }

        .palette-item {
            aspect-ratio: 1; border-radius: 7px;
            border: 1px solid var(--border);
            background: var(--surface-2);
            display: flex; align-items: center; justify-content: center;
            font-family: 'DM Mono', monospace;
            font-size: 10px; color: var(--muted);
            cursor: pointer;
            transition: background 0.18s, border-color 0.18s, color 0.18s;
        }
        .palette-item:hover { border-color: rgba(200,169,110,0.3); color: var(--accent); }
        .palette-item.answered {
            background: var(--green-dim);
            border-color: rgba(92,173,130,0.3);
            color: #6ec99a;
        }
        .palette-item.current {
            background: var(--accent-dim);
            border-color: rgba(200,169,110,0.4);
            color: var(--accent);
        }

        /* Legend */
        .legend { display: flex; flex-direction: column; gap: 8px; }
        .legend-item {
            display: flex; align-items: center; gap: 8px;
            font-size: 11px; color: var(--muted);
        }
        .legend-dot {
            width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0;
        }
        .legend-dot.answered { background: var(--green-dim); border: 1px solid rgba(92,173,130,0.3); }
        .legend-dot.current  { background: var(--accent-dim); border: 1px solid rgba(200,169,110,0.4); }
        .legend-dot.unvisited { background: var(--surface-2); border: 1px solid var(--border); }

        /* Answered count */
        .answered-stat {
            margin-top: 18px; padding-top: 16px;
            border-top: 1px solid var(--border);
            text-align: center;
        }
        .answered-num {
            font-family: 'Playfair Display', serif;
            font-size: 28px; font-weight: 400; color: var(--accent);
            line-height: 1;
        }
        .answered-label { font-size: 11px; color: var(--muted); margin-top: 4px; }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── RESPONSIVE ── */
        @media (max-width: 800px) {
            .page-wrap { grid-template-columns: 1fr; }
            .sidebar { position: static; }
            .palette-grid { grid-template-columns: repeat(8, 1fr); }
        }
    </style>
</head>
<body>

<!-- Submitting overlay -->
<div class="submitting-overlay" id="submitting-overlay">
    <div class="overlay-spinner"></div>
    <div class="overlay-title" id="overlay-title">Submitting Test…</div>
    <div class="overlay-msg" id="overlay-msg">Please wait while we calculate your score.</div>
</div>

<!-- Header -->
<header class="test-header">
    <div class="header-content">
        <div class="header-left">
            <div class="logo-mark">R</div>
            <div class="header-titles">
                <div class="main-title">Online Assessment</div>
                <div class="sub-title">
                    <?php echo htmlspecialchars($application['job_title']); ?>
                    &nbsp;·&nbsp; Round 1 — Aptitude &amp; Technical
                </div>
            </div>
        </div>
        <div class="timer-wrap" id="timer-wrap">
            <span class="timer-icon">◷</span>
            <span class="timer-display" id="time-display">00:00</span>
            <span class="timer-label">remaining</span>
        </div>
    </div>
</header>

<!-- Page -->
<div class="page-wrap">

    <!-- Main column -->
    <main>
        <!-- Section banner -->
        <div class="section-banner">
            <div class="eyebrow">Round 1 &nbsp;·&nbsp; Aptitude &amp; Technical</div>
            <div class="section-name" id="section-title">Loading…</div>
            <div class="progress-row">
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" id="progress-bar"></div>
                </div>
                <span class="progress-label" id="progress-label">— / —</span>
            </div>
        </div>

        <!-- Questions injected here -->
        <div id="questions-container"></div>

        <!-- Navigation -->
        <div class="nav-row">
            <button class="btn-nav" id="prev-btn" onclick="previousQuestion()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                Previous
            </button>
            <button class="btn-nav" id="next-btn" onclick="nextQuestion()">
                Next
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        <button class="btn-submit" id="submit-btn" onclick="submitTest()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Submit Test &amp; Proceed to Coding Round
        </button>
    </main>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="palette-card">
            <div class="palette-title">Question Map</div>
            <div class="palette-grid" id="palette"></div>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-dot answered"></div>
                    Answered
                </div>
                <div class="legend-item">
                    <div class="legend-dot current"></div>
                    Current
                </div>
                <div class="legend-item">
                    <div class="legend-dot unvisited"></div>
                    Not visited
                </div>
            </div>

            <div class="answered-stat">
                <div class="answered-num" id="answered-count">0</div>
                <div class="answered-label">answered</div>
            </div>
        </div>
    </aside>

</div>

<script>
    const testData = <?php echo $test_json; ?>;

    let currentQuestionIndex = 0;
    let allQuestions         = [];
    let answers              = {};
    let timeLeft             = testData.total_time * 60;
    let timerInterval;

    testData.test_structure.forEach(section => {
        section.questions.forEach(q => {
            allQuestions.push({...q, section: section.section_name});
        });
    });

    const KEYS = ['A','B','C','D'];

    function initTest() {
        document.getElementById('progress-label').textContent = `1 / ${allQuestions.length}`;
        renderQuestion(0);
        renderPalette();
        startTimer();
        window.addEventListener('beforeunload', e => { e.preventDefault(); e.returnValue = ''; });
    }

    function renderQuestion(index) {
        const container      = document.getElementById('questions-container');
        container.innerHTML  = '';
        const q              = allQuestions[index];
        currentQuestionIndex = index;

        const optKeys  = ['option_a','option_b','option_c','option_d'];
        const optHTML  = KEYS.map((k, i) => `
            <div class="option ${answers[q.id]===k ? 'selected' : ''}"
                 onclick="selectAnswer(${q.id},'${k}')">
                <input type="radio" name="q${q.id}" value="${k}" ${answers[q.id]===k ? 'checked' : ''}>
                <span class="option-key">${k}</span>
                <span class="option-label">${q[optKeys[i]]}</span>
            </div>
        `).join('');

        const card = document.createElement('div');
        card.className = 'question-card active';
        card.innerHTML = `
            <div class="question-meta">Question ${index + 1} of ${allQuestions.length} &nbsp;·&nbsp; ${q.section}</div>
            <div class="question-text">${q.question_text}</div>
            <div class="options">${optHTML}</div>
        `;
        container.appendChild(card);

        document.getElementById('section-title').textContent = q.section;
        updateProgress();
        updateNavButtons();
        renderPalette();
    }

    function selectAnswer(questionId, answer) {
        answers[questionId] = answer;
        renderQuestion(currentQuestionIndex);
    }

    function nextQuestion()     { if (currentQuestionIndex < allQuestions.length - 1) renderQuestion(currentQuestionIndex + 1); }
    function previousQuestion() { if (currentQuestionIndex > 0) renderQuestion(currentQuestionIndex - 1); }
    function jumpToQuestion(i)  { renderQuestion(i); }

    function updateNavButtons() {
        const prevBtn   = document.getElementById('prev-btn');
        const nextBtn   = document.getElementById('next-btn');
        const submitBtn = document.getElementById('submit-btn');
        prevBtn.disabled = currentQuestionIndex === 0;
        const isLast = currentQuestionIndex === allQuestions.length - 1;
        nextBtn.style.display = isLast ? 'none' : 'inline-flex';
        submitBtn.classList.toggle('visible', isLast);
    }

    function renderPalette() {
        const palette = document.getElementById('palette');
        palette.innerHTML = '';
        allQuestions.forEach((q, index) => {
            const item = document.createElement('div');
            item.className = 'palette-item';
            if      (index === currentQuestionIndex) item.classList.add('current');
            else if (answers[q.id])                  item.classList.add('answered');
            item.textContent = index + 1;
            item.onclick     = () => jumpToQuestion(index);
            palette.appendChild(item);
        });
    }

    function updateProgress() {
        const answered = Object.keys(answers).length;
        const pct      = answered / allQuestions.length * 100;
        document.getElementById('progress-bar').style.width   = pct + '%';
        document.getElementById('progress-label').textContent =
            `${currentQuestionIndex + 1} / ${allQuestions.length}`;
        document.getElementById('answered-count').textContent = answered;
    }

    function startTimer() {
        updateTimerDisplay();
        timerInterval = setInterval(() => {
            timeLeft--;
            updateTimerDisplay();
            if (timeLeft === 300) {
                document.getElementById('timer-wrap').classList.add('warning');
                alert('⏰ 5 minutes remaining!');
            }
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                alert('⏰ Time is up! Submitting now…');
                submitTest();
            }
        }, 1000);
    }

    function updateTimerDisplay() {
        const h = Math.floor(timeLeft / 3600);
        const m = Math.floor((timeLeft % 3600) / 60);
        const s = timeLeft % 60;
        let d   = '';
        if (h > 0) d = `${String(h).padStart(2,'0')}:`;
        d += `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        document.getElementById('time-display').textContent = d;
    }

    function submitTest() {
        const answered   = Object.keys(answers).length;
        const unanswered = allQuestions.length - answered;

        if (unanswered > 0 && !confirm(`You have ${unanswered} unanswered question(s). Submit anyway?`)) return;

        clearInterval(timerInterval);

        const submitBtn    = document.getElementById('submit-btn');
        submitBtn.disabled = true;

        document.getElementById('submitting-overlay').classList.add('show');

        const formData = new FormData();
        formData.append('application_id', testData.application_id);
        formData.append('answers',        JSON.stringify(answers));
        formData.append('time_taken',     (testData.total_time * 60) - timeLeft);

        fetch('../scripts/submit_test.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('overlay-title').textContent = '✓ Round 1 Complete';
                document.getElementById('overlay-msg').textContent   =
                    `Score: ${data.score}% · ${data.correct}/${data.total} correct · ${data.skipped} skipped. Loading Coding Round…`;
                window.onbeforeunload = null;
                setTimeout(() => { window.location.href = data.redirect; }, 2200);
            } else {
                document.getElementById('submitting-overlay').classList.remove('show');
                submitBtn.disabled = false;
                alert('Error: ' + data.error);
            }
        })
        .catch(err => {
            document.getElementById('submitting-overlay').classList.remove('show');
            submitBtn.disabled = false;
            alert('Network error: ' + err);
        });
    }

    window.onload = initTest;
</script>
</body>
</html>