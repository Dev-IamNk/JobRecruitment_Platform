<?php
require_once '../config/db.php';

redirectIfNotLoggedIn();
if (getUserType() != 'candidate') {
    header('Location: login.php');
    exit;
}

$candidate_id   = $_SESSION['user_id'];
$application_id = intval($_GET['application_id'] ?? 0);

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
    <title>Submission Successful — RPA Recruitment</title>
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
            --accent-glow: rgba(200,169,110,0.18);
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            min-height: 100vh;
            background-image:
                radial-gradient(ellipse 60% 50% at 50% 0%, rgba(200,169,110,0.08) 0%, transparent 60%);
        }

        /* grain */
        body::before {
            content: '';
            position: fixed; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none; z-index: 0;
        }

        /* Confetti layer */
        .confetti-wrap {
            position: fixed; top: 0; left: 0; right: 0;
            height: 140px; pointer-events: none; overflow: hidden; z-index: 10;
        }
        .cp {
            position: absolute; animation: fall 3s ease-in forwards;
        }
        @keyframes fall {
            0%   { transform: translateY(-12px) rotate(0deg);   opacity: 1; }
            100% { transform: translateY(380px) rotate(720deg); opacity: 0; }
        }

        /* Card */
        .card {
            position: relative; z-index: 1;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 52px 44px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 40px 100px rgba(0,0,0,0.55);
            animation: riseIn 0.6s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes riseIn {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Glow behind check */
        .card::before {
            content: '';
            position: absolute;
            top: -60px; left: 50%;
            transform: translateX(-50%);
            width: 340px; height: 340px;
            background: radial-gradient(circle, var(--accent-glow) 0%, transparent 68%);
            pointer-events: none;
        }

        /* Brand mark top */
        .card-brand {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            margin-bottom: 36px; opacity: 0.5;
        }
        .logo-mark {
            width: 28px; height: 28px; border-radius: 7px; background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 13px; font-weight: 600; color: #0d0d0f;
        }
        .brand-name { font-family: 'Playfair Display', serif; font-size: 14px; color: var(--text); }

        /* Check */
        .check-wrap {
            width: 84px; height: 84px; border-radius: 50%;
            background: var(--accent-dim);
            border: 1.5px solid rgba(200,169,110,0.35);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 28px;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.1s both;
            box-shadow: 0 0 0 8px rgba(200,169,110,0.06), 0 0 0 16px rgba(200,169,110,0.03);
        }
        .check-wrap svg {
            width: 38px; height: 38px;
            stroke: var(--accent); stroke-width: 2.5;
            fill: none; stroke-linecap: round; stroke-linejoin: round;
        }
        .check-path {
            stroke-dasharray: 60; stroke-dashoffset: 60;
            animation: drawCheck 0.55s ease 0.55s forwards;
        }

        @keyframes popIn {
            0%   { transform: scale(0.4); opacity: 0; }
            100% { transform: scale(1);   opacity: 1; }
        }
        @keyframes drawCheck {
            to { stroke-dashoffset: 0; }
        }

        /* Headline */
        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 30px; font-weight: 400; color: var(--text);
            margin-bottom: 8px; line-height: 1.2;
        }

        h1 em { color: var(--accent); font-style: italic; }

        .job-name {
            font-size: 14px; color: var(--muted);
            margin-bottom: 36px; line-height: 1.5;
        }
        .job-name strong { color: var(--text); font-weight: 500; }

        /* Info boxes */
        .info-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 28px; text-align: left; }

        .info-box {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            display: flex; align-items: flex-start; gap: 14px;
            transition: border-color 0.2s;
        }
        .info-box:hover { border-color: rgba(200,169,110,0.2); }

        .info-icon {
            width: 34px; height: 34px; border-radius: 9px;
            background: var(--accent-dim);
            border: 1px solid rgba(200,169,110,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; flex-shrink: 0;
        }

        .info-text strong {
            display: block; font-size: 13px; font-weight: 500;
            color: var(--text); margin-bottom: 4px;
        }

        .info-text p {
            font-size: 12px; color: var(--muted); line-height: 1.6;
        }

        .email-mono {
            font-family: 'DM Mono', monospace;
            font-size: 12px; color: var(--accent);
            background: var(--accent-dim);
            padding: 2px 7px; border-radius: 5px;
            display: inline-block; margin-top: 4px;
        }

        /* Countdown */
        .countdown-row {
            display: flex; align-items: center; justify-content: center;
            gap: 8px; font-size: 12px; color: var(--muted);
            margin-bottom: 10px;
            font-family: 'DM Mono', monospace;
        }
        .countdown-row span { color: var(--accent); font-weight: 500; }

        .redirect-bar-wrap {
            background: rgba(255,255,255,0.04);
            border-radius: 3px; height: 3px;
            margin-bottom: 20px; overflow: hidden;
        }
        .redirect-bar-fill {
            height: 100%; background: var(--accent);
            border-radius: 3px; width: 100%;
            animation: shrink 8s linear forwards;
        }
        @keyframes shrink {
            from { width: 100%; }
            to   { width: 0%; }
        }

        /* Button */
        .home-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            background: var(--accent); color: #0d0d0f;
            font-family: 'DM Sans', sans-serif; font-weight: 500; font-size: 14px;
            letter-spacing: 0.03em;
            padding: 14px 28px; border-radius: 11px;
            text-decoration: none; width: 100%;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
        }
        .home-btn:hover {
            opacity: 0.87; transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(200,169,110,0.22);
        }
        .home-btn:active { transform: translateY(0); }
    </style>
