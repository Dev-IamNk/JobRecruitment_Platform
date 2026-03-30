<?php
require_once '../config/db.php';

redirectIfNotLoggedIn();
if (getUserType() != 'candidate') {
    header('Location: login.php');
    exit;
}

$candidate_id   = $_SESSION['user_id'];
$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : 0;

if (!$application_id) die("Invalid application.");

$app_query = $pdo->prepare("
    SELECT a.*, j.title as job_title, j.id as job_id 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE a.id = ? AND a.candidate_id = ?
");
$app_query->execute([$application_id, $candidate_id]);
$application = $app_query->fetch(PDO::FETCH_ASSOC);

if (!$application) die("Application not found.");

if (isset($application['coding_status']) && $application['coding_status'] === 'completed') {
    header("Location: candidate_dashboard.php?msg=coding_already_done");
    exit;
}

$attempt_check = $pdo->prepare("
    SELECT id, total_score, max_score FROM test_attempts 
    WHERE application_id = ? AND round_type = 'aptitude_technical'
    ORDER BY started_at DESC LIMIT 1
");
$attempt_check->execute([$application_id]);
$aptitude_attempt = $attempt_check->fetch(PDO::FETCH_ASSOC);
if (!$aptitude_attempt) die("Please complete the aptitude & technical test first.");

$existing_coding = $pdo->prepare("
    SELECT id FROM test_attempts 
    WHERE application_id = ? AND round_type = 'coding'
    ORDER BY started_at DESC LIMIT 1
");
$existing_coding->execute([$application_id]);
$existing_coding_attempt = $existing_coding->fetch(PDO::FETCH_ASSOC);

$problems = [];
foreach (['easy' => 1, 'medium' => 1, 'hard' => 1] as $diff => $count) {
    $q = $pdo->prepare("SELECT * FROM coding_problems WHERE difficulty = ? ORDER BY RAND() LIMIT $count");
    $q->execute([$diff]);
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) $problems[] = $row;
}

if (empty($problems)) die("No coding problems available. Please contact the administrator.");

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
$time_limit     = $total_problems * 20;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coding Test — <?= htmlspecialchars($application['job_title']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=DM+Sans:wght@300;400;500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d0d0f;
            --surface: #141418;
            --surface-2: #1a1a1f;
            --surface-3: #0f0f12;
            --border: rgba(255,255,255,0.07);
            --border-2: rgba(255,255,255,0.04);
            --accent: #c8a96e;
            --accent-dim: rgba(200,169,110,0.1);
            --accent-glow: rgba(200,169,110,0.18);
            --text: #f0ece4;
            --muted: #7a7670;
            --green: #5cad82;
            --warn: #e6a845;
            --danger: #e05c5c;
            --code-text: #d4cfc8;
        }

        html, body {
            height: 100%; overflow: hidden;
            background: var(--bg); color: var(--text);
            font-family: 'DM Sans', sans-serif; font-weight: 300;
        }

        /* ── TOP BAR ── */
        .topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 20px; height: 56px;
            background: var(--surface); border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 100;
        }

        .topbar-left { display: flex; align-items: center; gap: 16px; }

        .logo-mark {
            width: 30px; height: 30px; border-radius: 8px; background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 14px; font-weight: 600; color: #0d0d0f;
            flex-shrink: 0;
        }

        .round-badge {
            font-size: 10px; font-weight: 500; letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--accent); background: var(--accent-dim);
            border: 1px solid rgba(200,169,110,0.2);
            padding: 4px 10px; border-radius: 20px;
        }

        .job-label { font-size: 13px; color: var(--muted); }
        .job-label strong { color: var(--text); font-weight: 400; }

        .topbar-center {
            display: flex; align-items: center; gap: 10px;
        }

        .timer-wrap {
            display: flex; align-items: center; gap: 8px;
            background: var(--surface-2); border: 1px solid var(--border);
            border-radius: 9px; padding: 7px 16px;
        }

        #timer {
            font-family: 'DM Mono', monospace; font-size: 17px; font-weight: 500;
            color: var(--accent); letter-spacing: 0.08em; transition: color 0.3s;
        }
        #timer.warn   { color: var(--warn); }
        #timer.danger { color: var(--danger); animation: pulse 1s infinite; }

        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }

        .topbar-right {
            font-size: 12px; color: var(--muted);
            display: flex; align-items: center; gap: 6px;
        }

        .candidate-dot {
            width: 26px; height: 26px; border-radius: 50%;
            background: var(--accent-dim); border: 1px solid rgba(200,169,110,0.2);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 11px; color: var(--accent);
        }

        /* ── MAIN LAYOUT ── */
        .main {
            display: grid; grid-template-columns: 320px 1fr;
            height: calc(100vh - 56px); overflow: hidden;
        }

        /* ── PROBLEM PANEL ── */
        .problem-panel {
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            overflow: hidden;
        }

        /* Problem tabs */
        .problem-tabs {
            display: flex; gap: 2px;
            padding: 10px 12px 0; border-bottom: 1px solid var(--border);
            background: var(--surface-2); flex-shrink: 0;
        }

        .prob-tab {
            display: flex; align-items: center; gap: 7px;
            padding: 8px 12px; border-radius: 7px 7px 0 0;
            font-size: 12px; font-weight: 400; cursor: pointer;
            border: 1px solid transparent; border-bottom: none;
            color: var(--muted); background: transparent;
            font-family: 'DM Sans', sans-serif;
            transition: color 0.15s, background 0.15s;
        }
        .prob-tab:hover  { color: var(--text); background: rgba(255,255,255,0.03); }
        .prob-tab.active { color: var(--text); background: var(--surface); border-color: var(--border); }

        .diff-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

        /* Progress dots */
        .progress-row {
            display: flex; align-items: center; gap: 8px;
            padding: 12px 16px; border-bottom: 1px solid var(--border);
            background: rgba(255,255,255,0.01); flex-shrink: 0;
        }

        .prog-label { font-size: 11px; color: var(--muted); letter-spacing: 0.06em; text-transform: uppercase; }

        .prog-dot {
            width: 28px; height: 28px; border-radius: 50%;
            border: 1.5px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 500; color: var(--muted);
            cursor: pointer; transition: all 0.2s;
            font-family: 'DM Mono', monospace;
        }
        .prog-dot:hover  { border-color: rgba(200,169,110,0.35); color: var(--accent); }
        .prog-dot.active { border-color: rgba(200,169,110,0.55); color: var(--accent); background: var(--accent-dim); }
        .prog-dot.done   { border-color: var(--green); color: var(--green); background: rgba(92,173,130,0.1); }

        /* Problem content */
        .problem-content {
            padding: 24px 20px; overflow-y: auto; flex: 1;
        }

        .prob-header {
            display: flex; align-items: center; gap: 8px; margin-bottom: 14px;
        }

        .prob-number {
            font-size: 10px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted); font-family: 'DM Mono', monospace;
        }

        .diff-badge {
            font-size: 10px; font-weight: 500; letter-spacing: 0.08em;
            padding: 3px 8px; border-radius: 20px; text-transform: uppercase;
        }
        .diff-easy   { background: rgba(92,173,130,0.12); color: #6ec99a; border: 1px solid rgba(92,173,130,0.2); }
        .diff-medium { background: rgba(230,168,69,0.12); color: #e6b84a; border: 1px solid rgba(230,168,69,0.2); }
        .diff-hard   { background: rgba(224,92,92,0.12);  color: #e87878; border: 1px solid rgba(224,92,92,0.2); }

        .prob-title {
            font-family: 'Playfair Display', serif;
            font-size: 17px; font-weight: 400; color: var(--text);
            margin-bottom: 14px; line-height: 1.35;
        }

        .prob-statement {
            font-size: 13px; line-height: 1.75; color: rgba(240,236,228,0.65);
            white-space: pre-wrap; margin-bottom: 20px;
        }

        .io-section { margin-bottom: 14px; }

        .io-label {
            font-size: 10px; font-weight: 500; text-transform: uppercase;
            letter-spacing: 0.1em; color: var(--muted); margin-bottom: 6px;
        }

        .io-box {
            background: var(--surface-3); border: 1px solid var(--border);
            border-radius: 8px; padding: 10px 14px;
            font-family: 'DM Mono', monospace; font-size: 12px;
            color: var(--accent); line-height: 1.6;
        }

        .io-box.constraints { color: var(--warn); }

        .prob-status-row { margin-top: 10px; }

        .status-chip {
            display: inline-block; font-size: 11px; padding: 4px 10px;
            border-radius: 20px; border: 1px solid var(--border);
            color: var(--muted); font-family: 'DM Mono', monospace;
            transition: all 0.2s;
        }
        .status-chip.saved {
            border-color: rgba(92,173,130,0.35); color: #6ec99a;
            background: rgba(92,173,130,0.08);
        }

        /* ── EDITOR PANEL ── */
        .editor-panel {
            display: flex; flex-direction: column;
            background: var(--bg); overflow: hidden;
        }

        .editor-topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px;
            background: var(--surface); border-bottom: 1px solid var(--border);
            flex-shrink: 0; gap: 10px;
        }

        .lang-select {
            background: var(--surface-2); border: 1px solid var(--border);
            border-radius: 8px; color: var(--text);
            font-family: 'DM Mono', monospace; font-size: 12px;
            padding: 7px 12px; cursor: pointer; outline: none;
            transition: border-color 0.2s;
        }
        .lang-select:focus { border-color: rgba(200,169,110,0.4); }

        .editor-actions { display: flex; align-items: center; gap: 8px; }

        .btn {
            font-family: 'DM Sans', sans-serif; font-weight: 500; font-size: 12px;
            padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer;
            transition: all 0.18s; letter-spacing: 0.03em;
            display: inline-flex; align-items: center; gap: 6px;
        }

        .btn-save {
            background: rgba(255,255,255,0.04); border: 1px solid var(--border); color: var(--muted);
        }
        .btn-save:hover { border-color: rgba(200,169,110,0.35); color: var(--accent); }

        .btn-next {
            background: var(--surface-2); border: 1px solid rgba(200,169,110,0.25); color: var(--accent);
        }
        .btn-next:hover { background: var(--accent-dim); border-color: rgba(200,169,110,0.5); transform: translateY(-1px); }

        .btn-submit {
            background: var(--accent); color: #0d0d0f; font-weight: 500;
        }
        .btn-submit:hover { opacity: 0.86; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(200,169,110,0.2); }
        .btn-submit:active { transform: translateY(0); }

        /* Editor */
        .code-editor-wrap { flex: 1; position: relative; overflow: hidden; }

        .line-numbers {
            position: absolute; left: 0; top: 0; bottom: 0; width: 48px;
            background: var(--surface); border-right: 1px solid var(--border-2);
            padding: 16px 8px; font-family: 'DM Mono', monospace; font-size: 13px;
            line-height: 1.65; color: rgba(122,118,112,0.4); text-align: right;
            overflow: hidden; user-select: none;
        }

        #code-editor {
            position: absolute; left: 48px; top: 0; right: 0; bottom: 0;
            background: var(--bg); color: var(--code-text);
            font-family: 'DM Mono', monospace; font-size: 13px; line-height: 1.65;
            padding: 16px 16px 16px 20px;
            border: none; outline: none; resize: none; tab-size: 4;
            caret-color: var(--accent);
        }

        #code-editor::selection { background: rgba(200,169,110,0.15); }

        /* Status bar */
        .statusbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 6px 16px; background: var(--surface); border-top: 1px solid var(--border);
            font-family: 'DM Mono', monospace; font-size: 11px; color: var(--muted);
            flex-shrink: 0;
        }

        .statusbar-left { display: flex; gap: 16px; align-items: center; }

        .status-dot {
            width: 6px; height: 6px; border-radius: 50%; background: var(--accent);
            box-shadow: 0 0 6px rgba(200,169,110,0.5);
        }

        /* ── MODALS ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.75); z-index: 999;
            align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.show { display: flex; }

        .modal {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 18px; padding: 40px 36px;
            max-width: 420px; width: 90%; text-align: center;
            box-shadow: 0 40px 100px rgba(0,0,0,0.6);
            animation: modalIn 0.3s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.94) translateY(12px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-icon { font-size: 40px; margin-bottom: 16px; }

        .modal h2 {
            font-family: 'Playfair Display', serif;
            font-size: 22px; font-weight: 400; color: var(--text); margin-bottom: 10px;
        }

        .modal p  { color: var(--muted); font-size: 13px; line-height: 1.65; margin-bottom: 28px; }

        .modal-btns { display: flex; gap: 10px; justify-content: center; }

        .btn-modal-cancel {
            background: rgba(255,255,255,0.04); border: 1px solid var(--border); color: var(--muted);
            padding: 10px 20px; border-radius: 9px;
            font-family: 'DM Sans', sans-serif; font-size: 13px; cursor: pointer;
            transition: color 0.2s, border-color 0.2s;
        }
        .btn-modal-cancel:hover { color: var(--text); border-color: rgba(255,255,255,0.15); }

        .btn-modal-submit {
            background: var(--accent); color: #0d0d0f;
            padding: 10px 24px; border-radius: 9px; border: none;
            font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500;
            cursor: pointer; transition: opacity 0.2s;
        }
        .btn-modal-submit:hover { opacity: 0.86; }

        /* Save flash */
        .save-flash {
            position: fixed; bottom: 56px; right: 20px;
            background: var(--surface); border: 1px solid rgba(92,173,130,0.35);
            color: #6ec99a; font-size: 12px; font-weight: 500;
            padding: 9px 16px; border-radius: 9px;
            opacity: 0; transform: translateY(8px);
            transition: all 0.25s; pointer-events: none; z-index: 200;
            display: flex; align-items: center; gap: 7px;
        }
        .save-flash.show { opacity: 1; transform: translateY(0); }

        /* Submitting overlay */
        .submitting-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(13,13,15,0.92); z-index: 9999;
            align-items: center; justify-content: center;
            flex-direction: column; gap: 20px;
            backdrop-filter: blur(6px);
        }
        .submitting-overlay.show { display: flex; }

        .submitting-spinner {
            width: 52px; height: 52px;
            border: 3px solid rgba(200,169,110,0.15);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .submitting-overlay h2 {
            font-family: 'Playfair Display', serif;
            font-size: 20px; font-weight: 400; color: var(--text);
        }
        .submitting-overlay p { font-size: 13px; color: var(--muted); }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.07); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.12); }
    </style>
