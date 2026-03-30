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

if (!$job) {
    header('Location: recruiter_dashboard.php');
    exit();
}

$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as candidate_name, u.email as candidate_email,
           ta.total_score as test_score, ta.status as test_status, ta.submitted_at as test_submitted
    FROM applications a 
    JOIN users u ON a.candidate_id = u.id 
    LEFT JOIN test_attempts ta ON ta.application_id = a.id
    WHERE a.job_id = ? AND a.status IN ('scored', 'test_completed', 'shortlisted')
    ORDER BY a.score DESC, a.applied_at ASC
");
$stmt->execute([$job_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getSectionScores($application_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT qb.section_type, qb.topic,
               COUNT(*) as total_questions,
               SUM(ta.is_correct) as correct_answers
        FROM test_answers ta
        JOIN question_bank qb ON ta.question_id = qb.id
        JOIN test_attempts tat ON ta.attempt_id = tat.id
        WHERE tat.application_id = ?
        GROUP BY qb.section_type, qb.topic
    ");
    $stmt->execute([$application_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$total_candidates = count($applications);
$test_completed = 0; $avg_test_score = 0; $avg_resume_score = 0;
foreach ($applications as $app) {
    if ($app['test_status'] == 'completed') $test_completed++;
    $avg_test_score += $app['test_score'] ?? 0;
    $avg_resume_score += $app['score'];
}
$avg_test_score = $total_candidates > 0 ? round($avg_test_score / $total_candidates, 1) : 0;
$avg_resume_score = $total_candidates > 0 ? round($avg_resume_score / $total_candidates, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results — <?php echo htmlspecialchars($job['title']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
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
            --blue: #4facfe;
            --red: #e05c5c;
        }

        html, body {
            min-height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-weight: 300;
            font-size: 14px;
        }

        body {
            background-image:
                radial-gradient(ellipse 70% 50% at 80% 5%, rgba(200,169,110,0.05) 0%, transparent 55%),
                radial-gradient(ellipse 40% 60% at 5% 95%, rgba(80,60,130,0.04) 0%, transparent 55%);
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }

        .page {
            position: relative;
            z-index: 1;
            max-width: 1080px;
            margin: 0 auto;
            padding: 32px 24px 72px;
        }

        /* Nav */
        .nav-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 28px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 40px;
        }

        .nav-brand { display: flex; align-items: center; gap: 12px; }

        .logo-mark {
            width: 34px; height: 34px; border-radius: 9px;
            background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 16px; font-weight: 600; color: #0d0d0f;
        }

        .nav-title { font-family: 'Playfair Display', serif; font-size: 16px; color: var(--text); }

        .nav-links { display: flex; align-items: center; gap: 6px; }

        .nav-links a {
            color: var(--muted); text-decoration: none; font-size: 13px;
            padding: 7px 14px; border-radius: 8px; border: 1px solid transparent;
            transition: color 0.2s, border-color 0.2s;
        }
        .nav-links a:hover { color: var(--text); border-color: var(--border); }
        .nav-links a.logout { color: rgba(224,92,92,0.7); }
        .nav-links a.logout:hover { color: #e05c5c; border-color: rgba(224,92,92,0.2); }

        /* Page header */
        .page-header {
            margin-bottom: 36px;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        .eyebrow {
            font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--accent); margin-bottom: 10px;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 30px; font-weight: 400; color: var(--text);
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 40px;
            animation: fadeUp 0.5s 0.08s cubic-bezier(0.22,1,0.36,1) both;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px 20px;
            position: relative;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 2px;
            background: var(--stat-color, var(--accent));
            opacity: 0.65;
        }
        .stat-card:hover { border-color: rgba(200,169,110,0.22); }
        .stat-number {
            font-family: 'Playfair Display', serif;
            font-size: 34px; font-weight: 400; color: var(--text);
            line-height: 1; margin-bottom: 6px;
        }
        .stat-label { font-size: 12px; color: var(--muted); letter-spacing: 0.04em; }

        /* Candidate card */
        .candidate-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 20px;
            transition: border-color 0.2s;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        .candidate-card:hover { border-color: rgba(200,169,110,0.22); }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
        }

        .candidate-info { display: flex; align-items: center; gap: 16px; }

        .candidate-avatar {
            width: 46px; height: 46px; border-radius: 50%;
            background: var(--accent-dim);
            border: 1px solid rgba(200,169,110,0.2);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 19px; color: var(--accent); flex-shrink: 0;
        }

        .candidate-name {
            font-family: 'Playfair Display', serif;
            font-size: 18px; font-weight: 400; color: var(--text);
        }
        .candidate-email { font-size: 12px; color: var(--muted); margin-top: 2px; }

        .rank-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 50%;
            font-size: 13px; font-weight: 600;
            font-family: 'Playfair Display', serif;
        }
        .rank-1 { background: rgba(255,215,0,0.12); color: #ffd700; border: 1px solid rgba(255,215,0,0.3); }
        .rank-2 { background: rgba(192,192,192,0.1); color: #c0c0c0; border: 1px solid rgba(192,192,192,0.25); }
        .rank-3 { background: rgba(205,127,50,0.12); color: #cd7f32; border: 1px solid rgba(205,127,50,0.28); }
        .rank-other { background: rgba(255,255,255,0.05); color: var(--muted); border: 1px solid var(--border); }

        /* Scores row */
        .scores-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            border-bottom: 1px solid var(--border);
        }

        .score-box {
            padding: 20px 22px;
            border-right: 1px solid var(--border);
            position: relative;
        }
        .score-box:last-child { border-right: none; }
        .score-box::before {
            content: '';
            position: absolute;
            bottom: 0; left: 22px; right: 22px;
            height: 2px;
            background: var(--score-color, transparent);
            opacity: 0.5;
        }

        .score-title {
            font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 10px;
        }
        .score-value {
            font-family: 'Playfair Display', serif;
            font-size: 26px; font-weight: 400; color: var(--text);
        }

        /* Status badge */
        .status-badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px;
            font-size: 11px; letter-spacing: 0.05em; text-transform: uppercase; font-weight: 500;
        }
        .status-shortlisted { background: rgba(92,173,130,0.1); color: #6ec99a; border: 1px solid rgba(92,173,130,0.2); }
        .status-test_completed { background: rgba(79,172,254,0.1); color: #7ec8f9; border: 1px solid rgba(79,172,254,0.2); }
        .status-scored { background: rgba(200,169,110,0.1); color: var(--accent); border: 1px solid rgba(200,169,110,0.2); }

        /* Breakdown */
        .breakdown-wrap { padding: 24px 28px; }

        .breakdown-title {
            font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 16px;
        }

        .section-group { margin-bottom: 20px; }
        .section-group:last-child { margin-bottom: 0; }

        .section-group-label {
            display: flex; align-items: center; gap: 10px;
            font-size: 12px; font-weight: 500;
            color: var(--accent); letter-spacing: 0.04em;
            margin-bottom: 8px;
        }
        .section-group-label::after {
            content: '';
            flex: 1; height: 1px; background: rgba(200,169,110,0.15);
        }

        .breakdown-table { width: 100%; border-collapse: collapse; }
        .breakdown-table th {
            font-size: 11px; letter-spacing: 0.07em; text-transform: uppercase;
            color: var(--muted); font-weight: 400; padding: 6px 10px 10px;
            text-align: left; border-bottom: 1px solid var(--border);
        }
        .breakdown-table td {
            padding: 10px 10px; border-bottom: 1px solid rgba(255,255,255,0.03);
            font-size: 13px; color: var(--text);
        }
        .breakdown-table tr:last-child td { border-bottom: none; }

        .mini-score {
            display: inline-flex; align-items: center; gap: 7px;
        }
        .mini-bar-bg { width: 60px; height: 3px; background: rgba(255,255,255,0.06); border-radius: 2px; }
        .mini-bar-fill { height: 100%; border-radius: 2px; }
        .score-high { color: #6ec99a; }
        .score-high .mini-bar-fill { background: #5cad82; }
        .score-medium { color: var(--accent); }
        .score-medium .mini-bar-fill { background: var(--accent); }
        .score-low { color: #e87878; }
        .score-low .mini-bar-fill { background: #e05c5c; }

        /* Pending test */
        .test-pending {
            padding: 16px 28px;
            display: flex; align-items: center; gap: 10px;
            background: rgba(200,169,110,0.05);
            border-top: 1px solid var(--border);
            font-size: 13px; color: var(--muted);
        }

        /* Actions */
        .card-actions {
            display: flex; align-items: center; gap: 10px;
            padding: 18px 28px;
            border-top: 1px solid var(--border);
            background: rgba(255,255,255,0.01);
        }

        .btn-resume {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; color: var(--muted); text-decoration: none;
            padding: 8px 14px; border-radius: 8px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s, background 0.2s;
        }
        .btn-resume:hover {
            color: var(--accent); border-color: rgba(200,169,110,0.35);
            background: var(--accent-dim);
        }

        .btn-shortlist {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; color: #0d0d0f;
            padding: 8px 16px; border-radius: 8px;
            border: none; background: var(--accent);
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            font-weight: 500; letter-spacing: 0.03em;
            transition: opacity 0.2s, transform 0.15s;
        }
        .btn-shortlist:hover { opacity: 0.85; transform: translateY(-1px); }

        .already-shortlisted {
            font-size: 12px; color: #6ec99a;
            display: flex; align-items: center; gap: 6px;
        }

        /* Empty */
        .empty-state {
            text-align: center; padding: 80px 32px; color: var(--muted);
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px;
        }
        .empty-state .icon { font-size: 40px; margin-bottom: 16px; }

        /* Back */
        .footer-nav { margin-top: 32px; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 13px; color: var(--muted); text-decoration: none;
            padding: 10px 18px; border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s;
        }
        .btn-back:hover { color: var(--text); border-color: rgba(255,255,255,0.15); }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .scores-row { grid-template-columns: repeat(2,1fr); }
            .card-header { flex-direction: column; align-items: flex-start; gap: 12px; }
        }
    </style>
</head>
<body>
<div class="page">

    <nav class="nav-bar">
        <div class="nav-brand">
            <div class="logo-mark">R</div>
            <span class="nav-title">RPA Recruitment</span>
        </div>
        <div class="nav-links">
            <a href="recruiter_dashboard.php">Dashboard</a>
            <a href="../scripts/logout.php" class="logout">Logout</a>
        </div>
    </nav>

    <div class="page-header">
        <div class="eyebrow">Test Analytics</div>
        <h1><?php echo htmlspecialchars($job['title']); ?></h1>
    </div>

    <div class="stats-grid">
        <div class="stat-card" style="--stat-color: var(--accent);">
            <div class="stat-number"><?php echo $total_candidates; ?></div>
            <div class="stat-label">Total Candidates</div>
        </div>
        <div class="stat-card" style="--stat-color: #f5836c;">
            <div class="stat-number"><?php echo $test_completed; ?></div>
            <div class="stat-label">Tests Completed</div>
        </div>
        <div class="stat-card" style="--stat-color: #4facfe;">
            <div class="stat-number"><?php echo $avg_resume_score; ?>%</div>
            <div class="stat-label">Avg Resume Score</div>
        </div>
        <div class="stat-card" style="--stat-color: #5cad82;">
            <div class="stat-number"><?php echo $avg_test_score; ?>%</div>
            <div class="stat-label">Avg Test Score</div>
        </div>
    </div>

    <?php if (empty($applications)): ?>
        <div class="empty-state">
            <div class="icon">📋</div>
            <p>No candidates available for this role yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($applications as $index => $app):
            $section_scores = getSectionScores($app['id']);
            $numerical = $logical = $verbal = $technical = [];
            foreach ($section_scores as $s) {
                ${$s['section_type']}[] = $s;
            }
            $rankNum = $index + 1;
            $rankClass = $rankNum == 1 ? 'rank-1' : ($rankNum == 2 ? 'rank-2' : ($rankNum == 3 ? 'rank-3' : 'rank-other'));
            $initial = strtoupper(substr($app['candidate_name'], 0, 1));
            $resume_only = $app['test_score'] ? round($app['score'] / 0.6 - ($app['test_score'] * 0.4 / 0.6), 1) : $app['score'];
        ?>
            <div class="candidate-card" style="animation-delay: <?php echo $index * 0.07; ?>s">

                <div class="card-header">
                    <div class="candidate-info">
                        <div class="candidate-avatar"><?php echo $initial; ?></div>
                        <div>
                            <div class="candidate-name"><?php echo htmlspecialchars($app['candidate_name']); ?></div>
                            <div class="candidate-email"><?php echo htmlspecialchars($app['candidate_email']); ?></div>
                        </div>
                    </div>
                    <span class="rank-badge <?php echo $rankClass; ?>">#<?php echo $rankNum; ?></span>
                </div>

                <div class="scores-row">
                    <div class="score-box" style="--score-color: #7ec8f9;">
                        <div class="score-title">Resume Score</div>
                        <div class="score-value"><?php echo number_format($resume_only, 1); ?>%</div>
                    </div>
                    <div class="score-box" style="--score-color: #6ec99a;">
                        <div class="score-title">Test Score</div>
                        <div class="score-value"><?php echo $app['test_score'] ? number_format($app['test_score'], 1).'%' : '—'; ?></div>
                    </div>
                    <div class="score-box" style="--score-color: var(--accent);">
                        <div class="score-title">Combined Score</div>
                        <div class="score-value"><?php echo number_format($app['score'], 1); ?>%</div>
                    </div>
                    <div class="score-box">
                        <div class="score-title">Status</div>
                        <div style="margin-top: 8px;">
                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($app['test_status'] == 'completed'): ?>
                    <div class="breakdown-wrap">
                        <div class="breakdown-title">Test Performance Breakdown</div>

                        <?php
                        $sections = [
                            'Numerical Reasoning' => $numerical,
                            'Logical Reasoning'   => $logical,
                            'Verbal Reasoning'    => $verbal,
                            'Technical MCQ'       => $technical,
                        ];
                        foreach ($sections as $label => $rows):
                            if (empty($rows)) continue;
                        ?>
                            <div class="section-group">
                                <div class="section-group-label"><?php echo $label; ?></div>
                                <table class="breakdown-table">
                                    <thead>
                                        <tr>
                                            <th>Topic</th>
                                            <th>Questions</th>
                                            <th>Correct</th>
                                            <th>Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $row):
                                            $pct = ($row['correct_answers'] / $row['total_questions']) * 100;
                                            $lvl = $pct >= 70 ? 'high' : ($pct >= 50 ? 'medium' : 'low');
                                        ?>
                                            <tr>
                                                <td><?php echo ucwords(str_replace('_', ' ', $row['topic'])); ?></td>
                                                <td style="color:var(--muted)"><?php echo $row['total_questions']; ?></td>
                                                <td style="color:var(--muted)"><?php echo $row['correct_answers']; ?></td>
                                                <td>
                                                    <span class="mini-score score-<?php echo $lvl; ?>">
                                                        <?php echo round($pct, 1); ?>%
                                                        <span class="mini-bar-bg">
                                                            <span class="mini-bar-fill" style="width:<?php echo min($pct,100); ?>%"></span>
                                                        </span>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="test-pending">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Test not yet completed by candidate
                    </div>
                <?php endif; ?>

                <div class="card-actions">
                    <a href="<?php echo htmlspecialchars($app['resume_path']); ?>" target="_blank" class="btn-resume">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        View Resume
                    </a>
                    <?php if ($app['status'] != 'shortlisted'): ?>
                        <form action="../scripts/shortlist_handler.php" method="POST" style="display:inline;">
                            <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                            <input type="hidden" name="mode" value="manual">
                            <input type="hidden" name="selected[]" value="<?php echo $app['id']; ?>">
                            <button type="submit" class="btn-shortlist">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Shortlist
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="already-shortlisted">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Shortlisted
                        </span>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="footer-nav">
        <a href="recruiter_dashboard.php" class="btn-back">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Dashboard
        </a>
    </div>

</div>
</body>
</html>