</head>
<body>

<div class="confetti-wrap" id="confetti"></div>

<div class="card">

    <div class="card-brand">
        <div class="logo-mark">R</div>
        <span class="brand-name">RPA Recruitment</span>
    </div>

    <div class="check-wrap">
        <svg viewBox="0 0 24 24">
            <path class="check-path" d="M5 13l4 4L19 7"/>
        </svg>
    </div>

    <h1>All <em>Done</em> 🎉</h1>
    <div class="job-name">
        You've completed all rounds for <strong><?= htmlspecialchars($job_title) ?></strong>
    </div>

    <div class="info-list">
        <div class="info-box">
            <div class="info-icon">✅</div>
            <div class="info-text">
                <strong>Submission Recorded</strong>
                <p>Your coding test has been submitted and all answers have been saved successfully.</p>
            </div>
        </div>

        <div class="info-box">
            <div class="info-icon">📧</div>
            <div class="info-text">
                <strong>Results via Email</strong>
                <p>The recruiter will review your performance and send results to:
                <?php if ($email): ?>
                    <span class="email-mono"><?= htmlspecialchars($email) ?></span>
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
                <p>Candidates are shortlisted based on resume, aptitude, and coding scores. You'll be notified about interview scheduling via email.</p>
            </div>
        </div>
    </div>

    <div class="countdown-row">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Redirecting to dashboard in <span id="count">8</span>s
    </div>
    <div class="redirect-bar-wrap">
        <div class="redirect-bar-fill"></div>
    </div>

    <a href="candidate_dashboard.php" class="home-btn">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Go to Dashboard
    </a>

</div>

<script>
// Confetti — gold palette to match theme
const colors = ['#c8a96e','#e6c88a','#f0ece4','#a08050','#ddd0b0','#8b6f3e'];
const wrap = document.getElementById('confetti');
for (let i = 0; i < 65; i++) {
    const el = document.createElement('div');
    el.className = 'cp';
    const size = 5 + Math.random() * 7;
    el.style.cssText = `
        left: ${Math.random() * 100}%;
        background: ${colors[Math.floor(Math.random() * colors.length)]};
        border-radius: ${Math.random() > 0.45 ? '50%' : '2px'};
        animation-delay: ${Math.random() * 2.2}s;
        animation-duration: ${2.2 + Math.random() * 2}s;
        width: ${size}px;
        height: ${size}px;
        opacity: ${0.6 + Math.random() * 0.4};
    `;
    wrap.appendChild(el);
}

// Countdown & auto-redirect
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