</head>
<body>

<!-- Submitting overlay -->
<div class="submitting-overlay" id="submitting-overlay">
    <div class="submitting-spinner"></div>
    <h2>Submitting Coding Test…</h2>
    <p>Please wait while we save your answers.</p>
</div>

<!-- TOP BAR -->
<div class="topbar">
    <div class="topbar-left">
        <div class="logo-mark">R</div>
        <span class="round-badge">Round 2 · Coding</span>
        <span class="job-label"><strong><?= htmlspecialchars($application['job_title']) ?></strong></span>
    </div>

    <div class="topbar-center">
        <div class="timer-wrap">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:0.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span id="timer">--:--</span>
        </div>
    </div>

    <div class="topbar-right">
        <div class="candidate-dot"><?= strtoupper(substr($_SESSION['full_name'] ?? $_SESSION['name'] ?? 'C', 0, 1)) ?></div>
        <span><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Candidate') ?></span>
    </div>
</div>

<!-- MAIN -->
<div class="main">

    <!-- LEFT: Problem panel -->
    <div class="problem-panel">
        <div class="problem-tabs">
            <?php foreach ($problems as $i => $prob):
                $dc = $prob['difficulty'] === 'easy' ? '#5cad82' : ($prob['difficulty'] === 'medium' ? '#e6a845' : '#e05c5c');
            ?>
                <button class="prob-tab <?= $i === 0 ? 'active' : '' ?>"
                        onclick="switchProblem(<?= $i ?>)" id="tab-<?= $i ?>">
                    <span class="diff-dot" style="background:<?= $dc ?>"></span>
                    Q<?= $i+1 ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="progress-row">
            <span class="prog-label">Progress</span>
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
                        <div class="io-box constraints"><?= htmlspecialchars($prob['constraints']) ?></div>
                    </div>
                <?php endif; ?>

                <div class="prob-status-row">
                    <span class="status-chip" id="chip-<?= $i ?>">Not saved</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- RIGHT: Editor panel -->
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
                <button class="btn btn-save" onclick="saveCurrentAnswer()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Save Answer
                </button>
                <button class="btn btn-next" id="btn-next" onclick="nextProblem()">
                    Next
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
                <button class="btn btn-submit" id="btn-submit" style="display:none" onclick="showSubmitModal()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Submit All
                </button>
            </div>
        </div>

        <div class="code-editor-wrap">
            <div class="line-numbers" id="line-numbers">1</div>
            <textarea id="code-editor"
                      placeholder="// Write your solution here…"
                      oninput="updateLineNumbers()"
                      onscroll="syncScroll()"
                      onkeydown="handleTab(event)"
                      spellcheck="false"></textarea>
        </div>

        <div class="statusbar">
            <div class="statusbar-left">
                <div style="display:flex;align-items:center;gap:6px">
                    <div class="status-dot"></div>
                    <span id="status-lang">Python</span>
                </div>
                <span id="status-lines">1 line</span>
                <span id="status-chars">0 chars</span>
            </div>
            <div style="color:var(--muted)">Problem <span id="status-prob">1</span> / <?= $total_problems ?></div>
        </div>
    </div>
