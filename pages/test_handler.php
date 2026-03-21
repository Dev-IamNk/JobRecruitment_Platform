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

// Verify this application belongs to logged-in candidate
$stmt = $pdo->prepare("SELECT a.*, j.title as job_title FROM applications a JOIN jobs j ON a.job_id = j.id WHERE a.id = ? AND a.candidate_id = ?");
$stmt->execute([$application_id, $candidate_id]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    header('Location: candidate_dashboard.php');
    exit();
}

// Check if already taken test
$stmt = $pdo->prepare("SELECT * FROM test_attempts WHERE application_id = ?");
$stmt->execute([$application_id]);
$existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_attempt && $existing_attempt['status'] == 'completed') {
    header('Location: candidate_dashboard.php?error=test_completed');
    exit();
}

// Generate test
$test_data = generateTest($application_id);

if (isset($test_data['error'])) {
    die("Error: " . $test_data['error']);
}

// Create test attempt if not exists
if (!$existing_attempt) {
    $stmt = $pdo->prepare("SELECT id FROM test_configs WHERE job_id = ? LIMIT 1");
    $stmt->execute([$application['job_id']]);
    $test_config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_config) {
        $attempt_id = createTestAttempt($application_id, $test_config['id']);
        
        // Save all questions for this attempt
        foreach ($test_data['test_structure'] as $section) {
            saveTestQuestions($attempt_id, $section['questions']);
        }
    }
}

