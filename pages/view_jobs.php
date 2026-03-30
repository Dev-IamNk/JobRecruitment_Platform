<?php
require_once '../config/db.php';
redirectIfNotLoggedIn();

if (getUserType() != 'candidate') {
    header('Location: ../pages/recruiter_dashboard.php');
    exit();
}

$stmt = $pdo->query("SELECT j.*, u.full_name as recruiter_name FROM jobs j JOIN users u ON j.recruiter_id = u.id WHERE j.status='open' ORDER BY j.created_at DESC");
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Jobs — RPA Recruitment</title>
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
                radial-gradient(ellipse 60% 50% at 85% 5%, rgba(200,169,110,0.06) 0%, transparent 55%),
                radial-gradient(ellipse 40% 60% at 10% 95%, rgba(80,60,130,0.04) 0%, transparent 55%);
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
            margin-bottom: 48px;
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

        .nav-links a:hover { color: var(--text); border-color: var(--border); }
        .nav-links a.logout { color: rgba(224,92,92,0.7); }
        .nav-links a.logout:hover { color: #e05c5c; border-color: rgba(224,92,92,0.2); }

        /* Page heading */
        .page-heading {
            margin-bottom: 40px;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }

        .page-heading .eyebrow {
            font-size: 11px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .page-heading h1 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 10px;
        }

        .page-heading p {
            font-size: 14px;
            color: var(--muted);
        }

        /* Job grid */
        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(480px, 1fr));
            gap: 20px;
        }

        /* Job card */
        .job-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            transition: border-color 0.25s, transform 0.2s, box-shadow 0.25s;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }

        .job-card:hover {
            border-color: rgba(200,169,110,0.3);
            transform: translateY(-2px);
            box-shadow: 0 16px 48px rgba(0,0,0,0.35);
        }

        .card-body {
            padding: 28px 28px 24px;
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .company-avatar {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: var(--accent-dim);
            border: 1px solid rgba(200,169,110,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            color: var(--accent);
            flex-shrink: 0;
        }

        .open-badge {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(92,173,130,0.1);
            border: 1px solid rgba(92,173,130,0.25);
            color: #6ec99a;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .job-title {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--muted);
        }

        .meta-item svg { opacity: 0.5; flex-shrink: 0; }

        .skills-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 20px;
        }

        .skill-chip {
            font-size: 11px;
            padding: 3px 9px;
            border-radius: 20px;
            background: var(--accent-dim);
            border: 1px solid rgba(200,169,110,0.15);
            color: var(--accent);
        }

        /* Apply form section */
        .apply-section {
            border-top: 1px solid var(--border);
            padding: 22px 28px 26px;
            background: rgba(255,255,255,0.01);
        }

        .apply-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
        }

        .apply-toggle-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--accent);
            letter-spacing: 0.03em;
        }

        .apply-toggle-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid rgba(200,169,110,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            transition: transform 0.2s;
        }

        .apply-form-body {
            display: none;
            padding-top: 20px;
            flex-direction: column;
            gap: 14px;
        }

        .apply-form-body.open { display: flex; }

        .apply-toggle.active .apply-toggle-icon { transform: rotate(45deg); }

        .form-group label {
            display: block;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .file-input-wrap {
            position: relative;
        }

        .file-input-wrap input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .file-display {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            background: rgba(255,255,255,0.04);
            border: 1px dashed rgba(200,169,110,0.25);
            border-radius: 10px;
            font-size: 13px;
            color: var(--muted);
            transition: border-color 0.2s, background 0.2s;
            cursor: pointer;
        }

        .file-input-wrap:hover .file-display {
            border-color: rgba(200,169,110,0.45);
            background: var(--accent-dim);
            color: var(--accent);
        }

        .file-name-display {
            font-size: 12px;
            color: var(--accent);
            margin-top: 6px;
            display: none;
        }

        textarea {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            color: var(--text);
            resize: vertical;
            min-height: 90px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        textarea:focus {
            border-color: rgba(200,169,110,0.4);
            box-shadow: 0 0 0 3px rgba(200,169,110,0.07);
        }

        textarea::placeholder { color: rgba(122,118,112,0.4); }

        .btn-apply {
            width: 100%;
            padding: 13px;
            background: var(--accent);
            color: #0d0d0f;
            border: none;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 0.04em;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.15s;
        }

        .btn-apply:hover { opacity: 0.87; transform: translateY(-1px); }
        .btn-apply:active { transform: translateY(0); }

        /* Empty state */
        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 80px 32px;
            color: var(--muted);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
        }
        .empty-state .icon { font-size: 40px; margin-bottom: 16px; }
        .empty-state p { font-size: 15px; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 600px) {
            .jobs-grid { grid-template-columns: 1fr; }
            .card-body, .apply-section { padding-left: 20px; padding-right: 20px; }
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
            <a href="candidate_dashboard.php">Dashboard</a>
            <a href="../scripts/logout.php" class="logout">Logout</a>
        </div>
    </nav>

    <!-- Heading -->
    <div class="page-heading">
        <div class="eyebrow">Candidate Portal</div>
        <h1>Available Positions</h1>
        <p><?php echo count($jobs); ?> open role<?php echo count($jobs) != 1 ? 's' : ''; ?> right now — find your next opportunity</p>
    </div>

    <!-- Jobs -->
    <div class="jobs-grid">
        <?php if (empty($jobs)): ?>
            <div class="empty-state">
                <div class="icon">🔍</div>
                <p>No open positions right now. Check back soon.</p>
            </div>
        <?php else: ?>
            <?php foreach ($jobs as $i => $job): 
                $initial = strtoupper(substr($job['recruiter_name'], 0, 1));
            ?>
                <div class="job-card" style="animation-delay: <?php echo $i * 0.07; ?>s">
                    <div class="card-body">
                        <div class="card-top">
                            <div class="company-avatar"><?php echo $initial; ?></div>
                            <span class="open-badge">Open</span>
                        </div>

                        <h2 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h2>

                        <div class="job-meta">
                            <span class="meta-item">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?php echo htmlspecialchars($job['recruiter_name']); ?>
                            </span>
                            <?php if (!empty($job['location'])): ?>
                            <span class="meta-item">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <?php echo htmlspecialchars($job['location']); ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($job['salary_range'])): ?>
                            <span class="meta-item">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                                <?php echo htmlspecialchars($job['salary_range']); ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="skills-row">
                            <?php foreach (explode(',', $job['required_skills']) as $skill): ?>
                                <span class="skill-chip"><?php echo htmlspecialchars(trim($skill)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Apply section (collapsible) -->
                    <div class="apply-section">
                        <div class="apply-toggle" onclick="toggleApply(this)">
                            <span class="apply-toggle-label">Apply for this role</span>
                            <span class="apply-toggle-icon">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            </span>
                        </div>

                        <form action="../scripts/apply_handler.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                            <div class="apply-form-body" id="form-<?php echo $job['id']; ?>">

                                <div class="form-group">
                                    <label>Resume (PDF)</label>
                                    <div class="file-input-wrap">
                                        <input type="file" name="resume" accept=".pdf" required
                                            onchange="showFilename(this)">
                                        <div class="file-display">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            Click to upload your PDF resume
                                        </div>
                                    </div>
                                    <div class="file-name-display" id="fname-<?php echo $job['id']; ?>"></div>
                                </div>

                                <div class="form-group">
                                    <label>Cover Letter <span style="opacity:0.5;font-size:10px;text-transform:none;">(optional)</span></label>
                                    <textarea name="cover_letter" placeholder="Briefly introduce yourself and why you're a great fit…"></textarea>
                                </div>

                                <button type="submit" class="btn-apply">Submit Application →</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
function toggleApply(toggle) {
    const form = toggle.nextElementSibling.querySelector('.apply-form-body');
    const isOpen = form.classList.contains('open');
    form.classList.toggle('open', !isOpen);
    toggle.classList.toggle('active', !isOpen);
}

function showFilename(input) {
    // Find sibling file-name-display by traversing up to .file-input-wrap parent then the next sibling
    const wrap = input.closest('.file-input-wrap');
    const display = wrap.nextElementSibling;
    if (input.files && input.files[0]) {
        display.textContent = '📎 ' + input.files[0].name;
        display.style.display = 'block';
    }
}
</script>
</body>
</html>