</div>

<!-- Save flash -->
<div class="save-flash" id="save-flash">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    Answer saved
</div>

<!-- Submit Modal -->
<div class="modal-overlay" id="submit-modal">
    <div class="modal">
        <div class="modal-icon">📤</div>
        <h2>Submit Coding Test?</h2>
        <p>You are about to submit all your answers. This action cannot be undone.<br><br>
            <span id="unanswered-warn" style="font-weight:500"></span>
        </p>
        <div class="modal-btns">
            <button class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
            <button class="btn-modal-submit" onclick="submitTest()">Submit Now</button>
        </div>
    </div>
</div>

<!-- Hidden form -->
<form id="submit-form" method="POST" action="../scripts/submit_coding.php">
    <input type="hidden" name="application_id" value="<?= $application_id ?>">
    <input type="hidden" name="attempt_id"      value="<?= $coding_attempt_id ?>">
    <input type="hidden" name="job_id"          value="<?= $application['job_id'] ?>">
    <?php foreach ($problems as $i => $prob): ?>
        <input   type="hidden" name="problem_ids[]" value="<?= $prob['id'] ?>">
        <textarea              name="codes[]"        id="hidden-code-<?= $i ?>" style="display:none"></textarea>
        <input   type="hidden" name="languages[]"    id="hidden-lang-<?= $i ?>" value="python">
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

    // Deactivate current
    document.getElementById(`problem-${currentProblem}`).style.display = 'none';
    document.getElementById(`tab-${currentProblem}`).classList.remove('active');
    const dot = document.getElementById(`prog-${currentProblem}`);
    dot.classList.remove('active');
    if (answers[currentProblem] && answers[currentProblem].code.trim()) dot.classList.add('done');
    else dot.classList.remove('done');

    // Activate new
    currentProblem = idx;
    document.getElementById(`problem-${currentProblem}`).style.display = 'block';
    document.getElementById(`tab-${currentProblem}`).classList.add('active');
    const newDot = document.getElementById(`prog-${currentProblem}`);
    newDot.classList.remove('done');
    newDot.classList.add('active');

    if (answers[currentProblem]) {
        document.getElementById('lang-select').value = answers[currentProblem].lang;
        document.getElementById('code-editor').value = answers[currentProblem].code;
    } else {
        document.getElementById('code-editor').value = templates[document.getElementById('lang-select').value];
    }

    updateLineNumbers();
    updateNavButtons();
    document.getElementById('status-prob').textContent = currentProblem + 1;
    document.getElementById('status-lang').textContent = capFirst(document.getElementById('lang-select').value);
}

function updateNavButtons() {
    const isLast = currentProblem === totalProblems - 1;
    document.getElementById('btn-next').style.display   = isLast ? 'none'         : 'inline-flex';
    document.getElementById('btn-submit').style.display = isLast ? 'inline-flex'  : 'none';
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
    document.getElementById('status-lang').textContent = capFirst(lang);
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
    if (unanswered > 0) {
        warn.textContent = `⚠ ${unanswered} problem(s) have no saved answer.`;
        warn.style.color = 'var(--warn)';
    } else {
        warn.textContent = '✓ All problems answered and ready to submit.';
        warn.style.color = 'var(--green)';
    }
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

function capFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Auto-save every 60s
setInterval(() => saveCurrentAnswer(false), 60000);

// Warn on page leave
window.addEventListener('beforeunload', e => { e.preventDefault(); e.returnValue = ''; });
</script>
</body>
</html>