// Convert test data to JSON for JavaScript
$test_json = json_encode($test_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Assessment - <?php echo htmlspecialchars($application['job_title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
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
        .timer.warning {
            background: #ff6b6b;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
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
        .question-card.active {
            display: block;
        }
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
        .options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .option {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .option:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .option.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        .option input[type="radio"] {
            margin-right: 15px;
            width: 20px;
            height: 20px;
        }
        .option-label {
            font-size: 16px;
            flex: 1;
        }
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
            max-width: 200px;
        }
        .palette-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin-top: 15px;
        }
        .palette-item {
            width: 35px;
            height: 35px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
        }
        .palette-item:hover {
            border-color: #667eea;
        }
        .palette-item.answered {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
        .palette-item.current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
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
        .submit-test-btn:hover {
            background: #218838;
        }
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
    </style>
</head>
<body>
    <div class="test-header">
        <div class="header-content">
            <div>
                <h2 style="margin: 0;">Online Assessment</h2>
                <p style="margin: 5px 0 0 0; opacity: 0.9;"><?php echo htmlspecialchars($application['job_title']); ?></p>
            </div>
            <div class="timer" id="timer">
                <span id="time-display">00:00</span>
            </div>
        </div>
    </div>
    
    <div class="test-container">
        <div class="section-header">
            <h3 style="margin: 0 0 10px 0;" id="section-title">Loading...</h3>
            <div class="progress-bar">
                <div class="progress-fill" id="progress-bar"></div>
            </div>
            <p style="margin: 10px 0 0 0; color: #666;">
                Question <span id="current-q">1</span> of <span id="total-q">0</span>
            </p>
        </div>
        
        <div id="questions-container">
            <!-- Questions will be loaded by JavaScript -->
        </div>
        
        <div class="navigation">
            <button class="btn btn-secondary" id="prev-btn" onclick="previousQuestion()">← Previous</button>
            <button class="btn" id="next-btn" onclick="nextQuestion()">Next →</button>
        </div>
        
        <button class="submit-test-btn" id="submit-btn" onclick="submitTest()" style="display: none;">
            Submit Test
        </button>
    </div>
    
    <div class="question-palette">
        <h4 style="margin: 0 0 10px 0;">Questions</h4>
        <div class="palette-grid" id="palette">
            <!-- Palette will be generated by JavaScript -->
        </div>
        <div style="margin-top: 15px; font-size: 12px; color: #666;">
            <div style="margin-bottom: 5px;">
                <span style="display: inline-block; width: 15px; height: 15px; background: #28a745; border-radius: 3px;"></span>
                Answered
            </div>
            <div style="margin-bottom: 5px;">
                <span style="display: inline-block; width: 15px; height: 15px; background: #667eea; border-radius: 3px;"></span>
                Current
            </div>
            <div>
                <span style="display: inline-block; width: 15px; height: 15px; border: 2px solid #e0e0e0; border-radius: 3px;"></span>
                Not Visited
            </div>
        </div>
    </div>
    
    <script>
        // Test data from PHP
        const testData = <?php echo $test_json; ?>;
        
        let currentQuestionIndex = 0;
        let allQuestions = [];
        let answers = {};
        let timeLeft = testData.total_time * 60; // Convert to seconds
        let timerInterval;
        
        // Flatten all questions from all sections
        testData.test_structure.forEach(section => {
            section.questions.forEach(q => {
                allQuestions.push({...q, section: section.section_name});
            });
        });
        
        // Initialize test
        function initTest() {
            document.getElementById('total-q').textContent = allQuestions.length;
            renderQuestion(0);
            renderPalette();
            startTimer();
            
            // Prevent page refresh
            window.addEventListener('beforeunload', function (e) {
                e.preventDefault();
                e.returnValue = '';
            });
        }
        
        // Render current question
        function renderQuestion(index) {
            const container = document.getElementById('questions-container');
            container.innerHTML = '';
            
            const question = allQuestions[index];
            currentQuestionIndex = index;
            
            const card = document.createElement('div');
            card.className = 'question-card active';
            card.innerHTML = `
                <div class="question-number">Question ${index + 1} of ${allQuestions.length} | ${question.section}</div>
                <div class="question-text">${question.question_text}</div>
                <div class="options">
                    <div class="option ${answers[question.id] === 'A' ? 'selected' : ''}" onclick="selectAnswer(${question.id}, 'A')">
                        <input type="radio" name="q${question.id}" value="A" ${answers[question.id] === 'A' ? 'checked' : ''}>
                        <span class="option-label">A) ${question.option_a}</span>
                    </div>
                    <div class="option ${answers[question.id] === 'B' ? 'selected' : ''}" onclick="selectAnswer(${question.id}, 'B')">
                        <input type="radio" name="q${question.id}" value="B" ${answers[question.id] === 'B' ? 'checked' : ''}>
                        <span class="option-label">B) ${question.option_b}</span>
                    </div>
                    <div class="option ${answers[question.id] === 'C' ? 'selected' : ''}" onclick="selectAnswer(${question.id}, 'C')">
                        <input type="radio" name="q${question.id}" value="C" ${answers[question.id] === 'C' ? 'checked' : ''}>
                        <span class="option-label">C) ${question.option_c}</span>
                    </div>
                    <div class="option ${answers[question.id] === 'D' ? 'selected' : ''}" onclick="selectAnswer(${question.id}, 'D')">
                        <input type="radio" name="q${question.id}" value="D" ${answers[question.id] === 'D' ? 'checked' : ''}>
                        <span class="option-label">D) ${question.option_d}</span>
                    </div>
                </div>
            `;
            
            container.appendChild(card);
            
            // Update UI
            document.getElementById('current-q').textContent = index + 1;
            document.getElementById('section-title').textContent = question.section;
            updateProgress();
            updateNavButtons();
            renderPalette();
        }
        
        // Select answer
        function selectAnswer(questionId, answer) {
            answers[questionId] = answer;
            renderQuestion(currentQuestionIndex);
        }
        
        // Navigation
        function nextQuestion() {
            if (currentQuestionIndex < allQuestions.length - 1) {
                renderQuestion(currentQuestionIndex + 1);
            }
        }
        
        function previousQuestion() {
            if (currentQuestionIndex > 0) {
                renderQuestion(currentQuestionIndex - 1);
            }
        }
        
        function jumpToQuestion(index) {
            renderQuestion(index);
        }
        
        function updateNavButtons() {
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            const submitBtn = document.getElementById('submit-btn');
            
            prevBtn.disabled = currentQuestionIndex === 0;
            
            if (currentQuestionIndex === allQuestions.length - 1) {
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'block';
            } else {
                nextBtn.style.display = 'block';
                submitBtn.style.display = 'none';
            }
        }
        
        // Render question palette
        function renderPalette() {
            const palette = document.getElementById('palette');
            palette.innerHTML = '';
            
            allQuestions.forEach((q, index) => {
                const item = document.createElement('div');
                item.className = 'palette-item';
                
                if (index === currentQuestionIndex) {
                    item.classList.add('current');
                } else if (answers[q.id]) {
                    item.classList.add('answered');
                }
                
                item.textContent = index + 1;
                item.onclick = () => jumpToQuestion(index);
                palette.appendChild(item);
            });
        }
        
        // Update progress bar
        function updateProgress() {
            const answered = Object.keys(answers).length;
            const progress = (answered / allQuestions.length) * 100;
            document.getElementById('progress-bar').style.width = progress + '%';
        }
        
        // Timer
        function startTimer() {
            updateTimerDisplay();
            
            timerInterval = setInterval(() => {
                timeLeft--;
                updateTimerDisplay();
                
                // Warning when 5 minutes left
                if (timeLeft === 300) {
                    document.getElementById('timer').classList.add('warning');
                    alert('⏰ 5 minutes remaining!');
                }
                
                // Auto submit when time is up
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    alert('⏰ Time is up! Test will be submitted automatically.');
                    submitTest();
                }
            }, 1000);
        }
        
        function updateTimerDisplay() {
            const hours = Math.floor(timeLeft / 3600);
            const minutes = Math.floor((timeLeft % 3600) / 60);
            const seconds = timeLeft % 60;
            
            let display = '';
            if (hours > 0) {
                display = `${hours.toString().padStart(2, '0')}:`;
            }
            display += `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            document.getElementById('time-display').textContent = display;
        }
        
        // Submit test
        function submitTest() {
            const answered = Object.keys(answers).length;
            const unanswered = allQuestions.length - answered;
            
            if (unanswered > 0) {
                if (!confirm(`You have ${unanswered} unanswered question(s). Are you sure you want to submit?`)) {
                    return;
                }
            }
            
            clearInterval(timerInterval);
            
            // Prepare submission data
            const formData = new FormData();
            formData.append('application_id', testData.application_id);
            formData.append('answers', JSON.stringify(answers));
            formData.append('time_taken', (testData.total_time * 60) - timeLeft);
            
            // Submit via AJAX
            fetch('../scripts/submit_test.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Test submitted successfully! Score: ' + data.score + '%');
                    window.location.href = 'candidate_dashboard.php?success=test_submitted';
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error submitting test: ' + error);
            });
        }
        
        // Initialize on page load
        window.onload = initTest;
    </script>
</body>
</html>