<!-- FILE: pages/recruiter_dashboard.php -->
<?php 
require_once '../config/db.php';
redirectIfNotLoggedIn();
if (getUserType() != 'recruiter') {
    header('Location: candidate_dashboard.php');
    exit();
}

$recruiter_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM jobs WHERE recruiter_id = ? ORDER BY created_at DESC");
$stmt->execute([$recruiter_id]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruiter Dashboard — RPA Recruitment</title>
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
            --yellow: #c8b86e;
            --yellow-dim: rgba(200,184,110,0.1);
            --yellow-border: rgba(200,184,110,0.3);
        }

        html, body {
            min-height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-weight: 300;
        }

        body {
            background-image:
                radial-gradient(ellipse 70% 40% at 80% 5%, rgba(200,169,110,0.05) 0%, transparent 55%),
                radial-gradient(ellipse 40% 60% at 5% 95%, rgba(80,60,130,0.04) 0%, transparent 55%);
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }

        /* ── NAV ── */
        .nav-bar {
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            height: 60px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .nav-brand { display: flex; align-items: center; gap: 12px; }

        .logo-mark {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 15px; font-weight: 600; color: #0d0d0f;
        }

        .nav-title { font-family: 'Playfair Display', serif; font-size: 15px; color: var(--text); }

        .nav-right { display: flex; align-items: center; gap: 8px; }

        .nav-greeting {
            font-size: 12px;
            color: var(--muted);
            padding: 0 12px;
            border-right: 1px solid var(--border);
            margin-right: 4px;
        }

        .nav-link {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 13px; color: var(--muted); text-decoration: none;
            padding: 7px 14px; border-radius: 8px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s, background 0.2s;
        }
        .nav-link:hover { color: var(--text); border-color: var(--border-hover); }
        .nav-link.primary {
            background: var(--accent-dim);
            border-color: var(--accent-border);
            color: var(--accent);
        }
        .nav-link.primary:hover { background: rgba(200,169,110,0.16); }

        /* ── PAGE ── */
        .page {
            position: relative; z-index: 1;
            max-width: 1000px; margin: 0 auto;
            padding: 36px 24px 80px;
        }

        /* Page header */
        .page-header {
            display: flex; align-items: flex-end; justify-content: space-between;
            margin-bottom: 36px;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }

        .page-header-left {}
        .eyebrow {
            font-size: 11px; letter-spacing: 0.1em;
            text-transform: uppercase; color: var(--accent); margin-bottom: 10px;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px; font-weight: 400; color: var(--text); margin-bottom: 6px;
        }
        .page-header .subtitle { font-size: 13px; color: var(--muted); }

        /* Alert */
        .alert {
            border-radius: 12px; padding: 14px 18px;
            font-size: 13px; margin-bottom: 24px;
            border: 1px solid;
            display: flex; align-items: center; gap: 10px;
            animation: fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) both;
        }
        .alert-success {
            background: var(--green-dim); color: #6ec99a;
            border-color: var(--green-border);
        }
        .alert a {
            color: inherit; text-decoration: underline;
            text-underline-offset: 3px; opacity: 0.8;
        }
        .alert a:hover { opacity: 1; }

        /* ── SECTION LABEL ── */
        .section-label {
            display: flex; align-items: center; gap: 12px;
            font-size: 12px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 18px;
        }
        .section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        /* ── STATS ROW ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px;
            margin-bottom: 36px;
            animation: fadeUp 0.5s 0.06s cubic-bezier(0.22,1,0.36,1) both;
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

        /* ── JOB CARD ── */
        .job-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; overflow: hidden;
            margin-bottom: 20px;
            transition: border-color 0.2s;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        .job-card:hover { border-color: rgba(200,169,110,0.2); }

        /* Card top bar */
        .job-card-header {
            display: flex; align-items: flex-start; justify-content: space-between;
            padding: 22px 24px; border-bottom: 1px solid var(--border); gap: 16px;
        }

        .job-title-wrap {}
        .job-title {
            font-family: 'Playfair Display', serif;
            font-size: 18px; font-weight: 400; color: var(--text); margin-bottom: 6px;
        }
        .job-meta { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .job-meta-item {
            display: flex; align-items: center; gap: 5px;
            font-size: 12px; color: var(--muted);
        }
        .job-meta-item svg { opacity: 0.5; }

        /* Deadline badge */
        .deadline-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 20px;
            font-size: 11px; font-weight: 500; letter-spacing: 0.04em;
            white-space: nowrap; flex-shrink: 0;
        }
        .badge-warning {
            background: var(--yellow-dim); color: var(--yellow);
            border: 1px solid var(--yellow-border);
        }
        .badge-expired {
            background: var(--red-dim); color: #e87878;
            border: 1px solid var(--red-border);
        }
        .badge-open {
            background: var(--green-dim); color: #6ec99a;
            border: 1px solid var(--green-border);
        }

        /* Info grid */
        .info-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            border-bottom: 1px solid var(--border);
        }

        .info-cell {
            padding: 16px 20px;
            border-right: 1px solid var(--border);
        }
        .info-cell:last-child { border-right: none; }

        .info-label {
            font-size: 10px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 6px;
        }
        .info-value {
            font-size: 13px; color: var(--text); line-height: 1.4;
        }
        .info-value.accent {
            font-family: 'Playfair Display', serif;
            font-size: 22px; color: var(--accent);
        }

        /* Skills */
        .skills-row {
            padding: 14px 20px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        }
        .skills-label {
            font-size: 10px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted); flex-shrink: 0;
        }
        .skill-tag {
            font-size: 11px; padding: 3px 10px; border-radius: 20px;
            background: var(--surface-2); border: 1px solid var(--border);
            color: var(--muted); font-family: 'DM Mono', monospace;
        }

        /* Action row */
        .action-row {
            display: flex; align-items: center; gap: 9px; flex-wrap: wrap;
            padding: 16px 20px; background: rgba(255,255,255,0.01);
        }

        .btn-action {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; font-family: 'DM Sans', sans-serif;
            padding: 9px 15px; border-radius: 9px; border: 1px solid;
            text-decoration: none; cursor: pointer;
            transition: opacity 0.2s, transform 0.15s, background 0.2s;
            white-space: nowrap;
        }
        .btn-action:hover { transform: translateY(-1px); }
        .btn-action:active { transform: translateY(0); }

        .btn-blue  { background: var(--blue-dim);   color: var(--blue);   border-color: var(--blue-border); }
        .btn-blue:hover  { background: rgba(110,168,200,0.16); }

        .btn-green { background: var(--green-dim);  color: #6ec99a; border-color: var(--green-border); }
        .btn-green:hover { background: rgba(92,173,130,0.18); }

        .btn-ghost {
            background: rgba(255,255,255,0.03);
            color: var(--muted); border-color: var(--border);
        }
        .btn-ghost:hover { color: var(--text); border-color: var(--border-hover); background: rgba(255,255,255,0.06); }

        .btn-accent { background: var(--accent-dim); color: var(--accent); border-color: var(--accent-border); }
        .btn-accent:hover { background: rgba(200,169,110,0.16); }

        /* Empty state */
        .empty-state {
            text-align: center; padding: 72px 32px; color: var(--muted);
            background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
        }
        .empty-state .icon { font-size: 40px; margin-bottom: 16px; }
        .empty-state p { font-size: 14px; line-height: 1.7; }
        .empty-state a { color: var(--accent); text-decoration: none; }
        .empty-state a:hover { text-decoration: underline; }

        /* Animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .job-card:nth-child(1) { animation-delay: 0.10s; }
        .job-card:nth-child(2) { animation-delay: 0.17s; }
        .job-card:nth-child(3) { animation-delay: 0.24s; }
        .job-card:nth-child(4) { animation-delay: 0.31s; }
        .job-card:nth-child(5) { animation-delay: 0.38s; }

        @media (max-width: 700px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .info-grid { grid-template-columns: 1fr 1fr; }
            .info-cell:nth-child(2) { border-right: none; }
            .info-cell:nth-child(3) { border-top: 1px solid var(--border); grid-column: 1 / -1; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
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
        <span class="nav-greeting">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
        <a href="post_job.php" class="nav-link primary">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Post New Job
        </a>
        <a href="../scripts/logout.php" class="nav-link">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            Logout
        </a>
    </div>
</nav>

<div class="page">

    <!-- Page header -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="eyebrow">Recruiter · Overview</div>
            <h1>My Job Postings</h1>
            <div class="subtitle"><?= count($jobs) ?> active listing<?= count($jobs) !== 1 ? 's' : '' ?> in your account</div>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <?php if ($_GET['success'] == 'posted'): ?>
            <div class="alert alert-success">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Job posted successfully!
            </div>
        <?php elseif ($_GET['success'] == 'shortlisted'): ?>
            <div class="alert alert-success">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Successfully shortlisted <?= intval($_GET['count'] ?? 0) ?> candidate(s)! &nbsp;
                <a href="view_applications.php?job_id=<?= intval($_GET['job_id'] ?? 0) ?>&filter=shortlisted">
                    View shortlisted →
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Summary stats -->
    <?php
        $total_apps = 0;
        $total_shortlisted = 0;
        foreach ($jobs as $j) {
            $s = $pdo->prepare("SELECT COUNT(*) as c FROM applications WHERE job_id = ?");
            $s->execute([$j['id']]);
            $total_apps += $s->fetch(PDO::FETCH_ASSOC)['c'];

            $s2 = $pdo->prepare("SELECT COUNT(*) as c FROM applications WHERE job_id = ? AND status = 'shortlisted'");
            $s2->execute([$j['id']]);
            $total_shortlisted += $s2->fetch(PDO::FETCH_ASSOC)['c'];
        }
    ?>
    <div class="stats-grid">
        <div class="stat-card" style="--stat-color: var(--accent);">
            <div class="stat-num" style="color:var(--accent)"><?= count($jobs) ?></div>
            <div class="stat-label">Total Listings</div>
        </div>
        <div class="stat-card" style="--stat-color: var(--blue);">
            <div class="stat-num" style="color:var(--blue)"><?= $total_apps ?></div>
            <div class="stat-label">Total Applications</div>
        </div>
        <div class="stat-card" style="--stat-color: var(--green);">
            <div class="stat-num" style="color:#6ec99a"><?= $total_shortlisted ?></div>
            <div class="stat-label">Shortlisted</div>
        </div>
    </div>

    <!-- Jobs list -->
    <div class="section-label">All Listings</div>

    <?php if (empty($jobs)): ?>
        <div class="empty-state">
            <div class="icon">📋</div>
            <p>You haven't posted any jobs yet.<br>
               <a href="post_job.php">Post your first job →</a>
            </p>
        </div>
    <?php else: ?>

        <?php foreach ($jobs as $job):
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE job_id = ?");
            $stmt->execute([$job['id']]);
            $app_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $stmt_short = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE job_id = ? AND status = 'shortlisted'");
            $stmt_short->execute([$job['id']]);
            $shortlisted = $stmt_short->fetch(PDO::FETCH_ASSOC)['count'];

            $deadline     = strtotime($job['application_deadline']);
            $now          = time();
            $deadline_passed = ($deadline < $now);
            $days_left    = ceil(($deadline - $now) / (60 * 60 * 24));

            $skills = array_map('trim', explode(',', $job['required_skills']));
        ?>

        <div class="job-card">

            <!-- Header -->
            <div class="job-card-header">
                <div class="job-title-wrap">
                    <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
                    <div class="job-meta">
                        <?php if (!empty($job['location'])): ?>
                        <span class="job-meta-item">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?= htmlspecialchars($job['location']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($job['salary_range'])): ?>
                        <span class="job-meta-item">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                            <?= htmlspecialchars($job['salary_range']) ?>
                        </span>
                        <?php endif; ?>
                        <span class="job-meta-item">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Posted <?= date('M d, Y', strtotime($job['created_at'])) ?>
                        </span>
                    </div>
                </div>

                <?php if ($deadline_passed): ?>
                    <span class="deadline-badge badge-expired">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        Deadline passed
                    </span>
                <?php elseif ($days_left <= 3): ?>
                    <span class="deadline-badge badge-warning">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?= $days_left ?> day<?= $days_left !== 1 ? 's' : '' ?> left
                    </span>
                <?php else: ?>
                    <span class="deadline-badge badge-open">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= $days_left ?> days left
                    </span>
                <?php endif; ?>
            </div>

            <!-- Info cells -->
            <div class="info-grid">
                <div class="info-cell">
                    <div class="info-label">Application Deadline</div>
                    <div class="info-value"><?= date('M d, Y · h:i A', strtotime($job['application_deadline'])) ?></div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Shortlisting Mode</div>
                    <div class="info-value">
                        <?php if ($job['shortlisting_mode'] == 'automatic'): ?>
                            Automatic — Top <?= $job['auto_shortlist_count'] ?>
                        <?php else: ?>
                            Manual Selection
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Applications</div>
                    <div class="info-value accent"><?= $app_count ?></div>
                </div>
            </div>

            <!-- Second row of info -->
            <div class="info-grid" style="border-bottom:1px solid var(--border)">
                <div class="info-cell">
                    <div class="info-label">Interview Date</div>
                    <div class="info-value">
                        <?= $job['interview_date']
                            ? date('M d, Y · h:i A', strtotime($job['interview_date']))
                            : '<span style="color:var(--muted)">Not scheduled yet</span>' ?>
                    </div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Shortlisted</div>
                    <div class="info-value" style="color:#6ec99a; font-family:'Playfair Display',serif; font-size:20px">
                        <?= $shortlisted ?>
                    </div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <?php if ($deadline_passed): ?>
                            <span style="color:#e87878">Closed · Ready to shortlist</span>
                        <?php else: ?>
                            <span style="color:#6ec99a">Accepting applications</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Skills -->
            <div class="skills-row">
                <span class="skills-label">Skills</span>
                <?php foreach (array_slice($skills, 0, 8) as $skill): ?>
                    <span class="skill-tag"><?= htmlspecialchars(trim($skill)) ?></span>
                <?php endforeach; ?>
                <?php if (count($skills) > 8): ?>
                    <span class="skill-tag" style="color:var(--muted-2)">+<?= count($skills) - 8 ?> more</span>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="action-row">
                <a href="test_results.php?job_id=<?= $job['id'] ?>" class="btn-action btn-blue">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    Test Results & Analytics
                </a>

                <a href="interview_results.php?job_id=<?= $job['id'] ?>" class="btn-action btn-accent">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    Interview Results
                </a>

                <?php if ($deadline_passed && $app_count > 0): ?>
                    <a href="shortlist_candidates.php?job_id=<?= $job['id'] ?>" class="btn-action btn-green">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Shortlist Candidates (<?= $app_count ?>)
                    </a>
                <?php endif; ?>

                <?php if ($shortlisted > 0): ?>
                    <a href="view_applications.php?job_id=<?= $job['id'] ?>&filter=shortlisted" class="btn-action btn-blue">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        View Shortlisted (<?= $shortlisted ?>)
                    </a>
                <?php endif; ?>

                <a href="edit_job.php?id=<?= $job['id'] ?>" class="btn-action btn-ghost">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Job
                </a>
            </div>

        </div>

        <?php endforeach; ?>
    <?php endif; ?>

</div>

</body>
</html>