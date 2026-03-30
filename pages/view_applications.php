<!-- FILE: pages/view_applications.php -->
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
    SELECT a.*, u.full_name as candidate_name, u.email as candidate_email 
    FROM applications a 
    JOIN users u ON a.candidate_id = u.id 
    WHERE a.job_id = ? 
    ORDER BY a.rank ASC, a.score DESC, a.applied_at DESC
");
$stmt->execute([$job_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pending = 0; $scored = 0; $shortlisted = 0;
foreach ($applications as $app) {
    if ($app['status'] == 'pending') $pending++;
    if ($app['status'] == 'scored') $scored++;
    if ($app['status'] == 'shortlisted') $shortlisted++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications — <?php echo htmlspecialchars($job['title']); ?></title>
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
            --accent-dim: rgba(200,169,110,0.12);
            --text: #f0ece4;
            --muted: #7a7670;
            --gold: #ffd700;
            --silver: #c0c0c0;
            --bronze: #cd7f32;
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
                radial-gradient(ellipse 70% 50% at 80% 10%, rgba(200,169,110,0.05) 0%, transparent 55%),
                radial-gradient(ellipse 50% 70% at 5% 90%, rgba(80,60,120,0.04) 0%, transparent 55%);
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px 64px;
        }

        /* Nav */
        .nav-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 28px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 36px;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-mark {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            font-weight: 600;
            color: #0d0d0f;
            flex-shrink: 0;
        }

        .nav-title {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            color: var(--text);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav-links a {
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            padding: 7px 14px;
            border-radius: 8px;
            border: 1px solid transparent;
            transition: color 0.2s, border-color 0.2s;
        }

        .nav-links a:hover {
            color: var(--text);
            border-color: var(--border);
        }

        .nav-links a.logout {
            color: rgba(224,92,92,0.7);
        }
        .nav-links a.logout:hover { color: #e05c5c; border-color: rgba(224,92,92,0.2); }

        /* Page header */
        .page-header {
            margin-bottom: 36px;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }

        .page-header .eyebrow {
            font-size: 11px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 12px;
        }

        .skills-tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .skill-tag {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            background: var(--accent-dim);
            border: 1px solid rgba(200,169,110,0.2);
            color: var(--accent);
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 36px;
            animation: fadeUp 0.5s 0.1s cubic-bezier(0.22,1,0.36,1) both;
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

        .stat-card:hover { border-color: rgba(200,169,110,0.25); }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: var(--stat-color, var(--accent));
            opacity: 0.6;
        }

        .stat-number {
            font-family: 'Playfair Display', serif;
            font-size: 34px;
            font-weight: 400;
            color: var(--text);
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--muted);
            letter-spacing: 0.05em;
        }

        /* Table */
        .table-wrapper {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            animation: fadeUp 0.5s 0.2s cubic-bezier(0.22,1,0.36,1) both;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            font-weight: 400;
            color: var(--text);
        }

        .table-count {
            font-size: 12px;
            color: var(--muted);
            background: rgba(255,255,255,0.04);
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid var(--border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            font-weight: 400;
            background: rgba(255,255,255,0.01);
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }

        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }
        tbody tr.top-ranked { background: rgba(200,169,110,0.04); }
        tbody tr.top-ranked:hover { background: rgba(200,169,110,0.07); }

        td {
            padding: 14px 16px;
            color: var(--text);
            vertical-align: middle;
        }

        .candidate-name { font-weight: 500; }
        .candidate-email { font-size: 12px; color: var(--muted); margin-top: 2px; }

        /* Rank badge */
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Playfair Display', serif;
        }

        .rank-1 { background: rgba(255,215,0,0.15); color: #ffd700; border: 1px solid rgba(255,215,0,0.3); }
        .rank-2 { background: rgba(192,192,192,0.12); color: #c0c0c0; border: 1px solid rgba(192,192,192,0.25); }
        .rank-3 { background: rgba(205,127,50,0.15); color: #cd7f32; border: 1px solid rgba(205,127,50,0.3); }
        .rank-other { background: rgba(255,255,255,0.05); color: var(--muted); border: 1px solid var(--border); }

        /* Score */
        .score-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .score-bar-bg {
            flex: 1;
            height: 4px;
            background: rgba(255,255,255,0.06);
            border-radius: 2px;
            max-width: 80px;
        }

        .score-bar-fill {
            height: 100%;
            border-radius: 2px;
        }

        .score-high .score-bar-fill { background: #5cad82; }
        .score-medium .score-bar-fill { background: var(--accent); }
        .score-low .score-bar-fill { background: #e05c5c; }

        .score-text { font-weight: 500; font-size: 13px; }
        .score-high .score-text { color: #6ec99a; }
        .score-medium .score-text { color: var(--accent); }
        .score-low .score-text { color: #e87878; }

        /* Skills */
        .skills-cell {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px;
            color: var(--muted);
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-weight: 500;
        }

        .status-pending { background: rgba(200,169,110,0.12); color: var(--accent); border: 1px solid rgba(200,169,110,0.2); }
        .status-scored { background: rgba(79,172,254,0.1); color: #7ec8f9; border: 1px solid rgba(79,172,254,0.2); }
        .status-shortlisted { background: rgba(92,173,130,0.1); color: #6ec99a; border: 1px solid rgba(92,173,130,0.2); }

        /* Action btn */
        .btn-resume {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--muted);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s, background 0.2s;
            white-space: nowrap;
        }

        .btn-resume:hover {
            color: var(--accent);
            border-color: rgba(200,169,110,0.35);
            background: var(--accent-dim);
        }

        /* Date */
        .date-text { font-size: 12px; color: var(--muted); }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 72px 32px;
            color: var(--muted);
        }

        .empty-state .icon { font-size: 40px; margin-bottom: 16px; }
        .empty-state p { font-size: 15px; }

        /* Footer nav */
        .footer-nav {
            margin-top: 32px;
            animation: fadeUp 0.5s 0.3s cubic-bezier(0.22,1,0.36,1) both;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--muted);
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s;
        }

        .btn-back:hover { color: var(--text); border-color: rgba(255,255,255,0.15); }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
<div class="page">

    <!-- Nav -->
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

    <!-- Page Header -->
    <div class="page-header">
        <div class="eyebrow">Applications Review</div>
        <h1><?php echo htmlspecialchars($job['title']); ?></h1>
        <div class="skills-tag-list">
            <?php foreach (explode(',', $job['required_skills']) as $skill): ?>
                <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card" style="--stat-color: var(--accent);">
            <div class="stat-number"><?php echo count($applications); ?></div>
            <div class="stat-label">Total Applications</div>
        </div>
        <div class="stat-card" style="--stat-color: #f5836c;">
            <div class="stat-number"><?php echo $pending; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card" style="--stat-color: #4facfe;">
            <div class="stat-number"><?php echo $scored; ?></div>
            <div class="stat-label">Scored</div>
        </div>
        <div class="stat-card" style="--stat-color: #5cad82;">
            <div class="stat-number"><?php echo $shortlisted; ?></div>
            <div class="stat-label">Shortlisted</div>
        </div>
    </div>

    <!-- Table -->
    <?php if (empty($applications)): ?>
        <div class="table-wrapper">
            <div class="empty-state">
                <div class="icon">📭</div>
                <p>No applications received yet for this role.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <div class="table-header">
                <h3>Candidate Applications</h3>
                <span class="table-count"><?php echo count($applications); ?> total</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Candidate</th>
                        <th>Score</th>
                        <th>Extracted Skills</th>
                        <th>Status</th>
                        <th>Applied</th>
                        <th>Resume</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): 
                        $rankClass = $app['rank'] == 1 ? 'rank-1' : ($app['rank'] == 2 ? 'rank-2' : ($app['rank'] == 3 ? 'rank-3' : 'rank-other'));
                        $scoreLevel = $app['score'] >= 70 ? 'high' : ($app['score'] >= 50 ? 'medium' : 'low');
                    ?>
                        <tr class="<?php echo $app['rank'] > 0 && $app['rank'] <= 3 ? 'top-ranked' : ''; ?>">
                            <td>
                                <?php if ($app['rank'] > 0): ?>
                                    <span class="rank-badge <?php echo $rankClass; ?>">#<?php echo $app['rank']; ?></span>
                                <?php else: ?>
                                    <span style="color:var(--border);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="candidate-name"><?php echo htmlspecialchars($app['candidate_name']); ?></div>
                                <div class="candidate-email"><?php echo htmlspecialchars($app['candidate_email']); ?></div>
                            </td>
                            <td>
                                <?php if ($app['score'] > 0): ?>
                                    <div class="score-wrap score-<?php echo $scoreLevel; ?>">
                                        <span class="score-text"><?php echo number_format($app['score'], 1); ?>%</span>
                                        <div class="score-bar-bg">
                                            <div class="score-bar-fill" style="width:<?php echo min($app['score'],100); ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--muted);font-size:12px;">Processing…</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="skills-cell" title="<?php echo htmlspecialchars($app['extracted_skills']); ?>">
                                    <?php echo htmlspecialchars($app['extracted_skills'] ?: 'Processing…'); ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="date-text"><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></span>
                            </td>
                            <td>
                                <a href="<?php echo htmlspecialchars($app['resume_path']); ?>" target="_blank" class="btn-resume">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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