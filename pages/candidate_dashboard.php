<?php 
require_once '../config/db.php';
redirectIfNotLoggedIn();
if (getUserType() != 'candidate') {
    header('Location: recruiter_dashboard.php');
    exit();
}

$candidate_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT a.*, j.title as job_title, j.location, j.salary_range, u.full_name as recruiter_name 
                       FROM applications a 
                       JOIN jobs j ON a.job_id = j.id 
                       JOIN users u ON j.recruiter_id = u.id 
                       WHERE a.candidate_id = ? 
                       ORDER BY a.applied_at DESC");
$stmt->execute([$candidate_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications — RPA Recruitment</title>
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
            --blue-dim:   rgba(100,140,220,0.1);
            --blue:       #7aa0e0;
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
                radial-gradient(ellipse 70% 40% at 80% 5%,  rgba(200,169,110,0.05) 0%, transparent 55%),
                radial-gradient(ellipse 40% 60% at 5% 95%,  rgba(80,60,130,0.04)   0%, transparent 55%);
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
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 15px; font-weight: 600; color: #0d0d0f;
        }

        .nav-title { font-family: 'Playfair Display', serif; font-size: 15px; color: var(--text); }

        .nav-right { display: flex; align-items: center; gap: 8px; }

        .nav-greeting {
            font-size: 12px; color: var(--muted); padding: 0 8px;
        }

        .btn-nav-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; color: var(--muted); text-decoration: none;
            padding: 7px 14px; border-radius: 8px; border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s;
        }
        .btn-nav-link:hover { color: var(--text); border-color: rgba(255,255,255,0.14); }
        .btn-nav-link.accent {
            color: var(--accent); border-color: rgba(200,169,110,0.25);
            background: var(--accent-dim);
        }
        .btn-nav-link.accent:hover { border-color: rgba(200,169,110,0.45); }

        /* ── PAGE ── */
        .page {
            position: relative; z-index: 1;
            max-width: 860px; margin: 0 auto;
            padding: 36px 24px 72px;
        }

        /* Page header */
        .page-header {
            margin-bottom: 32px;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        .eyebrow {
            font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--accent); margin-bottom: 10px;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px; font-weight: 400; color: var(--text); margin-bottom: 6px;
        }
        .page-header .subtitle { font-size: 13px; color: var(--muted); }

        /* ── ALERT ── */
        .alert-success {
            display: flex; align-items: center; gap: 10px;
            background: var(--green-dim); border: 1px solid rgba(92,173,130,0.25);
            border-radius: 12px; padding: 14px 18px;
            font-size: 13px; color: #6ec99a;
            margin-bottom: 28px;
            animation: fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) both;
        }

        /* ── SECTION TITLE ── */
        .section-title {
            display: flex; align-items: center; gap: 12px;
            font-size: 11px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 20px;
        }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 64px 32px;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; color: var(--muted);
        }
        .empty-state .icon { font-size: 36px; margin-bottom: 14px; }
        .empty-state p { font-size: 14px; line-height: 1.7; }
        .empty-state a {
            color: var(--accent); text-decoration: none; font-weight: 500;
        }
        .empty-state a:hover { text-decoration: underline; }

        /* ── APP CARD ── */
        .app-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; overflow: hidden;
            margin-bottom: 18px;
            transition: border-color 0.2s;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        .app-card:hover { border-color: rgba(200,169,110,0.18); }

        /* Card top accent line driven by status */
        .app-card::before {
            content: ''; display: block;
            height: 2px; background: var(--card-accent, var(--accent)); opacity: 0.55;
        }

        .card-header {
            display: flex; align-items: flex-start; justify-content: space-between;
            padding: 22px 24px 18px; gap: 16px;
        }

        .job-title {
            font-family: 'Playfair Display', serif;
            font-size: 18px; font-weight: 400; color: var(--text); margin-bottom: 6px;
        }

        .job-meta {
            font-size: 12px; color: var(--muted);
            display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
        }
        .job-meta-sep { opacity: 0.4; }

        /* Status badge */
        .status-badge {
            font-size: 10px; font-weight: 500; letter-spacing: 0.07em;
            text-transform: uppercase; white-space: nowrap;
            padding: 5px 11px; border-radius: 20px; flex-shrink: 0;
        }
        .badge-pending, .badge-scored {
            background: var(--accent-dim); color: var(--accent);
            border: 1px solid rgba(200,169,110,0.25);
        }
        .badge-shortlisted {
            background: var(--blue-dim); color: var(--blue);
            border: 1px solid rgba(100,140,220,0.25);
        }
        .badge-test_sent, .badge-test_completed {
            background: rgba(200,169,110,0.08); color: var(--accent);
            border: 1px solid rgba(200,169,110,0.2);
        }
        .badge-interview_scheduled {
            background: var(--blue-dim); color: var(--blue);
            border: 1px solid rgba(100,140,220,0.25);
        }
        .badge-selected {
            background: var(--green-dim); color: #6ec99a;
            border: 1px solid rgba(92,173,130,0.3);
        }
        .badge-rejected {
            background: var(--red-dim); color: #e87878;
            border: 1px solid rgba(224,92,92,0.25);
        }

        /* Meta grid */
        .card-meta {
            display: grid; grid-template-columns: 1fr 1fr;
            border-top: 1px solid var(--border);
        }
        .meta-item {
            padding: 16px 24px;
            border-right: 1px solid var(--border);
        }
        .meta-item:last-child { border-right: none; }
        .meta-label {
            font-size: 10px; letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 6px;
        }
        .meta-value {
            font-family: 'DM Mono', monospace;
            font-size: 13px; color: var(--text);
        }

        /* Status message strip */
        .status-msg {
            margin: 0 24px 20px;
            border-radius: 10px; padding: 14px 16px;
            font-size: 13px; line-height: 1.6;
            display: flex; flex-direction: column; gap: 10px;
        }
        .status-msg.pending    { background: var(--accent-dim);  color: var(--accent); border: 1px solid rgba(200,169,110,0.2); }
        .status-msg.shortlist  { background: var(--blue-dim);    color: var(--blue);   border: 1px solid rgba(100,140,220,0.2); }
        .status-msg.test_sent  { background: var(--accent-dim);  color: var(--accent); border: 1px solid rgba(200,169,110,0.2); }
        .status-msg.interview  { background: var(--blue-dim);    color: var(--blue);   border: 1px solid rgba(100,140,220,0.2); }
        .status-msg.selected   { background: var(--green-dim);   color: #6ec99a;       border: 1px solid rgba(92,173,130,0.25); }
        .status-msg.rejected   { background: var(--red-dim);     color: #e87878;       border: 1px solid rgba(224,92,92,0.22); }

        /* CTA buttons inside status msg */
        .btn-cta {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 12px; font-family: 'DM Sans', sans-serif; font-weight: 500;
            padding: 9px 16px; border-radius: 8px; text-decoration: none;
            border: none; cursor: pointer; width: fit-content;
            transition: opacity 0.2s, transform 0.15s;
        }
        .btn-cta:hover { opacity: 0.85; transform: translateY(-1px); }
        .btn-cta.green {
            background: rgba(92,173,130,0.18); color: #6ec99a;
            border: 1px solid rgba(92,173,130,0.3);
        }
        .btn-cta.amber {
            background: rgba(200,169,110,0.15); color: var(--accent);
            border: 1px solid rgba(200,169,110,0.3);
        }

        .test-done {
            font-size: 12px; color: #6ec99a;
            display: inline-flex; align-items: center; gap: 6px;
        }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 600px) {
            .card-meta { grid-template-columns: 1fr; }
            .meta-item { border-right: none; border-bottom: 1px solid var(--border); }
            .meta-item:last-child { border-bottom: none; }
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
        <span class="nav-greeting">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        <a href="view_jobs.php" class="btn-nav-link accent">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Browse Jobs
        </a>
        <a href="../scripts/logout.php" class="btn-nav-link">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </a>
    </div>
</nav>

<div class="page">

    <!-- Page header -->
    <div class="page-header">
        <div class="eyebrow">Candidate · Portal</div>
        <h1>My Applications</h1>
        <div class="subtitle">Track the status of every role you've applied to.</div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert-success">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Application submitted successfully! We'll review it and reach out with next steps.
        </div>
    <?php endif; ?>

    <div class="section-title">
        <?php echo count($applications); ?> Application<?php echo count($applications) !== 1 ? 's' : ''; ?>
    </div>

    <?php if (empty($applications)): ?>
        <div class="empty-state">
            <div class="icon">📋</div>
            <p>You haven't applied to any jobs yet.<br>
               <a href="view_jobs.php">Browse available positions →</a>
            </p>
        </div>

    <?php else: ?>
        <?php
        $status_labels = [
            'pending'              => 'Under Review',
            'scored'               => 'Under Review',
            'shortlisted'          => 'Shortlisted',
            'test_sent'            => 'Test Sent',
            'test_completed'       => 'Test Completed',
            'interview_scheduled'  => 'Interview Scheduled',
            'selected'             => 'Selected ✓',
            'rejected'             => 'Not Selected',
        ];
        $status_meta = [
            'pending'              => 'Application received',
            'scored'               => 'Application received',
            'shortlisted'          => 'Shortlisted for next round',
            'test_sent'            => 'Assessment test sent',
            'test_completed'       => 'Test submitted',
            'interview_scheduled'  => 'Interview scheduled',
            'selected'             => '🎉 Congratulations!',
            'rejected'             => 'Thank you for applying',
        ];
        $card_accents = [
            'pending'              => 'var(--accent)',
            'scored'               => 'var(--accent)',
            'shortlisted'          => 'var(--blue)',
            'test_sent'            => 'var(--accent)',
            'test_completed'       => 'var(--accent)',
            'interview_scheduled'  => 'var(--blue)',
            'selected'             => 'var(--green)',
            'rejected'             => 'var(--red)',
        ];
        ?>

        <?php foreach ($applications as $i => $app):
            $s      = $app['status'];
            $accent = $card_accents[$s] ?? 'var(--accent)';
        ?>
            <div class="app-card" style="--card-accent:<?= $accent ?>; animation-delay:<?= $i * 0.07 ?>s">

                <!-- Header -->
                <div class="card-header">
                    <div>
                        <div class="job-title"><?= htmlspecialchars($app['job_title']) ?></div>
                        <div class="job-meta">
                            <span><?= htmlspecialchars($app['recruiter_name']) ?></span>
                            <span class="job-meta-sep">·</span>
                            <span><?= htmlspecialchars($app['location']) ?></span>
                            <?php if ($app['salary_range']): ?>
                                <span class="job-meta-sep">·</span>
                                <span><?= htmlspecialchars($app['salary_range']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="status-badge badge-<?= $s ?>">
                        <?= $status_labels[$s] ?? ucfirst($s) ?>
                    </span>
                </div>

                <!-- Meta grid -->
                <div class="card-meta">
                    <div class="meta-item">
                        <div class="meta-label">Applied On</div>
                        <div class="meta-value"><?= date('d M Y', strtotime($app['applied_at'])) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Stage</div>
                        <div class="meta-value"><?= $status_meta[$s] ?? ucfirst($s) ?></div>
                    </div>
                </div>

                <!-- Status messages -->
                <?php if ($s === 'pending' || $s === 'scored'): ?>
                    <div class="status-msg pending">
                        ⏳ Your application is being reviewed. We'll notify you about next steps soon.
                    </div>

                <?php elseif ($s === 'shortlisted'): ?>
                    <?php
                        $stmt_test = $pdo->prepare("SELECT COUNT(*) as c FROM test_configs WHERE job_id = ?");
                        $stmt_test->execute([$app['job_id']]);
                        $test_exists = $stmt_test->fetch(PDO::FETCH_ASSOC)['c'] > 0;

                        $stmt_attempt = $pdo->prepare("SELECT status FROM test_attempts WHERE application_id = ? LIMIT 1");
                        $stmt_attempt->execute([$app['id']]);
                        $attempt = $stmt_attempt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <div class="status-msg shortlist">
                        ✓ You've been shortlisted for the next round.
                        <?php if ($test_exists): ?>
                            <?php if (!$attempt): ?>
                                <a href="take_test.php?app_id=<?= $app['id'] ?>" class="btn-cta green">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Take Online Assessment
                                </a>
                            <?php elseif ($attempt['status'] === 'in_progress'): ?>
                                <a href="take_test.php?app_id=<?= $app['id'] ?>" class="btn-cta amber">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    Resume Test
                                </a>
                            <?php elseif ($attempt['status'] === 'completed'): ?>
                                <span class="test-done">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    Test Completed
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            You'll receive further instructions via email shortly.
                        <?php endif; ?>
                    </div>

                <?php elseif ($s === 'test_sent'): ?>
                    <div class="status-msg test_sent">
                        📧 An assessment test has been sent to your email. Please complete it within the given timeframe.
                    </div>

                <?php elseif ($s === 'interview_scheduled'): ?>
                    <div class="status-msg interview">
                        📅 Your interview has been scheduled. Check your email for the meeting link and details.
                    </div>

                <?php elseif ($s === 'selected'): ?>
                    <div class="status-msg selected">
                        🎉 Congratulations! You've been selected for this position. Our HR team will contact you with the offer details.
                    </div>

                <?php elseif ($s === 'rejected'): ?>
                    <div class="status-msg rejected">
                        Thank you for your interest. While we won't be moving forward at this time, we encourage you to apply for other positions that match your skills.
                    </div>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>
</body>
</html>