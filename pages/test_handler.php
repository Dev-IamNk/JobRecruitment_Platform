<!-- FILE: pages/take_test.php -->
<?php 
require_once '../config/db.php';
require_once '../scripts/generate_test.php';

redirectIfNotLoggedIn();
if (getUserType() != 'candidate') {
    header('Location: recruiter_dashboard.php');
    exit();
}

$application_id = intval($_GET['app_id'] ?? 0);
$candidate_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT a.*, j.title as job_title FROM applications a JOIN jobs j ON a.job_id = j.id WHERE a.id = ? AND a.candidate_id = ?");
$stmt->execute([$application_id, $candidate_id]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) { header('Location: candidate_dashboard.php'); exit(); }

$stmt = $pdo->prepare("SELECT * FROM test_attempts WHERE application_id = ?");
$stmt->execute([$application_id]);
$existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_attempt && $existing_attempt['status'] == 'completed') {
    header('Location: candidate_dashboard.php?error=test_completed'); exit();
}

$test_data = generateTest($application_id);
if (isset($test_data['error'])) { die("Error: " . $test_data['error']); }

if (!$existing_attempt) {
    $stmt = $pdo->prepare("SELECT id FROM test_configs WHERE job_id = ? LIMIT 1");
    $stmt->execute([$application['job_id']]);
    $test_config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($test_config) {
        $attempt_id = createTestAttempt($application_id, $test_config['id']);
        foreach ($test_data['test_structure'] as $section) {
            saveTestQuestions($attempt_id, $section['questions']);
        }
    }
}

