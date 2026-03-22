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

if (!$job_id) {
    header('Location: recruiter_dashboard.php');
    exit;
}

// Verify job belongs to recruiter
$job_q = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
$job_q->execute([$job_id, $recruiter_id]);
$job = $job_q->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: recruiter_dashboard.php?error=invalid_job');
    exit;
}

// Fetch all candidates who completed coding test for this job
$cands_q = $pdo->prepare("
    SELECT 
        a.id as application_id,
        a.status,
        a.score         as resume_score,
        a.aptitude_tech_score,
        a.coding_score,
        a.final_score,
        a.applied_at,
        a.resume_path,
        u.full_name,
        u.email
    FROM applications a
    JOIN users u ON u.id = a.candidate_id
    WHERE a.job_id = ? 
      AND a.coding_status = 'completed'
      AND a.status NOT IN ('selected','rejected')
    ORDER BY a.final_score DESC
");
$cands_q->execute([$job_id]);
$candidates = $cands_q->fetchAll(PDO::FETCH_ASSOC);

// Also fetch already decided candidates
$decided_q = $pdo->prepare("
    SELECT 
        a.id as application_id,
        a.status,
        a.final_score,
        u.full_name,
        u.email
    FROM applications a
    JOIN users u ON u.id = a.candidate_id
    WHERE a.job_id = ? 
      AND a.status IN ('selected','rejected')
    ORDER BY a.status ASC, a.final_score DESC
");
$decided_q->execute([$job_id]);
$decided = $decided_q->fetchAll(PDO::FETCH_ASSOC);

function scoreColor($s) {
    if ($s >= 75) return '#00e5a0';
    if ($s >= 50) return '#ff9f43';
    return '#ff5252';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Results | <?= htmlspecialchars($job['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f6fb;
            --surface: #ffffff;
            --surface2: #f0f2f8;
            --border: #e0e4ef;
            --accent: #667eea;
            --accent2: #764ba2;
            --success: #00c48c;
            --warn: #ff9f43;
            --danger: #ff5252;
            --text: #1a1a2e;
            --muted: #7b8099;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Syne', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        /* HEADER */
        .topbar {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: white;
        }
        .topbar h1 { font-size: 22px; font-weight: 800; }
        .topbar p  { font-size: 13px; opacity: 0.85; margin-top: 4px; }
        .back-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s;
        }
        .back-btn:hover { background: rgba(255,255,255,0.3); }

        .container { max-width: 1100px; margin: 32px auto; padding: 0 20px; }

        /* STATS ROW */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px 24px;
            text-align: center;
        }
        .stat-num  { font-family: 'JetBrains Mono', monospace; font-size: 36px; font-weight: 700; }
        .stat-label { font-size: 13px; color: var(--muted); margin-top: 4px; }

        /* SECTION TITLE */
        .section-title {
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
        }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        /* CANDIDATE CARD */
        .candidate-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 16px;
            transition: box-shadow 0.2s;
        }
        .candidate-card:hover { box-shadow: 0 4px 20px rgba(102,126,234,0.1); }

        .card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .candidate-info { display: flex; align-items: center; gap: 14px; }
        .avatar {
            width: 48px; height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 18px;
            flex-shrink: 0;
        }
        .cand-name  { font-size: 16px; font-weight: 700; }
        .cand-email { font-size: 13px; color: var(--muted); margin-top: 2px; }

        /* Score pills row */
        .scores-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .score-pill {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 16px;
            text-align: center;
            min-width: 110px;
        }
        .score-pill-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); font-weight: 700; margin-bottom: 4px; }
        .score-pill-val   { font-family: 'JetBrains Mono', monospace; font-size: 20px; font-weight: 700; }
        .score-pill-weight { font-size: 10px; color: var(--muted); margin-top: 2px; }

        /* Final score big */
        .final-pill {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 8px;
            padding: 10px 20px;
            text-align: center;
            min-width: 120px;
        }
        .final-pill .score-pill-label { color: rgba(255,255,255,0.8); }
        .final-pill .score-pill-val   { color: white; font-size: 24px; }
        .final-pill .score-pill-weight { color: rgba(255,255,255,0.7); }

        /* Action buttons */
        .action-row {
            display: flex;
            gap: 12px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        .btn {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 14px;
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-accept {
            background: var(--success);
            color: white;
            flex: 1;
        }
        .btn-accept:hover { background: #00a877; transform: translateY(-1px); }
        .btn-reject {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--danger);
            flex: 1;
        }
        .btn-reject:hover { background: #fff0f0; border-color: var(--danger); transform: translateY(-1px); }
        .btn-resume {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--accent);
            padding: 10px 20px;
            text-decoration: none;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 14px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-resume:hover { border-color: var(--accent); }

        /* Decided candidates */
        .decided-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .decided-left { display: flex; align-items: center; gap: 12px; }
        .decided-name  { font-weight: 700; font-size: 15px; }
        .decided-email { font-size: 12px; color: var(--muted); }
        .status-badge {
            font-size: 12px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .badge-selected { background: rgba(0,196,140,0.12); color: var(--success); }
        .badge-rejected { background: rgba(255,82,82,0.12);  color: var(--danger); }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 48px;
            color: var(--muted);
        }
        .empty-state .icon { font-size: 48px; margin-bottom: 12px; }
        .empty-state p { font-size: 15px; }

        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1a1a2e;
            color: white;
            padding: 14px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s;
            z-index: 999;
            max-width: 360px;
        }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { border-left: 4px solid var(--success); }
        .toast.error   { border-left: 4px solid var(--danger); }

        /* Loading spinner on button */
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
    </style>
</head>
<body>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- HEADER -->
<div class="topbar">
    <div>
        <h1>Interview Results</h1>
        <p><?= htmlspecialchars($job['title']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($job['location'] ?? '') ?></p>
    </div>
    <a href="recruiter_dashboard.php" class="back-btn">← Dashboard</a>
</div>

<div class="container">

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-num" style="color:var(--accent)"><?= count($candidates) ?></div>
            <div class="stat-label">Awaiting Decision</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:var(--success)">
                <?= count(array_filter($decided, fn($d) => $d['status'] === 'selected')) ?>
            </div>
            <div class="stat-label">Selected</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:var(--danger)">
                <?= count(array_filter($decided, fn($d) => $d['status'] === 'rejected')) ?>
            </div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- PENDING DECISIONS -->
    <div class="section-title">Awaiting Your Decision</div>

    <?php if (empty($candidates)): ?>
    <div class="empty-state">
        <div class="icon">✅</div>
        <p>All candidates have been decided.<br>Check the section below for results.</p>
    </div>
    <?php else: ?>

    <?php foreach ($candidates as $c): ?>
    <?php
        $initials = strtoupper(substr($c['full_name'], 0, 1));
        $resume   = $c['resume_score']        ?? 0;
        $apt      = $c['aptitude_tech_score'] ?? 0;
        $coding   = $c['coding_score']        ?? 0;
        $final    = $c['final_score']         ?? 0;
    ?>
    <div class="candidate-card" id="card-<?= $c['application_id'] ?>">
        <div class="card-top">
            <div class="candidate-info">
                <div class="avatar"><?= $initials ?></div>
                <div>
                    <div class="cand-name"><?= htmlspecialchars($c['full_name']) ?></div>
                    <div class="cand-email"><?= htmlspecialchars($c['email']) ?></div>
                </div>
            </div>
        </div>

        <!-- Score breakdown -->
        <div class="scores-row">
            <div class="score-pill">
                <div class="score-pill-label">Resume</div>
                <div class="score-pill-val" style="color:<?= scoreColor($resume) ?>"><?= number_format($resume, 1) ?>%</div>
                <div class="score-pill-weight">40% weight</div>
            </div>
            <div class="score-pill">
                <div class="score-pill-label">Apt + Tech</div>
                <div class="score-pill-val" style="color:<?= scoreColor($apt) ?>"><?= number_format($apt, 1) ?>%</div>
                <div class="score-pill-weight">20% weight</div>
            </div>
            <div class="score-pill">
                <div class="score-pill-label">Coding</div>
                <div class="score-pill-val" style="color:<?= scoreColor($coding) ?>"><?= number_format($coding, 1) ?>%</div>
                <div class="score-pill-weight">40% weight</div>
            </div>
            <div class="final-pill">
                <div class="score-pill-label">Final Score</div>
                <div class="score-pill-val"><?= number_format($final, 1) ?>%</div>
                <div class="score-pill-weight">Combined</div>
            </div>
        </div>

        <!-- Actions -->
        <div class="action-row">
            <a href="<?= htmlspecialchars($c['resume_path']) ?>" target="_blank" class="btn-resume">
                📄 Resume
            </a>
            <button class="btn btn-accept"
                    onclick="decide(<?= $c['application_id'] ?>, 'selected', this)">
                ✅ Select Candidate
            </button>
            <button class="btn btn-reject"
                    onclick="decide(<?= $c['application_id'] ?>, 'rejected', this)">
                ✗ Reject
            </button>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- DECIDED CANDIDATES -->
    <?php if (!empty($decided)): ?>
    <div class="section-title" style="margin-top:40px">Already Decided</div>
    <?php foreach ($decided as $d): ?>
    <div class="decided-card">
        <div class="decided-left">
            <div class="avatar" style="width:38px;height:38px;font-size:14px">
                <?= strtoupper(substr($d['full_name'], 0, 1)) ?>
            </div>
            <div>
                <div class="decided-name"><?= htmlspecialchars($d['full_name']) ?></div>
                <div class="decided-email"><?= htmlspecialchars($d['email']) ?></div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:16px;">
            <span style="font-family:'JetBrains Mono',monospace;font-size:14px;color:var(--muted)">
                <?= number_format($d['final_score'] ?? 0, 1) ?>%
            </span>
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
    const label   = decision === 'selected' ? 'Select' : 'Reject';
    const confirm = window.confirm(
        `Are you sure you want to ${label} this candidate?\n\nAn email will be sent to them automatically.`
    );
    if (!confirm) return;

    // Disable both buttons in this card
    const card = document.getElementById('card-' + applicationId);
    card.querySelectorAll('button').forEach(b => b.disabled = true);

    // Show spinner on clicked button
    const originalText = btn.innerHTML;
    btn.innerHTML = '<div class="spinner"></div> Sending...';

    const formData = new FormData();
    formData.append('application_id', applicationId);
    formData.append('decision', decision);

    fetch('../scripts/accept_reject_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(
                decision === 'selected'
                    ? '✅ Candidate selected! Email queued.'
                    : '✗ Candidate rejected. Email queued.',
                'success'
            );
            // Fade out and remove card after 1.5s
            setTimeout(() => {
                card.style.transition = 'all 0.4s';
                card.style.opacity    = '0';
                card.style.transform  = 'translateY(-10px)';
                setTimeout(() => {
                    card.remove();
                    // Reload to update stats and decided list
                    setTimeout(() => location.reload(), 300);
                }, 400);
            }, 1500);
        } else {
            showToast('Error: ' + (data.error || 'Something went wrong'), 'error');
            card.querySelectorAll('button').forEach(b => b.disabled = false);
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        showToast('Network error. Please try again.', 'error');
        card.querySelectorAll('button').forEach(b => b.disabled = false);
        btn.innerHTML = originalText;
    });
}

function showToast(msg, type) {
    const toast    = document.getElementById('toast');
    toast.textContent = msg;
    toast.className   = 'toast show ' + type;
    setTimeout(() => toast.className = 'toast', 3500);
}
</script>
</body>
</html>
