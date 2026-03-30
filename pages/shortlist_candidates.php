<!-- FILE: pages/shortlist_candidates.php -->
<?php 
require_once '../config/db.php';
redirectIfNotLoggedIn();
if (getUserType() != 'recruiter') {
    header('Location: candidate_dashboard.php');
    exit();
}

$job_id = intval($_GET['job_id'] ?? 0);
$recruiter_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
$stmt->execute([$job_id, $recruiter_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) { header('Location: recruiter_dashboard.php'); exit(); }

$deadline = strtotime($job['application_deadline']);
$now = time();
$deadline_passed = ($deadline < $now);

$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as candidate_name, u.email as candidate_email 
    FROM applications a 
    JOIN users u ON a.candidate_id = u.id 
    WHERE a.job_id = ? AND a.status IN ('scored', 'test_completed', 'shortlisted')
    ORDER BY a.score DESC, a.applied_at ASC
");
$stmt->execute([$job_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$shortlisted_count = 0;
$test_completed_count = 0;
foreach ($applications as $app) {
    if ($app['status'] == 'shortlisted') $shortlisted_count++;
    if ($app['status'] == 'test_completed') $test_completed_count++;
}

function scoreColor($s) {
    if ($s >= 70) return '#6ec99a';
    if ($s >= 50) return '#c8a96e';
    return '#e87878';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shortlist Candidates — <?= htmlspecialchars($job['title']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d0d0f;
            --surface: #141418;
            --surface-2: #1a1a1f;
            --surface-3: #1f1f26;
            --border: rgba(255,255,255,0.07);
            --border-hover: rgba(255,255,255,0.14);
            --accent: #c8a96e;
            --accent-dim: rgba(200,169,110,0.1);
            --accent-border: rgba(200,169,110,0.3);
            --text: #f0ece4;
            --muted: #7a7670;
            --muted-2: #555250;
            --green: #5cad82;
            --green-dim: rgba(92,173,130,0.1);
            --green-border: rgba(92,173,130,0.3);
            --red: #e05c5c;
            --red-dim: rgba(224,92,92,0.1);
            --red-border: rgba(224,92,92,0.25);
            --blue: #6ea8c8;
            --blue-dim: rgba(110,168,200,0.1);
            --blue-border: rgba(110,168,200,0.28);
        }

        html, body { min-height: 100%; background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; font-weight: 300; }

        body {
            background-image:
                radial-gradient(ellipse 70% 40% at 80% 5%, rgba(200,169,110,0.05) 0%, transparent 55%),
                radial-gradient(ellipse 40% 60% at 5% 95%, rgba(80,60,130,0.04) 0%, transparent 55%);
        }
        body::before {
            content: ''; position: fixed; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none; z-index: 0;
        }

        /* ── NAV ── */
        .nav-bar {
            position: relative; z-index: 10;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 28px; height: 60px;
            background: var(--surface); border-bottom: 1px solid var(--border);
        }
        .nav-brand { display: flex; align-items: center; gap: 12px; }
        .logo-mark {
            width: 32px; height: 32px; border-radius: 8px; background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 15px; font-weight: 600; color: #0d0d0f;
        }
        .nav-title { font-family: 'Playfair Display', serif; font-size: 15px; color: var(--text); }
        .nav-right { display: flex; align-items: center; gap: 8px; }
        .nav-link {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 13px; color: var(--muted); text-decoration: none;
            padding: 7px 14px; border-radius: 8px; border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s;
        }
        .nav-link:hover { color: var(--text); border-color: var(--border-hover); }

        /* ── PAGE ── */
        .page {
            position: relative; z-index: 1;
            max-width: 1000px; margin: 0 auto;
            padding: 36px 24px 120px;
        }

        /* Page header */
        .page-header { margin-bottom: 28px; animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both; }
        .eyebrow { font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--accent); margin-bottom: 10px; }
        .page-header h1 { font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 400; color: var(--text); margin-bottom: 6px; }
        .page-header .subtitle { font-size: 13px; color: var(--muted); }

        /* Stats */
        .stats-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px;
            margin-bottom: 28px;
            animation: fadeUp 0.5s 0.06s cubic-bezier(0.22,1,0.36,1) both;
        }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 20px;
            position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: var(--stat-color, var(--accent)); opacity: 0.6;
        }
        .stat-num { font-family: 'Playfair Display', serif; font-size: 30px; font-weight: 400; line-height: 1; margin-bottom: 5px; }
        .stat-label { font-size: 12px; color: var(--muted); letter-spacing: 0.04em; }

        /* Alert */
        .alert {
            border-radius: 12px; padding: 14px 18px; font-size: 13px;
            margin-bottom: 20px; border: 1px solid;
            display: flex; align-items: center; gap: 10px;
            animation: fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) both;
        }
        .alert-warning { background: rgba(200,184,110,0.1); color: #c8b86e; border-color: rgba(200,184,110,0.3); }
        .alert-error   { background: var(--red-dim); color: #e87878; border-color: var(--red-border); }

        .btn-back {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 13px; color: var(--muted); text-decoration: none;
            padding: 9px 16px; border-radius: 9px; border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s;
            margin-top: 12px;
        }
        .btn-back:hover { color: var(--text); border-color: var(--border-hover); }

        /* ── MODE SELECTOR ── */
        .mode-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; padding: 24px;
            margin-bottom: 20px;
            animation: fadeUp 0.5s 0.1s cubic-bezier(0.22,1,0.36,1) both;
        }
        .mode-card-title {
            font-size: 12px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px;
        }
        .mode-card-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .mode-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 0; }

        .mode-btn {
            padding: 18px 20px; border: 1px solid var(--border);
            background: var(--surface-2); border-radius: 12px;
            cursor: pointer; transition: border-color 0.2s, background 0.2s;
            text-align: left;
        }
        .mode-btn:hover { border-color: var(--border-hover); background: var(--surface-3); }
        .mode-btn.active { border-color: var(--accent-border); background: var(--accent-dim); }

        .mode-btn-icon { font-size: 20px; margin-bottom: 8px; }
        .mode-btn-title { font-size: 14px; font-weight: 500; color: var(--text); margin-bottom: 4px; }
        .mode-btn-desc { font-size: 12px; color: var(--muted); }

        /* Auto settings */
        .auto-settings {
            display: none; margin-top: 20px;
            padding-top: 20px; border-top: 1px solid var(--border);
        }
        .auto-settings.active { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .auto-settings label { font-size: 12px; letter-spacing: 0.07em; text-transform: uppercase; color: var(--muted); }

        .auto-settings input[type="number"] {
            width: 100px; background: var(--surface-2); border: 1px solid var(--border);
            border-radius: 9px; padding: 10px 14px;
            font-family: 'Playfair Display', serif; font-size: 20px;
            color: var(--accent); text-align: center; outline: none;
            transition: border-color 0.2s;
        }
        .auto-settings input[type="number"]:focus { border-color: var(--accent-border); }
        .auto-settings-hint { font-size: 12px; color: var(--muted-2); }

        /* ── SECTION LABEL ── */
        .section-label {
            display: flex; align-items: center; gap: 12px;
            font-size: 12px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 16px; margin-top: 28px;
        }
        .section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        /* ── CANDIDATE CARD ── */
        .candidate-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; overflow: hidden; margin-bottom: 10px;
            transition: border-color 0.2s;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        .candidate-card:hover { border-color: var(--border-hover); }
        .candidate-card.is-selected { border-color: var(--green-border); }
        .candidate-card.is-shortlisted { border-color: var(--blue-border); }
        .candidate-card.will-auto { border-color: var(--green-border); }

        .card-main {
            display: flex; align-items: center; gap: 14px;
            padding: 16px 20px; cursor: pointer;
        }

        /* Custom checkbox */
        .custom-check {
            width: 20px; height: 20px; border-radius: 5px;
            border: 1px solid var(--border); background: var(--surface-2);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; transition: all 0.15s; cursor: pointer;
        }
        .custom-check.checked { background: var(--green-dim); border-color: var(--green-border); }
        .custom-check.checked svg { display: block; }
        .custom-check svg { display: none; }
        .custom-check.disabled { opacity: 0.5; cursor: default; }

        /* Rank badge */
        .rank-badge {
            width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 11px; font-weight: 600;
        }
        .rank-1 { background: rgba(255,215,0,0.12); color: #ffd700; border: 1px solid rgba(255,215,0,0.28); }
        .rank-2 { background: rgba(192,192,192,0.1); color: #c0c0c0; border: 1px solid rgba(192,192,192,0.22); }
        .rank-3 { background: rgba(205,127,50,0.12); color: #cd7f32; border: 1px solid rgba(205,127,50,0.26); }
        .rank-other { background: rgba(255,255,255,0.04); color: var(--muted); border: 1px solid var(--border); }

        .cand-info { flex: 1; min-width: 0; }
        .cand-name { font-size: 14px; font-weight: 500; color: var(--text); margin-bottom: 2px; }
        .cand-email { font-size: 12px; color: var(--muted); }

        .score-chip {
            font-family: 'Playfair Display', serif; font-size: 18px;
            font-weight: 400; flex-shrink: 0; min-width: 60px; text-align: right;
        }

        .status-pill {
            font-size: 10px; font-weight: 500; letter-spacing: 0.07em; text-transform: uppercase;
            padding: 4px 10px; border-radius: 20px; white-space: nowrap; flex-shrink: 0;
        }
        .pill-shortlisted { background: var(--blue-dim); color: var(--blue); border: 1px solid var(--blue-border); }
        .pill-will-select { background: var(--green-dim); color: #6ec99a; border: 1px solid var(--green-border); }
        .pill-skip        { background: rgba(255,255,255,0.03); color: var(--muted-2); border: 1px solid var(--border); }

        .expand-btn {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 11px; color: var(--muted); background: none;
            border: 1px solid var(--border); border-radius: 7px;
            padding: 5px 10px; cursor: pointer; flex-shrink: 0;
            transition: color 0.2s, border-color 0.2s;
        }
        .expand-btn:hover { color: var(--accent); border-color: var(--accent-border); }

        /* Skills strip */
        .skills-strip {
            display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
            padding: 0 20px 12px; border-bottom: 1px solid var(--border);
        }
        .skill-tag {
            font-size: 11px; padding: 3px 9px; border-radius: 20px;
            background: var(--surface-2); border: 1px solid var(--border);
            color: var(--muted); font-family: 'DM Mono', monospace;
        }

        /* Resume drawer */
        .resume-drawer {
            display: none; border-top: 1px solid var(--border);
            background: var(--surface-2); padding: 20px;
        }
        .resume-drawer.open { display: block; }

        .resume-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;
        }
        .resume-cell {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 14px;
        }
        .resume-cell.full { grid-column: 1 / -1; }
        .resume-cell-label {
            font-size: 10px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 7px;
        }
        .resume-cell-value { font-size: 13px; color: var(--text); line-height: 1.5; }

        .skills-list { display: flex; flex-wrap: wrap; gap: 6px; }
        .skill-chip {
            font-size: 11px; padding: 4px 10px; border-radius: 20px;
            background: var(--accent-dim); border: 1px solid var(--accent-border);
            color: var(--accent); font-family: 'DM Mono', monospace;
        }

        .resume-actions { display: flex; align-items: center; gap: 10px; }

        .btn-download {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; color: var(--muted); text-decoration: none;
            padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s;
        }
        .btn-download:hover { color: var(--accent); border-color: var(--accent-border); background: var(--accent-dim); }

        .btn-quick-shortlist {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; color: #6ec99a;
            padding: 8px 14px; border-radius: 8px;
            border: 1px solid var(--green-border);
            background: var(--green-dim); cursor: pointer;
            transition: background 0.2s;
            font-family: 'DM Sans', sans-serif;
        }
        .btn-quick-shortlist:hover { background: rgba(92,173,130,0.18); }

        /* ── STICKY FOOTER ── */
        .sticky-footer {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 50;
            background: var(--surface); border-top: 1px solid var(--border);
            padding: 16px 32px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 -12px 32px rgba(0,0,0,0.3);
        }

        .selection-info {
            display: flex; align-items: center; gap: 10px;
        }
        .selection-count {
            font-family: 'Playfair Display', serif; font-size: 22px; color: var(--accent);
        }
        .selection-label { font-size: 13px; color: var(--muted); }

        .footer-actions { display: flex; align-items: center; gap: 10px; }

        .btn-cancel {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 13px; color: var(--muted); text-decoration: none;
            padding: 10px 18px; border-radius: 10px; border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s;
        }
        .btn-cancel:hover { color: var(--text); border-color: var(--border-hover); }

        .btn-submit {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 500;
            padding: 11px 22px; border-radius: 10px;
            border: none; background: var(--accent); color: #0d0d0f;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            transition: opacity 0.2s, transform 0.15s;
        }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-submit:active { transform: translateY(0); }

        /* Empty / blocked */
        .empty-state {
            text-align: center; padding: 64px 32px; color: var(--muted);
            background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
        }
        .empty-state .icon { font-size: 36px; margin-bottom: 14px; }
        .empty-state p { font-size: 14px; line-height: 1.7; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .candidate-card:nth-child(1) { animation-delay: 0.08s; }
        .candidate-card:nth-child(2) { animation-delay: 0.13s; }
        .candidate-card:nth-child(3) { animation-delay: 0.18s; }
        .candidate-card:nth-child(4) { animation-delay: 0.23s; }
        .candidate-card:nth-child(5) { animation-delay: 0.28s; }

        @media (max-width: 680px) {
            .mode-buttons { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .resume-grid { grid-template-columns: 1fr; }
            .resume-cell.full { grid-column: 1; }
        }
    </style>
</head>
<body>

<!-- Nav -->
<nav class="nav-bar">
    <div class="nav-brand">
        <div class="logo-mark">R</div>
        <span class="nav-title">RPA Recruitment</span>
    </div>
    <div class="nav-right">
        <a href="view_applications.php?job_id=<?= $job_id ?>" class="nav-link">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            All Applications
        </a>
        <a href="recruiter_dashboard.php" class="nav-link">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Dashboard
        </a>
    </div>
</nav>

<div class="page">

    <!-- Page header -->
    <div class="page-header">
        <div class="eyebrow">Recruiter · Shortlisting</div>
        <h1>Shortlist Candidates</h1>
        <div class="subtitle"><?= htmlspecialchars($job['title']) ?><?= !empty($job['location']) ? ' &nbsp;·&nbsp; ' . htmlspecialchars($job['location']) : '' ?></div>
    </div>

    <?php if (!$deadline_passed): ?>

        <div class="alert alert-warning">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Application deadline hasn't passed yet. Closes <?= date('M d, Y · h:i A', $deadline) ?>
        </div>
        <a href="recruiter_dashboard.php" class="btn-back">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Dashboard
        </a>

    <?php elseif (count($applications) == 0): ?>

        <div class="empty-state">
            <div class="icon">📭</div>
            <p>No scored applications available for this job yet.</p>
        </div>
        <a href="recruiter_dashboard.php" class="btn-back">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Dashboard
        </a>

    <?php else: ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="--stat-color: var(--accent);">
                <div class="stat-num" style="color:var(--accent)"><?= count($applications) ?></div>
                <div class="stat-label">Total Scored</div>
            </div>
            <div class="stat-card" style="--stat-color: var(--blue);">
                <div class="stat-num" style="color:var(--blue)"><?= $shortlisted_count ?></div>
                <div class="stat-label">Already Shortlisted</div>
            </div>
            <div class="stat-card" style="--stat-color: var(--green);">
                <div class="stat-num" style="color:#6ec99a"><?= $test_completed_count ?></div>
                <div class="stat-label">Test Completed</div>
            </div>
        </div>

        <!-- Mode selector -->
        <div class="mode-card">
            <div class="mode-card-title">Shortlisting Method</div>
            <div class="mode-buttons">
                <div class="mode-btn" id="manual-mode-btn" onclick="selectMode('manual')">
                    <div class="mode-btn-icon">✋</div>
                    <div class="mode-btn-title">Manual Selection</div>
                    <div class="mode-btn-desc">Review and select candidates yourself</div>
                </div>
                <div class="mode-btn active" id="auto-mode-btn" onclick="selectMode('auto')">
                    <div class="mode-btn-icon">🤖</div>
                    <div class="mode-btn-title">Automatic Selection</div>
                    <div class="mode-btn-desc">System selects top-scoring candidates</div>
                </div>
            </div>

            <div class="auto-settings active" id="auto-settings">
                <label>Shortlist top</label>
                <input type="number" id="auto-count"
                       value="<?= min($job['auto_shortlist_count'], count($applications)) ?>"
                       min="1" max="<?= count($applications) ?>">
                <span class="auto-settings-hint">candidates by score</span>
            </div>
        </div>

        <!-- Manual list -->
        <div id="manual-list" style="display:none">
            <div class="section-label">Select Candidates</div>
            <form id="manual-form" method="POST" action="../scripts/shortlist_handler.php">
                <input type="hidden" name="job_id" value="<?= $job_id ?>">
                <input type="hidden" name="mode" value="manual">

                <?php foreach ($applications as $idx => $app):
                    $skills = array_map('trim', explode(',', $app['extracted_skills']));
                    $rNum = $idx + 1;
                    $rClass = $rNum === 1 ? 'rank-1' : ($rNum === 2 ? 'rank-2' : ($rNum === 3 ? 'rank-3' : 'rank-other'));
                    $isShortlisted = $app['status'] === 'shortlisted';
                    $sc = scoreColor($app['score']);
                ?>
                <div class="candidate-card <?= $isShortlisted ? 'is-shortlisted' : '' ?>" id="card-m-<?= $app['id'] ?>">
                    <div class="card-main" onclick="<?= $isShortlisted ? '' : "toggleCheck({$app['id']}, 'manual')" ?>">

                        <?php if ($isShortlisted): ?>
                            <div class="custom-check checked disabled">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#6ec99a" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                        <?php else: ?>
                            <div class="custom-check" id="chk-m-<?= $app['id'] ?>">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#6ec99a" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <input type="checkbox" name="selected[]" value="<?= $app['id'] ?>"
                                   id="input-m-<?= $app['id'] ?>" style="display:none" onchange="updateCount()">
                        <?php endif; ?>

                        <div class="rank-badge <?= $rClass ?>">#<?= $rNum ?></div>

                        <div class="cand-info">
                            <div class="cand-name"><?= htmlspecialchars($app['candidate_name']) ?></div>
                            <div class="cand-email"><?= htmlspecialchars($app['candidate_email']) ?></div>
                        </div>

                        <div class="score-chip" style="color:<?= $sc ?>"><?= number_format($app['score'], 1) ?>%</div>

                        <?php if ($isShortlisted): ?>
                            <span class="status-pill pill-shortlisted">✓ Shortlisted</span>
                        <?php endif; ?>

                        <button type="button" class="expand-btn" onclick="event.stopPropagation(); toggleDrawer('m', <?= $app['id'] ?>)">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            Resume
                        </button>
                    </div>

                    <div class="skills-strip">
                        <?php foreach (array_slice($skills, 0, 6) as $sk): ?>
                            <span class="skill-tag"><?= htmlspecialchars($sk) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($skills) > 6): ?>
                            <span class="skill-tag" style="color:var(--muted-2)">+<?= count($skills) - 6 ?> more</span>
                        <?php endif; ?>
                    </div>

                    <div class="resume-drawer" id="drawer-m-<?= $app['id'] ?>">
                        <div class="resume-grid">
                            <div class="resume-cell">
                                <div class="resume-cell-label">Applied</div>
                                <div class="resume-cell-value"><?= date('M d, Y · h:i A', strtotime($app['applied_at'])) ?></div>
                            </div>
                            <div class="resume-cell">
                                <div class="resume-cell-label">Resume Score</div>
                                <div class="resume-cell-value" style="color:<?= $sc ?>; font-family:'Playfair Display',serif; font-size:20px">
                                    <?= number_format($app['score'], 1) ?>%
                                    <span style="font-family:'DM Sans',sans-serif; font-size:12px; color:var(--muted)">
                                        · Rank #<?= $rNum ?> of <?= count($applications) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="resume-cell full">
                                <div class="resume-cell-label">Extracted Skills (<?= count($skills) ?>)</div>
                                <div class="skills-list">
                                    <?php foreach ($skills as $sk): ?>
                                        <span class="skill-chip"><?= htmlspecialchars($sk) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php if (!empty($app['cover_letter'])): ?>
                            <div class="resume-cell full">
                                <div class="resume-cell-label">Cover Letter</div>
                                <div class="resume-cell-value"><?= nl2br(htmlspecialchars($app['cover_letter'])) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="resume-actions">
                            <a href="<?= htmlspecialchars($app['resume_path']) ?>" target="_blank" class="btn-download">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                Download Resume
                            </a>
                            <?php if (!$isShortlisted): ?>
                            <button class="btn-quick-shortlist" onclick="quickShortlist(<?= $app['id'] ?>)">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Shortlist Now
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
        </div>

        <!-- Auto preview list -->
        <div id="auto-list">
            <div class="section-label">Auto-Selection Preview</div>
            <?php
            $preview_count = min($job['auto_shortlist_count'], count($applications));
            foreach ($applications as $idx => $app):
                $skills = array_map('trim', explode(',', $app['extracted_skills']));
                $rNum = $idx + 1;
                $rClass = $rNum === 1 ? 'rank-1' : ($rNum === 2 ? 'rank-2' : ($rNum === 3 ? 'rank-3' : 'rank-other'));
                $isShortlisted = $app['status'] === 'shortlisted';
                $willSelect = ($idx < $preview_count && !$isShortlisted);
                $sc = scoreColor($app['score']);
            ?>
            <div class="candidate-card <?= $willSelect ? 'will-auto' : '' ?> <?= $isShortlisted ? 'is-shortlisted' : '' ?>"
                 id="auto-card-<?= $app['id'] ?>">
                <div class="card-main">
                    <div class="custom-check <?= ($willSelect || $isShortlisted) ? 'checked' : '' ?>" style="cursor:default">
                        <?php if ($willSelect || $isShortlisted): ?>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#6ec99a" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php endif; ?>
                    </div>

                    <div class="rank-badge <?= $rClass ?>">#<?= $rNum ?></div>

                    <div class="cand-info">
                        <div class="cand-name"><?= htmlspecialchars($app['candidate_name']) ?></div>
                        <div class="cand-email"><?= htmlspecialchars($app['candidate_email']) ?></div>
                    </div>

                    <div class="score-chip" style="color:<?= $sc ?>"><?= number_format($app['score'], 1) ?>%</div>

                    <?php if ($isShortlisted): ?>
                        <span class="status-pill pill-shortlisted">Already shortlisted</span>
                    <?php elseif ($willSelect): ?>
                        <span class="status-pill pill-will-select auto-sel-pill" data-idx="<?= $idx ?>">Will shortlist</span>
                    <?php else: ?>
                        <span class="status-pill pill-skip auto-sel-pill" data-idx="<?= $idx ?>">Skipped</span>
                    <?php endif; ?>

                    <button type="button" class="expand-btn" onclick="toggleDrawer('a', <?= $app['id'] ?>)">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        Resume
                    </button>
                </div>

                <div class="skills-strip">
                    <?php foreach (array_slice($skills, 0, 6) as $sk): ?>
                        <span class="skill-tag"><?= htmlspecialchars($sk) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($skills) > 6): ?>
                        <span class="skill-tag" style="color:var(--muted-2)">+<?= count($skills) - 6 ?> more</span>
                    <?php endif; ?>
                </div>

                <div class="resume-drawer" id="drawer-a-<?= $app['id'] ?>">
                    <div class="resume-grid">
                        <div class="resume-cell">
                            <div class="resume-cell-label">Applied</div>
                            <div class="resume-cell-value"><?= date('M d, Y · h:i A', strtotime($app['applied_at'])) ?></div>
                        </div>
                        <div class="resume-cell">
                            <div class="resume-cell-label">Resume Score</div>
                            <div class="resume-cell-value" style="color:<?= $sc ?>; font-family:'Playfair Display',serif; font-size:20px">
                                <?= number_format($app['score'], 1) ?>%
                                <span style="font-family:'DM Sans',sans-serif; font-size:12px; color:var(--muted)">
                                    · Rank #<?= $rNum ?> of <?= count($applications) ?>
                                </span>
                            </div>
                        </div>
                        <div class="resume-cell full">
                            <div class="resume-cell-label">Extracted Skills (<?= count($skills) ?>)</div>
                            <div class="skills-list">
                                <?php foreach ($skills as $sk): ?>
                                    <span class="skill-chip"><?= htmlspecialchars($sk) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if (!empty($app['cover_letter'])): ?>
                        <div class="resume-cell full">
                            <div class="resume-cell-label">Cover Letter</div>
                            <div class="resume-cell-value"><?= nl2br(htmlspecialchars($app['cover_letter'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="resume-actions">
                        <a href="<?= htmlspecialchars($app['resume_path']) ?>" target="_blank" class="btn-download">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Download Resume
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<!-- Sticky footer -->
<?php if ($deadline_passed && count($applications) > 0): ?>
<div class="sticky-footer">
    <div class="selection-info">
        <div class="selection-count" id="sel-count">0</div>
        <div class="selection-label" id="sel-label">candidates will be shortlisted</div>
    </div>
    <div class="footer-actions">
        <a href="recruiter_dashboard.php" class="btn-cancel">Cancel</a>
        <button type="button" class="btn-submit" onclick="submitShortlist()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Confirm Shortlist
        </button>
    </div>
</div>
<?php endif; ?>

<script>
let currentMode = 'auto';
const totalApps = <?= count($applications) ?>;
const jobId = <?= $job_id ?>;

function selectMode(mode) {
    currentMode = mode;
    document.getElementById('manual-mode-btn').classList.toggle('active', mode === 'manual');
    document.getElementById('auto-mode-btn').classList.toggle('active', mode === 'auto');
    document.getElementById('auto-settings').classList.toggle('active', mode === 'auto');
    document.getElementById('manual-list').style.display = mode === 'manual' ? 'block' : 'none';
    document.getElementById('auto-list').style.display   = mode === 'auto'   ? 'block' : 'none';
    updateCount();
}

function toggleCheck(id, prefix) {
    const chk = document.getElementById(`chk-${prefix}-${id}`);
    const input = document.getElementById(`input-${prefix}-${id}`);
    const card  = document.getElementById(`card-${prefix}-${id}`);
    const active = !input.checked;
    input.checked = active;
    chk.classList.toggle('checked', active);
    card.classList.toggle('is-selected', active);
    updateCount();
}

function toggleDrawer(prefix, id) {
    const drawer = document.getElementById(`drawer-${prefix}-${id}`);
    // close others
    document.querySelectorAll('.resume-drawer').forEach(d => {
        if (d !== drawer) d.classList.remove('open');
    });
    drawer.classList.toggle('open');
}

function updateAutoPreview() {
    const count = parseInt(document.getElementById('auto-count').value) || 0;
    document.querySelectorAll('.auto-sel-pill').forEach(pill => {
        const idx = parseInt(pill.dataset.idx);
        const card = pill.closest('.candidate-card');
        const chk  = card.querySelector('.custom-check');
        if (idx < count) {
            pill.className = 'status-pill pill-will-select auto-sel-pill';
            pill.textContent = 'Will shortlist';
            card.classList.add('will-auto');
            chk.classList.add('checked');
            chk.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#6ec99a" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
        } else {
            pill.className = 'status-pill pill-skip auto-sel-pill';
            pill.textContent = 'Skipped';
            card.classList.remove('will-auto');
            chk.classList.remove('checked');
            chk.innerHTML = '';
        }
    });
    updateCount();
}

function updateCount() {
    let n = 0;
    if (currentMode === 'manual') {
        n = document.querySelectorAll('input[name="selected[]"]:checked').length;
    } else {
        n = parseInt(document.getElementById('auto-count').value) || 0;
    }
    document.getElementById('sel-count').textContent = n;
}

function quickShortlist(appId) {
    if (!confirm('Shortlist this candidate immediately?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../scripts/shortlist_handler.php';
    [['job_id', jobId], ['mode', 'manual'], ['selected[]', appId]].forEach(([n, v]) => {
        const i = document.createElement('input');
        i.type = 'hidden'; i.name = n; i.value = v;
        form.appendChild(i);
    });
    document.body.appendChild(form);
    form.submit();
}

function submitShortlist() {
    if (currentMode === 'manual') {
        const checked = document.querySelectorAll('input[name="selected[]"]:checked').length;
        if (checked === 0) { alert('Please select at least one candidate.'); return; }
        if (confirm(`Shortlist ${checked} candidate${checked !== 1 ? 's' : ''}?`)) {
            document.getElementById('manual-form').submit();
        }
    } else {
        const count = document.getElementById('auto-count').value;
        if (confirm(`Automatically shortlist top ${count} candidates by score?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../scripts/shortlist_handler.php';
            [['job_id', jobId], ['mode', 'auto'], ['count', count]].forEach(([n, v]) => {
                const i = document.createElement('input');
                i.type = 'hidden'; i.name = n; i.value = v;
                form.appendChild(i);
            });
            document.body.appendChild(form);
            form.submit();
        }
    }
}

// Init
selectMode('auto');
document.getElementById('auto-count').addEventListener('input', updateAutoPreview);
updateAutoPreview();
</script>
</body>
</html>