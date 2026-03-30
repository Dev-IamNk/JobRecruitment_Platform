<?php
// FILE: pages/interview_results.php
require_once '../config/db.php';
require_once '../config/functions.php';

redirectIfNotLoggedIn();
if (getUserType() != 'recruiter') {
    header('Location: login.php');
    exit;
}

$recruiter_id = $_SESSION['user_id'];
$job_id       = intval($_GET['job_id'] ?? 0);

if (!$job_id) { header('Location: recruiter_dashboard.php'); exit; }

$job_q = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
$job_q->execute([$job_id, $recruiter_id]);
$job = $job_q->fetch(PDO::FETCH_ASSOC);
if (!$job) { header('Location: recruiter_dashboard.php?error=invalid_job'); exit; }

$cands_q = $pdo->prepare("
    SELECT 
        a.id as application_id, a.status,
        a.score as resume_score, a.aptitude_tech_score,
        a.coding_score, a.final_score, a.applied_at, a.resume_path,
        u.full_name, u.email
    FROM applications a
    JOIN users u ON u.id = a.candidate_id
    WHERE a.job_id = ? AND a.coding_status = 'completed'
      AND a.status NOT IN ('selected','rejected')
    ORDER BY a.final_score DESC
");
$cands_q->execute([$job_id]);
$candidates = $cands_q->fetchAll(PDO::FETCH_ASSOC);

$decided_q = $pdo->prepare("
    SELECT a.id as application_id, a.status, a.final_score, u.full_name, u.email
    FROM applications a
    JOIN users u ON u.id = a.candidate_id
    WHERE a.job_id = ? AND a.status IN ('selected','rejected')
    ORDER BY a.status ASC, a.final_score DESC
");
$decided_q->execute([$job_id]);
$decided = $decided_q->fetchAll(PDO::FETCH_ASSOC);

function scoreColor($s) {
    if ($s >= 75) return '#6ec99a';
    if ($s >= 50) return '#c8a96e';
    return '#e87878';
}

function scoreLevel($s) {
    if ($s >= 75) return 'high';
    if ($s >= 50) return 'medium';
    return 'low';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Results — <?= htmlspecialchars($job['title']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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
            --green-dim: rgba(92,173,130,0.1);
            --red: #e05c5c;
            --red-dim: rgba(224,92,92,0.1);
        }

        html, body {
            min-height: 100%; background: var(--bg); color: var(--text);
            font-family: 'DM Sans', sans-serif; font-weight: 300;
        }

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

        .btn-back {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 13px; color: var(--muted); text-decoration: none;
            padding: 7px 14px; border-radius: 8px; border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s;
        }
        .btn-back:hover { color: var(--text); border-color: rgba(255,255,255,0.14); }

        /* ── PAGE ── */
        .page {
            position: relative; z-index: 1;
            max-width: 1000px; margin: 0 auto;
            padding: 36px 24px 72px;
        }

        /* Page header */
        .page-header {
            margin-bottom: 36px;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        .eyebrow { font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--accent); margin-bottom: 10px; }
        .page-header h1 { font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 400; color: var(--text); margin-bottom: 6px; }
        .page-header .subtitle { font-size: 13px; color: var(--muted); }

        /* ── STATS ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px;
            margin-bottom: 40px;
            animation: fadeUp 0.5s 0.08s cubic-bezier(0.22,1,0.36,1) both;
        }

        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 22px 20px;
            position: relative; overflow: hidden;
            transition: border-color 0.2s;
        }
        .stat-card::before {
            content: ''; position: absolute;
            top: 0; left: 0; right: 0; height: 2px;
            background: var(--stat-color, var(--accent)); opacity: 0.6;
        }
        .stat-card:hover { border-color: rgba(200,169,110,0.2); }

        .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 34px; font-weight: 400; line-height: 1; margin-bottom: 6px;
        }
        .stat-label { font-size: 12px; color: var(--muted); letter-spacing: 0.04em; }

        /* ── SECTION TITLES ── */
        .section-title {
            display: flex; align-items: center; gap: 12px;
            font-size: 12px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 18px; margin-top: 40px;
        }
        .section-title:first-of-type { margin-top: 0; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        /* ── CANDIDATE CARD ── */
        .candidate-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; overflow: hidden;
            margin-bottom: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        .candidate-card:hover { border-color: rgba(200,169,110,0.2); }

        .card-top {
            display: flex; align-items: center; justify-content: space-between;
            padding: 22px 24px; border-bottom: 1px solid var(--border);
            gap: 12px;
        }

        .candidate-info { display: flex; align-items: center; gap: 14px; }

        .avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: var(--accent-dim); border: 1px solid rgba(200,169,110,0.2);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 18px; color: var(--accent);
            flex-shrink: 0;
        }

        .cand-name { font-size: 15px; font-weight: 500; color: var(--text); margin-bottom: 2px; }
        .cand-email { font-size: 12px; color: var(--muted); }

        .rank-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 34px; height: 34px; border-radius: 50%;
            font-family: 'Playfair Display', serif; font-size: 12px; font-weight: 600;
        }
        .rank-1 { background: rgba(255,215,0,0.12); color: #ffd700; border: 1px solid rgba(255,215,0,0.28); }
        .rank-2 { background: rgba(192,192,192,0.1); color: #c0c0c0; border: 1px solid rgba(192,192,192,0.22); }
        .rank-3 { background: rgba(205,127,50,0.12); color: #cd7f32; border: 1px solid rgba(205,127,50,0.26); }
        .rank-other { background: rgba(255,255,255,0.04); color: var(--muted); border: 1px solid var(--border); }

        /* Score pills */
        .scores-row {
            display: grid; grid-template-columns: 1fr 1fr 1fr 1.1fr;
            border-bottom: 1px solid var(--border);
        }

        .score-pill {
            padding: 18px 20px; border-right: 1px solid var(--border);
            position: relative;
        }
        .score-pill:last-child { border-right: none; }

        .score-pill::after {
            content: ''; position: absolute;
            bottom: 0; left: 20px; right: 20px; height: 2px;
            background: var(--pill-color, transparent); opacity: 0.45;
        }

        .score-pill-label {
            font-size: 10px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 8px;
        }

        .score-pill-val {
            font-family: 'Playfair Display', serif;
            font-size: 24px; font-weight: 400; line-height: 1; margin-bottom: 4px;
        }

        .score-pill-weight { font-size: 10px; color: var(--muted); }

        /* Final score pill */
        .score-pill.final { background: rgba(200,169,110,0.05); }
        .score-pill.final .score-pill-val { color: var(--accent); font-size: 28px; }
        .score-pill.final::after { background: var(--accent); opacity: 0.5; }

        /* Mini score bar */
        .score-bar-wrap { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
        .score-bar-bg { flex: 1; height: 3px; background: rgba(255,255,255,0.05); border-radius: 2px; max-width: 70px; }
        .score-bar-fill { height: 100%; border-radius: 2px; }

        /* Actions */
        .action-row {
            display: flex; align-items: center; gap: 10px;
            padding: 16px 24px; background: rgba(255,255,255,0.01);
        }

        .btn-resume {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; color: var(--muted); text-decoration: none;
            padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s, background 0.2s;
            white-space: nowrap;
        }
        .btn-resume:hover { color: var(--accent); border-color: rgba(200,169,110,0.35); background: var(--accent-dim); }

        .btn-action {
            flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif; font-weight: 500;
            padding: 10px 18px; border-radius: 9px; border: none; cursor: pointer;
            transition: opacity 0.2s, transform 0.15s;
        }
        .btn-action:hover:not(:disabled) { transform: translateY(-1px); }
        .btn-action:active { transform: translateY(0); }
        .btn-action:disabled { opacity: 0.4; cursor: not-allowed; }

        .btn-accept {
            background: rgba(92,173,130,0.15); color: #6ec99a;
            border: 1px solid rgba(92,173,130,0.3);
        }
        .btn-accept:hover:not(:disabled) { background: rgba(92,173,130,0.22); }

        .btn-reject {
            background: rgba(224,92,92,0.1); color: #e87878;
            border: 1px solid rgba(224,92,92,0.25);
        }
        .btn-reject:hover:not(:disabled) { background: rgba(224,92,92,0.16); }

        /* ── DECIDED LIST ── */
        .decided-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 20px; margin-bottom: 10px;
            display: flex; align-items: center; justify-content: space-between;
            transition: border-color 0.2s;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        .decided-card:hover { border-color: rgba(255,255,255,0.1); }

        .decided-left { display: flex; align-items: center; gap: 12px; }

        .decided-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--surface-2); border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 14px; color: var(--muted);
            flex-shrink: 0;
        }

        .decided-name { font-size: 14px; color: var(--text); font-weight: 400; margin-bottom: 2px; }
        .decided-email { font-size: 11px; color: var(--muted); }

        .decided-right { display: flex; align-items: center; gap: 14px; }

        .final-score-mono {
            font-family: 'DM Mono', monospace; font-size: 13px; color: var(--muted);
        }

        .status-badge {
            font-size: 10px; font-weight: 500; letter-spacing: 0.07em; text-transform: uppercase;
            padding: 4px 10px; border-radius: 20px;
        }
        .badge-selected { background: var(--green-dim); color: #6ec99a; border: 1px solid rgba(92,173,130,0.25); }
        .badge-rejected { background: var(--red-dim);   color: #e87878; border: 1px solid rgba(224,92,92,0.22); }

        /* Empty state */
        .empty-state {
            text-align: center; padding: 64px 32px; color: var(--muted);
            background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
        }
        .empty-state .icon { font-size: 38px; margin-bottom: 14px; }
        .empty-state p { font-size: 14px; line-height: 1.6; }

        /* ── TOAST ── */
        .toast {
            position: fixed; bottom: 28px; right: 24px; z-index: 999;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 14px 18px;
            display: flex; align-items: center; gap: 10px;
            font-size: 13px; color: var(--text);
            opacity: 0; transform: translateY(12px);
            transition: all 0.3s; max-width: 340px;
            box-shadow: 0 16px 40px rgba(0,0,0,0.4);
        }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { border-left: 3px solid #6ec99a; }
        .toast.error   { border-left: 3px solid #e87878; }

        /* Spinner */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 13px; height: 13px;
            border: 2px solid rgba(200,169,110,0.25);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            flex-shrink: 0;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 700px) {
            .stats-grid { grid-template-columns: repeat(3,1fr); }
            .scores-row { grid-template-columns: 1fr 1fr; }
            .score-pill:nth-child(2) { border-right: none; }
            .score-pill:nth-child(3) { border-top: 1px solid var(--border); }
        }
    </style>
</head>
<body>

<!-- Toast -->
<div class="toast" id="toast"></div>

<!-- Nav -->
<nav class="nav-bar">
    <div class="nav-brand">
        <div class="logo-mark">R</div>
        <span class="nav-title">RPA Recruitment</span>
    </div>
    <div class="nav-right">
        <a href="recruiter_dashboard.php" class="btn-back">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Dashboard
        </a>
    </div>
</nav>

<div class="page">

    <!-- Page header -->
    <div class="page-header">
        <div class="eyebrow">Recruiter · Final Round</div>
        <h1>Interview Results</h1>
        <div class="subtitle">
            <?= htmlspecialchars($job['title']) ?>
            <?php if (!empty($job['location'])): ?>
                &nbsp;·&nbsp; <?= htmlspecialchars($job['location']) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card" style="--stat-color: var(--accent);">
            <div class="stat-num" style="color:var(--accent)"><?= count($candidates) ?></div>
            <div class="stat-label">Awaiting Decision</div>
        </div>
        <div class="stat-card" style="--stat-color: var(--green);">
            <div class="stat-num" style="color:#6ec99a">
                <?= count(array_filter($decided, fn($d) => $d['status'] === 'selected')) ?>
            </div>
            <div class="stat-label">Selected</div>
        </div>
        <div class="stat-card" style="--stat-color: var(--red);">
            <div class="stat-num" style="color:#e87878">
                <?= count(array_filter($decided, fn($d) => $d['status'] === 'rejected')) ?>
            </div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- Awaiting decision -->
    <div class="section-title">Awaiting Your Decision</div>

    <?php if (empty($candidates)): ?>
        <div class="empty-state">
            <div class="icon">✅</div>
            <p>All candidates have been decided.<br>See the results below.</p>
        </div>
    <?php else: ?>
        <?php foreach ($candidates as $idx => $c):
            $resume  = $c['resume_score']        ?? 0;
            $apt     = $c['aptitude_tech_score'] ?? 0;
            $coding  = $c['coding_score']        ?? 0;
            $final   = $c['final_score']         ?? 0;
            $rankNum = $idx + 1;
            $rClass  = $rankNum === 1 ? 'rank-1' : ($rankNum === 2 ? 'rank-2' : ($rankNum === 3 ? 'rank-3' : 'rank-other'));
        ?>
            <div class="candidate-card" id="card-<?= $c['application_id'] ?>"
                 style="animation-delay:<?= $idx * 0.07 ?>s">

                <div class="card-top">
                    <div class="candidate-info">
                        <div class="avatar"><?= strtoupper(substr($c['full_name'], 0, 1)) ?></div>
                        <div>
                            <div class="cand-name"><?= htmlspecialchars($c['full_name']) ?></div>
                            <div class="cand-email"><?= htmlspecialchars($c['email']) ?></div>
                        </div>
                    </div>
                    <span class="rank-badge <?= $rClass ?>">#<?= $rankNum ?></span>
                </div>

                <div class="scores-row">
                    <!-- Resume -->
                    <div class="score-pill" style="--pill-color:<?= scoreColor($resume) ?>">
                        <div class="score-pill-label">Resume</div>
                        <div class="score-pill-val" style="color:<?= scoreColor($resume) ?>">
                            <?= number_format($resume, 1) ?>%
                        </div>
                        <div class="score-bar-wrap">
                            <div class="score-bar-bg">
                                <div class="score-bar-fill" style="width:<?= min($resume,100) ?>%;background:<?= scoreColor($resume) ?>"></div>
                            </div>
                        </div>
                        <div class="score-pill-weight">40% weight</div>
                    </div>

                    <!-- Aptitude -->
                    <div class="score-pill" style="--pill-color:<?= scoreColor($apt) ?>">
                        <div class="score-pill-label">Apt + Tech</div>
                        <div class="score-pill-val" style="color:<?= scoreColor($apt) ?>">
                            <?= number_format($apt, 1) ?>%
                        </div>
                        <div class="score-bar-wrap">
                            <div class="score-bar-bg">
                                <div class="score-bar-fill" style="width:<?= min($apt,100) ?>%;background:<?= scoreColor($apt) ?>"></div>
                            </div>
                        </div>
                        <div class="score-pill-weight">20% weight</div>
                    </div>

                    <!-- Coding -->
                    <div class="score-pill" style="--pill-color:<?= scoreColor($coding) ?>">
                        <div class="score-pill-label">Coding</div>
                        <div class="score-pill-val" style="color:<?= scoreColor($coding) ?>">
                            <?= number_format($coding, 1) ?>%
                        </div>
                        <div class="score-bar-wrap">
                            <div class="score-bar-bg">
                                <div class="score-bar-fill" style="width:<?= min($coding,100) ?>%;background:<?= scoreColor($coding) ?>"></div>
                            </div>
                        </div>
                        <div class="score-pill-weight">40% weight</div>
                    </div>

                    <!-- Final -->
                    <div class="score-pill final">
                        <div class="score-pill-label">Final Score</div>
                        <div class="score-pill-val"><?= number_format($final, 1) ?>%</div>
                        <div class="score-pill-weight">Combined</div>
                    </div>
                </div>

                <div class="action-row">
                    <a href="<?= htmlspecialchars($c['resume_path']) ?>" target="_blank" class="btn-resume">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Resume
                    </a>
                    <button class="btn-action btn-accept"
                            onclick="decide(<?= $c['application_id'] ?>, 'selected', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Select Candidate
                    </button>
                    <button class="btn-action btn-reject"
                            onclick="decide(<?= $c['application_id'] ?>, 'rejected', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Reject
                    </button>
                </div>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Already decided -->
    <?php if (!empty($decided)): ?>
        <div class="section-title">Already Decided</div>
        <?php foreach ($decided as $i => $d): ?>
            <div class="decided-card" style="animation-delay:<?= $i * 0.05 ?>s">
                <div class="decided-left">
                    <div class="decided-avatar"><?= strtoupper(substr($d['full_name'], 0, 1)) ?></div>
                    <div>
                        <div class="decided-name"><?= htmlspecialchars($d['full_name']) ?></div>
                        <div class="decided-email"><?= htmlspecialchars($d['email']) ?></div>
                    </div>
                </div>
                <div class="decided-right">
                    <span class="final-score-mono"><?= number_format($d['final_score'] ?? 0, 1) ?>%</span>
                    <span class="status-badge <?= $d['status'] === 'selected' ? 'badge-selected' : 'badge-rejected' ?>">
                        <?= $d['status'] === 'selected' ? '✓ Selected' : '✗ Rejected' ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
function decide(applicationId, decision, btn) {
    const label = decision === 'selected' ? 'select' : 'reject';
    if (!window.confirm(`Are you sure you want to ${label} this candidate?\n\nAn email notification will be sent to them.`)) return;

    const card = document.getElementById('card-' + applicationId);
    card.querySelectorAll('button').forEach(b => b.disabled = true);

    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<div class="spinner"></div> Sending…';

    const formData = new FormData();
    formData.append('application_id', applicationId);
    formData.append('decision', decision);

    fetch('../scripts/accept_reject_handler.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(
                    decision === 'selected'
                        ? '✓ Candidate selected — email queued.'
                        : '✗ Candidate rejected — email queued.',
                    'success'
                );
                setTimeout(() => {
                    card.style.transition = 'opacity 0.35s, transform 0.35s';
                    card.style.opacity    = '0';
                    card.style.transform  = 'translateY(-8px)';
                    setTimeout(() => { card.remove(); setTimeout(() => location.reload(), 200); }, 380);
                }, 1400);
            } else {
                showToast('Error: ' + (data.error || 'Something went wrong'), 'error');
                card.querySelectorAll('button').forEach(b => b.disabled = false);
                btn.innerHTML = originalHTML;
            }
        })
        .catch(() => {
            showToast('Network error. Please try again.', 'error');
            card.querySelectorAll('button').forEach(b => b.disabled = false);
            btn.innerHTML = originalHTML;
        });
}

function showToast(msg, type) {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className   = `toast show ${type}`;
    setTimeout(() => toast.className = 'toast', 3500);
}
</script>
</body>
</html>