<?php require_once '../config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - RPA Recruitment</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d0d0f;
            --surface: #141418;
            --border: rgba(255,255,255,0.07);
            --accent: #c8a96e;
            --accent-dim: rgba(200,169,110,0.15);
            --text: #f0ece4;
            --muted: #7a7670;
            --error: #e05c5c;
            --success: #5cad82;
        }

        html, body {
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-weight: 300;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
            background-image:
                radial-gradient(ellipse 80% 60% at 70% 20%, rgba(200,169,110,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 60% 80% at 10% 80%, rgba(100,80,160,0.04) 0%, transparent 60%);
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
            opacity: 0.5;
        }

        .split-layout {
            display: flex;
            width: 100%;
            max-width: 900px;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: 0 40px 120px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.03);
            position: relative;
            z-index: 1;
            animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) forwards;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .brand-panel {
            flex: 0 0 340px;
            background: linear-gradient(160deg, #1a1710 0%, #0f0e0c 100%);
            padding: 56px 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .brand-panel::after {
            content: '';
            position: absolute;
            bottom: -60px;
            left: -60px;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            border: 1px solid rgba(200,169,110,0.12);
        }
        .brand-panel::before {
            content: '';
            position: absolute;
            bottom: -20px;
            left: -20px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            border: 1px solid rgba(200,169,110,0.08);
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo .logo-mark {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 600;
            color: #0d0d0f;
        }

        .brand-logo .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 17px;
            letter-spacing: 0.02em;
            color: var(--text);
        }

        .brand-copy {
            position: relative;
            z-index: 1;
        }

        .brand-copy h1 {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            line-height: 1.3;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 16px;
        }

        .brand-copy h1 em {
            color: var(--accent);
            font-style: italic;
        }

        .brand-copy p {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.7;
            max-width: 220px;
        }

        .brand-steps {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .step {
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .step-num {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 1px solid rgba(200,169,110,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: var(--accent);
            flex-shrink: 0;
            margin-top: 1px;
        }

        .step-text {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }

        /* Form panel */
        .form-panel {
            flex: 1;
            background: var(--surface);
            padding: 48px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 32px;
        }

        .form-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 8px;
        }

        .form-header p {
            font-size: 13px;
            color: var(--muted);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 24px;
            border: 1px solid;
        }

        .alert-error {
            background: rgba(224,92,92,0.1);
            border-color: rgba(224,92,92,0.25);
            color: #e87878;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            letter-spacing: 0.07em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"],
        select {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 13px 16px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: var(--text);
            outline: none;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            -webkit-appearance: none;
        }

        input:focus, select:focus {
            border-color: rgba(200,169,110,0.5);
            background: rgba(200,169,110,0.05);
            box-shadow: 0 0 0 3px rgba(200,169,110,0.08);
        }

        input::placeholder { color: rgba(122,118,112,0.5); }
        select option { background: #1a1a1e; }

        /* Role selector */
        .role-select-group {
            margin-bottom: 18px;
        }

        .role-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 8px;
        }

        .role-option {
            position: relative;
        }

        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .role-option label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            text-transform: none;
            letter-spacing: 0;
            font-size: 14px;
            color: var(--muted);
        }

        .role-option input:checked + label {
            border-color: rgba(200,169,110,0.5);
            background: rgba(200,169,110,0.07);
            color: var(--accent);
        }

        .role-icon { font-size: 18px; }

        button[type="submit"] {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: #0d0d0f;
            border: none;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 0.04em;
            cursor: pointer;
            margin-top: 8px;
            transition: opacity 0.2s, transform 0.15s;
        }

        button[type="submit"]:hover { opacity: 0.88; transform: translateY(-1px); }
        button[type="submit"]:active { transform: translateY(0); }

        .form-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
            color: var(--muted);
        }

        .form-footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }

        .form-footer a:hover { text-decoration: underline; }

        @media (max-width: 700px) {
            .brand-panel { display: none; }
            .form-panel { padding: 40px 28px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>

<div class="split-layout">
    <!-- Brand Panel -->
    <div class="brand-panel">
        <div class="brand-logo">
            <div class="logo-mark">R</div>
            <span class="logo-text">RPA Recruitment</span>
        </div>

        <div class="brand-copy">
            <h1>Join a <em>smarter</em> way to hire.</h1>
            <p>Whether you're searching or hiring, we've built the right tools for you.</p>
        </div>

        <div class="brand-steps">
            <div class="step">
                <div class="step-num">1</div>
                <div class="step-text">Create your free account in under a minute</div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-text">Build your profile or post your first role</div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-text">Connect with the right people, faster</div>
            </div>
        </div>
    </div>

    <!-- Form Panel -->
    <div class="form-panel">
        <div class="form-header">
            <h2>Create an account</h2>
            <p>Fill in your details to get started</p>
        </div>

        <?php
        if (isset($_GET['error'])) {
            if ($_GET['error'] == 'exists') {
                echo '<div class="alert alert-error">An account with this email already exists.</div>';
            } elseif ($_GET['error'] == 'empty') {
                echo '<div class="alert alert-error">Please fill in all required fields.</div>';
            } elseif ($_GET['error'] == 'failed') {
                echo '<div class="alert alert-error">Registration failed. Please try again.</div>';
            }
        }
        ?>

        <form action="../scripts/register_handler.php" method="POST">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" name="full_name" id="full_name" placeholder="Jane Smith" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" placeholder="you@example.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                </div>
            </div>

            <div class="role-select-group">
                <label>I am joining as a</label>
                <div class="role-options">
                    <div class="role-option">
                        <input type="radio" name="user_type" id="role_candidate" value="candidate" required>
                        <label for="role_candidate">
                            <span class="role-icon">🎯</span>
                            Candidate
                        </label>
                    </div>
                    <div class="role-option">
                        <input type="radio" name="user_type" id="role_recruiter" value="recruiter">
                        <label for="role_recruiter">
                            <span class="role-icon">🏢</span>
                            Recruiter
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit">Create Account</button>
        </form>

        <div class="form-footer">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </div>
</div>

</body>
</html>