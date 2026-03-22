<?php
require_once '../config/db.php';

redirectIfNotLoggedIn();
if (getUserType() != 'candidate') {
    header('Location: login.php');
    exit;
}

$candidate_id   = $_SESSION['user_id'];
$application_id = intval($_GET['application_id'] ?? 0);

// Fetch job title for display
$job_title = 'the position';
if ($application_id) {
    $q = $pdo->prepare("
        SELECT j.title FROM applications a 
        JOIN jobs j ON a.job_id = j.id 
        WHERE a.id = ? AND a.candidate_id = ?
    ");
    $q->execute([$application_id, $candidate_id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if ($row) $job_title = $row['title'];
}

// Get candidate email
$email = '';
$eq = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$eq->execute([$candidate_id]);
$erow = $eq->fetch(PDO::FETCH_ASSOC);
if ($erow) $email = $erow['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Successful</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f;
            --surface: #111118;
            --surface2: #1a1a24;
            --border: #2a2a3a;
            --accent: #00e5a0;
            --accent2: #7c6fff;
            --text: #e8e8f0;
            --muted: #6b6b8a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Syne', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 56px 48px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Glow behind checkmark */
        .card::before {
            content: '';
            position: absolute;
            top: -80px; left: 50%;
            transform: translateX(-50%);
            width: 320px; height: 320px;
            background: radial-gradient(circle, rgba(0,229,160,0.10) 0%, transparent 70%);
            pointer-events: none;
        }

        /* Animated checkmark circle */
        .check-wrap {
            width: 90px; height: 90px;
            border-radius: 50%;
            background: rgba(0,229,160,0.12);
            border: 2px solid rgba(0,229,160,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        .check-wrap svg {
            width: 44px; height: 44px;
            stroke: var(--accent);
            stroke-width: 2.5;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .check-path {
            stroke-dasharray: 60;
            stroke-dashoffset: 60;
            animation: drawCheck 0.5s ease 0.3s forwards;
        }
        @keyframes popIn {
            0%   { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1);   opacity: 1; }
        }
        @keyframes drawCheck {
            to { stroke-dashoffset: 0; }
        }

        h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 12px;
            color: var(--accent);
        }

        .job-name {
            font-size: 15px;
            color: var(--muted);
            margin-bottom: 32px;
        }
        .job-name span {
            color: var(--text);
            font-weight: 600;
        }

        /* Info boxes */
        .info-box {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 14px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            text-align: left;
        }
        .info-icon {
            font-size: 22px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .info-text strong {
            display: block;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 3px;
            color: var(--text);
        }
        .info-text p {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }
        .info-text .email-highlight {
            font-family: 'JetBrains Mono', monospace;
            color: var(--accent2);
            font-size: 12px;
        }

        /* Countdown */
        .countdown {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            color: var(--muted);
            margin: 24px 0 20px;
        }
        .countdown span {
            color: var(--accent);
            font-weight: 700;
        }

        /* Button */
        .home-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--accent);
            color: #000;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 15px;
            padding: 14px 36px;
            border-radius: 10px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        .home-btn:hover {
            background: #00cc8c;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,229,160,0.25);
        }

        /* Progress bar for auto-redirect */
        .redirect-bar-wrap {
            background: var(--surface2);
            border-radius: 4px;
            height: 4px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .redirect-bar-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 4px;
            width: 100%;
            animation: shrink 8s linear forwards;
        }
        @keyframes shrink {
            from { width: 100%; }
            to   { width: 0%; }
        }

        /* Confetti */
        .confetti-wrap { position: fixed; top: 0; left: 0; right: 0; height: 120px; pointer-events: none; overflow: hidden; }
        .cp { position: absolute; width: 8px; height: 8px; animation: fall 3s ease-in forwards; }
        @keyframes fall {
            0%   { transform: translateY(-10px) rotate(0deg);   opacity: 1; }
            100% { transform: translateY(350px) rotate(720deg); opacity: 0; }
        }
    </style>
</head>
<body>

<div class="confetti-wrap" id="confetti"></div>

<div class="card">

    <!-- Check icon -->
    <div class="check-wrap">
        <svg viewBox="0 0 24 24">
            <path class="check-path" d="M5 13l4 4L19 7"/>
        </svg>
    </div>

    <h1>All Done! 🎉</h1>
    <div class="job-name">
        You've completed all rounds for <span><?= htmlspecialchars($job_title) ?></span>
    </div>

    <!-- Info boxes -->
    <div class="info-box">
        <div class="info-icon">✅</div>
        <div class="info-text">
            <strong>Submission Successful</strong>
            <p>Your coding test has been submitted and your answers have been recorded successfully.</p>
        </div>
    </div>

    <div class="info-box">
        <div class="info-icon">📧</div>
        <div class="info-text">
            <strong>Results Will Be Sent to Your Email</strong>
            <p>The recruiter will review your performance and you will receive the results at:<br>
            <?php if ($email): ?>
            <span class="email-highlight"><?= htmlspecialchars($email) ?></span>
            <?php else: ?>
            your registered email address.
            <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="info-box">
        <div class="info-icon">⏳</div>
        <div class="info-text">
            <strong>What Happens Next?</strong>
            <p>The recruiter will shortlist candidates based on resume, aptitude, and coding scores. You'll be notified about interview scheduling via email.</p>
        </div>
    </div>

    <!-- Auto redirect countdown -->
    <div class="countdown">
        Redirecting to dashboard in <span id="count">8</span>s...
    </div>
    <div class="redirect-bar-wrap">
        <div class="redirect-bar-fill"></div>
    </div>

    <a href="candidate_dashboard.php" class="home-btn">
        🏠 Go to Dashboard
    </a>
</div>

<script>
// Confetti
const colors = ['#00e5a0','#7c6fff','#ff9f43','#ff5252','#ffffff'];
const wrap   = document.getElementById('confetti');
for (let i = 0; i < 70; i++) {
    const el = document.createElement('div');
    el.className = 'cp';
    el.style.cssText = `
        left: ${Math.random() * 100}%;
        background: ${colors[Math.floor(Math.random() * colors.length)]};
        border-radius: ${Math.random() > 0.5 ? '50%' : '2px'};
        animation-delay: ${Math.random() * 2.5}s;
        animation-duration: ${2 + Math.random() * 2}s;
        width: ${6 + Math.random() * 6}px;
        height: ${6 + Math.random() * 6}px;
    `;
    wrap.appendChild(el);
}

// Countdown and auto redirect
let count = 8;
const countEl = document.getElementById('count');
const interval = setInterval(() => {
    count--;
    countEl.textContent = count;
    if (count <= 0) {
        clearInterval(interval);
        window.location.href = 'candidate_dashboard.php';
    }
}, 1000);
</script>
</body>
</html>