$test_json = json_encode($test_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment — <?php echo htmlspecialchars($application['job_title']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d0d0f;
            --surface: #141418;
            --surface-2: #1a1a1f;
            --border: rgba(255,255,255,0.07);
            --accent: #c8a96e;
            --accent-dim: rgba(200,169,110,0.1);
            --text: #f0ece4;
            --muted: #7a7670;
            --green: #5cad82;
        }

        html, body {
            min-height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-weight: 300;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }

        /* ── Sticky header ── */
        .test-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(13,13,15,0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
        }

        .header-inner {
            max-width: 1100px;
            margin: 0 auto;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .header-brand {
            display: flex; align-items: center; gap: 12px;
        }

        .logo-mark {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 15px; font-weight: 600; color: #0d0d0f;
        }

        .header-job {
            font-family: 'Playfair Display', serif;
            font-size: 15px; color: var(--text);
        }

        .header-job span {
            font-family: 'DM Sans', sans-serif;
            font-size: 12px; color: var(--muted); font-weight: 300;
            display: block; margin-top: 1px;
        }

        .header-right { display: flex; align-items: center; gap: 16px; }

        /* Progress text */
        .header-progress {
            font-size: 12px; color: var(--muted);
            display: flex; align-items: center; gap: 8px;
        }

        .header-progress-bar {
            width: 100px; height: 4px;
            background: rgba(255,255,255,0.06);
            border-radius: 2px; overflow: hidden;
        }

        .header-progress-fill {
            height: 100%; background: var(--accent);
            border-radius: 2px; width: 0%;
            transition: width 0.3s;
        }

        /* Timer */
        .timer-display {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 16px; border-radius: 10px;
            background: var(--surface);
            border: 1px solid var(--border);
            font-size: 16px; font-weight: 500;
            font-variant-numeric: tabular-nums;
            color: var(--text);
            transition: background 0.3s, border-color 0.3s, color 0.3s;
        }

        .timer-display.warning {
            background: rgba(224,92,92,0.12);
            border-color: rgba(224,92,92,0.3);
            color: #e87878;
            animation: pulse 1.2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.65; }
        }

        /* ── Main layout ── */
        .test-layout {
            display: grid;
            grid-template-columns: 1fr 220px;
            gap: 24px;
            max-width: 1100px;
            margin: 32px auto;
            padding: 0 24px 72px;
            position: relative;
            z-index: 1;
        }

        /* ── Left: question area ── */
        .question-area {}

        .section-banner {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .section-name {
            font-family: 'Playfair Display', serif;
            font-size: 17px; font-weight: 400; color: var(--text);
        }

        .question-counter {
            font-size: 12px; color: var(--muted);
            white-space: nowrap;
        }

        /* Question card */
        .question-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            animation: fadeUp 0.35s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .question-body { padding: 28px 28px 24px; }

        .question-meta {
            font-size: 11px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--accent); margin-bottom: 14px;
        }

        .question-text {
            font-size: 17px; line-height: 1.65;
            color: var(--text); font-weight: 400;
        }

        .options-list {
            list-style: none;
            padding: 24px 28px 28px;
            display: flex; flex-direction: column; gap: 10px;
            border-top: 1px solid var(--border);
        }

        .option-item {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 18px;
            border: 1px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            background: rgba(255,255,255,0.02);
            transition: border-color 0.18s, background 0.18s;
            user-select: none;
        }

        .option-item:hover {
            border-color: rgba(200,169,110,0.35);
            background: var(--accent-dim);
        }

        .option-item.selected {
            border-color: rgba(200,169,110,0.6);
            background: rgba(200,169,110,0.1);
        }

        .option-item input[type="radio"] { display: none; }

        .option-dot {
            width: 20px; height: 20px; border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.12);
            flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            transition: border-color 0.18s;
        }

        .option-item.selected .option-dot {
            border-color: var(--accent);
            background: var(--accent);
        }

        .option-item.selected .option-dot::after {
            content: '';
            width: 8px; height: 8px; border-radius: 50%;
            background: #0d0d0f;
        }

        .option-letter {
            width: 22px; height: 22px; border-radius: 5px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 500; color: var(--muted);
            flex-shrink: 0;
        }

        .option-item.selected .option-letter {
            background: rgba(200,169,110,0.15);
            border-color: rgba(200,169,110,0.3);
            color: var(--accent);
        }

        .option-text { font-size: 14px; color: var(--text); line-height: 1.5; }

        /* Navigation */
        .nav-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 28px;
            border-top: 1px solid var(--border);
            background: rgba(255,255,255,0.01);
        }

        .btn-nav {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif;
            color: var(--muted); padding: 9px 16px;
            border-radius: 9px; border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            cursor: pointer; transition: color 0.2s, border-color 0.2s;
        }
        .btn-nav:hover:not(:disabled) { color: var(--text); border-color: rgba(255,255,255,0.15); }
        .btn-nav:disabled { opacity: 0.3; cursor: default; }

        .btn-submit {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif; font-weight: 500;
            color: #0d0d0f; padding: 10px 20px;
            border-radius: 9px; border: none;
            background: var(--accent);
            cursor: pointer; transition: opacity 0.2s, transform 0.15s;
            letter-spacing: 0.03em;
        }
        .btn-submit:hover { opacity: 0.86; transform: translateY(-1px); }
        .btn-submit:active { transform: translateY(0); }

        /* ── Right: palette ── */
        .palette-panel {
            position: sticky;
            top: 88px;
            align-self: start;
        }

        .palette-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
        }

        .palette-heading {
            font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 16px;
        }

        .palette-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
            margin-bottom: 20px;
        }

        .palette-item {
            width: 100%; aspect-ratio: 1;
            border: 1px solid var(--border);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 500; color: var(--muted);
            cursor: pointer;
            background: rgba(255,255,255,0.02);
            transition: border-color 0.15s, background 0.15s, color 0.15s;
        }

        .palette-item:hover { border-color: rgba(200,169,110,0.35); color: var(--accent); }

        .palette-item.answered {
            background: rgba(92,173,130,0.12);
            border-color: rgba(92,173,130,0.3);
            color: #6ec99a;
        }

        .palette-item.current {
            background: var(--accent-dim);
            border-color: rgba(200,169,110,0.5);
            color: var(--accent);
        }

        .legend { display: flex; flex-direction: column; gap: 8px; }
        .legend-item {
            display: flex; align-items: center; gap: 8px;
            font-size: 12px; color: var(--muted);
        }
        .legend-dot {
            width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0;
        }
        .legend-answered { background: rgba(92,173,130,0.5); }
        .legend-current { background: rgba(200,169,110,0.6); }
        .legend-unanswered { background: rgba(255,255,255,0.08); border: 1px solid var(--border); }

        /* Submit full-width */
        .submit-area {
            margin-top: 16px;
        }

        .btn-submit-full {
            width: 100%;
            padding: 13px;
            background: var(--accent);
            color: #0d0d0f;
            border: none;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px; font-weight: 500; letter-spacing: 0.04em;
            cursor: pointer;
            transition: opacity 0.2s;
            display: none;
        }
        .btn-submit-full:hover { opacity: 0.86; }

        @media (max-width: 768px) {
            .test-layout { grid-template-columns: 1fr; }
            .palette-panel { position: static; }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="test-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="logo-mark">R</div>
                <div class="header-job">
                    <?php echo htmlspecialchars($application['job_title']); ?>
                    <span>Online Assessment</span>
                </div>
            </div>
            <div class="header-right">
                <div class="header-progress">
                    <span id="progress-text">0 answered</span>
                    <div class="header-progress-bar">
                        <div class="header-progress-fill" id="header-progress-fill"></div>
                    </div>
                </div>
                <div class="timer-display" id="timer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span id="time-display">00:00</span>
                </div>
            </div>
        </div>
    </header>

    <div class="test-layout">

        <!-- Question Area -->
        <div class="question-area">
            <div class="section-banner">
                <div class="section-name" id="section-title">Loading…</div>
                <div class="question-counter">Question <span id="current-q">1</span> of <span id="total-q">0</span></div>
            </div>

            <div class="question-card" id="question-card">
                <div class="question-body">
                    <div class="question-meta" id="question-meta"></div>
                    <div class="question-text" id="question-text"></div>
                </div>
                <ul class="options-list" id="options-list"></ul>
                <div class="nav-row">
                    <button class="btn-nav" id="prev-btn" onclick="previousQuestion()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        Previous
                    </button>
                    <button class="btn-nav btn-submit" id="next-btn" onclick="nextQuestion()">
                        Next
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                    <button class="btn-submit" id="submit-inline-btn" onclick="submitTest()" style="display:none;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Submit Test
                    </button>
                </div>
            </div>
        </div>

        <!-- Palette panel -->
        <aside class="palette-panel">
            <div class="palette-card">
                <div class="palette-heading">Question Navigator</div>
                <div class="palette-grid" id="palette"></div>
                <div class="legend">
                    <div class="legend-item"><div class="legend-dot legend-current"></div> Current</div>
                    <div class="legend-item"><div class="legend-dot legend-answered"></div> Answered</div>
                    <div class="legend-item"><div class="legend-dot legend-unanswered"></div> Not visited</div>
                </div>
                <div class="submit-area">
                    <button class="btn-submit-full" id="submit-palette-btn" onclick="submitTest()">Submit Test</button>
                </div>
            </div>
        </aside>

    </div>

    <script>
        const testData = <?php echo $test_json; ?>;

        let currentQuestionIndex = 0;
        let allQuestions = [];
        let answers = {};
        let timeLeft = testData.total_time * 60;
        let timerInterval;

        testData.test_structure.forEach(section => {
            section.questions.forEach(q => {
                allQuestions.push({...q, section: section.section_name});
            });
        });

        function initTest() {
            document.getElementById('total-q').textContent = allQuestions.length;
            renderQuestion(0);
            renderPalette();
            startTimer();
            window.addEventListener('beforeunload', e => { e.preventDefault(); e.returnValue = ''; });
        }

        function renderQuestion(index) {
            currentQuestionIndex = index;
            const q = allQuestions[index];

            // Re-animate card
            const card = document.getElementById('question-card');
            card.style.animation = 'none';
            card.offsetHeight;
            card.style.animation = '';

            document.getElementById('question-meta').textContent = `Question ${index + 1} of ${allQuestions.length} · ${q.section}`;
            document.getElementById('question-text').textContent = q.question_text;
            document.getElementById('section-title').textContent = q.section;
            document.getElementById('current-q').textContent = index + 1;

            const opts = document.getElementById('options-list');
            opts.innerHTML = '';
            ['A','B','C','D'].forEach(letter => {
                const val = q[`option_${letter.toLowerCase()}`];
                const selected = answers[q.id] === letter;
                const li = document.createElement('li');
                li.className = `option-item${selected ? ' selected' : ''}`;
                li.onclick = () => selectAnswer(q.id, letter);
                li.innerHTML = `
                    <input type="radio" name="q${q.id}" value="${letter}" ${selected ? 'checked' : ''}>
                    <div class="option-dot">${selected ? '' : ''}</div>
                    <span class="option-letter">${letter}</span>
                    <span class="option-text">${val}</span>
                `;
                opts.appendChild(li);
            });

            updateNavButtons();
            renderPalette();
            updateProgress();
        }

        function selectAnswer(questionId, answer) {
            answers[questionId] = answer;
            renderQuestion(currentQuestionIndex);
        }

        function nextQuestion() {
            if (currentQuestionIndex < allQuestions.length - 1) renderQuestion(currentQuestionIndex + 1);
        }

        function previousQuestion() {
            if (currentQuestionIndex > 0) renderQuestion(currentQuestionIndex - 1);
        }

        function jumpToQuestion(index) { renderQuestion(index); }

        function updateNavButtons() {
            const isLast = currentQuestionIndex === allQuestions.length - 1;
            document.getElementById('prev-btn').disabled = currentQuestionIndex === 0;
            document.getElementById('next-btn').style.display = isLast ? 'none' : 'inline-flex';
            document.getElementById('submit-inline-btn').style.display = isLast ? 'inline-flex' : 'none';
            document.getElementById('submit-palette-btn').style.display = isLast ? 'block' : 'none';
        }

        function renderPalette() {
            const palette = document.getElementById('palette');
            palette.innerHTML = '';
            allQuestions.forEach((q, i) => {
                const div = document.createElement('div');
                div.className = 'palette-item';
                if (i === currentQuestionIndex) div.classList.add('current');
                else if (answers[q.id]) div.classList.add('answered');
                div.textContent = i + 1;
                div.onclick = () => jumpToQuestion(i);
                palette.appendChild(div);
            });
        }

        function updateProgress() {
            const answered = Object.keys(answers).length;
            const pct = (answered / allQuestions.length) * 100;
            document.getElementById('header-progress-fill').style.width = pct + '%';
            document.getElementById('progress-text').textContent = `${answered} answered`;
        }

        function startTimer() {
            updateTimerDisplay();
            timerInterval = setInterval(() => {
                timeLeft--;
                updateTimerDisplay();
                if (timeLeft === 300) {
                    document.getElementById('timer').classList.add('warning');
                    alert('⏰ 5 minutes remaining!');
                }
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    alert('⏰ Time is up! Your test will now be submitted.');
                    submitTest();
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            const h = Math.floor(timeLeft / 3600);
            const m = Math.floor((timeLeft % 3600) / 60);
            const s = timeLeft % 60;
            let d = h > 0 ? `${String(h).padStart(2,'0')}:` : '';
            d += `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
            document.getElementById('time-display').textContent = d;
        }

        function submitTest() {
            const unanswered = allQuestions.length - Object.keys(answers).length;
            if (unanswered > 0 && !confirm(`You have ${unanswered} unanswered question(s). Submit anyway?`)) return;

            clearInterval(timerInterval);

            const formData = new FormData();
            formData.append('application_id', testData.application_id);
            formData.append('answers', JSON.stringify(answers));
            formData.append('time_taken', (testData.total_time * 60) - timeLeft);

            fetch('../scripts/submit_test.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Test submitted! Score: ' + data.score + '%');
                        window.location.href = 'candidate_dashboard.php?success=test_submitted';
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(err => alert('Submission error: ' + err));
        }

        window.onload = initTest;
    </script>
</body>
</html>