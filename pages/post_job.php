<!-- FILE: pages/post_job.php -->
<?php 
require_once '../config/db.php';
redirectIfNotLoggedIn();
if (getUserType() != 'recruiter') {
    header('Location: candidate_dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post New Job — RPA Recruitment</title>
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
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 15px;
            font-weight: 600;
            color: #0d0d0f;
        }

        .nav-title { font-family: 'Playfair Display', serif; font-size: 15px; color: var(--text); }

        .nav-right { display: flex; align-items: center; gap: 8px; }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            color: var(--muted);
            text-decoration: none;
            padding: 7px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            transition: color 0.2s, border-color 0.2s;
        }
        .btn-back:hover { color: var(--text); border-color: var(--border-hover); }

        /* ── PAGE ── */
        .page {
            position: relative;
            z-index: 1;
            max-width: 860px;
            margin: 0 auto;
            padding: 36px 24px 80px;
        }

        /* Page header */
        .page-header {
            margin-bottom: 36px;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        .eyebrow {
            font-size: 11px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 10px;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 6px;
        }
        .page-header .subtitle { font-size: 13px; color: var(--muted); }

        /* Alert */
        .alert {
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 13px;
            margin-bottom: 24px;
            border: 1px solid;
            animation: fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) both;
        }
        .alert-error {
            background: var(--red-dim);
            color: #e87878;
            border-color: rgba(224,92,92,0.25);
        }

        /* ── FORM SECTION ── */
        .form-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 20px;
            animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: rgba(255,255,255,0.01);
        }

        .section-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: var(--accent-dim);
            border: 1px solid var(--accent-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .section-title-text {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            font-weight: 400;
            color: var(--text);
        }
        .section-subtitle {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .section-body { padding: 24px; }

        /* ── FORM GROUPS ── */
        .form-group { margin-bottom: 20px; }
        .form-group:last-child { margin-bottom: 0; }

        .form-group label {
            display: block;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
            font-weight: 400;
        }

        .form-group small {
            display: block;
            font-size: 11px;
            color: var(--muted-2);
            margin-top: 6px;
        }

        input[type="text"],
        input[type="url"],
        input[type="number"],
        input[type="datetime-local"],
        textarea,
        select {
            width: 100%;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 11px 14px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 300;
            color: var(--text);
            outline: none;
            transition: border-color 0.2s, background 0.2s;
            -webkit-appearance: none;
            appearance: none;
        }

        input[type="text"]:focus,
        input[type="url"]:focus,
        input[type="number"]:focus,
        input[type="datetime-local"]:focus,
        textarea:focus,
        select:focus {
            border-color: var(--accent-border);
            background: var(--surface-3);
        }

        input::placeholder,
        textarea::placeholder { color: var(--muted-2); }

        textarea { resize: vertical; min-height: 110px; line-height: 1.6; }

        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237a7670' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
            cursor: pointer;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ── INPUT INLINE SMALL ── */
        input.inline-num {
            width: 90px;
            display: inline-block;
            padding: 8px 10px;
            font-size: 13px;
        }

        /* ── TEST SECTION ── */
        .test-block {
            border: 1px solid var(--border);
            border-radius: 13px;
            overflow: hidden;
            margin-bottom: 14px;
            transition: border-color 0.25s;
        }
        .test-block.enabled { border-color: rgba(200,169,110,0.3); }

        .test-toggle-bar {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 20px;
            cursor: pointer;
            background: rgba(255,255,255,0.01);
            user-select: none;
        }

        /* Custom toggle switch */
        .toggle-switch {
            position: relative;
            width: 38px;
            height: 22px;
            flex-shrink: 0;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .toggle-track {
            position: absolute;
            inset: 0;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 11px;
            transition: background 0.2s, border-color 0.2s;
            cursor: pointer;
        }
        .toggle-thumb {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--muted-2);
            transition: transform 0.2s, background 0.2s;
        }
        .toggle-switch input:checked ~ .toggle-track { background: var(--accent-dim); border-color: var(--accent-border); }
        .toggle-switch input:checked ~ .toggle-track .toggle-thumb { transform: translateX(16px); background: var(--accent); }

        .test-toggle-label {
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
        }
        .test-toggle-desc { font-size: 12px; color: var(--muted); margin-top: 1px; }

        .test-config {
            display: none;
            padding: 0 20px 20px;
            border-top: 1px solid var(--border);
        }
        .test-config.active { display: block; }

        /* Sub-section within test */
        .sub-section { margin-top: 20px; }
        .sub-section-title {
            font-size: 11px;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sub-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        /* Topic header row */
        .topic-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 44px;
            gap: 8px;
            padding: 0 12px;
            margin-bottom: 6px;
        }
        .topic-header span {
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted-2);
        }

        /* Topic row */
        .topic-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 44px;
            gap: 8px;
            align-items: center;
            padding: 10px 12px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 9px;
            margin-bottom: 8px;
        }

        .topic-row input[type="number"],
        .topic-row select {
            padding: 7px 10px;
            font-size: 12px;
            border-radius: 7px;
        }
        .topic-row input[type="number"] { text-align: center; }

        .topic-total-cell {
            font-family: 'DM Mono', monospace;
            font-size: 13px;
            color: var(--accent);
            text-align: center;
        }

        .remove-btn {
            width: 32px;
            height: 32px;
            border-radius: 7px;
            border: 1px solid rgba(224,92,92,0.25);
            background: var(--red-dim);
            color: #e87878;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: background 0.2s, border-color 0.2s;
            flex-shrink: 0;
        }
        .remove-btn:hover { background: rgba(224,92,92,0.18); border-color: rgba(224,92,92,0.4); }

        .add-topic-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px dashed rgba(200,169,110,0.3);
            background: transparent;
            color: var(--accent);
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
        }
        .add-topic-btn:hover { background: var(--accent-dim); border-color: rgba(200,169,110,0.5); }

        /* Timer options */
        .timer-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 4px;
        }
        .timer-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--surface-2);
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            font-size: 13px;
            color: var(--text);
        }
        .timer-option:has(input:checked) { border-color: var(--accent-border); background: var(--accent-dim); }

        .timer-option input[type="radio"] { accent-color: var(--accent); }
        .timer-option input[type="number"] {
            width: 72px;
            padding: 5px 8px;
            font-size: 13px;
            border-radius: 6px;
        }

        /* Coding problem */
        .coding-problem {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 10px;
        }
        .coding-problem h4 {
            font-family: 'Playfair Display', serif;
            font-size: 13px;
            font-weight: 400;
            color: var(--accent);
            margin-bottom: 14px;
        }

        /* Auto count */
        #auto_count_field { display: none; }

        /* ── SUBMIT AREA ── */
        .form-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 32px;
            animation: fadeUp 0.5s 0.3s cubic-bezier(0.22,1,0.36,1) both;
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 28px;
            border-radius: 11px;
            border: none;
            background: var(--accent);
            color: #0d0d0f;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.15s;
        }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-submit:active { transform: translateY(0); }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 12px 20px;
            border-radius: 11px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            color: var(--muted);
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            text-decoration: none;
            transition: color 0.2s, border-color 0.2s;
        }
        .btn-cancel:hover { color: var(--text); border-color: var(--border-hover); }

        /* Animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-section:nth-child(1) { animation-delay: 0.05s; }
        .form-section:nth-child(2) { animation-delay: 0.1s; }
        .form-section:nth-child(3) { animation-delay: 0.15s; }
        .form-section:nth-child(4) { animation-delay: 0.2s; }

        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
            .topic-header,
            .topic-row { grid-template-columns: 1fr 1fr 1fr 1fr 44px; }
            .topic-header span:first-child,
            .topic-row select { grid-column: 1 / -1; }
            .timer-options { flex-direction: column; }
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
        <a href="recruiter_dashboard.php" class="btn-back">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Dashboard
        </a>
        <a href="../scripts/logout.php" class="btn-back">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            Logout
        </a>
    </div>
</nav>

<div class="page">

    <!-- Page header -->
    <div class="page-header">
        <div class="eyebrow">Recruiter · New Listing</div>
        <h1>Post a New Job</h1>
        <div class="subtitle">Fill in the details below to publish a job and configure assessments.</div>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <?php
            if ($_GET['error'] == 'empty') echo '⚠ Please fill all required fields before submitting.';
            else echo '⚠ Failed to post job. Please try again.';
            ?>
        </div>
    <?php endif; ?>

    <form action="../scripts/job_handler.php" method="POST" id="job-form">

        <!-- SECTION 1: Basic Info -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">📋</div>
                <div>
                    <div class="section-title-text">Basic Job Information</div>
                    <div class="section-subtitle">Core details about the role</div>
                </div>
            </div>
            <div class="section-body">

                <div class="form-group">
                    <label>Job Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Senior Python Developer">
                </div>

                <div class="form-group">
                    <label>Job Description *</label>
                    <textarea name="description" required placeholder="Describe the role, responsibilities, and requirements…" rows="6"></textarea>
                </div>

                <div class="form-group">
                    <label>Required Skills * <span style="color:var(--muted-2);font-size:10px;text-transform:none;letter-spacing:0">(comma-separated)</span></label>
                    <input type="text" name="required_skills" required placeholder="e.g. Python, Django, MySQL, REST API">
                    <small>These skills will be matched with candidate resumes</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Location *</label>
                        <input type="text" name="location" required placeholder="e.g. New York, Remote">
                    </div>
                    <div class="form-group">
                        <label>Salary Range</label>
                        <input type="text" name="salary_range" placeholder="e.g. $80,000 – $120,000">
                    </div>
                </div>

            </div>
        </div>

        <!-- SECTION 2: Application Settings -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">⚙️</div>
                <div>
                    <div class="section-title-text">Application Settings</div>
                    <div class="section-subtitle">Deadline & shortlisting configuration</div>
                </div>
            </div>
            <div class="section-body">

                <div class="form-group">
                    <label>Application Deadline *</label>
                    <input type="datetime-local" name="application_deadline" required>
                    <small>Applications will close after this date</small>
                </div>

                <div class="form-group">
                    <label>Shortlisting Mode *</label>
                    <select name="shortlisting_mode" id="shortlisting_mode" required>
                        <option value="manual">Manual Selection — I will review and select</option>
                        <option value="automatic">Automatic — System selects top candidates</option>
                    </select>
                </div>

                <div class="form-group" id="auto_count_field">
                    <label>Auto-select Top N Candidates</label>
                    <input type="number" name="auto_shortlist_count" value="10" min="1" max="100">
                    <small>System will automatically shortlist this many top-scoring candidates</small>
                </div>

            </div>
        </div>

        <!-- SECTION 3: Interview Schedule -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">📅</div>
                <div>
                    <div class="section-title-text">Interview Schedule</div>
                    <div class="section-subtitle">Optional — can be configured later</div>
                </div>
            </div>
            <div class="section-body">

                <div class="form-row">
                    <div class="form-group">
                        <label>Interview Date & Time</label>
                        <input type="datetime-local" name="interview_date">
                        <small>Schedule can be set later</small>
                    </div>
                    <div class="form-group">
                        <label>Interview Link</label>
                        <input type="url" name="interview_link" placeholder="https://zoom.us/j/123456789">
                        <small>Meeting link will be sent to shortlisted candidates</small>
                    </div>
                </div>

            </div>
        </div>

        <!-- SECTION 4: Tests -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">📝</div>
                <div>
                    <div class="section-title-text">Online Assessment Tests</div>
                    <div class="section-subtitle">Optional — configure tests for shortlisted candidates</div>
                </div>
            </div>
            <div class="section-body">

                <!-- Aptitude Test -->
                <div class="test-block" id="aptitude-section">
                    <div class="test-toggle-bar" onclick="toggleTest('aptitude')">
                        <label class="toggle-switch" onclick="event.stopPropagation()">
                            <input type="checkbox" id="enable-aptitude" name="enable_aptitude" value="1" onchange="toggleTest('aptitude')">
                            <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        </label>
                        <div>
                            <div class="test-toggle-label">Aptitude Test</div>
                            <div class="test-toggle-desc">Numerical, logical & verbal reasoning</div>
                        </div>
                    </div>

                    <div class="test-config" id="aptitude-config">
                        <input type="hidden" name="aptitude_topics" id="aptitude-topics-data">

                        <!-- Numerical -->
                        <div class="sub-section">
                            <div class="sub-section-title">Numerical Reasoning</div>
                            <div class="topic-header">
                                <span>Topic</span><span>Easy</span><span>Medium</span><span>Hard</span><span>Total</span><span></span>
                            </div>
                            <div id="numerical-topics">
                                <div class="topic-row">
                                    <select name="numerical_topic[]">
                                        <option value="time_work">Time &amp; Work</option>
                                        <option value="profit_loss">Profit &amp; Loss</option>
                                        <option value="percentage">Percentages</option>
                                        <option value="ratio_proportion">Ratio &amp; Proportion</option>
                                        <option value="simple_interest">Simple &amp; Compound Interest</option>
                                    </select>
                                    <input type="number" name="numerical_easy[]" min="0" max="20" value="2">
                                    <input type="number" name="numerical_medium[]" min="0" max="20" value="2">
                                    <input type="number" name="numerical_hard[]" min="0" max="20" value="1">
                                    <div class="topic-total-cell"><span class="topic-total">5</span></div>
                                    <button type="button" class="remove-btn" onclick="removeRow(this)">✕</button>
                                </div>
                            </div>
                            <button type="button" class="add-topic-btn" onclick="addNumericalTopic()">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Topic
                            </button>
                        </div>

                        <!-- Logical -->
                        <div class="sub-section">
                            <div class="sub-section-title">Logical Reasoning</div>
                            <div class="topic-header">
                                <span>Topic</span><span>Easy</span><span>Medium</span><span>Hard</span><span>Total</span><span></span>
                            </div>
                            <div id="logical-topics">
                                <div class="topic-row">
                                    <select name="logical_topic[]">
                                        <option value="patterns">Number/Letter Patterns</option>
                                        <option value="puzzles">Puzzles</option>
                                        <option value="blood_relations">Blood Relations</option>
                                        <option value="syllogism">Syllogism</option>
                                        <option value="coding_decoding">Coding-Decoding</option>
                                    </select>
                                    <input type="number" name="logical_easy[]" min="0" max="20" value="3">
                                    <input type="number" name="logical_medium[]" min="0" max="20" value="2">
                                    <input type="number" name="logical_hard[]" min="0" max="20" value="0">
                                    <div class="topic-total-cell"><span class="topic-total">5</span></div>
                                    <button type="button" class="remove-btn" onclick="removeRow(this)">✕</button>
                                </div>
                            </div>
                            <button type="button" class="add-topic-btn" onclick="addLogicalTopic()">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Topic
                            </button>
                        </div>

                        <!-- Verbal -->
                        <div class="sub-section">
                            <div class="sub-section-title">Verbal Reasoning</div>
                            <div class="topic-header">
                                <span>Topic</span><span>Easy</span><span>Medium</span><span>Hard</span><span>Total</span><span></span>
                            </div>
                            <div id="verbal-topics">
                                <div class="topic-row">
                                    <select name="verbal_topic[]">
                                        <option value="synonyms">Synonyms</option>
                                        <option value="antonyms">Antonyms</option>
                                        <option value="sentence_correction">Sentence Correction</option>
                                        <option value="comprehension">Reading Comprehension</option>
                                        <option value="fill_blanks">Fill in the Blanks</option>
                                    </select>
                                    <input type="number" name="verbal_easy[]" min="0" max="20" value="3">
                                    <input type="number" name="verbal_medium[]" min="0" max="20" value="2">
                                    <input type="number" name="verbal_hard[]" min="0" max="20" value="0">
                                    <div class="topic-total-cell"><span class="topic-total">5</span></div>
                                    <button type="button" class="remove-btn" onclick="removeRow(this)">✕</button>
                                </div>
                            </div>
                            <button type="button" class="add-topic-btn" onclick="addVerbalTopic()">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Topic
                            </button>
                        </div>

                        <!-- Timer -->
                        <div class="sub-section">
                            <div class="sub-section-title">Timer Settings</div>
                            <div class="timer-options">
                                <label class="timer-option">
                                    <input type="radio" name="aptitude_timer_type" value="overall" id="apt-overall" checked>
                                    Overall Timer:
                                    <input type="number" name="aptitude_overall_time" value="60" min="10" max="180" style="width:70px"> min
                                </label>
                                <label class="timer-option">
                                    <input type="radio" name="aptitude_timer_type" value="sectional" id="apt-sectional">
                                    Sectional Timer:
                                    <input type="number" name="aptitude_sectional_time" value="20" min="5" max="60" style="width:70px"> min / section
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Technical MCQ -->
                <div class="test-block" id="technical-section">
                    <div class="test-toggle-bar" onclick="toggleTest('technical')">
                        <label class="toggle-switch" onclick="event.stopPropagation()">
                            <input type="checkbox" id="enable-technical" name="enable_technical" value="1" onchange="toggleTest('technical')">
                            <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        </label>
                        <div>
                            <div class="test-toggle-label">Technical MCQ Test</div>
                            <div class="test-toggle-desc">Language-specific concept questions</div>
                        </div>
                    </div>

                    <div class="test-config" id="technical-config">
                        <div class="form-group" style="margin-top:4px">
                            <label>Technology / Language</label>
                            <select name="technical_technology" id="tech-technology">
                                <option value="python">Python</option>
                                <option value="java">Java</option>
                                <option value="javascript">JavaScript</option>
                                <option value="php">PHP</option>
                                <option value="cpp">C++</option>
                                <option value="sql">SQL / Database</option>
                            </select>
                        </div>

                        <div class="sub-section">
                            <div class="sub-section-title">Technical Concepts</div>
                            <div class="topic-header">
                                <span>Concept</span><span>Easy</span><span>Medium</span><span>Hard</span><span>Total</span><span></span>
                            </div>
                            <div id="technical-topics">
                                <div class="topic-row">
                                    <select name="technical_topic[]" class="tech-topic-select"></select>
                                    <input type="number" name="technical_easy[]" min="0" max="20" value="3">
                                    <input type="number" name="technical_medium[]" min="0" max="20" value="3">
                                    <input type="number" name="technical_hard[]" min="0" max="20" value="2">
                                    <div class="topic-total-cell"><span class="topic-total">8</span></div>
                                    <button type="button" class="remove-btn" onclick="removeRow(this)">✕</button>
                                </div>
                            </div>
                            <button type="button" class="add-topic-btn" onclick="addTechnicalTopic()">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Concept
                            </button>
                        </div>

                        <div class="form-group" style="margin-top:20px">
                            <label>Test Duration (minutes)</label>
                            <input type="number" name="technical_time" value="30" min="10" max="120" style="max-width:150px">
                        </div>
                    </div>
                </div>

                <!-- Coding Test -->
                <div class="test-block" id="coding-section">
                    <div class="test-toggle-bar" onclick="toggleTest('coding')">
                        <label class="toggle-switch" onclick="event.stopPropagation()">
                            <input type="checkbox" id="enable-coding" name="enable_coding" value="1" onchange="toggleTest('coding')">
                            <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        </label>
                        <div>
                            <div class="test-toggle-label">Coding Test</div>
                            <div class="test-toggle-desc">Algorithm &amp; problem-solving challenges</div>
                        </div>
                    </div>

                    <div class="test-config" id="coding-config">
                        <div class="form-group" style="margin-top:4px">
                            <label>Number of Problems</label>
                            <select name="coding_num_problems" id="num-problems" onchange="updateCodingProblems()" style="max-width:200px">
                                <option value="1">1 Problem</option>
                                <option value="2" selected>2 Problems</option>
                                <option value="3">3 Problems</option>
                            </select>
                        </div>

                        <div id="coding-problems">
                            <div class="coding-problem">
                                <h4>Problem 1</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Difficulty</label>
                                        <select name="coding_difficulty[]">
                                            <option value="easy">Easy</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="hard">Hard</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Topic</label>
                                        <select name="coding_topic[]">
                                            <option value="arrays">Arrays</option>
                                            <option value="strings">Strings</option>
                                            <option value="linked_lists">Linked Lists</option>
                                            <option value="trees">Trees</option>
                                            <option value="graphs">Graphs</option>
                                            <option value="dynamic_programming">Dynamic Programming</option>
                                            <option value="sorting_searching">Sorting &amp; Searching</option>
                                            <option value="greedy">Greedy Algorithms</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="coding-problem">
                                <h4>Problem 2</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Difficulty</label>
                                        <select name="coding_difficulty[]">
                                            <option value="easy">Easy</option>
                                            <option value="medium">Medium</option>
                                            <option value="hard" selected>Hard</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Topic</label>
                                        <select name="coding_topic[]">
                                            <option value="arrays">Arrays</option>
                                            <option value="strings">Strings</option>
                                            <option value="linked_lists">Linked Lists</option>
                                            <option value="trees">Trees</option>
                                            <option value="graphs">Graphs</option>
                                            <option value="dynamic_programming" selected>Dynamic Programming</option>
                                            <option value="sorting_searching">Sorting &amp; Searching</option>
                                            <option value="greedy">Greedy Algorithms</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:4px">
                            <label>Time Per Problem (minutes)</label>
                            <input type="number" name="coding_time_per_problem" value="30" min="15" max="60" style="max-width:150px">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <button type="submit" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Post Job
            </button>
            <a href="recruiter_dashboard.php" class="btn-cancel">Cancel</a>
        </div>

    </form>
</div>

<script>
    const technicalTopics = {
        python:     ['OOP Concepts','Data Structures','Django/Flask','NumPy/Pandas','Exception Handling','Decorators','Generators'],
        java:       ['OOP Concepts','Collections Framework','Spring Boot','Multithreading','Exception Handling','JDBC','Servlets/JSP'],
        javascript: ['ES6 Features','DOM Manipulation','Async/Promises','React/Vue','Node.js','Event Loop','Closures'],
        php:        ['OOP in PHP','Laravel Framework','MySQL Integration','Sessions/Cookies','RESTful APIs','Security'],
        cpp:        ['OOP Concepts','STL','Pointers','Memory Management','Templates','Exception Handling'],
        sql:        ['SQL Queries','Joins','Indexing','Normalization','Stored Procedures','Transactions']
    };

    function toggleTest(testType) {
        const cb      = document.getElementById(`enable-${testType}`);
        const config  = document.getElementById(`${testType}-config`);
        const section = document.getElementById(`${testType}-section`);
        const active  = cb.checked;
        config.classList.toggle('active', active);
        section.classList.toggle('enabled', active);
    }

    function updateTotal(row) {
        let total = 0;
        row.querySelectorAll('input[type="number"]').forEach(i => total += parseInt(i.value) || 0);
        row.querySelector('.topic-total').textContent = total;
    }

    document.querySelectorAll('.topic-row').forEach(row => {
        row.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('input', () => updateTotal(row));
        });
    });

    function addRow(containerId, selectHTML) {
        const container = document.getElementById(containerId);
        const row = container.querySelector('.topic-row').cloneNode(true);
        row.querySelectorAll('input[type="number"]').forEach(i => i.value = '0');
        updateTotal(row);
        row.querySelectorAll('input[type="number"]').forEach(i => i.addEventListener('input', () => updateTotal(row)));
        if (selectHTML) row.querySelector('select').outerHTML = selectHTML;
        container.appendChild(row);
    }

    function addNumericalTopic() { addRow('numerical-topics'); }
    function addLogicalTopic()   { addRow('logical-topics'); }
    function addVerbalTopic()    { addRow('verbal-topics'); }

    function addTechnicalTopic() {
        const container = document.getElementById('technical-topics');
        const row = container.querySelector('.topic-row').cloneNode(true);
        row.querySelectorAll('input[type="number"]').forEach(i => i.value = '0');
        updateTotal(row);
        row.querySelectorAll('input[type="number"]').forEach(i => i.addEventListener('input', () => updateTotal(row)));
        row.querySelector('.remove-btn').onclick = function() { removeRow(this); };
        updateTechnicalTopics(row.querySelector('.tech-topic-select'));
        container.appendChild(row);
    }

    function removeRow(btn) { btn.closest('.topic-row').remove(); }

    function updateTechnicalTopics(selectElement = null) {
        const technology = document.getElementById('tech-technology').value;
        const topics = technicalTopics[technology];
        const selects = selectElement ? [selectElement] : document.querySelectorAll('.tech-topic-select');
        selects.forEach(select => {
            select.innerHTML = '';
            topics.forEach(topic => {
                const opt = document.createElement('option');
                opt.value = topic.toLowerCase().replace(/\s+/g, '_').replace(/\//g, '_');
                opt.textContent = topic;
                select.appendChild(opt);
            });
        });
    }

    updateTechnicalTopics();
    document.getElementById('tech-technology').addEventListener('change', () => updateTechnicalTopics());

    function updateCodingProblems() {
        const num = parseInt(document.getElementById('num-problems').value);
        const container = document.getElementById('coding-problems');
        const current = container.querySelectorAll('.coding-problem').length;
        if (num > current) {
            for (let i = current; i < num; i++) {
                const prob = container.querySelector('.coding-problem').cloneNode(true);
                prob.querySelector('h4').textContent = `Problem ${i + 1}`;
                container.appendChild(prob);
            }
        } else {
            const problems = container.querySelectorAll('.coding-problem');
            for (let i = num; i < current; i++) problems[i].remove();
        }
    }

    document.getElementById('shortlisting_mode').addEventListener('change', function() {
        document.getElementById('auto_count_field').style.display =
            this.value === 'automatic' ? 'block' : 'none';
    });
</script>
</body>
</html>