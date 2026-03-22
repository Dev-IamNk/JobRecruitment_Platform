<?php
require_once '../config/db.php'; // db.php already calls session_start()

redirectIfNotLoggedIn();
if (getUserType() != 'candidate') {
    header('Location: login.php');
    exit;
}

$candidate_id   = $_SESSION['user_id'];
$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : 0;

if (!$application_id) {
    die("Invalid application.");
}

// Verify this application belongs to the candidate
$app_query = $pdo->prepare("
    SELECT a.*, j.title as job_title, j.id as job_id 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE a.id = ? AND a.candidate_id = ?
");
$app_query->execute([$application_id, $candidate_id]);
$application = $app_query->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    die("Application not found.");
}

// Check if coding test already completed
if (isset($application['coding_status']) && $application['coding_status'] === 'completed') {
    header("Location: candidate_dashboard.php?msg=coding_already_done");
    exit;
}

// Check if aptitude/tech test was completed first
$attempt_check = $pdo->prepare("
    SELECT id, total_score, max_score FROM test_attempts 
    WHERE application_id = ? AND round_type = 'aptitude_technical'
    ORDER BY started_at DESC LIMIT 1
");
$attempt_check->execute([$application_id]);
$aptitude_attempt = $attempt_check->fetch(PDO::FETCH_ASSOC);

if (!$aptitude_attempt) {
    die("Please complete the aptitude & technical test first.");
}

// Check if a coding attempt already exists (handle page refresh)
$existing_coding = $pdo->prepare("
    SELECT id FROM test_attempts 
    WHERE application_id = ? AND round_type = 'coding'
    ORDER BY started_at DESC LIMIT 1
");
$existing_coding->execute([$application_id]);
$existing_coding_attempt = $existing_coding->fetch(PDO::FETCH_ASSOC);

// Fetch coding problems: 1 easy, 1 medium, 1 hard
$problems = [];
foreach (['easy' => 1, 'medium' => 1, 'hard' => 1] as $diff => $count) {
    $q = $pdo->prepare("SELECT * FROM coding_problems WHERE difficulty = ? ORDER BY RAND() LIMIT $count");
    $q->execute([$diff]);
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $problems[] = $row;
    }
}

if (empty($problems)) {
    die("No coding problems available. Please contact the administrator.");
}

// Create coding attempt only if not already exists
if ($existing_coding_attempt) {
    $coding_attempt_id = $existing_coding_attempt['id'];
} else {
    $tc = $pdo->prepare("SELECT id FROM test_configs WHERE job_id = ? LIMIT 1");
    $tc->execute([$application['job_id']]);
    $test_config = $tc->fetch(PDO::FETCH_ASSOC);
    $tc_id = $test_config ? $test_config['id'] : 1;

    $insert_attempt = $pdo->prepare("
        INSERT INTO test_attempts (application_id, test_config_id, round_type, started_at) 
        VALUES (?, ?, 'coding', NOW())
    ");
    $insert_attempt->execute([$application_id, $tc_id]);
    $coding_attempt_id = $pdo->lastInsertId();
}

$total_problems = count($problems);
$time_limit     = $total_problems * 20; // 20 mins per problem
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coding Test | <?= htmlspecialchars($application['job_title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f;
            --surface: #111118;
            --surface2: #1a1a24;
            --border: #2a2a3a;
            --accent: #00e5a0;
            --accent2: #7c6fff;
            --warn: #ff9f43;
            --danger: #ff5252;
            --text: #e8e8f0;
            --muted: #6b6b8a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Syne', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }

        /* TOP BAR */
        .topbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 28px; background: var(--surface); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .topbar-left { display: flex; align-items: center; gap: 16px; }
        .logo-badge { background: var(--accent); color: #000; font-weight: 800; font-size: 11px; letter-spacing: 1.5px; padding: 5px 10px; border-radius: 4px; }
        .job-label { font-size: 14px; color: var(--muted); }
        .job-label span { color: var(--text); font-weight: 600; }
        .timer-wrap { display: flex; align-items: center; gap: 10px; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 8px 18px; }
        #timer { font-family: 'JetBrains Mono', monospace; font-size: 20px; font-weight: 700; color: var(--accent); letter-spacing: 2px; transition: color 0.3s; }
        #timer.warn   { color: var(--warn); }
        #timer.danger { color: var(--danger); animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }

        /* MAIN */
        .main { display: grid; grid-template-columns: 320px 1fr; flex: 1; height: calc(100vh - 61px); }

        /* PROBLEM PANEL */
        .problem-panel { background: var(--surface); border-right: 1px solid var(--border); overflow-y: auto; display: flex; flex-direction: column; }
        .problem-tabs { display: flex; border-bottom: 1px solid var(--border); padding: 12px 16px 0; gap: 4px; }
        .prob-tab { padding: 8px 14px; border-radius: 6px 6px 0 0; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; border-bottom: none; color: var(--muted); transition: all 0.2s; background: transparent; font-family: 'Syne', sans-serif; }
        .prob-tab:hover  { color: var(--text); background: var(--surface2); }
        .prob-tab.active { color: var(--text); background: var(--bg); border-color: var(--border); }
        .diff-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 6px; }
        .progress-row { display: flex; align-items: center; gap: 8px; padding: 12px 20px; border-bottom: 1px solid var(--border); }
        .prog-label { font-size: 12px; color: var(--muted); margin-right: 4px; }
        .prog-dot { width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: var(--muted); cursor: pointer; transition: all 0.2s; font-family: 'JetBrains Mono', monospace; }
        .prog-dot.active { border-color: var(--accent2); color: var(--accent2); background: rgba(124,111,255,0.15); }
        .prog-dot.done   { border-color: var(--accent); color: #000; background: var(--accent); }
        .problem-content { padding: 24px 20px; flex: 1; }
        .prob-header { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
        .prob-number { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
        .diff-badge { font-size: 11px; font-weight: 700; letter-spacing: 1px; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; }
        .diff-easy   { background: rgba(0,229,160,0.15); color: #00e5a0; }
        .diff-medium { background: rgba(255,159,67,0.15); color: #ff9f43; }
        .diff-hard   { background: rgba(255,82,82,0.15);  color: #ff5252; }
        .prob-title     { font-size: 18px; font-weight: 700; margin-bottom: 14px; line-height: 1.3; }
        .prob-statement { font-size: 13.5px; line-height: 1.7; color: #c0c0d8; white-space: pre-wrap; margin-bottom: 20px; }
        .io-section { margin-bottom: 14px; }
        .io-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); margin-bottom: 6px; }
        .io-box { background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 10px 14px; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--accent); line-height: 1.5; }
        .prob-status-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
        .status-chip { font-size: 11px; padding: 3px 10px; border-radius: 20px; border: 1px solid var(--border); color: var(--muted); font-family: 'JetBrains Mono', monospace; }
        .status-chip.saved { border-color: var(--accent); color: var(--accent); background: rgba(0,229,160,0.08); }

        /* EDITOR */
        .editor-panel { display: flex; flex-direction: column; background: var(--bg); overflow: hidden; }
        .editor-topbar { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; background: var(--surface); border-bottom: 1px solid var(--border); }
        .lang-select { background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 13px; padding: 6px 12px; cursor: pointer; outline: none; }
        .lang-select:focus { border-color: var(--accent2); }
        .editor-actions { display: flex; align-items: center; gap: 10px; }
        .btn { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 13px; padding: 8px 18px; border-radius: 6px; border: none; cursor: pointer; transition: all 0.2s; letter-spacing: 0.5px; }
        .btn-save   { background: var(--surface2); border: 1px solid var(--border); color: var(--text); }
        .btn-save:hover { border-color: var(--accent2); color: var(--accent2); }
        .btn-next   { background: var(--accent2); color: #fff; }
        .btn-next:hover { background: #6a5fff; transform: translateY(-1px); }
        .btn-submit { background: var(--accent); color: #000; font-weight: 800; }
        .btn-submit:hover { background: #00cc8c; transform: translateY(-1px); }
        .code-editor-wrap { flex: 1; position: relative; overflow: hidden; }
        .line-numbers { position: absolute; left: 0; top: 0; bottom: 0; width: 50px; background: var(--surface); border-right: 1px solid var(--border); padding: 16px 8px; font-family: 'JetBrains Mono', monospace; font-size: 13px; line-height: 1.6; color: var(--muted); text-align: right; overflow: hidden; user-select: none; }
        #code-editor { position: absolute; left: 50px; top: 0; right: 0; bottom: 0; background: var(--bg); color: #c9d1d9; font-family: 'JetBrains Mono', monospace; font-size: 13.5px; line-height: 1.6; padding: 16px; border: none; outline: none; resize: none; tab-size: 4; caret-color: var(--accent); }
        .statusbar { display: flex; align-items: center; justify-content: space-between; padding: 8px 16px; background: var(--surface); border-top: 1px solid var(--border); font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--muted); }
        .statusbar-left { display: flex; gap: 16px; align-items: center; }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); }

        /* MODAL */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 999; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 36px; max-width: 440px; width: 90%; text-align: center; }
        .modal-icon { font-size: 48px; margin-bottom: 16px; }
        .modal h2 { font-size: 22px; font-weight: 800; margin-bottom: 10px; }
        .modal p  { color: var(--muted); font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
        .modal-btns { display: flex; gap: 12px; justify-content: center; }

        /* SAVE FLASH */
        .save-flash { position: fixed; bottom: 60px; right: 24px; background: var(--accent); color: #000; font-weight: 700; font-size: 13px; padding: 10px 20px; border-radius: 8px; opacity: 0; transform: translateY(10px); transition: all 0.3s; pointer-events: none; z-index: 200; }
        .save-flash.show { opacity: 1; transform: translateY(0); }

        /* SUBMITTING OVERLAY */
        .submitting-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; flex-direction: column; color: white; text-align: center; }
        .submitting-overlay.show { display: flex; }
        .submitting-spinner { width: 56px; height: 56px; border: 5px solid rgba(255,255,255,0.2); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .submitting-overlay h2 { margin: 0 0 8px; font-size: 22px; font-family: 'Syne', sans-serif; }
        .submitting-overlay p  { margin: 0; opacity: 0.8; font-size: 14px; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    </style>
</head>
<body>

<!-- SUBMITTING OVERLAY -->
<div class="submitting-overlay" id="submitting-overlay">
    <div class="submitting-spinner"></div>
    <h2>Submitting Coding Test...</h2>
    <p>Please wait while we save your answers.</p>
</div>

<!-- TOP BAR -->
<div class="topbar">
    <div class="topbar-left">
        <div class="logo-badge">CODING</div>
        <div class="job-label">Round 2 &nbsp;·&nbsp; <span><?= htmlspecialchars($application['job_title']) ?></span></div>
    </div>
    <div class="timer-wrap">
        <span>⏱</span>
        <span id="timer">--:--</span>
    </div>
    <div style="font-size:13px;color:var(--muted);">
        <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Candidate') ?>
    </div>
</div>

<!-- MAIN -->
<div class="main">

    <!-- LEFT: PROBLEM PANEL -->
    <div class="problem-panel">
        <div class="problem-tabs">
            <?php foreach ($problems as $i => $prob):
                $dc = $prob['difficulty'] === 'easy' ? '#00e5a0' : ($prob['difficulty'] === 'medium' ? '#ff9f43' : '#ff5252');
            ?>
            <button class="prob-tab <?= $i === 0 ? 'active' : '' ?>"
                    onclick="switchProblem(<?= $i ?>)" id="tab-<?= $i ?>">
                <span class="diff-dot" style="background:<?= $dc ?>"></span>Q<?= $i+1 ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="progress-row">
            <span class="prog-label">Progress:</span>
            <?php foreach ($problems as $i => $prob): ?>
            <div class="prog-dot <?= $i === 0 ? 'active' : '' ?>" id="prog-<?= $i ?>" onclick="switchProblem(<?= $i ?>)"><?= $i+1 ?></div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($problems as $i => $prob): ?>
        <div class="problem-content" id="problem-<?= $i ?>" style="display:<?= $i === 0 ? 'block' : 'none' ?>">
            <div class="prob-header">
                <span class="prob-number">Problem <?= $i+1 ?> / <?= $total_problems ?></span>
                <span class="diff-badge diff-<?= $prob['difficulty'] ?>"><?= ucfirst($prob['difficulty']) ?></span>
            </div>
            <div class="prob-title"><?= htmlspecialchars($prob['title']) ?></div>
            <div class="prob-statement"><?= htmlspecialchars($prob['problem_statement']) ?></div>

            <?php if (!empty($prob['sample_input'])): ?>
            <div class="io-section">
                <div class="io-label">Sample Input</div>
                <div class="io-box"><?= htmlspecialchars($prob['sample_input']) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($prob['sample_output'])): ?>
            <div class="io-section">
                <div class="io-label">Expected Output</div>
                <div class="io-box"><?= htmlspecialchars($prob['sample_output']) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($prob['constraints'])): ?>
            <div class="io-section">
                <div class="io-label">Constraints</div>
                <div class="io-box" style="color:var(--warn)"><?= htmlspecialchars($prob['constraints']) ?></div>
            </div>
            <?php endif; ?>

            <div class="prob-status-row">
                <div class="status-chip" id="chip-<?= $i ?>">Not saved</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- RIGHT: CODE EDITOR -->
    <div class="editor-panel">
        <div class="editor-topbar">
            <select class="lang-select" id="lang-select" onchange="updateTemplate()">
                <option value="python">Python</option>
                <option value="java">Java</option>
                <option value="javascript">JavaScript</option>
                <option value="c">C</option>
                <option value="cpp">C++</option>
            </select>
            <div class="editor-actions">
                <button class="btn btn-save"   onclick="saveCurrentAnswer()">💾 Save Answer</button>
                <button class="btn btn-next"   id="btn-next"   onclick="nextProblem()">Next →</button>
                <button class="btn btn-submit" id="btn-submit" style="display:none" onclick="showSubmitModal()">✓ Submit All</button>
            </div>
        </div>

        <div class="code-editor-wrap">
            <div class="line-numbers" id="line-numbers">1</div>
            <textarea id="code-editor"
                      placeholder="// Write your solution here..."
                      oninput="updateLineNumbers()"
                      onscroll="syncScroll()"
                      onkeydown="handleTab(event)"
                      spellcheck="false"></textarea>
        </div>

        <div class="statusbar">
            <div class="statusbar-left">
                <div style="display:flex;align-items:center;gap:5px">
                    <div class="status-dot"></div>
                    <span id="status-lang">Python</span>
                </div>
                <span id="status-lines">1 line</span>
                <span id="status-chars">0 chars</span>
            </div>
            <div>Problem <span id="status-prob">1</span> of <?= $total_problems ?></div>
        </div>
    </div>
</div>

<!-- Save flash -->
<div class="save-flash" id="save-flash">✓ Answer saved!</div>

<!-- Submit Modal -->
<div class="modal-overlay" id="submit-modal">
    <div class="modal">
        <div class="modal-icon">🚀</div>
        <h2>Submit Coding Test?</h2>
        <p>You are about to submit all answers. This cannot be undone.<br><br>
           <span id="unanswered-warn"></span>
        </p>
        <div class="modal-btns">
            <button class="btn btn-save"   onclick="closeModal()">Cancel</button>
            <button class="btn btn-submit" onclick="submitTest()">Submit Now</button>
        </div>
    </div>
</div>

<!-- Hidden form -->
<form id="submit-form" method="POST" action="../scripts/submit_coding.php">
    <input type="hidden" name="application_id" value="<?= $application_id ?>">
    <input type="hidden" name="attempt_id"      value="<?= $coding_attempt_id ?>">
    <input type="hidden" name="job_id"          value="<?= $application['job_id'] ?>">
    <?php foreach ($problems as $i => $prob): ?>
    <input   type="hidden" name="problem_ids[]"  value="<?= $prob['id'] ?>">
    <textarea              name="codes[]"         id="hidden-code-<?= $i ?>" style="display:none"></textarea>
    <input   type="hidden" name="languages[]"     id="hidden-lang-<?= $i ?>" value="python">
    <?php endforeach; ?>
</form>

<script>
const totalProblems = <?= $total_problems ?>;
let currentProblem  = 0;
const answers       = {};
let timeLeft        = <?= $time_limit * 60 ?>;
let timerInterval;

const templates = {
    python:     `# Write your Python solution here\n\ndef solution():\n    # Your code here\n    pass\n\nprint(solution())`,
    java:       `// Write your Java solution here\n\npublic class Solution {\n    public static void main(String[] args) {\n        // Your code here\n    }\n}`,
    javascript: `// Write your JavaScript solution here\n\nfunction solution() {\n    // Your code here\n}\n\nconsole.log(solution());`,
    c:          `// Write your C solution here\n\n#include <stdio.h>\n\nint main() {\n    // Your code here\n    return 0;\n}`,
    cpp:        `// Write your C++ solution here\n\n#include <iostream>\nusing namespace std;\n\nint main() {\n    // Your code here\n    return 0;\n}`
};

window.onload = () => {
    document.getElementById('code-editor').value = templates['python'];
    updateLineNumbers();
    startTimer();
    updateNavButtons();
};

function startTimer() {
    updateTimerDisplay();
    timerInterval = setInterval(() => {
        timeLeft--;
        updateTimerDisplay();
        if (timeLeft <= 0) { clearInterval(timerInterval); submitTest(); }
    }, 1000);
}

function updateTimerDisplay() {
    const m  = Math.floor(timeLeft / 60).toString().padStart(2, '0');
    const s  = (timeLeft % 60).toString().padStart(2, '0');
    const el = document.getElementById('timer');
    el.textContent = `${m}:${s}`;
    el.className   = timeLeft <= 300 ? 'danger' : timeLeft <= 600 ? 'warn' : '';
}

function switchProblem(idx) {
    saveCurrentAnswer(false);
    document.getElementById(`problem-${currentProblem}`).style.display = 'none';
    document.getElementById(`tab-${currentProblem}`).classList.remove('active');
    const dot = document.getElementById(`prog-${currentProblem}`);
    dot.classList.remove('active');
    if (answers[currentProblem] && answers[currentProblem].code.trim()) dot.classList.add('done');

    currentProblem = idx;
    document.getElementById(`problem-${currentProblem}`).style.display = 'block';
    document.getElementById(`tab-${currentProblem}`).classList.add('active');
    const newDot = document.getElementById(`prog-${currentProblem}`);
    newDot.classList.remove('done');
    newDot.classList.add('active');

    if (answers[currentProblem]) {
        document.getElementById('lang-select').value  = answers[currentProblem].lang;
        document.getElementById('code-editor').value  = answers[currentProblem].code;
    } else {
        document.getElementById('code-editor').value = templates[document.getElementById('lang-select').value];
    }
    updateLineNumbers();
    updateNavButtons();
    document.getElementById('status-prob').textContent = currentProblem + 1;
}

function updateNavButtons() {
    document.getElementById('btn-next').style.display   = currentProblem === totalProblems - 1 ? 'none'  : 'block';
    document.getElementById('btn-submit').style.display = currentProblem === totalProblems - 1 ? 'block' : 'none';
}

function nextProblem() {
    if (currentProblem < totalProblems - 1) switchProblem(currentProblem + 1);
}

function updateTemplate() {
    const lang     = document.getElementById('lang-select').value;
    const existing = document.getElementById('code-editor').value.trim();
    const isTpl    = Object.values(templates).some(t => t.trim() === existing);
    if (!existing || isTpl) {
        document.getElementById('code-editor').value = templates[lang];
        updateLineNumbers();
    }
    document.getElementById('status-lang').textContent = lang.charAt(0).toUpperCase() + lang.slice(1);
}

function saveCurrentAnswer(flash = true) {
    const code = document.getElementById('code-editor').value;
    const lang = document.getElementById('lang-select').value;
    answers[currentProblem] = { code, lang };
    document.getElementById(`hidden-code-${currentProblem}`).value = code;
    document.getElementById(`hidden-lang-${currentProblem}`).value = lang;
    const chip = document.getElementById(`chip-${currentProblem}`);
    if (code.trim()) { chip.textContent = '✓ Saved'; chip.classList.add('saved'); }
    if (flash) showSaveFlash();
}

function showSaveFlash() {
    const el = document.getElementById('save-flash');
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2000);
}

function updateLineNumbers() {
    const editor = document.getElementById('code-editor');
    const lines  = editor.value.split('\n').length;
    document.getElementById('line-numbers').textContent = Array.from({length: lines}, (_, i) => i + 1).join('\n');
    document.getElementById('status-lines').textContent = `${lines} line${lines > 1 ? 's' : ''}`;
    document.getElementById('status-chars').textContent = `${editor.value.length} chars`;
}

function syncScroll() {
    document.getElementById('line-numbers').scrollTop = document.getElementById('code-editor').scrollTop;
}

function handleTab(e) {
    if (e.key === 'Tab') {
        e.preventDefault();
        const ta = document.getElementById('code-editor');
        const s  = ta.selectionStart;
        ta.value = ta.value.substring(0, s) + '    ' + ta.value.substring(ta.selectionEnd);
        ta.selectionStart = ta.selectionEnd = s + 4;
        updateLineNumbers();
    }
}

function showSubmitModal() {
    saveCurrentAnswer(false);
    let unanswered = 0;
    for (let i = 0; i < totalProblems; i++) {
        if (!answers[i] || !answers[i].code.trim()) unanswered++;
    }
    const warn = document.getElementById('unanswered-warn');
    warn.textContent  = unanswered > 0 ? `⚠ ${unanswered} problem(s) have no answer.` : '✓ All problems answered!';
    warn.style.color  = unanswered > 0 ? 'var(--warn)' : 'var(--accent)';
    document.getElementById('submit-modal').classList.add('show');
}

function closeModal() {
    document.getElementById('submit-modal').classList.remove('show');
}

function submitTest() {
    clearInterval(timerInterval);
    closeModal();
    for (let i = 0; i < totalProblems; i++) {
        if (answers[i]) {
            document.getElementById(`hidden-code-${i}`).value = answers[i].code;
            document.getElementById(`hidden-lang-${i}`).value = answers[i].lang;
        }
    }
    document.getElementById('submitting-overlay').classList.add('show');
    setTimeout(() => document.getElementById('submit-form').submit(), 500);
}
</script>
</body>
</html>
