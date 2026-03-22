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

// Verify this application belongs to logged-in candidate
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

// Check if test configuration exists
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM test_configs WHERE job_id = ?");
$stmt->execute([$application['job_id']]);
$test_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($test_count == 0) {
    die("No test configured for this job. Please contact the recruiter.");
}

// Check if already taken aptitude/technical test
$stmt = $pdo->prepare("
    SELECT * FROM test_attempts 
    WHERE application_id = ? AND round_type = 'aptitude_technical'
    ORDER BY started_at DESC LIMIT 1
");
$stmt->execute([$application_id]);
$existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_attempt && $existing_attempt['status'] == 'completed') {
    // Check if coding test also done
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

// Generate test
$test_data = generateTest($application_id);

if (isset($test_data['error'])) {
    die("Error generating test: " . $test_data['error']);
}

// Create test attempt if not exists
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

// Convert test data to JSON for JavaScript
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
    <title>Online Assessment - <?php echo htmlspecialchars($application['job_title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { margin: 0; padding: 0; overflow-x: hidden; }

        .test-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .timer {
            font-size: 24px;
            font-weight: bold;
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
        }
        .timer.warning { background: #ff6b6b; animation: pulse 1s infinite; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.7; }
        }
        .test-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .section-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #667eea;
        }
        .question-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
        }
        .question-card.active { display: block; }
        .question-number {
            color: #667eea;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .question-text {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            line-height: 1.6;
            color: #333;
        }
        .options { display: flex; flex-direction: column; gap: 15px; }
        .option {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .option:hover    { border-color: #667eea; background: #f0f4ff; }
        .option.selected { border-color: #667eea; background: #667eea; color: white; }
        .option input[type="radio"] { margin-right: 15px; width: 20px; height: 20px; }
        .option-label { font-size: 16px; flex: 1; }
        .navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
        .question-palette {
            position: fixed;
            right: 20px;
            top: 100px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            width: 220px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .palette-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin-top: 15px;
        }
        .palette-item {
            width: 32px;
            height: 32px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s;
        }
        .palette-item:hover    { border-color: #667eea; }
        .palette-item.answered { background: #28a745; color: white; border-color: #28a745; }
        .palette-item.current  { background: #667eea; color: white; border-color: #667eea; }
        .submit-test-btn {
            background: #28a745;
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 30px;
        }
        .submit-test-btn:hover    { background: #218838; }
        .submit-test-btn:disabled { background: #6c757d; cursor: not-allowed; }
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 15px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s;
        }
        .submitting-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: white;
            font-family: sans-serif;
            text-align: center;
        }
        .submitting-overlay.show { display: flex; }
        .submitting-spinner {
            width: 56px;
            height: 56px;
            border: 5px solid rgba(255,255,255,0.2);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .submitting-overlay h2 { margin: 0 0 8px 0; font-size: 22px; }
        .submitting-overlay p  { margin: 0; opacity: 0.8; font-size: 14px; }
    </style>
</head>
<body>

    <!-- Submitting overlay -->
    <div class="submitting-overlay" id="submitting-overlay">
        <div class="submitting-spinner"></div>
        <h2 id="overlay-title">Submitting Test...</h2>
        <p id="overlay-msg">Please wait while we calculate your score.</p>
    </div>

    <div class="test-header">
        <div class="header-content">
            <div>
                <h2 style="margin:0;">Online Assessment</h2>
                <p style="margin:5px 0 0 0;opacity:0.9;">
                    <?php echo htmlspecialchars($application['job_title']); ?>
                    &nbsp;|&nbsp; Round 1: Aptitude &amp; Technical
                </p>
            </div>
            <div class="timer" id="timer">
                <span id="time-display">00:00</span>
            </div>
        </div>
    </div>

    <div class="test-container">
        <div class="section-header">
            <h3 style="margin:0 0 10px 0;" id="section-title">Loading...</h3>
            <div class="progress-bar">
                <div class="progress-fill" id="progress-bar"></div>
            </div>
            <p style="margin:10px 0 0 0;color:#666;">
                Question <span id="current-q">1</span> of <span id="total-q">0</span>
            </p>
        </div>

        <div id="questions-container"></div>

        <div class="navigation">
            <button class="btn btn-secondary" id="prev-btn" onclick="previousQuestion()">← Previous</button>
            <button class="btn" id="next-btn" onclick="nextQuestion()">Next →</button>
        </div>

        <button class="submit-test-btn" id="submit-btn" onclick="submitTest()" style="display:none;">
            Submit Test &amp; Proceed to Coding Round →
        </button>
    </div>

    <div class="question-palette">
        <h4 style="margin:0 0 10px 0;">Questions</h4>
        <div class="palette-grid" id="palette"></div>
        <div style="margin-top:15px;font-size:12px;color:#666;">
            <div style="margin-bottom:5px;">
                <span style="display:inline-block;width:15px;height:15px;background:#28a745;border-radius:3px;"></span> Answered
            </div>
            <div style="margin-bottom:5px;">
                <span style="display:inline-block;width:15px;height:15px;background:#667eea;border-radius:3px;"></span> Current
            </div>
            <div>
                <span style="display:inline-block;width:15px;height:15px;border:2px solid #e0e0e0;border-radius:3px;"></span> Not Visited
            </div>
        </div>
    </div>

    <script>
        const testData = <?php echo $test_json; ?>;

        let currentQuestionIndex = 0;
        let allQuestions         = [];
        let answers              = {};
        let timeLeft             = testData.total_time * 60;
        let timerInterval;

        // Flatten all questions from all sections
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
            window.addEventListener('beforeunload', function(e) {
                e.preventDefault();
                e.returnValue = '';
            });
        }

        function renderQuestion(index) {
            const container      = document.getElementById('questions-container');
            container.innerHTML  = '';
            const question       = allQuestions[index];
            currentQuestionIndex = index;

            const card       = document.createElement('div');
            card.className   = 'question-card active';
            card.innerHTML   = `
                <div class="question-number">Question ${index + 1} of ${allQuestions.length} | ${question.section}</div>
                <div class="question-text">${question.question_text}</div>
                <div class="options">
                    <div class="option ${answers[question.id]==='A'?'selected':''}" onclick="selectAnswer(${question.id},'A')">
                        <input type="radio" name="q${question.id}" value="A" ${answers[question.id]==='A'?'checked':''}>
                        <span class="option-label">A) ${question.option_a}</span>
                    </div>
                    <div class="option ${answers[question.id]==='B'?'selected':''}" onclick="selectAnswer(${question.id},'B')">
                        <input type="radio" name="q${question.id}" value="B" ${answers[question.id]==='B'?'checked':''}>
                        <span class="option-label">B) ${question.option_b}</span>
                    </div>
                    <div class="option ${answers[question.id]==='C'?'selected':''}" onclick="selectAnswer(${question.id},'C')">
                        <input type="radio" name="q${question.id}" value="C" ${answers[question.id]==='C'?'checked':''}>
                        <span class="option-label">C) ${question.option_c}</span>
                    </div>
                    <div class="option ${answers[question.id]==='D'?'selected':''}" onclick="selectAnswer(${question.id},'D')">
                        <input type="radio" name="q${question.id}" value="D" ${answers[question.id]==='D'?'checked':''}>
                        <span class="option-label">D) ${question.option_d}</span>
                    </div>
                </div>
            `;
            container.appendChild(card);

            document.getElementById('current-q').textContent    = index + 1;
            document.getElementById('section-title').textContent = question.section;
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
            if (currentQuestionIndex === allQuestions.length - 1) {
                nextBtn.style.display   = 'none';
                submitBtn.style.display = 'block';
            } else {
                nextBtn.style.display   = 'block';
                submitBtn.style.display = 'none';
            }
        }

        function renderPalette() {
            const palette    = document.getElementById('palette');
            palette.innerHTML = '';
            allQuestions.forEach((q, index) => {
                const item     = document.createElement('div');
                item.className = 'palette-item';
                if (index === currentQuestionIndex) item.classList.add('current');
                else if (answers[q.id])             item.classList.add('answered');
                item.textContent = index + 1;
                item.onclick     = () => jumpToQuestion(index);
                palette.appendChild(item);
            });
        }

        function updateProgress() {
            const answered = Object.keys(answers).length;
            document.getElementById('progress-bar').style.width =
                (answered / allQuestions.length * 100) + '%';
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
                    alert('⏰ Time is up! Submitting now...');
                    submitTest();
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            const hours   = Math.floor(timeLeft / 3600);
            const minutes = Math.floor((timeLeft % 3600) / 60);
            const seconds = timeLeft % 60;
            let display   = '';
            if (hours > 0) display = `${hours.toString().padStart(2,'0')}:`;
            display += `${minutes.toString().padStart(2,'0')}:${seconds.toString().padStart(2,'0')}`;
            document.getElementById('time-display').textContent = display;
        }

        function submitTest() {
            const answered   = Object.keys(answers).length;
            const unanswered = allQuestions.length - answered;

            if (unanswered > 0) {
                if (!confirm(`You have ${unanswered} unanswered question(s). Are you sure you want to submit?`))
                    return;
            }

            clearInterval(timerInterval);

            const submitBtn       = document.getElementById('submit-btn');
            submitBtn.disabled    = true;
            submitBtn.textContent = 'Submitting...';

            const overlay = document.getElementById('submitting-overlay');
            overlay.classList.add('show');

            const formData = new FormData();
            formData.append('application_id', testData.application_id);
            formData.append('answers',        JSON.stringify(answers));
            formData.append('time_taken',     (testData.total_time * 60) - timeLeft);

            fetch('../scripts/submit_test.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('overlay-title').textContent = '✅ Round 1 Complete!';
                    document.getElementById('overlay-msg').textContent   =
                        `Score: ${data.score}% (${data.correct}/${data.total} correct, ${data.skipped} skipped). Loading Coding Round...`;
                    window.onbeforeunload = null;
                    setTimeout(() => { window.location.href = data.redirect; }, 2000);
                } else {
                    overlay.classList.remove('show');
                    submitBtn.disabled    = false;
                    submitBtn.textContent = 'Submit Test & Proceed to Coding Round →';
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                overlay.classList.remove('show');
                submitBtn.disabled    = false;
                submitBtn.textContent = 'Submit Test & Proceed to Coding Round →';
                alert('Error submitting test: ' + error);
            });
        }

        window.onload = initTest;
    </script>
</body>
</html>